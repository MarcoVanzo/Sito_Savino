<x-filament-panels::page>
    @php $data = $this->overview(); @endphp

    @unless ($data['campaigns_ok'])
        <x-filament::section>
            <x-slot name="heading">Campagne non disponibili</x-slot>

            <p class="text-sm text-gray-600 dark:text-gray-300">
                {{ $data['campaigns_message'] ?? 'ActiveCampaign non ha risposto.' }}
                I dati sugli iscritti qui sopra vengono dall'archivio del sito e restano validi.
            </p>
        </x-filament::section>
    @endunless

    <x-filament::section>
        <x-slot name="heading">Ultime campagne</x-slot>
        <x-slot name="description">Dati di ActiveCampaign. I tassi sono calcolati sul numero di invii.</x-slot>

        @if ($data['campaigns'] === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">Nessuna campagna inviata.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="pb-2 pr-4 font-medium">Campagna</th>
                            <th class="pb-2 pr-4 text-right font-medium">Inviate</th>
                            <th class="pb-2 pr-4 text-right font-medium">Aperture</th>
                            <th class="pb-2 pr-4 text-right font-medium">Click</th>
                            <th class="pb-2 pr-4 text-right font-medium">Disiscritti</th>
                            <th class="pb-2 text-right font-medium">Rimbalzi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($data['campaigns'] as $campaign)
                            <tr>
                                <td class="py-2 pr-4">
                                    <div class="font-medium text-gray-950 dark:text-white">{{ $campaign['name'] }}</div>
                                    @if ($campaign['sent_at'])
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ \Illuminate\Support\Carbon::parse($campaign['sent_at'])->format('d/m/Y H:i') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-right tabular-nums">{{ number_format($campaign['sent'], 0, ',', '.') }}</td>
                                <td class="py-2 pr-4 text-right tabular-nums">
                                    {{ number_format($campaign['unique_opens'], 0, ',', '.') }}
                                    <span class="text-gray-400">
                                        {{ $campaign['open_rate'] === null ? '' : number_format($campaign['open_rate'], 1, ',', '.').'%' }}
                                    </span>
                                </td>
                                <td class="py-2 pr-4 text-right tabular-nums">
                                    {{ number_format($campaign['unique_clicks'], 0, ',', '.') }}
                                    <span class="text-gray-400">
                                        {{ $campaign['click_rate'] === null ? '' : number_format($campaign['click_rate'], 1, ',', '.').'%' }}
                                    </span>
                                </td>
                                <td class="py-2 pr-4 text-right tabular-nums text-gray-500 dark:text-gray-400">
                                    {{ number_format($campaign['unsubscribes'], 0, ',', '.') }}
                                </td>
                                <td class="py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">
                                    {{ number_format($campaign['bounces'], 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
