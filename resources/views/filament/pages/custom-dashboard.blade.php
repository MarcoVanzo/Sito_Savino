<x-filament-panels::page>
    <div class="space-y-8">
        <!-- Header / Welcome Section -->
        <div class="flex flex-col gap-2 mb-8">
            <p class="text-gray-500 dark:text-gray-400">
                Seleziona un'area di gestione per iniziare.
            </p>
        </div>

        <!-- Dashboard Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
            @php
                $cards = [
                    ['title' => 'Stagione', 'url' => '/admin/roster', 'icon' => 'heroicon-s-trophy', 'color' => '#ec4899', 'bg' => 'rgba(236, 72, 153, 0.1)'],
                    ['title' => 'Società', 'url' => '/admin/management', 'icon' => 'heroicon-s-building-office-2', 'color' => '#3b82f6', 'bg' => 'rgba(59, 130, 246, 0.1)'],
                    ['title' => 'Ticketing', 'url' => '/admin/under-construction', 'icon' => 'heroicon-s-ticket', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.1)'],
                    ['title' => 'Sponsor', 'url' => '/admin/sponsor', 'icon' => 'heroicon-s-briefcase', 'color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.1)'],
                    ['title' => 'SDB Youth', 'url' => '/admin/teams', 'icon' => 'heroicon-s-academic-cap', 'color' => '#06b6d4', 'bg' => 'rgba(6, 182, 212, 0.1)'],
                    
                    ['title' => 'Camp', 'url' => '/admin/under-construction', 'icon' => 'heroicon-s-sun', 'color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.1)'],
                    ['title' => 'Sociale', 'url' => '/admin/under-construction', 'icon' => 'heroicon-s-heart', 'color' => '#a855f7', 'bg' => 'rgba(168, 85, 247, 0.1)'],
                    ['title' => 'Media', 'url' => '/admin/news', 'icon' => 'heroicon-s-megaphone', 'color' => '#6366f1', 'bg' => 'rgba(99, 102, 241, 0.1)'],
                    ['title' => 'Shop', 'url' => '/admin/shop/products', 'icon' => 'heroicon-s-shopping-bag', 'color' => '#d946ef', 'bg' => 'rgba(217, 70, 239, 0.1)'],
                    ['title' => 'Sistema', 'url' => '/admin/settings', 'icon' => 'heroicon-s-cog-6-tooth', 'color' => '#64748b', 'bg' => 'rgba(100, 116, 139, 0.15)'],
                ];
            @endphp

            @foreach($cards as $card)
                @php $isWip = str_contains($card['url'], 'under-construction'); @endphp
                <a href="{{ url($card['url']) }}" 
                   class="group relative flex flex-col items-center justify-center p-6 bg-white/80 dark:bg-gray-900/80 backdrop-blur-md border border-gray-200/50 dark:border-gray-800/50 rounded-2xl shadow-sm hover:shadow-2xl transition-all duration-500 ease-out hover:-translate-y-1.5 overflow-hidden ring-1 ring-gray-900/5 dark:ring-white/10 hover:ring-gray-500/50 {{ $isWip ? 'opacity-60 hover:opacity-90' : '' }}">
                    
                    @if($isWip)
                    <div class="absolute top-2 right-2 z-20 bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-400 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full">
                        Prossimamente
                    </div>
                    @endif

                    <!-- Decorative Background Gradient on Hover -->
                    <div class="absolute inset-0 bg-gradient-to-br from-transparent to-gray-50 dark:to-gray-800/50 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    
                    <!-- Icon -->
                    <div class="relative z-10 flex items-center justify-center w-16 h-16 mb-4 rounded-2xl group-hover:scale-110 transition-transform duration-500 ease-out border border-gray-200/50 dark:border-gray-700/50" style="background-color: {{ $card['bg'] }}">
                        <x-filament::icon
                            :icon="$card['icon']"
                            class="!w-8 !h-8 drop-shadow-sm group-hover:animate-bounce-short"
                            style="color: {{ $card['color'] }}"
                        />
                    </div>
                    
                    <!-- Title -->
                    <div class="relative z-10 text-gray-700 dark:text-gray-200 font-bold text-base tracking-wide text-center group-hover:text-gray-900 dark:group-hover:text-white transition-colors duration-300">
                        {{ $card['title'] }}
                    </div>

                    <!-- Floating orb effect (subtle) -->
                    <div class="absolute -bottom-8 -right-8 w-24 h-24 {{ $card['color'] }} rounded-full blur-3xl opacity-0 group-hover:opacity-10 transition-opacity duration-700"></div>
                </a>
            @endforeach
        </div>
    </div>

    <style>
        .animate-bounce-short {
            animation: bounce-short 0.5s ease-in-out 1;
        }
        @keyframes bounce-short {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10%); }
        }
    </style>
</x-filament-panels::page>
