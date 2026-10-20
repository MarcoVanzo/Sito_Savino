/**
 * Le chiavi che il vecchio sito WordPress lasciava nel `localStorage` di chi lo
 * visitava, sullo stesso indirizzo di oggi.
 *
 * Il modulo d'iscrizione di ActiveCampaign incorporato in quelle pagine
 * (`activehosted.com/f/embed.php?id=1`) scriveva `_form_1_` e `acf`, e il
 * reCAPTCHA di Contact Form 7 `_grecaptcha`. Il sito nuovo non carica né l'uno
 * né l'altro (verificato il 20/10/2026 con un browser pulito, sito pubblico e
 * pannello), ma il browser li conserva: chi apre gli strumenti di sviluppo li
 * trova e pensa che reCAPTCHA sia ancora qui. Si tolgono al primo caricamento,
 * come `RispostaSenzaResidui` fa scadere i cookie dello stesso sito.
 *
 * Dal 1° aprile 2027, come per quei cookie, nessuno dovrebbe più averli: allora
 * il modulo si può togliere.
 */
const CHIAVI = ['_grecaptcha', 'acf'];
const PREFISSI = ['_form_'];

export function dimenticaIResiduiDelVecchioSito(archivio = globalThis.localStorage) {
    try {
        const daTogliere = [];

        for (let i = 0; i < archivio.length; i++) {
            const chiave = archivio.key(i);

            if (chiave && (CHIAVI.includes(chiave) || PREFISSI.some((p) => chiave.startsWith(p)))) {
                daTogliere.push(chiave);
            }
        }

        daTogliere.forEach((chiave) => archivio.removeItem(chiave));

        return daTogliere;
    } catch {
        // Navigazione privata o archivio bloccato: non c'è niente da pulire.
        return [];
    }
}
