<?php

namespace App\Console\Commands;

use App\Services\CatenaDeiConsensi;
use Illuminate\Console\Command;

/**
 * Ricalcola la catena delle impronte del registro dei consensi ai cookie e
 * dice la prima riga alterata.
 *
 * Gira dallo scheduler una volta a settimana: se esce con errore,
 * `AvvisoDelPianificatore` manda l'avviso per email. Una catena rotta vuol dire
 * che qualcuno ha toccato il registro fuori dal sito — un consenso ritoccato
 * non prova più niente, e va capito prima che qualcuno lo chieda.
 *
 * L'ultima impronta stampata in fondo riassume tutto il registro fino a quel
 * momento: annotata fuori dal database (un'email, un ticket), rende
 * riconoscibile anche una catena ricalcolata per intero.
 */
class VerificaIConsensiCookie extends Command
{
    protected $signature = 'consensi:verifica';

    protected $description = 'Verifica la catena delle impronte del registro dei consensi ai cookie';

    public function handle(): int
    {
        $esito = CatenaDeiConsensi::verifica();

        if ($esito['guasto'] !== null) {
            $this->error(sprintf(
                'Registro dei consensi alterato alla riga %d: %s. Righe verificate prima del guasto: %d.',
                $esito['guasto']['id'],
                $esito['guasto']['motivo'],
                $esito['righe'],
            ));

            return self::FAILURE;
        }

        if ($esito['righe'] === 0) {
            $this->info('Registro dei consensi vuoto: niente da verificare.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Catena integra: %d %s. Ultima impronta: %s',
            $esito['righe'],
            $esito['righe'] === 1 ? 'riga' : 'righe',
            $esito['ultima_impronta'],
        ));

        return self::SUCCESS;
    }
}
