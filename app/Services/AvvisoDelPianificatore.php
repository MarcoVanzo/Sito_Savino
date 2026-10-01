<?php

namespace App\Services;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

/**
 * Avvisa per email quando un comando pianificato esce con errore.
 *
 * `schedule:work` manda l'output dei comandi in `/dev/null`: un comando che
 * esce con FAILURE non lascia traccia nei log di App Platform, e senza
 * un'eccezione nemmeno in Sentry. È successo con `social:sync-meta`, fallito
 * ogni notte per settimane con l'APP_KEY vuota sullo scheduler.
 *
 * Si aggancia a tutti gli eventi in fondo a `routes/console.php`, non comando
 * per comando: un comando nuovo è coperto senza ricordarsene. L'output del
 * giro si cattura su un file (uno per comando, riscritto a ogni esecuzione) e
 * la coda finisce nell'email: è l'unica copia di cosa è andato storto.
 */
class AvvisoDelPianificatore
{
    /**
     * Un comando orario rotto per un giorno farebbe ventiquattro email uguali;
     * uno al minuto, millequattrocento. Sei ore bastano a non perderlo di
     * vista senza riempire la casella.
     */
    public const SILENZIO_SECONDI = 6 * 3600;

    /** Quanto output finisce nell'email: la coda, dove sta l'errore. */
    private const CARATTERI_DI_OUTPUT = 4000;

    public static function aggancia(Event $evento): void
    {
        $evento->onFailureWithOutput(
            fn (Stringable $output) => app(self::class)->comandoFallito($evento, (string) $output),
        );
    }

    public function __construct(
        private readonly AvvisoTecnico $avviso,
    ) {}

    public function comandoFallito(Event $evento, string $output): void
    {
        $nome = self::nome($evento);
        $output = trim($output);

        $testo = "Il comando pianificato «{$nome}» è uscito con codice {$evento->exitCode}.\n\n"
            .($output === ''
                ? '(Nessun output.)'
                : "Ultime righe dell'output:\n\n".Str::substr($output, -self::CARATTERI_DI_OUTPUT))
            ."\n\nLo stesso comando non manda un altro avviso per sei ore.";

        $this->avviso->invia(
            'Comando pianificato fallito: '.$nome,
            $testo,
            'pianificatore:'.Str::slug($nome),
            self::SILENZIO_SECONDI,
        );
    }

    /**
     * `lvf:sync` invece di `'/usr/bin/php8.4' 'artisan' lvf:sync`.
     */
    public static function nome(Event $evento): string
    {
        if ($evento->description) {
            return $evento->description;
        }

        $comando = (string) $evento->command;

        return trim(Str::contains($comando, 'artisan') ? Str::after($comando, "'artisan' ") : $comando);
    }
}
