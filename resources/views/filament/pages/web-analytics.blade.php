<x-filament-panels::page>
    @php
        $data = $this->overview();
        $site = $this->site();
    @endphp

    {{-- Nessun sito configurato: la pagina spiega cosa manca invece di mostrare zeri. --}}
    @if ($site === null)
        <x-filament::section>
            <x-slot name="heading">Nessun sito configurato</x-slot>

            <div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
                <p>
                    Per vedere il traffico serve almeno una proprietà Google Analytics 4 collegata.
                    Aggiungila da <strong>Amministrazione → Siti Analytics</strong>.
                </p>

                @if ($this->serviceAccountEmail())
                    <p>
                        Ricordati di aggiungere
                        <code class="rounded bg-gray-100 px-1 py-0.5 dark:bg-gray-800">{{ $this->serviceAccountEmail() }}</code>
                        come <strong>Visualizzatore</strong> sulla proprietà, in
                        Google Analytics → Amministrazione → Gestione accessi.
                    </p>
                @else
                    <p class="text-danger-600 dark:text-danger-400">
                        Il service account Google non è configurato: manca <code>GA4_SERVICE_ACCOUNT_JSON</code>
                        nelle variabili d'ambiente.
                    </p>
                @endif
            </div>
        </x-filament::section>
    @else
        @if ($data['error'] ?? null)
            <x-filament::section>
                <x-slot name="heading">
                    <span class="text-danger-600 dark:text-danger-400">Dati non disponibili</span>
                </x-slot>

                <p class="text-sm text-gray-600 dark:text-gray-300">{{ $data['error']['message'] }}</p>

                @if ($data['error']['reason'] === 'not_authorized' && $this->serviceAccountEmail())
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        Service account da autorizzare:
                        <code class="rounded bg-gray-100 px-1 py-0.5 dark:bg-gray-800">{{ $this->serviceAccountEmail() }}</code>
                    </p>
                @endif
            </x-filament::section>
        @elseif ($data['degraded'] ?? null)
            <x-filament::section>
                <x-slot name="heading">Dati dall'archivio locale</x-slot>

                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ $data['degraded']['message'] }}
                    I numeri qui sotto vengono dalla serie già salvata: KPI e andamento sono attendibili,
                    le ripartizioni (pagine, canali, città) non sono disponibili.
                </p>
            </x-filament::section>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-gray-500 dark:text-gray-400">
            <span>
                <strong class="text-gray-950 dark:text-white">{{ $site->name }}</strong>
                · {{ \Illuminate\Support\Carbon::parse($data['period']['start'])->format('d/m/Y') }}
                – {{ \Illuminate\Support\Carbon::parse($data['period']['end'])->format('d/m/Y') }}
            </span>

            @if (($data['realtime']['active_users'] ?? null) !== null)
                <span class="inline-flex items-center gap-2">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-success-400 opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-success-500"></span>
                    </span>
                    {{ $data['realtime']['active_users'] }} online negli ultimi 30 minuti
                </span>
            @endif
        </div>

        {{-- Il pezzo forte: quali pagine sono state viste. --}}
        <x-filament::section>
            <x-slot name="heading">Pagine più viste</x-slot>

            @if ($data['pages'] === [])
                <p class="text-sm text-gray-500 dark:text-gray-400">Nessuna pagina vista nel periodo.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="pb-2 pr-4 font-medium">Pagina</th>
                                <th class="pb-2 pr-4 text-right font-medium">Visualizzazioni</th>
                                <th class="pb-2 pr-4 text-right font-medium">Utenti</th>
                                <th class="pb-2 text-right font-medium">Durata media</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($data['pages'] as $page)
                                <tr>
                                    <td class="py-2 pr-4">
                                        <div class="truncate font-medium text-gray-950 dark:text-white" title="{{ $page['title'] }}">
                                            {{ $page['title'] !== '' ? $page['title'] : $page['path'] }}
                                        </div>
                                        <div class="truncate text-xs text-gray-500 dark:text-gray-400" title="{{ $page['path'] }}">
                                            {{ $page['path'] }}
                                        </div>
                                    </td>
                                    <td class="py-2 pr-4 text-right tabular-nums">{{ number_format($page['views'], 0, ',', '.') }}</td>
                                    <td class="py-2 pr-4 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ number_format($page['users'], 0, ',', '.') }}</td>
                                    <td class="py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">
                                        {{ sprintf('%d:%02d', intdiv($page['avg_duration'], 60), $page['avg_duration'] % 60) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Da dove arrivano</x-slot>
                <x-slot name="description">Canali di acquisizione</x-slot>

                @include('filament.analytics.bar-list', ['items' => $data['channels'], 'labelKey' => 'name', 'valueKey' => 'sessions'])
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Sorgenti</x-slot>
                <x-slot name="description">Siti e motori che portano traffico</x-slot>

                @include('filament.analytics.bar-list', ['items' => $data['sources'], 'labelKey' => 'name', 'valueKey' => 'sessions'])
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Dispositivi</x-slot>

                @include('filament.analytics.bar-list', ['items' => $data['devices'], 'labelKey' => 'name', 'valueKey' => 'users'])
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Città</x-slot>

                @php
                    $cities = array_map(
                        fn ($city) => [
                            'name' => $city['country'] !== '' && $city['country'] !== 'Italy'
                                ? $city['city'].' ('.$city['country'].')'
                                : $city['city'],
                            'users' => $city['users'],
                        ],
                        $data['cities'],
                    );
                @endphp

                @include('filament.analytics.bar-list', ['items' => $cities, 'labelKey' => 'name', 'valueKey' => 'users'])
            </x-filament::section>

            <x-filament::section class="lg:col-span-2">
                <x-slot name="heading">Pagine di ingresso</x-slot>
                <x-slot name="description">La prima pagina vista in una sessione</x-slot>

                @include('filament.analytics.bar-list', ['items' => $data['landing'], 'labelKey' => 'page', 'valueKey' => 'sessions'])
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
