<?php

namespace App\Services;

use App\Models\ConsensoCookie;
use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * La catena delle impronte del registro dei consensi ai cookie.
 *
 * Ogni riga porta l'HMAC dei propri campi e dell'impronta della riga prima:
 * ritoccare una data, un'origine o una scelta, togliere o aggiungere una riga
 * in mezzo, spezza la catena da quel punto in poi, e `consensi:verifica` lo
 * trova (Garante, 25/09/2025: data e origine del consenso non devono potersi
 * modificare).
 *
 * L'impronta è un HMAC-SHA256 con il segreto `CONSENSI_SALE` (lo stesso
 * dell'impronta dell'IP, ConsensoCookie::sale()): chi ha accesso in scrittura
 * al database ma non al segreto non può ricalcolare la catena dopo aver
 * ritoccato una riga. Il rovescio: cambiare il segreto fa risultare alterato
 * tutto il registro. Si imposta una volta e non si tocca più; se manca, vale
 * `APP_KEY`, e ruotarla senza aver prima impostato `CONSENSI_SALE` rompe la
 * catena (la verifica lo segnala alla prima riga).
 *
 * La testa del registro si accorcia con `consensi:pota`, che lascia in
 * `consensi_cookie_potature` l'impronta dell'ultima riga tolta: la prima riga
 * rimasta deve agganciarsi a quella, e diventa l'ancora.
 */
class CatenaDeiConsensi
{
    /**
     * I campi che entrano nell'impronta, in quest'ordine. Cambiarli rompe la
     * verifica di tutte le righe già scritte.
     */
    public const CAMPI = [
        'riferimento',
        'statistiche',
        'marketing',
        'azione',
        'versione',
        'impronta_testi',
        'locale',
        'user_agent',
        'impronta_ip',
        'created_at',
    ];

    private const LUCCHETTO = 'consensi_cookie:catena';

    /**
     * Registra un consenso in coda alla catena.
     *
     * Leggere l'ultima impronta e scrivere la riga nuova devono stare insieme:
     * due consensi nello stesso istante leggerebbero la stessa ultima riga e
     * la catena si biforcherebbe. Il lucchetto sta nello store `persistente`
     * (nel database, condiviso fra le istanze del web), non in quello
     * predefinito che `start.sh` svuota a ogni avvio.
     *
     * @param  array<string, mixed>  $attributi
     */
    public static function registra(array $attributi): ConsensoCookie
    {
        return self::conIlLucchetto(function () use ($attributi) {
            // Righe scritte dal codice di prima durante un rilascio (il web
            // vecchio serve ancora mentre il nuovo parte): si sigillano prima
            // di attaccarci la nuova, o la verifica le troverebbe scoperte.
            self::sigillaLeMancanti();

            $attributi['created_at'] = now()->format('Y-m-d H:i:s');
            $attributi['impronta_precedente'] = self::ultimaImpronta();
            $attributi['impronta_riga'] = self::impronta($attributi, $attributi['impronta_precedente']);

            $consenso = new ConsensoCookie;
            $consenso->forceFill($attributi)->save();

            return $consenso;
        });
    }

    /**
     * Sigilla, in ordine di id, le righe che non hanno ancora l'impronta: le
     * righe scritte prima della catena (dalla migrazione) e quelle del codice
     * vecchio durante un rilascio. Restituisce quante ne ha sigillate.
     */
    public static function sigillaIMancanti(): int
    {
        return self::conIlLucchetto(fn () => self::sigillaLeMancanti());
    }

    private static function sigillaLeMancanti(): int
    {
        $sigillate = 0;
        $primaScoperta = DB::table('consensi_cookie')->whereNull('impronta_riga')->min('id');

        if ($primaScoperta === null) {
            return 0;
        }

        $precedente = DB::table('consensi_cookie')
            ->where('id', '<', $primaScoperta)
            ->orderByDesc('id')
            ->value('impronta_riga') ?? self::impronteDellaPotatura();

        DB::table('consensi_cookie')
            ->where('id', '>=', $primaScoperta)
            ->chunkById(500, function ($righe) use (&$precedente, &$sigillate) {
                foreach ($righe as $riga) {
                    $riga = (array) $riga;

                    // Una riga già sigillata più avanti (non dovrebbe esserci:
                    // le righe nuove passano dal lucchetto) non si riscrive:
                    // la catena da lì la giudica la verifica.
                    if ($riga['impronta_riga'] !== null) {
                        $precedente = $riga['impronta_riga'];

                        continue;
                    }

                    $impronta = self::impronta($riga, $precedente);

                    DB::table('consensi_cookie')->where('id', $riga['id'])->update([
                        'impronta_precedente' => $precedente,
                        'impronta_riga' => $impronta,
                    ]);

                    $precedente = $impronta;
                    $sigillate++;
                }
            });

        return $sigillate;
    }

