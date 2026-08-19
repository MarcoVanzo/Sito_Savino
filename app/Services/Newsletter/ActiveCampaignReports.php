<?php

namespace App\Services\Newsletter;

use App\Services\ActiveCampaignService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lettura delle campagne da ActiveCampaign.
 *
 * Sta a parte da {@see ActiveCampaignService}, che si occupa di
 * scrivere (sincronizzare contatti, iscrivere, disiscrivere): quello è nel
 * percorso di un'iscrizione dal sito e non deve crescere con codice di
 * reportistica che serve solo al pannello.
 *
 * Gli errori non risalgono: la pagina della newsletter deve restare leggibile
 * anche quando ActiveCampaign non risponde, perché la parte che conta davvero
 * — gli iscritti — è in casa nostra.
 */
class ActiveCampaignReports
{
    private const CACHE_TTL = 1800;

    /** Stato ActiveCampaign di una campagna ormai spedita. */
    private const STATUS_COMPLETED = 5;

    public function isConfigured(): bool
    {
        return filled(config('services.activecampaign.url'))
            && filled(config('services.activecampaign.key'));
    }

    /**
     * Ultime campagne inviate, già normalizzate con i tassi calcolati.
     *
     * @return array{ok: bool, campaigns: list<array<string, mixed>>, message?: string}
     */
    public function campaigns(int $limit = 12): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'campaigns' => [], 'message' => 'ActiveCampaign non configurato.'];
        }

        return Cache::remember(
            "newsletter:campaigns:{$limit}",
            self::CACHE_TTL,
            fn (): array => $this->fetchCampaigns($limit),
        );
    }

    /**
     * @return array{ok: bool, campaigns: list<array<string, mixed>>, message?: string}
     */
    private function fetchCampaigns(int $limit): array
    {
        $baseUrl = rtrim((string) config('services.activecampaign.url'), '/');

        try {
            $response = Http::withHeaders(['Api-Token' => (string) config('services.activecampaign.key')])
                ->acceptJson()
                ->timeout(15)
                ->retry(2, 1000, throw: false)
                ->get($baseUrl.'/api/3/campaigns', [
                    'limit' => $limit,
                    'orders[sdate]' => 'DESC',
                ]);
        } catch (\Throwable $e) {
            Log::warning('ActiveCampaign: campagne non raggiungibili', ['error' => $e->getMessage()]);

            return ['ok' => false, 'campaigns' => [], 'message' => 'ActiveCampaign non raggiungibile.'];
        }

        if (! $response->successful()) {
            Log::warning('ActiveCampaign: errore nella lettura delle campagne', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 300),
            ]);

            return [
                'ok' => false,
                'campaigns' => [],
                'message' => 'ActiveCampaign ha risposto con un errore ('.$response->status().').',
            ];
        }

        $campaigns = [];

        foreach ((array) $response->json('campaigns', []) as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $campaign = self::normalize($raw);

            // Le bozze e le campagne programmate non hanno numeri da mostrare.
            if ($campaign['sent'] > 0 || $campaign['status'] >= self::STATUS_COMPLETED) {
                $campaigns[] = $campaign;
            }
        }

        return ['ok' => true, 'campaigns' => $campaigns];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private static function normalize(array $raw): array
    {
        $sent = (int) ($raw['send_amt'] ?? 0);
        $uniqueOpens = (int) ($raw['uniqueopens'] ?? 0);
        $uniqueClicks = (int) ($raw['uniquelinkclicks'] ?? 0);

        return [
            'id' => (string) ($raw['id'] ?? ''),
            'name' => (string) ($raw['name'] ?? 'Senza nome'),
            'sent_at' => filled($raw['sdate'] ?? null) ? (string) $raw['sdate'] : null,
            'status' => (int) ($raw['status'] ?? 0),
            'sent' => $sent,
            'opens' => (int) ($raw['opens'] ?? 0),
            'unique_opens' => $uniqueOpens,
            'clicks' => (int) ($raw['linkclicks'] ?? 0),
            'unique_clicks' => $uniqueClicks,
            'unsubscribes' => (int) ($raw['unsubscribes'] ?? 0),
            'bounces' => (int) ($raw['hardbounces'] ?? 0) + (int) ($raw['softbounces'] ?? 0),
            // Tassi sul numero di invii: è la base che usa ActiveCampaign stesso,
            // e il confronto fra campagne resta coerente.
            'open_rate' => self::rate($uniqueOpens, $sent),
            'click_rate' => self::rate($uniqueClicks, $sent),
        ];
    }

    private static function rate(int $part, int $total): ?float
    {
        return $total > 0 ? round($part / $total * 100, 1) : null;
    }
}
