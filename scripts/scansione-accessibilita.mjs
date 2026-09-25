/**
 * La scansione di accessibilità del sito, con un browser vero e axe-core.
 *
 * Lo shop rientra nell'European Accessibility Act (D.Lgs. 82/2022): la
 * società non è una microimpresa, quindi dal 28/06/2025 i "servizi di
 * commercio elettronico" devono rispettare la EN 301 549, cioè le WCAG 2.1
 * livello AA. axe trova in automatico una parte dei difetti (contrasti,
 * etichette, ruoli ARIA, titoli, lingua): non sostituisce la prova con la
 * tastiera e con uno screen reader, ma impedisce di tornare indietro su quello
 * che si può misurare.
 *
 * Come per i cookie, le pagine vengono dalla sitemap (una per famiglia) più
 * quelle dello shop, che nella sitemap non stanno tutte. Il comando esce con
 * 1 se trova violazioni gravi o critiche e con 2 se una pagina non si e'
 * potuta esaminare (timeout, stato HTTP >= 400): in GitHub Actions diventano
 * entrambe una run rossa. I contrasti che axe non riesce a decidere da solo
 * (testo su immagini o gradienti, `incomplete`) finiscono nel rapporto come
 * avvisi da guardare a occhio, senza far fallire la run.
 *
 *   node scripts/scansione-accessibilita.mjs --url=https://... [--pagine=20] [--rapporto=file.json] [--tutte]
 *
 * `--tutte` fa fallire anche sulle violazioni moderate e lievi.
 */

import { chromium } from 'playwright';
import { readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);

const opzioni = Object.fromEntries(
    process.argv.slice(2)
        .filter((a) => a.startsWith('--'))
        .map((a) => {
            const [nome, valore = 'true'] = a.replace(/^--/, '').split('=');
            return [nome, valore];
        }),
);

const base = (opzioni.url ?? process.env.URL_SITO ?? '').replace(/\/$/, '');

if (! base) {
    console.error('Serve l\'indirizzo del sito: --url=https://...');
    process.exit(2);
}

const quantePagine = Number.parseInt(opzioni.pagine ?? '20', 10);
const GRAVI = opzioni.tutte ? ['minor', 'moderate', 'serious', 'critical'] : ['serious', 'critical'];

/** Le regole delle WCAG 2.0 e 2.1, livelli A e AA: quelle della EN 301 549. */
const TAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'];

/** La chiave con cui il sito ricorda la scelta fatta sul banner dei cookie. */
const CHIAVE_CONSENSO = 'cookie-consent-v2';

/**
 * La versione dell'informativa che il sito sta servendo, letta dalle props
 * della pagina come fa la scansione dei cookie: un consenso salvato senza la
 * versione giusta vale come nessun consenso, e il banner resterebbe aperto
 * sopra ogni pagina esaminata.
 */
async function versioneDellInformativa() {
    const risposta = await fetch(`${base}/`);
    const html = await risposta.text();
    const dati = html.match(/data-page="([^"]+)"/);

    if (! dati) {
        return null;
    }

    const props = JSON.parse(dati[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&').replace(/&#039;/g, "'"));

    return props?.props?.consensoCookie?.versione ?? null;
}

/** Pagine dello shop e dei servizi che la sitemap non elenca. */
const SEMPRE = ['/shop', '/shop/carrello', '/shop/aste', '/login', '/shop/registrati', '/contatti'];

async function pagineDaVisitare() {
    const scelte = new Map(SEMPRE.map((p) => [p, `${base}${p}`]));
    const risposta = await fetch(`${base}/sitemap.xml`);

    if (risposta.ok) {
        const indirizzi = [...(await risposta.text()).matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1]);

        for (const indirizzo of indirizzi) {
            const percorso = new URL(indirizzo).pathname;
            const famiglia = percorso.split('/').filter(Boolean).slice(0, 2).join('/') || 'home';

            if (! scelte.has(famiglia)) {
                // La pagina viene visitata sul sito sotto esame, non su quello
                // scritto nella sitemap: in locale la sitemap punta a APP_URL.
                scelte.set(famiglia, `${base}${percorso}`);
            }
        }
    } else {
        console.warn(`La sitemap non risponde (${risposta.status}): controllo solo le pagine fisse.`);
        scelte.set('home', `${base}/`);
    }

    return [...scelte.values()].slice(0, quantePagine);
}

