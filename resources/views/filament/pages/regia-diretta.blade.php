<x-filament-panels::page>
    @php
        $gare = $this->gare();
        $riepilogo = $this->riepilogo();
    @endphp

    <div class="mx-auto w-full max-w-xl space-y-6" wire:poll.10s>
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Gara</span>
            <select
                wire:model.live="gara"
                class="block w-full rounded-lg border-gray-300 text-base shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
            >
                @forelse ($gare as $g)
                    <option value="{{ $g->id }}">
                        {{ $g->match_date->format('d/m H:i') }} · {{ $g->homeTeam?->nomePubblico() }} – {{ $g->awayTeam?->nomePubblico() }}
                    </option>
                @empty
                    <option value="">Nessuna gara della società in questi giorni</option>
                @endforelse
            </select>
        </label>

        @if (! $this->abilitata())
            <x-filament::section>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Gli sponsor nella diretta sono spenti: si accendono da Sponsor → Diretta.
                </p>
            </x-filament::section>
        @elseif ($riepilogo === null)
            <x-filament::section>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Questa gara non ha ancora una diretta con sponsor.
                    @if ($this->urlDellaGara())
                        Impostala da <a href="{{ $this->urlDellaGara() }}" class="font-semibold text-primary-600 underline">Sponsor → Diretta</a> → «Imposta».
                    @endif
                </p>
            </x-filament::section>
        @else
            <x-filament::section>
                <div class="flex flex-wrap items-center gap-3">
                    <x-filament::badge :color="$riepilogo['stato']->getColor()" size="lg">
                        {{ $riepilogo['stato']->getLabel() }}
                    </x-filament::badge>

                    @if ($riepilogo['in_onda'])
                        <span class="text-base font-semibold">{{ $riepilogo['in_onda'] }}</span>
                    @endif
                </div>

                @if ($riepilogo['fino_alle'])
                    <p
                        class="mt-3 text-3xl font-bold tabular-nums"
                        x-data="{ fine: new Date(@js($riepilogo['fino_alle'])).getTime(), resto: '' }"
                        x-init="const passo = () => { const s = Math.max(0, Math.round((fine - Date.now()) / 1000)); resto = Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); }; passo(); setInterval(passo, 1000)"
                        x-text="resto"
                        aria-live="off"
                    ></p>
                @endif

                <dl class="mt-4 grid grid-cols-2 gap-2 text-sm">
                    <dt class="text-gray-500">Ciclo automatico</dt>
                    <dd class="font-medium">{{ $riepilogo['ciclo_attivo'] ? 'attivo' : 'sospeso' }}</dd>
                    <dt class="text-gray-500">Prossimo ciclo</dt>
                    <dd class="font-medium tabular-nums">{{ $riepilogo['prossimo_ciclo'] ?? '—' }}</dd>
                    <dt class="text-gray-500">Cornici in rotazione</dt>
                    <dd class="font-medium">{{ $riepilogo['cornici'] }}</dd>
                </dl>
            </x-filament::section>

            <div class="grid grid-cols-1 gap-3">
                @if ($this->pausaTraISetAction->isVisible()) {{ $this->pausaTraISetAction }} @endif
                @if ($this->finePausaAction->isVisible()) {{ $this->finePausaAction }} @endif
                @if ($this->mostraAction->isVisible()) {{ $this->mostraAction }} @endif
                <div class="grid grid-cols-2 gap-3">
                    @if ($this->cicloAction->isVisible()) {{ $this->cicloAction }} @endif
                    @if ($this->ripartiDaAdessoAction->isVisible()) {{ $this->ripartiDaAdessoAction }} @endif
                </div>
                @if ($this->riaccendiAction->isVisible()) {{ $this->riaccendiAction }} @endif
                @if ($this->spegniTuttoAction->isVisible()) {{ $this->spegniTuttoAction }} @endif
            </div>
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
