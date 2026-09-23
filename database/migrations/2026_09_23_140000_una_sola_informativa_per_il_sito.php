<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Il footer serviva due PDF al posto delle pagine dell'informativa.
 *
 * `SiteFooter.vue` chiedeva `legalDocs.privacy_policy` e ripiegava sulla pagina
 * solo se il PDF mancava. I PDF c'erano, quindi da ogni pagina del sito il link
 * "Privacy Policy" apriva un documento e non l'informativa che la redazione
 * mantiene dal pannello — quella che il banner dei cookie e le caselle di
 * newsletter, registrazione e checkout fanno accettare.
 *
 * I due documenti, peraltro, dicevano altro:
 *
 * - `Informativa Cookie.pdf` è l'informativa del vecchio sito WordPress:
 *   elenca i cookie del plugin GDPR Cookie Consent, di AddThis e di Universal
 *   Analytics (`_gat_gtag_UA_80836627_23`), che qui non esistono, e non nomina
 *   il pixel di Meta, che invece c'è. Si fonda sul D.Lgs. 196/2003 e sul
 *   provvedimento del Garante del 2014. Va ritirata: il sito che descrive non
 *   c'è più.
 * - `Informativa generale Privacy.pdf` non è l'informativa del sito: riguarda
 *   l'invio di informazioni e promozioni via email, social e WhatsApp. È un
 *   documento valido, ma stava sotto il nome sbagliato — resta, come
 *   "Informativa comunicazioni promozionali".
 *
 * I file su Spaces non si toccano: qui si cambia solo quale impostazione li
 * indica, e il footer torna a mandare alle pagine.
 *
 * Le impostazioni convivono in due forme (`group` = 'legal' con chiave nuda,
 * oppure chiave `legal.x` nel gruppo predefinito: le riconcilia
 * `SiteSetting::collocazione()`), e in produzione e in locale non è la stessa.
 * Qui si cercano entrambe.
 */
return new class extends Migration
{
    private const PROMOZIONALE = 'informativa_promozionale';

    public function up(): void
    {
        $questa = fn (string $chiave) => DB::table('site_settings')
            ->where(fn ($q) => $q->where('key', $chiave)->orWhere('key', 'legal.'.$chiave));

        // Se la casella nuova è già compilata comanda lei: questa migrazione
        // non passa sopra a una scelta fatta dal pannello.
        $giaScelta = (clone $questa(self::PROMOZIONALE))->whereNotNull('value')->where('value', '!=', '')->exists();

        $vecchia = (clone $questa('privacy_policy'))->first();

        if (! $giaScelta && $vecchia && (string) $vecchia->value !== '') {
            // La riga si rinomina invece di crearne una nuova: così resta nella
            // forma che quell'ambiente usa già, gruppo compreso.
            DB::table('site_settings')->where('id', $vecchia->id)->update([
                'key' => str_contains((string) $vecchia->key, '.') ? 'legal.'.self::PROMOZIONALE : self::PROMOZIONALE,
                'updated_at' => now(),
            ]);
        } else {
            (clone $questa('privacy_policy'))->delete();
        }

        // L'informativa cookie del vecchio sito non ha un posto dove andare.
        (clone $questa('cookie_policy'))->delete();
    }

    /**
     * Non si annulla: rimettere quelle chiavi significherebbe rimettere il PDF
     * davanti all'informativa vera.
     */
    public function down(): void
    {
        // no-op documentato
    }
};
