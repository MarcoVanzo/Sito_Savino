<?php

use App\Support\TestiDelleInformative;
use Illuminate\Database\Migrations\Migration;

/**
 * Il titolare del trattamento si indica con la ragione sociale.
 *
 * L'informativa apriva con «Savino Del Bene Volley S.S.D. a r.l.», che non è la
 * denominazione di nessuno: è il nome con cui la squadra gioca, abbreviato a
 * mano. La società è **Pallavolo Scandicci Savino Del Bene Società Sportiva
 * Dilettantistica a Responsabilità Limitata**, ed è verso quella persona
 * giuridica che si esercitano i diritti degli articoli 15-22 — quindi è quella
 * che va scritta per esteso. Nello stesso giro la sede prende la virgola che ha
 * nelle impostazioni del sito («Via Benozzo Gozzoli, 5/6»).
 *
 * Cambia anche la casella: i diritti si esercitano scrivendo a
 * `privacy@savinodelbenevolley.it`, che è la stessa indicata dall'informativa
 * fornitori e da quella promozionale, non a `info@` — il recapito generale del
 * sito, che risponde a tutt'altro.
 *
 * Il copyright del footer continua a dire «Savino Del Bene Volley»: lì serve il
 * nome d'uso, non la denominazione.
 *
 * A guardie come le altre correzioni ai testi in produzione: la firma è il nome
 * sbagliato, che il testo nuovo non contiene più. Se la redazione ha nel
 * frattempo riscritto la pagina, non si tocca.
 */
return new class extends Migration
{
    public function up(): void
    {
        TestiDelleInformative::riscriviDoveNonToccata();
    }

    /**
     * Non si annulla: il nome di prima non era quello del titolare.
     */
    public function down(): void
    {
        // no-op documentato
    }
};
