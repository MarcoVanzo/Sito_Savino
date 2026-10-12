<x-filament-panels::page>
    @php($righe = $this->righe())

    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Gara</span>
            <select wire:model.live="gara" class="block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                <option value="">Tutte le gare</option>
                @foreach ($this->gare() as $id => $nome)
                    <option value="{{ $id }}">{{ $nome }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Sponsor</span>
            <select wire:model.live="sponsor" class="block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                <option value="">Tutti gli sponsor</option>
                @foreach ($this->sponsorConDati() as $id => $nome)
                    <option value="{{ $id }}">{{ $nome }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <x-filament::section>
        @if ($righe === [])
            <p class="text-sm text-gray-600 dark:text-gray-300">Ancora nessun dato: i contatori partono alla prima diretta con sponsor.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">Visualizzazioni e clic degli sponsor nella diretta, per gara e creatività</caption>
                    <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-white/10">
                        <tr>
                            <th scope="col" class="py-2 pr-3">Gara</th>
                            <th scope="col" class="py-2 pr-3">Sponsor · creatività</th>
                            <th scope="col" class="py-2 pr-3 text-right">Pre-roll avviati</th>
                            <th scope="col" class="py-2 pr-3 text-right">Visti fino in fondo</th>
                            <th scope="col" class="py-2 pr-3 text-right">Saltati</th>
                            <th scope="col" class="py-2 pr-3 text-right">Completamento</th>
                            <th scope="col" class="py-2 pr-3 text-right">Cornici (ciclo)</th>
                            <th scope="col" class="py-2 pr-3 text-right">Cornici (regia)</th>
                            <th scope="col" class="py-2 text-right">Clic</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($righe as $riga)
                            <tr>
                                <td class="py-2 pr-3"><span class="font-medium">{{ $riga['gara'] }}</span><br><span class="text-xs text-gray-500">{{ $riga['data'] }}</span></td>
                                <td class="py-2 pr-3">{{ $riga['sponsor'] }} · {{ $riga['creativita'] }}</td>
                                <td class="py-2 pr-3 text-right tabular-nums">{{ $riga['preroll_avviati'] }}</td>
                                <td class="py-2 pr-3 text-right tabular-nums">{{ $riga['preroll_completati'] }}</td>
                                <td class="py-2 pr-3 text-right tabular-nums">{{ $riga['preroll_saltati'] }}</td>
                                <td class="py-2 pr-3 text-right tabular-nums">{{ $riga['completamento'] === null ? '—' : number_format($riga['completamento'], 1, ',', '').' %' }}</td>
                                <td class="py-2 pr-3 text-right tabular-nums">{{ $riga['cicli'] }}</td>
                                <td class="py-2 pr-3 text-right tabular-nums">{{ $riga['regia'] }}</td>
                                <td class="py-2 text-right tabular-nums">{{ $riga['clic'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
