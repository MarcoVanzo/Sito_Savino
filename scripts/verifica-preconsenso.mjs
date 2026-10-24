/**
 * Il banner dei cookie messo alla prova come lo userebbe un visitatore.
 *
 * La scansione settimanale (`scansione-cookie.mjs`) scrive il consenso nel
 * browser prima di aprire le pagine: dice che cosa il sito carica, non che il
 * banner funzioni. Qui invece, in un browser pulito, si aprono le pagine che
 * più rischiano di chiamare terzi (home, shop, una scheda prodotto, contatti,
 * palazzetto con la mappa, una pagina con video) e si controlla:
 *
 *   1. senza nessuna scelta non parte niente verso Google, Meta, reCAPTCHA,
 *      YouTube o Maps;
 *   2. dopo «Rifiuta tutti», idem;
 *   3. dopo «Accetta tutti», GA4 e il Pixel di Meta si caricano davvero.
 *
 * La registrazione del consenso sul server (`/consenso-cookie`) viene
 * intercettata e mai inviata: il registro dei consensi è una prova per
 * persone vere, un robot settimanale non deve scriverci.
 *
 *   node scripts/verifica-preconsenso.mjs --url=https://... [--canale=chrome]
 */

import { chromium } from 'playwright';

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

/**
 * Gli host che non devono mai essere contattati prima del consenso. Sono
 * espressioni sul nome dell'host (e, per reCAPTCHA, sul percorso: sta sotto
 * www.google.com).
 */
const VIETATI = [
    { nome: 'Google Tag Manager', host: /(^|\.)googletagmanager\.com$/ },
    { nome: 'Google Analytics', host: /(^|\.)google-analytics\.com$/ },
    { nome: 'Pixel di Meta', host: /(^|\.)connect\.facebook\.net$/ },
    { nome: 'reCAPTCHA', host: /(^|\.)google\.com$/, percorso: /^\/recaptcha\// },
    { nome: 'reCAPTCHA (gstatic)', host: /(^|\.)gstatic\.com$/ },
    { nome: 'YouTube', host: /(^|\.)(youtube|youtube-nocookie|ytimg|googlevideo)\.com$/ },
    { nome: 'Google Maps', host: /^maps\.(google|googleapis|gstatic)\./ },
    { nome: 'Google Maps', host: /(^|\.)google\.com$/, percorso: /^\/maps/ },
];

function vietato(indirizzo) {
    let url;

    try {
        url = new URL(indirizzo);
    } catch {
        return null;
    }

    return VIETATI.find((v) => v.host.test(url.hostname) && (! v.percorso || v.percorso.test(url.pathname)))?.nome ?? null;
}

/**
 * Una scheda prodotto qualunque, presa dalla sitemap: i prodotti cambiano, un
 * indirizzo scritto qui smetterebbe di esistere alla prima stagione.
 */
async function unaSchedaProdotto() {
    const indice = await (await fetch(`${base}/sitemap.xml`)).text();
    const sitemaps = [...indice.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => new URL(m[1]).pathname);
    const elenchi = indice.includes('<sitemapindex') ? sitemaps : ['/sitemap.xml'];

    for (const percorso of elenchi) {
        const xml = percorso === '/sitemap.xml' ? indice : await (await fetch(`${base}${percorso}`)).text();
        const prodotto = [...xml.matchAll(/<loc>([^<]+)<\/loc>/g)]
            .map((m) => new URL(m[1]).pathname)
            .find((p) => /^\/shop\/(prodotto|product|p)\/[^/]+/.test(p));

        if (prodotto) {
            return prodotto;
        }
    }

    return null;
}

// Senza sitemap si prova lo stesso il resto: la scheda prodotto e' una pagina
// in piu', non il motivo della verifica.
const prodotto = await unaSchedaProdotto().catch((errore) => {
    console.warn(`! sitemap illeggibile (${errore.message}).`);

    return null;
});

// La pagina con video: `double-face` ha i video della redazione, e il
// palazzetto la mappa di Google. Gli indirizzi veri, non i vecchi che fanno
// 301. La pagina con video si cambia da riga di comando.
const PAGINE = [
    '/',
    '/shop',
    ...(prodotto ? [prodotto] : []),
    '/contatti',
    '/societa/palazzetto',
    opzioni.video ?? '/comunicazione/double-face',
];

if (! prodotto) {
    console.warn('! nessuna scheda prodotto in sitemap: la salto.');
}

const browser = await chromium.launch(opzioni.canale ? { channel: opzioni.canale } : {});

/**
 * Apre un contesto pulito, che non manda mai al server la scelta fatta sul
 * banner e annota ogni richiesta verso un host vietato.
 */
async function contestoPulito() {
    const contesto = await browser.newContext({ locale: 'it-IT' });
    const contattati = [];

    await contesto.route('**/consenso-cookie', (rotta) => rotta.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ riferimento: 'verifica-automatica' }),
    }));

    contesto.on('request', (richiesta) => {
        const chi = vietato(richiesta.url());

        if (chi) {
            contattati.push({ chi, url: richiesta.url().slice(0, 120) });
        }
    });

    return { contesto, contattati };
}

