<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Il giro OAuth con Meta e il salvataggio degli account collegati.
 *
 * Un solo collegamento porta dentro TUTTE le Pagine che l'utente amministra:
 * prima squadra e settore giovanile stanno su portfolio business diversi ma
 * sotto lo stesso profilo personale, quindi arrivano insieme e diventano righe
 * distinte in `social_accounts`. Ricollegare non duplica: la Pagina è unica in
 * tabella e il collegamento aggiorna la riga esistente, serie storica compresa.
 */
class MetaOAuthService
{
    /**
     * Permessi richiesti quando l'app non ha una configurazione di Facebook
     * Login for Business.
     *
     * `read_insights` è quello che si dimentica: senza, le metriche della Pagina
     * arrivano vuote e la Graph API non segnala alcun errore.
     */
    private const SCOPES = [
        'instagram_basic',
        'instagram_manage_insights',
        'pages_show_list',
        'pages_read_engagement',
        'read_insights',
        'business_management',
    ];

    /** Lo state vive il tempo di un login, non di più. */
    private const STATE_TTL_MINUTES = 10;

    public function __construct(private readonly MetaClient $client) {}

    public function isConfigured(): bool
    {
        return filled(config('services.meta.app_id')) && filled(config('services.meta.app_secret'));
    }

    /**
     * URI a cui Meta rimanda dopo il consenso. Va dichiarato identico nell'app
     * Meta: è la stringa da copiare nella configurazione.
     */
    public function redirectUri(): string
    {
        $configured = (string) config('services.meta.redirect_uri', '');

        return $configured !== '' ? $configured : route('admin.social.meta.callback');
    }

    /**
     * URL della schermata di consenso, con lo state già registrato.
     */
    public function authorizationUrl(?int $userId, ?SocialAccount $account = null): string
    {
        $state = $this->createState($userId, $account);

        $params = [
            'client_id' => (string) config('services.meta.app_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'state' => $state,
            // Chi ha già collegato una volta e ha negato un permesso non vedrebbe
            // più la schermata: `rerequest` la ripropone.
            'auth_type' => 'rerequest',
            'display' => 'page',
        ];

        $configId = (string) config('services.meta.config_id', '');

        if ($configId !== '') {
            // Con Facebook Login for Business i permessi stanno nella
            // configurazione dell'app, non nella richiesta.
            $params['config_id'] = $configId;
        } else {
            $params['scope'] = implode(',', self::SCOPES);
        }

        return 'https://www.facebook.com/'.$this->client->version().'/dialog/oauth?'.http_build_query($params);
    }

    public function createState(?int $userId, ?SocialAccount $account = null): string
    {
        $this->pruneExpiredStates();

        $token = Str::random(48);

        DB::table('social_oauth_states')->insert([
            'token' => $token,
            'user_id' => $userId,
            'social_account_id' => $account?->id,
            'expires_at' => now()->addMinutes(self::STATE_TTL_MINUTES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    /**
     * Consuma lo state: valido una volta sola, e solo se non è scaduto.
     *
     * @return array{user_id: int|null, social_account_id: int|null}|null
     */
    public function consumeState(string $token): ?array
    {
        $row = DB::table('social_oauth_states')->where('token', $token)->first();

        if ($row === null) {
            return null;
        }

        DB::table('social_oauth_states')->where('token', $token)->delete();

        if (Carbon::parse($row->expires_at)->isPast()) {
            return null;
        }

        return [
            'user_id' => $row->user_id === null ? null : (int) $row->user_id,
            'social_account_id' => $row->social_account_id === null ? null : (int) $row->social_account_id,
        ];
    }

    /**
     * Codice di consenso → account collegati.
     *
     * @return Collection<int, SocialAccount>
     *
     * @throws MetaException
     */
    public function connect(string $code, ?int $userId)
    {
        $shortLived = $this->client->exchangeCode($code, $this->redirectUri());

        if ($shortLived['access_token'] === '') {
            throw new MetaException('unavailable', 'Meta non ha restituito un token');
        }

        // Il token di Pagina eredita la durata da quello utente: partire dal
        // long-lived è ciò che rende il collegamento duraturo invece che di un'ora.
        $longLived = $this->client->longLivedToken($shortLived['access_token']);
        $userToken = $longLived['access_token'] !== '' ? $longLived['access_token'] : $shortLived['access_token'];
        $expiresAt = $longLived['expires_in'] > 0 ? now()->addSeconds($longLived['expires_in']) : null;

        $pages = $this->client->pages($userToken);

        if ($pages === []) {
            throw new MetaException('permission', 'Nessuna Pagina Facebook amministrata da questo profilo');
        }

        return collect($pages)
            ->map(fn (array $page): SocialAccount => $this->storePage($page, $userId, $expiresAt))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $page
     */
    private function storePage(array $page, ?int $userId, ?Carbon $expiresAt): SocialAccount
    {
        $instagram = is_array($page['instagram_business_account'] ?? null)
            ? $page['instagram_business_account']
            : [];

        $pageId = (string) $page['id'];
        $existing = SocialAccount::query()->where('page_id', $pageId)->first();

        $attributes = [
            'page_name' => (string) ($page['name'] ?? ''),
            'ig_account_id' => filled($instagram['id'] ?? null) ? (string) $instagram['id'] : null,
            'ig_username' => filled($instagram['username'] ?? null) ? (string) $instagram['username'] : null,
            'access_token' => (string) ($page['access_token'] ?? ''),
            'token_expires_at' => $expiresAt,
            'connected_by' => $userId,
            'connected_at' => now(),
        ];

        // L'etichetta è redazionale: si imposta al primo collegamento con il nome
        // della Pagina e poi non si tocca più, perché il nome su Meta cambia
        // (sponsor, stagione) mentre la voce in elenco deve restare riconoscibile.
        if ($existing === null) {
            $attributes['name'] = (string) ($page['name'] ?? 'Account Meta');
        }

        $account = SocialAccount::query()->updateOrCreate(['page_id' => $pageId], $attributes);

        Log::info('Meta: account collegato', [
            'social_account_id' => $account->id,
            'page_id' => $pageId,
            'has_instagram' => $account->hasInstagram(),
        ]);

        return $account;
    }

    private function pruneExpiredStates(): void
    {
        DB::table('social_oauth_states')->where('expires_at', '<', now())->delete();
    }
}
