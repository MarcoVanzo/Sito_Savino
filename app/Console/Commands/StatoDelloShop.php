<?php

namespace App\Console\Commands;

use App\Http\Middleware\CachePublicResponse;
use App\Models\Auction;
use App\Models\SiteSetting;
use Illuminate\Console\Command;

/**
 * Apre e chiude il negozio e le aste da riga di comando.
 *
 * I due interruttori si governano solo dalla pagina "Impostazioni Shop &
 * Aste", e quando sono spenti per sbaglio — com'è successo il 21/09/2026, con
 * il primo salvataggio di un modulo che si apriva vuoto — riaccenderli
 * richiede di entrare nel pannello con le credenziali di un super admin. Da
 * fuori resta il database, che in produzione si interroga in sola lettura, e
 * la console dell'app, dove una riga lunga arriva storpiata (il websocket
 * duplica caratteri: `siite_settings`, `typpe`). Da qui bastano tre parole.
 *
 * Senza argomenti dice soltanto come stanno, tutti e due, e non scrive niente:
 * è la domanda che si fa per prima quando qualcosa non si vede online.
 */
class StatoDelloShop extends Command
{
    protected $signature = 'shop:stato
        {interruttore? : negozio oppure aste; da solo mostra quello, omesso mostra entrambi}
        {stato? : aperto oppure chiuso}';

    protected $description = 'Mostra, apre o chiude il negozio pubblico e le aste (shop.enabled, auctions.enabled)';

    /**
     * Gli interruttori governati, con la chiave dell'impostazione e le parole
     * con cui l'output li nomina.
     *
     * @var array<string, array{chiave: string, nome: string, titolo: string, e: string, era: string, acceso: string, spento: string}>
     */
    private const INTERRUTTORI = [
        'negozio' => [
            'chiave' => 'shop.enabled',
            'nome' => 'Il negozio', 'titolo' => 'Negozio',
            'e' => 'è', 'era' => 'era',
            'acceso' => 'aperto', 'spento' => 'chiuso',
        ],
        'aste' => [
            'chiave' => 'auctions.enabled',
            'nome' => 'Le aste', 'titolo' => 'Aste',
            'e' => 'sono', 'era' => 'erano',
            'acceso' => 'attive', 'spento' => 'sospese',
        ],
    ];

    /** @var array<string, string> */
    private const ALIAS = ['shop' => 'negozio', 'auctions' => 'aste', 'asta' => 'aste'];

    public function handle(): int
    {
        [$interruttore, $stato] = $this->argomenti();

        if ($interruttore !== null && ! isset(self::INTERRUTTORI[$interruttore])) {
            $this->error('Interruttore non riconosciuto: usare "negozio" o "aste".');

            return self::FAILURE;
        }

        if ($stato === null) {
            foreach ($interruttore === null ? array_keys(self::INTERRUTTORI) : [$interruttore] as $quale) {
                $this->mostra($quale);
            }

            return self::SUCCESS;
        }

        $vuoleAcceso = $this->leggiLoStato($stato);

        if ($vuoleAcceso === null) {
            $this->error('Stato non riconosciuto: usare "aperto" o "chiuso".');

            return self::FAILURE;
        }

        return $this->cambia($interruttore ?? 'negozio', $vuoleAcceso);
    }

