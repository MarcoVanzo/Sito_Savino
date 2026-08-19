<x-filament-panels::page>
    @php
        $account = $this->account();
        $data = $this->overview();
        $labels = [
            'follower' => 'Follower',
            'non_follower' => 'Non follower',
            'unfollower' => 'Hanno smesso di seguire',
            'ad' => 'Inserzioni',
            'feed' => 'Feed',
            'reels' => 'Reel',
            'story' => 'Storie',
            'carousel_container' => 'Caroselli',
            'igtv' => 'IGTV',
            'post' => 'Post',
            'unknown' => 'Non specificato',
        ];
    @endphp

    @if (! $this->metaConfigured())
        <x-filament::section>
            <x-slot name="heading">App Meta non configurata</x-slot>

            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                <p>
                    Mancano <code>META_APP_ID</code> e <code>META_APP_SECRET</code> nelle variabili d'ambiente.
                    Servono un'app Meta con il prodotto <strong>Facebook Login for Business</strong> e una
                    configurazione che includa <code>read_insights</code>: senza quel permesso le metriche
                    della Pagina arrivano vuote senza segnalare alcun errore.
                </p>
                <p>
                    URI di reindirizzamento da dichiarare nell'app:
                    <code class="rounded bg-gray-100 px-1 py-0.5 dark:bg-gray-800">{{ $this->redirectUri() }}</code>
                </p>
            </div>
        </x-filament::section>
    @elseif ($account === null)
        <x-filament::section>
            <x-slot name="heading">Nessun account collegato</x-slot>

            <p class="text-sm text-gray-600 dark:text-gray-300">
                Usa <strong>Collega Meta</strong> qui sopra. Un solo collegamento porta dentro tutte le
                Pagine amministrate dal profilo, ciascuna con il proprio profilo Instagram business.
            </p>
        </x-filament::section>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-gray-500 dark:text-gray-400">
            <span>
                <strong class="text-gray-950 dark:text-white">{{ $account->name }}</strong>
                @if ($account->ig_username) · {{ '@'.$account->ig_username }} @endif
                @if ($account->page_name) · {{ $account->page_name }} @endif
                · ultimi {{ $data['period']['days'] }} giorni
            </span>

            @if ($account->last_synced_at)
                <span>Ultimo aggiornamento: {{ $account->last_synced_at->diffForHumans() }}</span>
            @endif
        </div>

        @if ($data['error'] ?? null)
            <x-filament::section>
                <x-slot name="heading">
                    <span class="text-danger-600 dark:text-danger-400">Dati non disponibili</span>
                </x-slot>

                <p class="text-sm text-gray-600 dark:text-gray-300">{{ $data['error']['message'] }}</p>
            </x-filament::section>
        @endif

        @if (($data['pending_days'] ?? 0) > 0)
            <x-filament::section>
                <x-slot name="heading">Serie giornaliera in costruzione</x-slot>

                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Mancano ancora {{ $data['pending_days'] }} giorni. Meta non fornisce lo storico giorno per
                    giorno in un colpo solo: si scarica un giorno per chiamata, dal più recente, e il resto lo
                    completa la sincronizzazione notturna.
                </p>
            </x-filament::section>
        @endif

        @if ($account->hasInstagram() && $data['totals'] !== [])
            <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-600 dark:text-gray-300">
                <span>{{ number_format($data['totals']['likes'], 0, ',', '.') }} mi piace</span>
                <span>{{ number_format($data['totals']['comments'], 0, ',', '.') }} commenti</span>
                <span>{{ number_format($data['totals']['shares'], 0, ',', '.') }} condivisioni</span>
                <span>{{ number_format($data['totals']['saves'], 0, ',', '.') }} salvataggi</span>
                <span>{{ number_format($data['totals']['reposts'], 0, ',', '.') }} repost</span>
                <span>{{ number_format($data['totals']['replies'], 0, ',', '.') }} risposte alle storie</span>
            </div>
        @endif

        @if (! $account->hasInstagram())
            <x-filament::section>
                <x-slot name="heading">Instagram non collegato alla Pagina</x-slot>

                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Questo account ha solo la Pagina Facebook. Collega il profilo Instagram business alla
                    Pagina da Meta Business Suite, poi ricollega l'account da qui.
                </p>
            </x-filament::section>
        @else
            <div class="grid gap-6 lg:grid-cols-2">
                @foreach ([
                    'views_by_follower_type' => ['Visualizzazioni: follower e non', null],
                    'views_by_media_type' => ['Visualizzazioni per tipo di contenuto', null],
                    'reach_by_media_type' => ['Reach per tipo di contenuto', null],
                    'profile_links_by_type' => ['Tap sui link del profilo', 'Nessun tap nel periodo'],
                ] as $key => [$heading, $emptyText])
                    <x-filament::section>
                        <x-slot name="heading">{{ $heading }}</x-slot>

                        @php
                            $raw = $data['breakdowns'][$key] ?? null;
                            $items = $raw === null ? [] : collect($raw)
                                ->map(fn ($value, $dimension) => [
                                    'name' => $labels[$dimension] ?? \Illuminate\Support\Str::headline($dimension),
                                    'value' => $value,
                                ])
                                ->values()
                                ->all();
                        @endphp

                        @if ($raw === null)
                            <p class="text-sm text-gray-500 dark:text-gray-400">Meta non fornisce questa ripartizione per l'account.</p>
                        @else
                            @include('filament.analytics.bar-list', [
                                'items' => $items,
                                'labelKey' => 'name',
                                'valueKey' => 'value',
                                'empty' => $emptyText ?? 'Nessun dato nel periodo',
                            ])
                        @endif
                    </x-filament::section>
                @endforeach
            </div>

            <x-filament::section>
                <x-slot name="heading">Chi vi segue</x-slot>
                <x-slot name="description">Dati degli ultimi 30 giorni, forniti da Meta solo sopra i 100 follower</x-slot>

                @if ($data['demographics'] === null)
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Demografia non disponibile per questo account.
                    </p>
                @else
                    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                        @foreach (['age' => 'Età', 'gender' => 'Genere', 'city' => 'Città', 'country' => 'Paesi'] as $key => $heading)
                            <div>
                                <h4 class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">{{ $heading }}</h4>

                                @php
                                    $raw = $data['demographics'][$key] ?? null;
                                    // L'età si legge per fascia crescente, non per numerosità.
                                    if ($key === 'age' && is_array($raw)) {
                                        ksort($raw);
                                    }
                                    $items = $raw === null ? [] : collect($raw)
                                        ->take(6)
                                        ->map(fn ($value, $dimension) => ['name' => $dimension, 'value' => $value])
                                        ->values()
                                        ->all();
                                @endphp

                                @include('filament.analytics.bar-list', [
                                    'items' => $items,
                                    'labelKey' => 'name',
                                    'valueKey' => 'value',
                                    'empty' => 'Non disponibile',
                                ])
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Ultimi contenuti</x-slot>

                @if ($data['posts'] === [])
                    <p class="text-sm text-gray-500 dark:text-gray-400">Nessun contenuto recente.</p>
                @else
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($data['posts'] as $post)
                            @php
                                $insights = $post['insights'] ?? [];
                                $thumb = $post['thumbnail_url'] ?? $post['media_url'] ?? null;
                            @endphp
                            <div class="flex gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                                @if ($thumb)
                                    <img src="{{ $thumb }}" alt="" loading="lazy"
                                         class="h-20 w-20 shrink-0 rounded-lg object-cover" />
                                @endif

                                <div class="min-w-0 flex-1 space-y-1">
                                    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        <span>{{ \Illuminate\Support\Str::headline(\Illuminate\Support\Str::lower($post['media_product_type'] ?? $post['media_type'] ?? 'Post')) }}</span>
                                        @if (! empty($post['timestamp']))
                                            <span>· {{ \Illuminate\Support\Carbon::parse($post['timestamp'])->format('d/m/Y') }}</span>
                                        @endif
                                    </div>

                                    <p class="line-clamp-2 text-sm text-gray-700 dark:text-gray-200">
                                        {{ \Illuminate\Support\Str::limit($post['caption'] ?? '', 90) }}
                                    </p>

                                    <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs tabular-nums text-gray-500 dark:text-gray-400">
                                        <span>{{ number_format((float) ($insights['views'] ?? 0), 0, ',', '.') }} visual.</span>
                                        <span>{{ number_format((float) ($insights['reach'] ?? 0), 0, ',', '.') }} reach</span>
                                        <span>{{ number_format((float) ($insights['likes'] ?? 0), 0, ',', '.') }} like</span>
                                        <span>{{ number_format((float) ($insights['comments'] ?? 0), 0, ',', '.') }} commenti</span>
                                    </div>

                                    @if (! empty($post['permalink']))
                                        <a href="{{ $post['permalink'] }}" target="_blank" rel="noopener"
                                           class="inline-block text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">
                                            Apri su Instagram
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        @endif

        @if ($data['facebook'] !== null)
            @php $fb = $data['facebook']; @endphp

            <x-filament::section>
                <x-slot name="heading">Facebook — {{ $account->page_name ?? 'Pagina' }}</x-slot>

                @if ($fb['missing_read_insights'] ?? false)
                    <p class="mb-4 text-sm text-danger-600 dark:text-danger-400">
                        Manca il permesso <code>read_insights</code> sul collegamento Meta: le statistiche della
                        Pagina arrivano vuote. Aggiungi il permesso alla configurazione "Facebook Login for
                        Business" dell'app, poi ricollega l'account.
                    </p>
                @endif

                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    @foreach ([
                        'followers' => 'Follower della Pagina',
                        'new_follows' => 'Nuovi follower',
                        'media_views' => 'Visualizzazioni contenuti',
                        'unique_viewers' => 'Persone raggiunte',
                        'page_views' => 'Visite alla Pagina',
                        'post_engagements' => 'Interazioni sui post',
                        'video_views' => 'Visualizzazioni video',
                        'fans' => 'Mi piace alla Pagina',
                    ] as $key => $label)
                        <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                            <div class="mt-1 text-xl font-semibold tabular-nums text-gray-950 dark:text-white">
                                {{ ($fb[$key] ?? null) === null ? 'n/d' : number_format((int) $fb[$key], 0, ',', '.') }}
                            </div>
                        </div>
                    @endforeach
                </div>

                @if (! empty($fb['reactions']))
                    <div class="mt-6">
                        <h4 class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">Reazioni ai post</h4>

                        @php
                            $reactions = collect($fb['reactions'])
                                ->map(fn ($value, $type) => ['name' => \Illuminate\Support\Str::headline($type), 'value' => $value])
                                ->values()
                                ->all();
                        @endphp

                        @include('filament.analytics.bar-list', [
                            'items' => $reactions,
                            'labelKey' => 'name',
                            'valueKey' => 'value',
                        ])
                    </div>
                @endif

                @if (! empty($fb['unavailable']) && ! ($fb['missing_read_insights'] ?? false))
                    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                        Alcune metriche non sono disponibili: Meta ne fornisce parte solo alle Pagine sopra i
                        100 "mi piace" e ne ha ritirate diverse fra il 2024 e il 2026.
                    </p>
                @endif
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
