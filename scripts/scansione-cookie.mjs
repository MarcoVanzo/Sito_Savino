/**
 * La scansione dei cookie e dei tracker del sito, con un browser vero.
 *
 * È il pezzo che i gestori del consenso a pagamento vendono come "scansione
 * automatica": visita un campione di pagine due volte — una senza aver
 * risposto al banner, una dopo aver accettato tutto — e scrive che cosa ha
 * trovato in `database/data/cookie_rilevati.json`, da cui la dichiarazione
 * pubblica si aggiorna da sola.
 *
 * Non è solo un inventario. La passata senza consenso è un controllo: se lì
 * compare un cookie o un host che non sia strettamente necessario, il banner
 * sta chiedendo un permesso che qualcuno si è già preso, e il comando esce con
 * un errore — in GitHub Actions diventa una run rossa, non una riga di log che
 * nessuno legge.
 *
 *   node scripts/scansione-cookie.mjs --url=https://... [--pagine=14] [--scrivi] [--canale=chrome]
 */

import { chromium } from 'playwright';
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const RADICE = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const CATALOGO = JSON.parse(readFileSync(resolve(RADICE, 'database/data/catalogo_cookie.json'), 'utf8'));
const DESTINAZIONE = resolve(RADICE, 'database/data/cookie_rilevati.json');

/** La chiave con cui il sito ricorda la scelta fatta sul banner. */
const CHIAVE_CONSENSO = 'cookie-consent-v2';

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

const quantePagine = Number.parseInt(opzioni.pagine ?? '14', 10);

/**
 * Le pagine da visitare, prese dalla sitemap del sito.
 *
 * Non si scandaglia tutto — duemila indirizzi per trovare gli stessi quattro
 * cookie sarebbero ore di scansione e nessuna informazione in più — ma nemmeno
 * si elencano a mano, o il giorno in cui nasce una sezione nuova nessuno se ne
 * accorge: si prende la sitemap e si campiona una pagina per famiglia.
 */
async function pagineDaVisitare() {
    const risposta = await fetch(`${base}/sitemap.xml`);

    if (! risposta.ok) {
        throw new Error(`La sitemap non risponde (${risposta.status}): senza non so quali pagine guardare.`);
    }

    const indirizzi = [...(await risposta.text()).matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1]);
    const perFamiglia = new Map();

    for (const indirizzo of indirizzi) {
        const percorso = new URL(indirizzo).pathname;
        // "/en/news/qualcosa" e "/news/qualcosa" sono due famiglie: la versione
        // inglese può caricare cose diverse (un video, una mappa).
        const famiglia = percorso.split('/').filter(Boolean).slice(0, 2).join('/') || 'home';

        if (! perFamiglia.has(famiglia)) {
            perFamiglia.set(famiglia, indirizzo);
        }
    }

    return [...perFamiglia.values()].slice(0, quantePagine);
}

function ospite(indirizzo) {
    try {
        return new URL(indirizzo).hostname;
    } catch {
        return null;
    }
}

const nostroHost = ospite(base);

function classificaCookie(nome) {
    for (const voce of CATALOGO.cookie) {
        if (voce.nome === nome || (voce.alias ?? []).includes(nome)) {
            return voce;
        }

        if (voce.schema && new RegExp(voce.schema).test(nome)) {
            return voce;
        }
    }

    return null;
}

function classificaHost(host) {
    return CATALOGO.domini.find((voce) => voce.host === host || host.endsWith(`.${voce.host}`)) ?? null;
}

/**
 * Una passata sul sito. `conConsenso` scrive la scelta nel browser prima che
 * la pagina si carichi, come se il visitatore avesse già accettato tutto.
 */
async function passata(browser, pagine, conConsenso) {
    const contesto = await browser.newContext({ locale: 'it-IT' });

    if (conConsenso) {
        await contesto.addInitScript(([chiave, valore]) => {
            try {
                window.localStorage.setItem(chiave, valore);
            } catch {
                // Se il browser non lo lascia scrivere la passata non vale:
                // se ne accorge il conteggio dei cookie, che resterà vuoto.
            }
        }, [CHIAVE_CONSENSO, JSON.stringify({
            necessary: true,
            statistiche: true,
            marketing: true,
            analytics: true,
            versione: opzioni.versione ?? null,
            data: new Date().toISOString(),
        })]);
    }

    const hostContattati = new Set();

    contesto.on('request', (richiesta) => {
        const host = ospite(richiesta.url());

        if (host && host !== nostroHost) {
            hostContattati.add(host);
        }
    });

    for (const indirizzo of pagine) {
        const scheda = await contesto.newPage();

        try {
            await scheda.goto(indirizzo, { waitUntil: 'networkidle', timeout: 45000 });
            // I tag di misurazione partono dopo il primo rendering: senza
            // questa attesa si fotografa il sito un attimo prima che lo facciano.
            await scheda.waitForTimeout(2500);
        } catch (errore) {
            console.warn(`  ! ${indirizzo}: ${errore.message.split('\n')[0]}`);
        } finally {
            await scheda.close();
        }
    }

    const cookie = await contesto.cookies();

    await contesto.close();

    return { cookie, host: [...hostContattati].sort() };
}

