<x-filament-panels::page>
    <div x-data="{ activeTab: @js($activeTab) }" class="space-y-6">
        <x-filament::tabs>
            <x-filament::tabs.item
                alpine-active="activeTab === 'settings'"
                x-on:click="activeTab = 'settings'"
                icon="heroicon-o-cog-6-tooth"
            >
                Impostazioni
            </x-filament::tabs.item>

            <x-filament::tabs.item
                alpine-active="activeTab === 'slides'"
                x-on:click="activeTab = 'slides'"
                icon="heroicon-o-photo"
            >
                Slide Hero
            </x-filament::tabs.item>
        </x-filament::tabs>

        {{-- Tab Impostazioni: Form generale della Homepage --}}
        <div x-show="activeTab === 'settings'" x-cloak class="space-y-6">
            <form wire:submit="save">
                {{ $this->form }}
                <div class="mt-4 flex justify-end">
                    <x-filament::button type="submit" size="sm">
                        Salva Impostazioni
                    </x-filament::button>
                </div>
            </form>
        </div>

        {{-- Tab Slide Hero: Tabella di gestione delle slide --}}
        <div x-show="activeTab === 'slides'" x-cloak>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
