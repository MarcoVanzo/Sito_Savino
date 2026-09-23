<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Web, worker e scheduler sono tre ambienti distinti: una variabile scritta
 * sotto `services:` non arriva alla coda ne' al pianificatore, e non lo dice
 * nessuno. E' gia' successo due volte, sempre in silenzio:
 *
 * - `APP_KEY` vuota su due componenti per mesi (`social:sync-meta` falliva
 *   ogni notte, l'output di `schedule:work` va in /dev/null e Sentry e' spento);
 * - le credenziali PayPal solo sul web, trovate il 23/09/2026 lanciando
 *   `verifica:lancio` sulla console di ciascun componente invece che del solo
 *   primo. Li' non rompeva ancora niente, perche' nessun job in coda
 *   costruisce PayPalPaymentService — ma il primo rimborso messo in coda
 *   sarebbe fallito senza lasciare traccia.
 *
 * Il guasto e' sempre lo stesso e non si vede a occhio: la spec e' lunga
 * seicento righe e le tre liste si leggono una alla volta. Questo test le
 * confronta.
 *
 * Restano fuori solo le variabili che vivono dentro una richiesta HTTP, che su
 * worker e scheduler non avrebbero senso: sono elencate qui sotto una per una,
 * perche' l'eccezione va motivata, non dedotta da un prefisso.
 */
class VariabiliAllineateFraIComponentiTest extends TestCase
{
    /** Solo il servizio web risponde a una richiesta: queste valgono solo li'. */
    private const SOLO_WEB = [
        // La protezione con utente e password dell'indirizzo di anteprima.
        'PREVIEW_AUTH_ENABLED',
        'PREVIEW_AUTH_USER',
        'PREVIEW_AUTH_PASS',
        // La sessione esiste solo dentro una richiesta.
        'SESSION_DRIVER',
        'SESSION_ENCRYPT',
        'SESSION_LIFETIME',
        'SESSION_SECURE_COOKIE',
        // Il rendering lato server non e' attivo, e comunque riguarda la risposta.
        'INERTIA_SSR_ENABLED',
    ];

    #[Test]
    public function ogni_variabile_del_web_esiste_anche_su_worker_e_scheduler(): void
    {
        $componenti = $this->chiaviPerComponente();

        foreach (['worker', 'scheduler'] as $componente) {
            $mancanti = array_values(array_diff(
                $componenti['web'],
                $componenti[$componente],
                self::SOLO_WEB,
            ));

            $this->assertSame([], $mancanti, sprintf(
                'Su "%s" mancano variabili che il servizio web ha: %s. '
                .'Se l\'assenza e\' voluta, va aggiunta a SOLO_WEB con il motivo.',
                $componente,
                implode(', ', $mancanti),
            ));
        }
    }

    #[Test]
    public function la_chiave_applicativa_e_la_stessa_sui_tre_componenti(): void
    {
        // Non basta che APP_KEY ci sia: dev'essere lo STESSO blob. Con una
        // chiave diversa niente va in errore — semplicemente le firme prodotte
        // dalla coda non si verificano dal web, e ogni disiscrizione dalla
        // newsletter risponde 403.
        $valori = array_unique(array_values($this->valoreDi('APP_KEY')));

        $this->assertCount(1, $valori, 'APP_KEY non e\' identica sui tre componenti.');
    }

    /**
     * La spec e' YAML, ma qui serve una cosa sola: l'elenco delle chiavi di
     * ciascun componente. Un parser completo sarebbe una dipendenza in piu' per
     * leggere quattro righe su cinque; questo legge per righe e si ferma appena
     * la forma del file non e' piu' quella attesa (vedi il controllo in fondo),
     * cosi' un domani non passa per finta.
     *
     * @return array<string, list<string>>
     */
    private function chiaviPerComponente(): array
    {
        return array_map(
            fn (array $c): array => array_column($c['envs'], 'key'),
            $this->componenti(),
        );
    }

    /** @return array<string, string> componente => valore della chiave */
    private function valoreDi(string $chiave): array
    {
        $valori = [];

        foreach ($this->componenti() as $nome => $componente) {
            foreach ($componente['envs'] as $env) {
                if ($env['key'] === $chiave) {
                    $valori[$nome] = $env['value'];
                }
            }
        }

        $this->assertCount(3, $valori, "{$chiave} non e' presente su tutti e tre i componenti.");

        return $valori;
    }

    /** @return array<string, array{envs: list<array{key: string, value: string}>}> */
    private function componenti(): array
    {
        $righe = file(base_path('.do/app.yaml'), FILE_IGNORE_NEW_LINES);

        $componenti = [];
        $dentro = false;
        $corrente = null;

        foreach ($righe as $riga) {
            // Sezioni di primo livello: solo services/workers/jobs contengono
            // componenti; envs: alla radice sono le variabili globali dell'app.
            if (preg_match('/^([a-z_]+):/', $riga, $m)) {
                $dentro = in_array($m[1], ['services', 'workers', 'jobs'], true);

                continue;
            }

            if (! $dentro) {
                continue;
            }

            if (str_starts_with($riga, '- ')) {
                $componenti[] = ['nome' => null, 'envs' => []];
                $corrente = array_key_last($componenti);
            }

            if ($corrente === null) {
                continue;
            }

            if (preg_match('/^  name: (\S+)/', $riga, $m)) {
                $componenti[$corrente]['nome'] = $m[1];
            }

            if (preg_match('/^  - key: (\S+)/', $riga, $m)) {
                $componenti[$corrente]['envs'][] = ['key' => $m[1], 'value' => null];

                continue;
            }

            if (preg_match('/^    value: (.*)$/', $riga, $m) && $componenti[$corrente]['envs'] !== []) {
                $ultima = array_key_last($componenti[$corrente]['envs']);
                $componenti[$corrente]['envs'][$ultima]['value'] = $m[1];
            }
        }

        $perNome = [];

        foreach ($componenti as $componente) {
            $perNome[$componente['nome']] = ['envs' => $componente['envs']];
        }

        // Se la forma della spec cambia, questo test deve rompersi invece di
        // confrontare liste vuote e dichiararle allineate.
        $this->assertSame(
            ['web', 'worker', 'scheduler'],
            array_keys($perNome),
            'La forma di .do/app.yaml e\' cambiata: il test non sta piu\' leggendo i tre componenti.',
        );

        foreach ($perNome as $nome => $componente) {
            $this->assertGreaterThan(
                20,
                count($componente['envs']),
                "Lette troppe poche variabili per \"{$nome}\": il parsing non sta funzionando.",
            );
        }

        return $perNome;
    }
}