    /**
     * L'HMAC-SHA256 di una riga, dai valori come stanno nel database.
     *
     * I valori si portano tutti a stringa (o null) prima di serializzarli: il
     * driver restituisce `1` o `"1"` per lo stesso booleano a seconda della
     * versione, e l'impronta deve venire uguale in scrittura e in verifica.
     *
     * @param  array<string, mixed>  $riga
     */
    public static function impronta(array $riga, ?string $precedente): string
    {
        $valori = [];

        foreach (self::CAMPI as $campo) {
            $valore = $riga[$campo] ?? null;

            $valori[$campo] = match (true) {
                $valore === null => null,
                in_array($campo, ['statistiche', 'marketing'], true) => (string) (int) (bool) $valore,
                $valore instanceof \DateTimeInterface => $valore->format('Y-m-d H:i:s'),
                default => (string) $valore,
            };
        }

        $valori['impronta_precedente'] = $precedente;

        return hash_hmac(
            'sha256',
            json_encode($valori, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ConsensoCookie::sale(),
        );
    }

    /**
     * Ricalcola la catena dalla prima riga rimasta all'ultima.
     *
     * @return array{righe: int, ultima_impronta: ?string, guasto: ?array{id: int, motivo: string}}
     */
    public static function verifica(): array
    {
        $righe = 0;
        $precedente = null;
        $guasto = null;
        $attesaAllAncora = self::impronteDellaPotatura();

        DB::table('consensi_cookie')->chunkById(500, function ($blocco) use (&$righe, &$precedente, &$guasto, $attesaAllAncora) {
            foreach ($blocco as $riga) {
                $riga = (array) $riga;

                if ($riga['impronta_riga'] === null) {
                    $guasto = ['id' => (int) $riga['id'], 'motivo' => 'la riga non è sigillata'];

                    return false;
                }

                // La prima riga è l'ancora: deve agganciarsi all'ultima riga
                // tolta da `consensi:pota` (o a niente, se non si è mai potato).
                $attesa = $righe === 0 ? $attesaAllAncora : $precedente;

                if ($riga['impronta_precedente'] !== $attesa) {
                    $guasto = ['id' => (int) $riga['id'], 'motivo' => $righe === 0
                        ? 'la prima riga non si aggancia all\'ultima potatura: mancano righe in testa che consensi:pota non ha tolto'
                        : 'la catena si interrompe: la riga prima è stata tolta, aggiunta o cambiata'];

                    return false;
                }

                if (self::impronta($riga, $riga['impronta_precedente']) !== $riga['impronta_riga']) {
                    $guasto = ['id' => (int) $riga['id'], 'motivo' => 'il contenuto della riga è stato cambiato'];

                    return false;
                }

                $precedente = $riga['impronta_riga'];
                $righe++;
            }

            return true;
        });

        return ['righe' => $righe, 'ultima_impronta' => $precedente, 'guasto' => $guasto];
    }

    /**
     * Toglie le righe più vecchie di `$limite`, lasciando l'ancora per la
     * verifica. Si toglie sempre una testa contigua (fino all'ultimo id più
     * vecchio del limite): un buco in mezzo spezzerebbe la catena.
     */
    public static function pota(\DateTimeInterface $limite): int
    {
        return self::conIlLucchetto(function () use ($limite) {
            $finoA = DB::table('consensi_cookie')->where('created_at', '<', $limite)->max('id');

            if ($finoA === null) {
                return 0;
            }

            $ultima = DB::table('consensi_cookie')->where('id', $finoA)->value('impronta_riga');

            return DB::transaction(function () use ($finoA, $ultima) {
                $tolte = DB::table('consensi_cookie')->where('id', '<=', $finoA)->delete();

                DB::table('consensi_cookie_potature')->insert([
                    'fino_a_id' => $finoA,
                    'righe' => $tolte,
                    'ultima_impronta' => $ultima,
                    'created_at' => now(),
                ]);

                return $tolte;
            });
        });
    }

    private static function ultimaImpronta(): ?string
    {
        return DB::table('consensi_cookie')->orderByDesc('id')->value('impronta_riga')
            ?? self::impronteDellaPotatura();
    }

    private static function impronteDellaPotatura(): ?string
    {
        return DB::table('consensi_cookie_potature')->orderByDesc('id')->value('ultima_impronta');
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $lavoro
     * @return T
     */
    private static function conIlLucchetto(Closure $lavoro): mixed
    {
        // Dieci secondi di vita e cinque di attesa: una registrazione dura
        // millisecondi, e se il lucchetto non arriva la richiesta fallisce e
        // il banner riprova alla visita successiva (consenso.js).
        $store = Cache::store('persistente')->getStore();

        // Uno store senza lucchetti (file su più istanze, null) lascerebbe
        // biforcare la catena in silenzio: meglio non registrare.
        if (! $store instanceof LockProvider) {
            throw new LogicException('Lo store di cache `persistente` non offre lucchetti: la catena dei consensi non si può scrivere in sicurezza.');
        }

        return $store->lock(self::LUCCHETTO, 10)->block(5, $lavoro);
    }
}