function riassumi({ cookie, host }, conConsenso) {
    const righeCookie = cookie.map((c) => {
        const conosciuto = classificaCookie(c.name);

        return {
            nome: c.name,
            dominio: c.domain,
            categoria: conosciuto?.categoria ?? 'non classificati',
            fornitore: conosciuto?.fornitore ?? null,
            durata: conosciuto?.durata ?? null,
            scopo: conosciuto?.scopo ?? null,
            scadenza: c.expires && c.expires > 0 ? new Date(c.expires * 1000).toISOString().slice(0, 10) : 'sessione',
            conConsenso,
        };
    });

    const righeHost = host.map((h) => {
        const conosciuto = classificaHost(h);

        return {
            host: h,
            categoria: conosciuto?.categoria ?? 'non classificati',
            fornitore: conosciuto?.fornitore ?? null,
            conConsenso,
        };
    });

    return { cookie: righeCookie, host: righeHost };
}

// In GitHub Actions si usa il Chromium che Playwright scarica; in locale
// conviene `--canale=chrome`, che prende il Chrome già installato invece di
// tirare giù altri 150 MB.
const browser = await chromium.launch(opzioni.canale ? { channel: opzioni.canale } : {});

try {
    const pagine = await pagineDaVisitare();

    console.log(`Scansione di ${pagine.length} pagine su ${base}`);

    console.log('· senza consenso');
    const prima = riassumi(await passata(browser, pagine, false), false);

    console.log('· con tutto accettato');
    const dopo = riassumi(await passata(browser, pagine, true), true);

    // Chi c'era già prima del consenso non si conta due volte.
    const nomiPrima = new Set(prima.cookie.map((c) => c.nome));
    const hostPrima = new Set(prima.host.map((h) => h.host));

    const rilevato = {
        generato_il: new Date().toISOString(),
        indirizzo: base,
        pagine_visitate: pagine.length,
        cookie: [
            ...prima.cookie,
            ...dopo.cookie.filter((c) => ! nomiPrima.has(c.nome)),
        ].sort((a, b) => a.nome.localeCompare(b.nome)),
        host: [
            ...prima.host,
            ...dopo.host.filter((h) => ! hostPrima.has(h.host)),
        ].sort((a, b) => a.host.localeCompare(b.host)),
    };

    // Senza consenso si ammette solo ciò che è strettamente necessario: tutto
    // il resto è un permesso che qualcuno si è preso da solo.
    const abusivi = [
        ...prima.cookie.filter((c) => c.categoria !== 'necessari').map((c) => `cookie ${c.nome} (${c.categoria})`),
        ...prima.host.filter((h) => h.categoria !== 'necessari').map((h) => `richiesta a ${h.host} (${h.categoria})`),
    ];

    const nonClassificati = [
        ...rilevato.cookie.filter((c) => c.categoria === 'non classificati').map((c) => `cookie ${c.nome}`),
        ...rilevato.host.filter((h) => h.categoria === 'non classificati').map((h) => `host ${h.host}`),
    ];

    rilevato.senza_consenso_in_regola = abusivi.length === 0;
    rilevato.non_classificati = nonClassificati;

    if (opzioni.scrivi) {
        writeFileSync(DESTINAZIONE, `${JSON.stringify(rilevato, null, 4)}\n`);
        console.log(`Scritto ${DESTINAZIONE.replace(RADICE + '/', '')}`);
    } else {
        console.log(JSON.stringify(rilevato, null, 2));
    }

    console.log(`\nCookie: ${rilevato.cookie.length} · host esterni: ${rilevato.host.length}`);

    if (nonClassificati.length > 0) {
        console.log(`\nDa classificare in database/data/catalogo_cookie.json:\n  ${nonClassificati.join('\n  ')}`);
    }

    if (abusivi.length > 0) {
        console.error(`\nPrima del consenso il sito ha già fatto partire:\n  ${abusivi.join('\n  ')}`);
        process.exit(1);
    }

    console.log('\nPrima del consenso non parte niente che non sia necessario.');
} finally {
    await browser.close();
}
