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
 * Oltre alle pagine si percorrono i passaggi dello shop, perche' e' li' che
 * stanno i difetti che una pagina vuota non mostra: la scheda prodotto con la
 * scelta della taglia, il carrello, il checkout con gli errori di validazione
 * a schermo, la conferma d'ordine, il recesso online (anche con i suoi errori)
 * e il checkout di un'asta vinta.
 *
 * Sul sito vero si fa solo quello che non scrive niente: scheda prodotto,
 * carrello vuoto e i due passaggi del recesso, che fino alla conferma vivono
 * nel browser. Carrello pieno, checkout, ordine e asta richiedono scritture —
 * un articolo nel carrello, un ordine inviato, un'asta vinta — e girano solo
 * con `--flussi` contro l'app locale seminata con `ScansioneAccessibilitaSeeder`
 * (lavoro `flussi` del workflow). `--flussi` rifiuta qualunque indirizzo che
 * non sia locale: nessun ordine parte mai verso produzione.
 *
 *   node scripts/scansione-accessibilita.mjs --url=https://... [--pagine=20] [--rapporto=file.json] [--tutte]
 *   node scripts/scansione-accessibilita.mjs --url=http://127.0.0.1:8000 --flussi [--solo-flussi]
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

/**
 * I passaggi che scrivono (carrello, ordine, asta) solo in locale: localhost,
 * 127.0.0.1, [::1] o un nome .test/.localhost. Il controllo guarda l'host
 * davvero contattato, non un'etichetta passata a mano.
 */
const FLUSSI = opzioni.flussi === 'true';
const hostLocale = (indirizzo) => {
    const { hostname } = new URL(indirizzo);
    return ['localhost', '127.0.0.1', '[::1]', '::1'].includes(hostname)
        || hostname.endsWith('.test') || hostname.endsWith('.localhost');
};

if (FLUSSI && ! hostLocale(base)) {
    console.error(`--flussi invia ordini di prova: gira solo sull'app locale, non su ${base}.`);
    process.exit(2);
}

