<x-filament-panels::page>
    {{-- Tabs di navigazione --}}
    <div x-data="{ activeTab: 'inventario' }" class="space-y-6">
        <x-filament::tabs>
            <x-filament::tabs.item
                alpine-active="activeTab === 'inventario'"
                x-on:click="activeTab = 'inventario'"
                icon="heroicon-o-cube"
            >
                Inventario
            </x-filament::tabs.item>

            <x-filament::tabs.item
                alpine-active="activeTab === 'movimenti'"
                x-on:click="activeTab = 'movimenti'"
                icon="heroicon-o-arrows-right-left"
            >
                Movimenti
            </x-filament::tabs.item>
        </x-filament::tabs>

        {{-- Tab Inventario: tabella principale della Page --}}
        <div x-show="activeTab === 'inventario'" x-cloak>
            {{ $this->table }}
        </div>

        {{-- Tab Movimenti: widget con lazy loading --}}
        <div x-show="activeTab === 'movimenti'" x-cloak>
            @foreach ($this->getFooterWidgets() as $widget)
                @livewire($widget)
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
