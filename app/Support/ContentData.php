<?php

namespace App\Support;

/**
 * Riporta `content_data` alla forma che i template si aspettano.
 *
 * Lo stato grezzo di un modulo Filament non è quello che va in archivio: un
 * Repeater tiene le voci in una mappa `{uuid: voce}` e un FileUpload singolo
 * tiene il percorso in una mappa `{uuid: percorso}`. Quella forma è finita in
 * tabella per le pagine salvate dal pannello (piani abbonamento, progetti,
 * valori, cartelle stampa, documenti), e i componenti Vue — che chiedono un
 * elenco con `Array.isArray` — nascondevano la sezione intera.
 *
 * Qui una mappa con sole chiavi UUID torna a essere un elenco, e una mappa
 * UUID con un solo percorso torna a essere il percorso. Il resto passa
 * intatto. È idempotente: su dati già corretti non cambia nulla.
 */
class ContentData
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * Le chiavi che nei form del CMS sono elenchi ripetibili (Repeater).
     *
     * Servono perché i modelli di pagina condividono lo spazio dei nomi di
     * `content_data` e Filament idrata anche i campi delle sezioni nascoste:
     * un testo salvato sotto il nome di un elenco — è successo con `partners`,
     * nota del Talent Day ed elenco delle Convenzioni — arrivava al Repeater
     * dell'altro modello e mandava la pagina in 500 prima di disegnarla.
     *
     * `ContentDataChiaviElencoTest` verifica che questo elenco sia lo stesso
     * che i form dichiarano.
     *
     * @var list<string>
     */
    public const CHIAVI_ELENCO = [
        'activities',
        'affiliates',
        'benefits',
        'contacts_list',
        'dates',
        'documents',
        'form_topics',
        'impact_stats',
        'magazines',
        'partners',
        'phases',
        'plans',
        'press_kits',
        'projects',
        'services',
        'slots',
        'stages',
        'standings',
        'team_photos',
        'timeline',
        'youtube_videos',
    ];

    public static function normalizza(mixed $valore): mixed
    {
        if (! is_array($valore)) {
            return $valore;
        }

        if ($valore !== [] && self::haSoloChiaviUuid($valore)) {
            $voci = array_values(array_map(self::normalizza(...), $valore));

            // Un FileUpload singolo: la mappa contiene il solo percorso.
            if (count($voci) === 1 && is_string($voci[0])) {
                return $voci[0];
            }

            return $voci;
        }

        return array_map(self::normalizza(...), $valore);
    }

    /**
     * @param  array<int|string, mixed>  $valore
     */
    private static function haSoloChiaviUuid(array $valore): bool
    {
        foreach (array_keys($valore) as $chiave) {
            if (! is_string($chiave) || preg_match(self::UUID, $chiave) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Gli elenchi rimasti vuoti in una lingua prendono quelli della lingua
     * di partenza.
     *
     * `content_data` è tradotto in blocco, quindi una classifica, un listino,
     * i partner di una convenzione o i PDF di una pagina andrebbero ricopiati
     * a mano in ogni lingua. La redazione li compila in italiano e basta: in
     * inglese la Club Race diceva "classifica non disponibile", le Convenzioni
     * "offerte in arrivo", e il Bilancio di Sostenibilità non aveva il PDF.
     * Un elenco in italiano dentro una pagina inglese è meglio di una sezione
     * che dichiara vuoto ciò che vuoto non è; appena la lingua ha un elenco
     * suo, vale quello.
     *
     * @param  array<string, mixed>  $contenuti  i valori della lingua richiesta
     * @param  array<string, mixed>  $diPartenza  i valori della lingua di partenza
     * @return array<string, mixed>
     */
    public static function conGliElenchiDiRipiego(array $contenuti, array $diPartenza): array
    {
        foreach (self::CHIAVI_ELENCO as $chiave) {
            $elenco = $contenuti[$chiave] ?? null;
            $ripiego = $diPartenza[$chiave] ?? null;

            if (($elenco === null || $elenco === []) && is_array($ripiego) && $ripiego !== []) {
                $contenuti[$chiave] = $ripiego;
            }
        }

        // Lo stesso vale per i valori che non hanno lingua: un link d'acquisto,
        // un'immagine, un'email, un numero. In inglese la Biglietteria non
        // aveva il link a Vivaticket né la grafica della Gift Card, Hospitality
        // non aveva il pulsante, Sponsor non aveva i numeri: nessuno li aveva
        // ricopiati nella scheda inglese. I testi restano senza ripiego.
        foreach ($diPartenza as $chiave => $ripiego) {
            if (! self::nonHaLingua((string) $chiave) || ! is_string($ripiego) || trim($ripiego) === '') {
                continue;
            }

            $valore = $contenuti[$chiave] ?? null;

            if ($valore === null || $valore === '' || $valore === []) {
                $contenuti[$chiave] = $ripiego;
            }
        }

        return $contenuti;
    }

    /**
     * Le chiavi il cui valore è lo stesso in ogni lingua, riconosciute dal
     * suffisso con cui i form le chiamano (`tickets_url`, `gift_card_image`,
     * `maps_link`, `report_email`, `maps_iframe_src`, `stat1_value`…).
     */
    private static function nonHaLingua(string $chiave): bool
    {
        foreach (['_url', '_image', '_link', '_email', '_src', '_value', '_file'] as $suffisso) {
            if (str_ends_with($chiave, $suffisso)) {
                return true;
            }
        }

        return false;
    }

    /**
     * `content_data` pronto per riempire il modulo: le chiavi che il modulo
     * tratta come elenchi restano solo se in archivio sono davvero elenchi.
     *
     * Un valore di un altro tipo non si perde — in archivio resta, e il
     * salvataggio riscrive solo i campi che il modulo mostra — ma non viene
     * dato in pasto a un Repeater, che con una stringa in mano fallisce.
     *
     * @param  array<string, mixed>  $contenuti
     * @return array<string, mixed>
     */
    public static function soloElenchiValidi(array $contenuti): array
    {
        foreach (self::CHIAVI_ELENCO as $chiave) {
            if (array_key_exists($chiave, $contenuti) && ! is_array($contenuti[$chiave])) {
                unset($contenuti[$chiave]);
            }
        }

        return $contenuti;
    }
}