/** Dati di prova dell'app locale: gli stessi di ScansioneAccessibilitaSeeder. */
const PROVA = {
    prodotto: opzioni.prodotto ?? 'maglia-scansione-accessibilita',
    email: process.env.SCANSIONE_EMAIL ?? 'scansione-accessibilita@example.test',
    password: process.env.SCANSIONE_PASSWORD ?? 'Scansione-Accessibilita-2026!',
    tokenAsta: opzioni['token-asta'] ?? '00000000-0000-4000-8000-000000000a11',
};
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
const SEMPRE = ['/shop', '/shop/carrello', '/shop/aste', '/login', '/shop/registrati', '/contatti', '/recesso', '/en/shop'];

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
        // Lo scorrimento morbido del sito fa leggere ad axe lo sfondo della
        // pagina invece di quello degli elementi fuori schermo (il pulsante
        // dell'ordine risultava bianco su bianco): per la misura si toglie.
        await scheda.addStyleTag({ content: 'html{scroll-behavior:auto!important}' });
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

    for (const indirizzo of (opzioni['solo-flussi'] ? [] : pagine)) {
        const scheda = await contesto.newPage();

        try {
            await visita(scheda, indirizzo);
        } finally {
            await scheda.close();
        }
    }

    // Il banner dei cookie, com'è la prima volta che si entra.
    if (! opzioni['solo-flussi']) {
        const senzaScelta = await browser.newContext(impostazioni);
        const primaVisita = await senzaScelta.newPage();

        await visita(primaVisita, `${base}/`, `${base}/ (banner cookie)`);
    }

    // I passaggi: ognuno in una scheda sua, e un passaggio che si rompe e' un
    // errore della scansione come una pagina che non risponde.
    const passaggio = async (nome, azioni, ctx = contesto) => {
        const scheda = await ctx.newPage();
        try {
            await azioni(scheda, (etichetta) => esamina(scheda, null, `${nome} · ${etichetta}`));
        } catch (errore) {
            const motivo = errore.message.split('\n')[0];
            errori.push({ pagina: nome, motivo });
            console.error(`  ! ${nome}: ${motivo}`);
        } finally {
            await scheda.close();
        }
    };
    const apri = async (scheda, percorso) => {
        const risposta = await scheda.goto(`${base}${percorso}`, { waitUntil: 'networkidle', timeout: 45000 });
        const stato = risposta?.status() ?? 0;
        if (stato === 0 || stato >= 400) {
            throw new Error(`${percorso}: stato HTTP ${stato || 'assente'}`);
        }
        await scheda.waitForTimeout(500);
    };
    const attendi = (scheda) => scheda.waitForLoadState('networkidle').then(() => scheda.waitForTimeout(500));

    // Recesso online: i due passaggi stanno nel browser fino alla conferma,
    // quindi si provano anche sul sito vero. La conferma non si preme mai.
    await passaggio('recesso', async (scheda, controlla) => {
        await apri(scheda, '/recesso');
        await scheda.locator('form:has(#recesso-nome) button[type="submit"]').click();
        await attendi(scheda);
        await controlla('errori del passaggio 1');
        await scheda.fill('#recesso-nome', 'Prova Accessibilita');
        await scheda.fill('#recesso-email', 'prova@example.test');
        await scheda.fill('#recesso-numero_ordine', 'SDB-PROVA');
        await scheda.locator('form:has(#recesso-nome) button[type="submit"]').click();
        await attendi(scheda);
        await controlla('riepilogo prima della conferma');
    });

    // La scheda prodotto con la scelta della taglia: in produzione il primo
    // prodotto della vetrina, in locale quello di prova. Il clic su "aggiungi"
    // senza taglia mostra l'errore e non scrive niente.
    await passaggio('scheda prodotto', async (scheda, controlla) => {
        if (FLUSSI) {
            await apri(scheda, `/shop/prodotto/${PROVA.prodotto}`);
        } else {
            await apri(scheda, '/shop');
            const link = scheda.locator('a[href*="/shop/prodotto/"]').first();
            if (! await link.count()) {
                console.warn('  (vetrina vuota o negozio in manutenzione: scheda prodotto saltata)');
                return;
            }
            await apri(scheda, new URL(await link.getAttribute('href'), base).pathname);
        }
        await controlla('scheda');
        const taglie = scheda.locator('[data-scelta-taglia] button');
        if (await taglie.count()) {
            await scheda.locator('[data-aggiungi-al-carrello]').click();
            await scheda.waitForTimeout(300);
            await controlla('errore taglia mancante');
        }
    });

    if (FLUSSI) {
        await passaggio('checkout shop', async (scheda, controlla) => {
            await apri(scheda, `/shop/prodotto/${PROVA.prodotto}`);
            await scheda.locator('[data-scelta-taglia] button:not([disabled])').first().click();
            await scheda.locator('[data-aggiungi-al-carrello]').click();
            await attendi(scheda);
            await apri(scheda, '/shop/carrello');
            await controlla('carrello');
            await apri(scheda, '/shop/checkout');
            await controlla('passaggio 1');
            await scheda.locator('[data-passaggio-successivo]').click();
            await scheda.waitForTimeout(400);
            await controlla('passaggio 1 con errori');
            for (const [campo, valore] of Object.entries({
                '#checkout-guest-name': 'Prova Accessibilita',
                '#checkout-email': 'prova@example.test',
                '#checkout-phone': '055 000 0000',
                '#checkout-first-name': 'Prova',
                '#checkout-last-name': 'Accessibilita',
                '#checkout-street': 'Via di Prova 1',
                '#checkout-city': 'Scandicci',
                '#checkout-zip': '50018',
                '#checkout-province': 'FI',
                '#checkout-cf': 'RSSMRA80A01H501U',
            })) {
                await scheda.fill(campo, valore);
            }
            await scheda.keyboard.press('Escape');
            await scheda.locator('[data-passaggio-successivo]').click();
            await scheda.waitForTimeout(400);
            await controlla('passaggio 2');
            await scheda.getByRole('button', { name: /obbligo di pagamento/i }).click();
            await scheda.waitForTimeout(400);
            await controlla('passaggio 2 con errori');
            await scheda.locator('input[name="payment_gateway"][value="bank_transfer"]').check();
            await scheda.locator('input[aria-required="true"][type="checkbox"]').check();
            await scheda.getByRole('button', { name: /obbligo di pagamento/i }).click();
            await scheda.waitForURL(/\/checkout\/(conferma|confirmation)\//, { timeout: 30000 });
            await attendi(scheda);
            await controlla('conferma ordine (bonifico)');
        });

        // Il checkout dell'asta chiede il vincitore autenticato.
        const vincitore = await browser.newContext(impostazioni);
        await vincitore.addInitScript(([chiave, valore]) => {
            try { window.localStorage.setItem(chiave, valore); } catch { /* vedi sopra */ }
        }, [CHIAVE_CONSENSO, JSON.stringify({ necessary: true, statistiche: false, marketing: false, analytics: false, versione, data: new Date().toISOString() })]);
        await passaggio('checkout asta', async (scheda, controlla) => {
            await apri(scheda, '/login');
            await scheda.fill('input[type="email"]', PROVA.email);
            await scheda.fill('input[type="password"]', PROVA.password);
            await scheda.locator('form:has(input[type="password"]) button[type="submit"]').click();
            await attendi(scheda);
            await apri(scheda, `/shop/checkout/asta/${PROVA.tokenAsta}`);
            await controlla('modulo');
            await scheda.getByRole('button', { name: /obbligo di pagamento/i }).click();
            await scheda.waitForTimeout(400);
            await controlla('modulo con errori');
        }, vincitore);
        await vincitore.close();
    }

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
