<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center gap-3 mb-6">
            <div class="bg-primary-100 dark:bg-primary-500/20 text-primary-600 dark:text-primary-400 p-2 rounded-lg">
                <x-heroicon-o-bolt class="w-6 h-6" />
            </div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Azioni Rapide</h2>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Azione 1 -->
            <a href="/admin/posts/create" class="group relative overflow-hidden bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-1">
                <div class="absolute -right-4 -bottom-4 text-warning-500/10 group-hover:text-warning-500/20 transition-colors duration-300">
                    <x-heroicon-s-pencil-square class="w-24 h-24" />
                </div>
                <div class="relative z-10">
                    <div class="bg-warning-50 dark:bg-warning-500/10 w-10 h-10 rounded-full flex items-center justify-center mb-3">
                        <x-heroicon-o-pencil-square class="w-5 h-5 text-warning-600 dark:text-warning-400" />
                    </div>
                    <h3 class="font-bold text-gray-900 dark:text-white mb-1">Scrivi News</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Pubblica un nuovo articolo o press release</p>
                </div>
            </a>
            
            <!-- Azione 2 -->
            <a href="/admin/games" class="group relative overflow-hidden bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-1">
                <div class="absolute -right-4 -bottom-4 text-success-500/10 group-hover:text-success-500/20 transition-colors duration-300">
                    <x-heroicon-s-calendar-days class="w-24 h-24" />
                </div>
                <div class="relative z-10">
                    <div class="bg-success-50 dark:bg-success-500/10 w-10 h-10 rounded-full flex items-center justify-center mb-3">
                        <x-heroicon-o-calendar-days class="w-5 h-5 text-success-600 dark:text-success-400" />
                    </div>
                    <h3 class="font-bold text-gray-900 dark:text-white mb-1">Calendario</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Gestisci le partite in programma</p>
                </div>
            </a>

            <!-- Azione 3 -->
            <a href="/admin/players" class="group relative overflow-hidden bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-1">
                <div class="absolute -right-4 -bottom-4 text-primary-500/10 group-hover:text-primary-500/20 transition-colors duration-300">
                    <x-heroicon-s-user-group class="w-24 h-24" />
                </div>
                <div class="relative z-10">
                    <div class="bg-primary-50 dark:bg-primary-500/10 w-10 h-10 rounded-full flex items-center justify-center mb-3">
                        <x-heroicon-o-user-group class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                    </div>
                    <h3 class="font-bold text-gray-900 dark:text-white mb-1">Roster</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Aggiorna le schede delle atlete</p>
                </div>
            </a>
            
            <!-- Azione 4 -->
            <a href="/admin/pages" class="group relative overflow-hidden bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-1">
                <div class="absolute -right-4 -bottom-4 text-danger-500/10 group-hover:text-danger-500/20 transition-colors duration-300">
                    <x-heroicon-s-document-text class="w-24 h-24" />
                </div>
                <div class="relative z-10">
                    <div class="bg-danger-50 dark:bg-danger-500/10 w-10 h-10 rounded-full flex items-center justify-center mb-3">
                        <x-heroicon-o-document-text class="w-5 h-5 text-danger-600 dark:text-danger-400" />
                    </div>
                    <h3 class="font-bold text-gray-900 dark:text-white mb-1">Pagine CMS</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Modifica contenuti statici del sito</p>
                </div>
            </a>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
