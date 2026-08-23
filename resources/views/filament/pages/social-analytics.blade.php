<x-filament-panels::page>
    @php
        $account = $this->account();
        $data = $this->overview();
        $labels = [
            'follower' => 'Follower',
            'non_follower' => 'Non follower',
            'unfollower' => 'Hanno smesso di seguire',
            'ad' => 'Inserzioni',
            'feed' => 'Post',
            'post' => 'Post',
            'reel' => 'Reel',
            'reels' => 'Reel',
            'story' => 'Storie',
            'carousel_container' => 'Caroselli',
            'carousel' => 'Caroselli',
            'album' => 'Caroselli',
            'video' => 'Video',
            'igtv' => 'IGTV',
            'image' => 'Immagini',
            'unknown' => 'Non specificato',
            'other' => 'Altro',
            'bio_link' => 'Link in bio',
            'call' => 'Chiamate',
            'direction' => 'Indicazioni',
            'email' => 'Email',
            'text' => 'Messaggi',
            'website' => 'Sito web',
            'like' => 'Mi piace',
            'love' => 'Love',
            'wow' => 'Wow',
            'haha' => 'Haha',
            'sorry' => 'Sigh',
            'anger' => 'Grrr',
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
        @php $profilo = $data['profile'] ?? []; @endphp

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-4">
                @if (! empty($profilo['profile_picture_url']))
                    <img src="{{ $profilo['profile_picture_url'] }}" alt="" loading="lazy"
                         class="h-16 w-16 shrink-0 rounded-full object-cover ring-2 ring-primary-500/40" />
                @endif

                <div class="min-w-0">
                    <div class="text-base font-semibold text-gray-950 dark:text-white">
                        {{ $profilo['name'] ?? $account->name }}
                    </div>

                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        @if ($account->ig_username) {{ '@'.$account->ig_username }} @endif
                        @if (! empty($profilo['media_count'])) · {{ number_format((int) $profilo['media_count'], 0, ',', '.') }} post @endif
                        @if (! empty($profilo['follows_count'])) · segue {{ number_format((int) $profilo['follows_count'], 0, ',', '.') }} @endif
                    </div>

                    @if (! empty($profilo['biography']))
                        <p class="mt-1 max-w-2xl text-sm text-gray-600 dark:text-gray-300">{{ $profilo['biography'] }}</p>
                    @endif
                </div>
            </div>

            <div class="text-right text-sm text-gray-500 dark:text-gray-400">
                <div>Ultimi {{ $data['period']['days'] }} giorni</div>
                @if ($account->last_synced_at)
                    <div class="text-xs">Aggiornato {{ $account->last_synced_at->diffForHumans() }}</div>
                @endif
            </div>
        </div>

        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            Instagram — ultimi {{ $data['period']['days'] }} giorni
        </h3>

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
                {{--
                    Una ripartizione senza niente da mostrare non lascia il suo
                    riquadro vuoto: sparisce. I due casi sono diversi e nessuno
                    dei due si risolve da questa parte — `null` è Meta che
                    rifiuta la combinazione metrica/ripartizione (views per
                    follower_type non la concede, mentre media_product_type sì),
                    tutti zero è il dato vero (nessun tap sul link del profilo).
                    In entrambi i casi il riquadro spiegava all'utente un
                    dettaglio dell'API che non gli serve e occupava mezza pagina.
                    Restano fuori dal loop, quindi tornano da soli il giorno in
                    cui il dato arriva.
                --}}
                @foreach ([
                    'views_by_follower_type' => 'Visualizzazioni: follower e non',
                    'views_by_media_type' => 'Visualizzazioni per tipo di contenuto',
                    'reach_by_media_type' => 'Reach per tipo di contenuto',
                    'profile_links_by_type' => 'Tap sui link del profilo',
                ] as $key => $heading)
                    @php
                        $raw = $data['breakdowns'][$key] ?? null;
                        $items = $raw === null ? [] : collect($raw)
                            ->filter(fn ($value) => (int) $value > 0)
                            ->map(fn ($value, $dimension) => [
                                'name' => $labels[$dimension] ?? \Illuminate\Support\Str::headline($dimension),
                                'value' => $value,
                            ])
                            ->values()
                            ->all();
                    @endphp

                    @continue($items === [])

                    <x-filament::section>
                        <x-slot name="heading">{{ $heading }}</x-slot>

                        @include('filament.analytics.bar-list', [
                            'items' => $items,
                            'labelKey' => 'name',
                            'valueKey' => 'value',
                        ])
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
                <x-slot name="heading">Post migliori del periodo</x-slot>
                <x-slot name="description">In ordine di interazioni. Il tasso è sulle persone raggiunte: quanti, fra chi ha visto, hanno poi fatto qualcosa</x-slot>

                @if (($data['top_posts'] ?? []) === [])
                    <p class="text-sm text-gray-500 dark:text-gray-400">Nessun contenuto nel periodo.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="pb-2 pr-3 font-medium">#</th>
                                    <th class="pb-2 pr-4 font-medium">Post</th>
                                    <th class="pb-2 pr-4 text-right font-medium">Views</th>
                                    <th class="pb-2 pr-4 text-right font-medium">Reach</th>
                                    <th class="pb-2 pr-4 text-right font-medium">Interazioni</th>
                                    <th class="pb-2 pr-4 text-right font-medium">Tasso</th>
                                    <th class="pb-2 pr-4 text-right font-medium">Salvati</th>
                                    <th class="pb-2 pr-4 text-right font-medium">Condivisi</th>
                                    <th class="pb-2 pr-4 text-right font-medium">Visione media</th>
                                    <th class="pb-2 text-right font-medium">Skip</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($data['top_posts'] as $index => $post)
                                    @php
                                        $ins = $post['insights'] ?? [];
                                        $thumb = $post['thumbnail_url'] ?? $post['media_url'] ?? null;
                                        $watch = $ins['ig_reels_avg_watch_time'] ?? null;
                                        $skip = $ins['reels_skip_rate'] ?? null;
                                    @endphp
                                    <tr>
                                        <td class="py-2 pr-3 align-top text-gray-400 tabular-nums">{{ $index + 1 }}</td>
                                        <td class="py-2 pr-4">
                                            <div class="flex items-start gap-2">
                                                @if ($thumb)
                                                    <img src="{{ $thumb }}" alt="" loading="lazy"
                                                         class="h-10 w-10 shrink-0 rounded object-cover" />
                                                @endif
                                                <div class="min-w-0">
                                                    <div class="truncate font-medium text-gray-950 dark:text-white" style="max-width: 22rem;">
                                                        {{ \Illuminate\Support\Str::limit($post['caption'] ?? '—', 60) }}
                                                    </div>
                                                    @if (! empty($post['timestamp']))
                                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                                            {{ \Illuminate\Support\Carbon::parse($post['timestamp'])->format('d/m/Y') }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-2 pr-4 text-right tabular-nums">{{ number_format((float) ($ins['views'] ?? 0), 0, ',', '.') }}</td>
                                        <td class="py-2 pr-4 text-right tabular-nums">{{ number_format((float) ($ins['reach'] ?? 0), 0, ',', '.') }}</td>
                                        <td class="py-2 pr-4 text-right font-semibold tabular-nums text-gray-950 dark:text-white">
                                            {{ number_format((float) ($ins['total_interactions'] ?? 0), 0, ',', '.') }}
                                        </td>
                                        <td class="py-2 pr-4 text-right tabular-nums">
                                            {{ $post['rank_rate'] === null ? '—' : number_format($post['rank_rate'], 1, ',', '.').'%' }}
                                        </td>
                                        <td class="py-2 pr-4 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ number_format((float) ($ins['saved'] ?? 0), 0, ',', '.') }}</td>
                                        <td class="py-2 pr-4 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ number_format((float) ($ins['shares'] ?? 0), 0, ',', '.') }}</td>
                                        {{-- Solo i reel hanno tempo di visione e skip: per gli altri contenuti Meta non li calcola. --}}
                                        <td class="py-2 pr-4 text-right tabular-nums text-gray-500 dark:text-gray-400">
                                            {{ $watch === null ? '—' : number_format($watch / 1000, 1, ',', '.').'s' }}
                                        </td>
                                        {{-- Meta restituisce lo skip rate già in percentuale (50,1 = metà
                                             di chi ha aperto il reel l'ha saltato): moltiplicarlo per cento
                                             produceva un 5.010%. --}}
                                        <td class="py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">
                                            {{ $skip === null ? '—' : number_format((float) $skip, 1, ',', '.').'%' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Ultimi contenuti</x-slot>

                @if ($data['posts'] === [])
                    <p class="text-sm text-gray-500 dark:text-gray-400">Nessun contenuto recente.</p>
                @else
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @foreach ($data['posts'] as $post)
                            @php
                                $ins = $post['insights'] ?? [];
                                $thumb = $post['thumbnail_url'] ?? $post['media_url'] ?? null;
                                $tipo = $labels[\Illuminate\Support\Str::lower($post['media_product_type'] ?? $post['media_type'] ?? '')] ?? null;
                            @endphp

                            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                                @if ($thumb)
                                    <a href="{{ $post['permalink'] ?? '#' }}" target="_blank" rel="noopener" class="block">
                                        <img src="{{ $thumb }}" alt="" loading="lazy"
                                             class="aspect-square w-full object-cover transition hover:opacity-90" />
                                    </a>
                                @endif

                                <div class="space-y-2 p-3">
                                    {{-- Le stesse sei metriche dello screenshot, in riga sotto l'immagine. --}}
                                    <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs tabular-nums text-gray-500 dark:text-gray-400">
                                        <span title="Visualizzazioni">{{ number_format((float) ($ins['views'] ?? 0), 0, ',', '.') }} visual.</span>
                                        <span title="Account raggiunti">{{ number_format((float) ($ins['reach'] ?? 0), 0, ',', '.') }} reach</span>
                                        <span title="Mi piace">{{ number_format((float) ($ins['likes'] ?? 0), 0, ',', '.') }} like</span>
                                        <span title="Commenti">{{ number_format((float) ($ins['comments'] ?? 0), 0, ',', '.') }} comm.</span>
                                        <span title="Condivisioni">{{ number_format((float) ($ins['shares'] ?? 0), 0, ',', '.') }} cond.</span>
                                        <span title="Salvataggi">{{ number_format((float) ($ins['saved'] ?? 0), 0, ',', '.') }} salv.</span>
                                    </div>

                                    <p class="line-clamp-2 text-sm text-gray-700 dark:text-gray-200">
                                        {{ \Illuminate\Support\Str::limit($post['caption'] ?? '', 80) }}
                                    </p>

                                    <div class="flex items-center justify-between text-xs text-gray-400">
                                        <span>{{ ! empty($post['timestamp']) ? \Illuminate\Support\Carbon::parse($post['timestamp'])->format('d M') : '' }}</span>
                                        @if ($tipo)
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 dark:bg-gray-800">{{ $tipo }}</span>
                                        @endif
                                    </div>
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
                <x-slot name="heading">
                    Facebook — {{ $account->page_name ?? 'Pagina' }}
                    @if (! empty($fb['link']))
                        <a href="{{ $fb['link'] }}" target="_blank" rel="noopener"
                           class="ml-1 text-sm font-normal text-primary-600 hover:underline dark:text-primary-400"
                           title="Apri la Pagina su Facebook">↗</a>
                    @endif
                </x-slot>

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
                        <h4 class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">
                            Reazioni ai post ({{ $data['period']['days'] }} gg)
                        </h4>

                        @php
                            $reactions = collect($fb['reactions'])
                                ->map(fn ($value, $type) => [
                                    'name' => $labels[\Illuminate\Support\Str::lower($type)] ?? \Illuminate\Support\Str::headline($type),
                                    'value' => $value,
                                ])
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