async function main() {
    const versione = await versioneDellInformativa();

    if (! versione) {
        console.error('Non riesco a leggere la versione dell\'informativa dalla pagina: senza, il banner dei cookie resterebbe aperto e la scansione misurerebbe sempre lui.');
        process.exit(2);
    }

    const pagine = await pagineDaVisitare();
    const axe = readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');
    const browser = await chromium.launch(opzioni.canale ? { channel: opzioni.canale } : {});
    // `bypassCSP`: la Content Security Policy del sito (giustamente) non
    // lascia eseguire script in linea senza nonce, e axe si inietta cosi'.
    // Vale solo per questo browser di prova.
    const impostazioni = { locale: 'it-IT', viewport: { width: 1280, height: 900 }, bypassCSP: true };
    const contesto = await browser.newContext(impostazioni);

    // Il banner dei cookie si rifiuta prima di entrare: altrimenti copre ogni
    // pagina e axe misurerebbe sempre lui. Il banner stesso si controlla a
    // parte, sulla prima pagina, senza scelta salvata.
    await contesto.addInitScript(([chiave, valore]) => {
        try { window.localStorage.setItem(chiave, valore); } catch { /* vedi sopra */ }
    }, [CHIAVE_CONSENSO, JSON.stringify({ necessary: true, statistiche: false, marketing: false, analytics: false, versione, data: new Date().toISOString() })]);

    const risultati = [];
    // Pagine che non si sono potute esaminare: non sono pagine pulite.
    const errori = [];

    const esamina = async (scheda, indirizzo, etichetta = indirizzo) => {
        await scheda.addScriptTag({ content: axe });
        const esito = await scheda.evaluate(async (tag) => {
            // eslint-disable-next-line no-undef
            const r = await axe.run(document, { runOnly: { type: 'tag', values: tag }, resultTypes: ['violations', 'incomplete'] });
            const voce = (v) => ({
                regola: v.id,
                impatto: v.impact,
                descrizione: v.help,
                aiuto: v.helpUrl,
                nodi: v.nodes.slice(0, 50).map((n) => ({ selettore: n.target.join(' '), html: n.html.slice(0, 200), sintesi: n.failureSummary })),
                quanti: v.nodes.length,
            });
            return {
                violazioni: r.violations.map(voce),
                // Solo il contrasto: gli altri `incomplete` sono per lo piu'
                // rumore (regole che non si applicano alla pagina).
                daVerificare: r.incomplete.filter((v) => v.id === 'color-contrast').map(voce),
            };
        }, TAG);

        risultati.push({ pagina: etichetta, violazioni: esito.violazioni, contrasti_da_verificare: esito.daVerificare });
        const gravi = esito.violazioni.filter((v) => GRAVI.includes(v.impatto));
        const avvisi = esito.daVerificare.reduce((n, v) => n + v.quanti, 0);
        console.log(`${gravi.length ? '✗' : '✓'} ${etichetta}${esito.violazioni.length ? ` — ${esito.violazioni.map((v) => `${v.regola}(${v.quanti})`).join(', ')}` : ''}${avvisi ? ` · contrasti da verificare: ${avvisi}` : ''}`);
    };

    // Apre la pagina e la esamina; una pagina che non risponde, va in timeout o
    // torna un errore HTTP e' un errore della scansione, non una pagina pulita.
    const visita = async (scheda, indirizzo, etichetta = indirizzo) => {
        try {
            const risposta = await scheda.goto(indirizzo, { waitUntil: 'networkidle', timeout: 45000 });
            const stato = risposta?.status() ?? 0;

            if (stato === 0 || stato >= 400) {
                throw new Error(`stato HTTP ${stato || 'assente'}`);
            }

            await scheda.waitForTimeout(1000);
            await esamina(scheda, indirizzo, etichetta);
        } catch (errore) {
            const motivo = errore.message.split('\n')[0];
            errori.push({ pagina: etichetta, motivo });
            console.error(`  ! ${etichetta}: ${motivo}`);
        }
    };

    for (const indirizzo of pagine) {
        const scheda = await contesto.newPage();

        try {
            await visita(scheda, indirizzo);
        } finally {
            await scheda.close();
        }
    }

    // Il banner dei cookie, com'è la prima volta che si entra.
    const senzaScelta = await browser.newContext(impostazioni);
    const primaVisita = await senzaScelta.newPage();

    await visita(primaVisita, `${base}/`, `${base}/ (banner cookie)`);

    await browser.close();

    if (opzioni.rapporto) {
        writeFileSync(opzioni.rapporto, JSON.stringify({ sito: base, data: new Date().toISOString(), informativa: versione, errori, risultati }, null, 2) + '\n');
    }

    // Una scansione che non ha controllato niente non e' una scansione pulita.
    if (risultati.length === 0) {
        console.error('Nessuna pagina esaminata: il sito non risponde o axe non si e\' caricato.');
        process.exit(2);
    }

    const perRegola = new Map();

    for (const { pagina, violazioni } of risultati) {
        for (const v of violazioni) {
            const voce = perRegola.get(v.regola) ?? { ...v, pagine: [], totale: 0 };
            voce.pagine.push(pagina);
            voce.totale += v.quanti;
            perRegola.set(v.regola, voce);
        }
    }

    console.log('\nRiepilogo per regola:');

    for (const v of [...perRegola.values()].sort((a, b) => b.totale - a.totale)) {
        console.log(`- [${v.impatto}] ${v.regola}: ${v.totale} elementi in ${v.pagine.length} pagine — ${v.descrizione}`);
        console.log(`    es. ${v.nodi[0]?.selettore}`);
    }

    const daVerificare = risultati.flatMap(({ pagina, contrasti_da_verificare: c }) => (c ?? []).map((v) => ({ pagina, ...v })));

    if (daVerificare.length) {
        const totale = daVerificare.reduce((n, v) => n + v.quanti, 0);
        console.warn(`\nAvviso: ${totale} contrasti che axe non sa decidere (testo su foto o gradienti) in ${new Set(daVerificare.map((v) => v.pagina)).size} pagine: vanno guardati a occhio, l'elenco e' nel rapporto.`);
    }

    const gravi = [...perRegola.values()].filter((v) => GRAVI.includes(v.impatto));

    if (errori.length) {
        console.error(`\n${errori.length} pagine non esaminate: ${errori.map((e) => e.pagina).join(', ')}.`);
    }

    if (gravi.length) {
        console.error(`\n${gravi.length} regole violate con impatto ${GRAVI.join('/')}.`);
    }

    if (errori.length) {
        process.exit(2);
    }

    if (gravi.length) {
        process.exit(1);
    }

    console.log('\nNessuna violazione grave.');
}

main().catch((errore) => {
    console.error(errore);
    process.exit(2);
});
