<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L'informativa privacy indica `privacy@savinodelbenevolley.com` come indirizzo
 * a cui scrivere per esercitare i diritti degli articoli 15-22 del GDPR. Quel
 * dominio non esiste — la società è sul `.it` — quindi una richiesta scritta
 * seguendo l'informativa non arriva a nessuno: è l'unico recapito che una legge
 * obbliga a tenere raggiungibile.
 *
 * Si sostituisce solo il dominio, in entrambe le lingue, senza toccare il resto
 * del testo: l'informativa è un documento legale e non è materia da riscrivere
 * in una migrazione.
 */
return new class extends Migration
{
    private const SBAGLIATO = 'savinodelbenevolley.com';

    private const GIUSTO = 'savinodelbenevolley.it';

    public function up(): void
    {
        $this->sostituisci(self::SBAGLIATO, self::GIUSTO);
    }

    public function down(): void
    {
        $this->sostituisci(self::GIUSTO, self::SBAGLIATO);
    }

    private function sostituisci(string $da, string $a): void
    {
        $pagina = DB::table('pages')->where('slug', 'privacy-policy')->first(['id', 'content']);

        if ($pagina === null || ! str_contains((string) $pagina->content, $da)) {
            return;
        }

        DB::table('pages')->where('id', $pagina->id)->update([
            // La colonna è translatable: contiene il JSON con le due lingue e la
            // sostituzione vale per entrambe senza doverlo decodificare.
            'content' => str_replace($da, $a, (string) $pagina->content),
            'updated_at' => now(),
        ]);
    }
};