async function visita(contesto, percorso) {
    const scheda = await contesto.newPage();

    try {
        const risposta = await scheda.goto(`${base}${percorso}`, { waitUntil: 'networkidle', timeout: 45000 });

        if (! risposta || risposta.status() >= 400) {
            throw new Error(`${percorso} risponde ${risposta?.status() ?? 'niente'}`);
        }

        // I tag di misurazione partono dopo il primo rendering.
        await scheda.waitForTimeout(2500);
    } finally {
        await scheda.close();
    }
}

/** Clicca un pulsante del banner sulla home e aspetta che si chiuda. */
async function scegli(contesto, etichetta) {
    const scheda = await contesto.newPage();

    try {
        await scheda.goto(`${base}/`, { waitUntil: 'networkidle', timeout: 45000 });
        const pulsante = scheda.getByRole('button', { name: etichetta, exact: true });
        await pulsante.click({ timeout: 15000 });
        await pulsante.waitFor({ state: 'hidden', timeout: 10000 });
        await scheda.waitForTimeout(2500);
    } finally {
        await scheda.close();
    }
}

const errori = [];

function controllaNessunTerzo(fase, contattati) {
    if (contattati.length === 0) {
        console.log(`  ok: nessuna richiesta verso terzi (${fase})`);

        return;
    }

    for (const { chi, url } of contattati) {
        errori.push(`${fase}: ${chi} → ${url}`);
    }
}

try {
    console.log(`Verifica del consenso su ${base}`);
    console.log(`  pagine: ${PAGINE.join(', ')}`);

    // 1. Nessuna scelta.
    console.log('· senza scelta');
    {
        const { contesto, contattati } = await contestoPulito();

        for (const percorso of PAGINE) {
            await visita(contesto, percorso);
        }

        controllaNessunTerzo('senza scelta', contattati);
        await contesto.close();
    }

    // 2. «Rifiuta tutti».
    console.log('· dopo «Rifiuta tutti»');
    {
        const { contesto, contattati } = await contestoPulito();

        await scegli(contesto, 'Rifiuta tutti');

        for (const percorso of PAGINE) {
            await visita(contesto, percorso);
        }

        controllaNessunTerzo('dopo il rifiuto', contattati);
        await contesto.close();
    }

    // 3. «Accetta tutti»: GA4 e Pixel devono partire.
    console.log('· dopo «Accetta tutti»');
    {
        const { contesto, contattati } = await contestoPulito();

        await scegli(contesto, 'Accetta tutti');
        await visita(contesto, '/');

        const chi = new Set(contattati.map((c) => c.chi));

        for (const atteso of ['Google Tag Manager', 'Pixel di Meta']) {
            if (chi.has(atteso)) {
                console.log(`  ok: ${atteso} caricato`);
            } else {
                errori.push(`dopo l'accettazione: ${atteso} non si è caricato`);
            }
        }

        await contesto.close();
    }
} catch (errore) {
    errori.push(`la verifica non è arrivata in fondo: ${errore.message.split('\n')[0]}`);
} finally {
    await browser.close();
}

if (errori.length > 0) {
    console.error(`\nIl banner dei cookie non fa quello che promette:\n  ${errori.join('\n  ')}`);
    process.exit(1);
}

console.log('\nPrima della scelta e dopo il rifiuto non parte niente; dopo l\'accettazione partono GA4 e il Pixel.');
