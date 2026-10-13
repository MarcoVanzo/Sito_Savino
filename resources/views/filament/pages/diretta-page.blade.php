<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->table }}

        <form wire:submit="save">
            {{ $this->form }}
            <div class="mt-4 flex justify-end">
                <x-filament::button type="submit">
                    Salva le impostazioni
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