    /**
     * Gli argomenti, con la forma di prima ancora valida.
     *
     * Il comando è nato governando il solo negozio (`shop:stato aperto`) ed è
     * quello che si digita di corsa quando il sito è chiuso: chiedergli in
     * quel momento una parola in più sarebbe un errore da leggere e correggere
     * sotto pressione.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function argomenti(): array
    {
        $primo = $this->argomentoNormalizzato('interruttore');
        $secondo = $this->argomentoNormalizzato('stato');

        if ($primo !== null && $secondo === null && $this->leggiLoStato($primo) !== null) {
            return [null, $primo];
        }

        return [$primo, $secondo];
    }

    private function argomentoNormalizzato(string $nome): ?string
    {
        $valore = $this->argument($nome);

        if ($valore === null) {
            return null;
        }

        $valore = mb_strtolower(trim($valore));

        return self::ALIAS[$valore] ?? $valore;
    }

    private function leggiLoStato(string $parola): ?bool
    {
        return match ($parola) {
            'aperto', 'aperte', 'apri', 'attive', 'attivo', 'on', '1' => true,
            'chiuso', 'chiuse', 'chiudi', 'sospese', 'sospeso', 'off', '0' => false,
            default => null,
        };
    }

    private function mostra(string $quale): void
    {
        $acceso = $this->eAcceso($quale);
        $parole = self::INTERRUTTORI[$quale];
        $parola = $acceso
            ? '<info>'.$parole['acceso'].'</info>'
            : '<comment>'.$parole['spento'].'</comment>';

        $this->line($parole['nome'].' '.$parole['e'].' '.$parola.'.');

        if ($quale === 'aste' && $acceso) {
            $this->avvisaSeNonCeNienteDaVedere();
        }
    }

    private function cambia(string $quale, bool $vuoleAcceso): int
    {
        $parole = self::INTERRUTTORI[$quale];

        if ($vuoleAcceso === $this->eAcceso($quale)) {
            $this->line($parole['nome'].' '.$parole['era'].' già '.($vuoleAcceso ? $parole['acceso'] : $parole['spento']).': niente da fare.');

            return self::SUCCESS;
        }

        $this->scrivi($parole['chiave'], $vuoleAcceso);

        // La pagina pubblica sta in cache per un minuto: senza questo, il
        // negozio riaprirebbe con quel ritardo e chi lancia il comando
        // penserebbe che non ha funzionato.
        CachePublicResponse::flush();

        // Riletto dall'archivio, non dato per scritto: in console l'eco della
        // riga digitata non è affidabile, e la conferma deve valere qualcosa.
        if ($this->eAcceso($quale) !== $vuoleAcceso) {
            $this->error($parole['titolo'].': la scrittura non ha avuto effetto.');

            return self::FAILURE;
        }

        $this->info($parole['titolo'].' '.($vuoleAcceso ? $parole['acceso'] : $parole['spento']).'.');

        if ($quale === 'aste' && $vuoleAcceso) {
            $this->avvisaSeNonCeNienteDaVedere();
        }

        return self::SUCCESS;
    }

    /**
     * Lo stesso ripiego del codice che governa davvero le due sezioni
     * (ShopController::index e EnsureAuctionsEnabled): senza la riga in
     * archivio sono aperte.
     */
    private function eAcceso(string $quale): bool
    {
        return filter_var(SiteSetting::get(self::INTERRUTTORI[$quale]['chiave'], true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Scrive sull'impostazione che governa davvero la sezione.
     *
     * La stessa impostazione può stare in archivio in due forme — la chiave
     * intera (`shop.enabled`) o la chiave nuda nella colonna `group` — e in
     * SiteSetting::get() la prima vince sulla seconda: creare la forma
     * letterale quando esiste solo l'altra oscurerebbe in silenzio il valore
     * buono. Quindi si cerca la riga che c'è e si scrive su quella.
     *
     * Quando non c'è affatto, la riga si crea completa: `type` fa di un valore
     * un interruttore, in lettura e nel modulo del pannello, e SiteSetting::set()
     * non lo scrive — una riga senza tipo torna al pannello come testo, e al
     * frontend come la stringa "0", che in JavaScript è vera.
     */
    private function scrivi(string $chiave, bool $valore): void
    {
        [$gruppo, $nuda] = explode('.', $chiave, 2);

        $riga = SiteSetting::query()->where('key', $chiave)->first()
            ?? SiteSetting::query()->where('key', $nuda)->where('group', $gruppo)->first();

        if ($riga === null) {
            $definizione = collect(SiteSetting::definizioniDelloShop())->firstWhere('key', $chiave) ?? [];

            SiteSetting::create(array_merge($definizione, [
                'key' => $chiave,
                'value' => $valore ? '1' : '0',
                'type' => 'boolean',
            ]));

            return;
        }

        $riga->value = $valore ? '1' : '0';
        $riga->type = $riga->type ?: 'boolean';
        $riga->save();
    }

    /**
     * L'interruttore acceso non basta a far vedere qualcosa: la pagina elenca
     * le aste attive, programmate e concluse, e una in bozza non c'è. Chi
     * accende da qui non ha il pannello sotto gli occhi per accorgersene.
     */
    private function avvisaSeNonCeNienteDaVedere(): void
    {
        $pubblicabili = Auction::query()->whereIn('status', ['active', 'scheduled', 'ended'])->count();

        if ($pubblicabili > 0) {
            return;
        }

        $inBozza = Auction::query()->where('status', 'draft')->count();

        $this->warn('Attenzione: la pagina delle aste è vuota'.($inBozza > 0
            ? ' ('.$inBozza.' '.($inBozza === 1 ? 'asta è' : 'aste sono').' in bozza).'
            : ' (nessuna asta in archivio).'));
    }
}
