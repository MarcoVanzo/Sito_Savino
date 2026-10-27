<?php

namespace App\Services\Youtube;

use App\Services\Analytics\Ga4Credentials;
use App\Services\Analytics\Ga4Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * YouTube Data API v3, in sola lettura sui video pubblici.
 *
 * Si autentica con il service account di GA4, come la traduzione automatica:
 * stesso progetto Google, dove va abilitata la «YouTube Data API v3». Una
 * lettura di `videos.list` costa un'unità sulle 10.000 gratuite al giorno.
 */
class YoutubeClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/youtube.readonly';

    private const ENDPOINT = 'https://www.googleapis.com/youtube/v3/videos';

    public function __construct(
        private readonly Ga4Credentials $credentials,
        private readonly int $timeout,
    ) {}

    /** Null senza service account: il controllo allora non si può fare. */
    public static function fromConfig(): ?self
    {
        $credentials = Ga4Credentials::fromConfig();

        return $credentials === null
            ? null
            : new self($credentials, (int) config('services.youtube.timeout', 5));
    }

    /**
     * Vero se chi ha pubblicato il video lo vede solo in alcuni paesi o lo
     * nasconde in alcuni (`contentDetails.regionRestriction`, con `allowed`
     * o `blocked`). Le dirette della Lega lo fanno per i diritti televisivi.
     *
     * @throws YoutubeException
     */
    public function haLimitiPerPaese(string $videoId): bool
    {
        try {
            $token = $this->credentials->accessToken(self::SCOPE);
        } catch (Ga4Exception $e) {
            throw new YoutubeException('Token per YouTube non ottenuto: '.$e->getMessage(), previous: $e);
        }

        try {
            $risposta = Http::withToken($token)
                ->timeout($this->timeout)
                ->get(self::ENDPOINT, ['part' => 'contentDetails', 'id' => $videoId]);
        } catch (ConnectionException $e) {
            throw new YoutubeException('YouTube non raggiungibile: '.$e->getMessage(), previous: $e);
        }

        if (! $risposta->successful()) {
            throw new YoutubeException(sprintf(
                'YouTube ha risposto %d: %s',
                $risposta->status(),
                mb_substr($risposta->body(), 0, 300),
            ));
        }

        $video = $risposta->json('items.0');

        if (! is_array($video)) {
            throw new YoutubeException('YouTube non trova il video '.$videoId);
        }

        $limiti = (array) ($video['contentDetails']['regionRestriction'] ?? []);

        return ($limiti['allowed'] ?? null) !== null || ($limiti['blocked'] ?? []) !== [];
    }
}
