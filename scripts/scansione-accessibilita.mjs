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
 * un errore se trova violazioni gravi o critiche: in GitHub Actions diventa
 * una run rossa.
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
    }, [CHIAVE_CONSENSO, JSON.stringify({ necessary: true, statistiche: false, marketing: false, analytics: false, data: new Date().toISOString() })]);

    const risultati = [];

    const esamina = async (scheda, indirizzo, etichetta = indirizzo) => {
        await scheda.addScriptTag({ content: axe });
        const esito = await scheda.evaluate(async (tag) => {
            // eslint-disable-next-line no-undef
            const r = await axe.run(document, { runOnly: { type: 'tag', values: tag }, resultTypes: ['violations'] });
            return r.violations.map((v) => ({
                regola: v.id,
                impatto: v.impact,
                descrizione: v.help,
                aiuto: v.helpUrl,
                nodi: v.nodes.slice(0, 50).map((n) => ({ selettore: n.target.join(' '), html: n.html.slice(0, 200), sintesi: n.failureSummary })),
                quanti: v.nodes.length,
            }));
        }, TAG);

        risultati.push({ pagina: etichetta, violazioni: esito });
        const gravi = esito.filter((v) => GRAVI.includes(v.impatto));
        console.log(`${gravi.length ? '✗' : '✓'} ${etichetta}${esito.length ? ` — ${esito.map((v) => `${v.regola}(${v.quanti})`).join(', ')}` : ''}`);
    };

    for (const indirizzo of pagine) {
        const scheda = await contesto.newPage();

        try {
            await scheda.goto(indirizzo, { waitUntil: 'networkidle', timeout: 45000 });
            await scheda.waitForTimeout(1000);
            await esamina(scheda, indirizzo);
        } catch (errore) {
            console.warn(`  ! ${indirizzo}: ${errore.message.split('\n')[0]}`);
        } finally {
            await scheda.close();
        }
    }

    // Il banner dei cookie, com'è la prima volta che si entra.
    const senzaScelta = await browser.newContext(impostazioni);
    const primaVisita = await senzaScelta.newPage();

    try {
        await primaVisita.goto(`${base}/`, { waitUntil: 'networkidle', timeout: 45000 });
        await primaVisita.waitForTimeout(1000);
        await esamina(primaVisita, `${base}/`, `${base}/ (banner cookie)`);
    } catch (errore) {
        console.warn(`  ! banner: ${errore.message.split('\n')[0]}`);
    }

    await browser.close();

    if (opzioni.rapporto) {
        writeFileSync(opzioni.rapporto, JSON.stringify({ sito: base, data: new Date().toISOString(), risultati }, null, 2) + '\n');
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

    const gravi = [...perRegola.values()].filter((v) => GRAVI.includes(v.impatto));

    if (gravi.length) {
        console.error(`\n${gravi.length} regole violate con impatto ${GRAVI.join('/')}.`);
        process.exit(1);
    }

    console.log('\nNessuna violazione grave.');
}

main().catch((errore) => {
    console.error(errore);
    process.exit(2);
});
