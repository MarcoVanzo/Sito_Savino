<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center gap-4 mb-4">
            <x-heroicon-o-bolt class="w-6 h-6 text-primary-600" />
            <h2 class="text-lg font-bold">Azioni Rapide</h2>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-filament::button tag="a" href="/admin/posts/create" color="warning" icon="heroicon-o-pencil-square" class="w-full">
                Scrivi Nuova News
            </x-filament::button>
            
            <x-filament::button tag="a" href="/admin/games" color="success" icon="heroicon-o-calendar-days" class="w-full">
                Gestisci Partite
            </x-filament::button>
            
            <x-filament::button tag="a" href="/admin/pages" color="primary" icon="heroicon-o-document-text" class="w-full">
                Modifica Pagine CMS
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
