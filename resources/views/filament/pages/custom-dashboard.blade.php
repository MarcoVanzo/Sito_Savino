<x-filament-panels::page>
    <div class="space-y-8">
        <!-- Titolo e Area Riservata -->
        <div class="text-center pb-4 border-b border-gray-200 dark:border-gray-800 border-dashed">
            <h2 class="text-sm font-bold tracking-widest text-gray-500 uppercase">
                🔒 AREA RISERVATA &mdash; PANNELLO DI AMMINISTRAZIONE (NON VISIBILE AL PUBBLICO)
            </h2>
        </div>

        <!-- Grid 10 elements -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6">
            @php
                $cards = [
                    ['title' => 'Stagione', 'url' => '/admin/roster', 'icon' => '🏆', 'color' => 'bg-pink-500'],
                    ['title' => 'Società', 'url' => '/admin/management', 'icon' => '🏢', 'color' => 'bg-blue-900'],
                    ['title' => 'Ticketing', 'url' => '/admin/pages', 'icon' => '🎟️', 'color' => 'bg-green-500'],
                    ['title' => 'Sponsor', 'url' => '/admin/sponsor', 'icon' => '🤝', 'color' => 'bg-yellow-500'],
                    ['title' => 'SDB Youth', 'url' => '/admin/teams', 'icon' => '🎓', 'color' => 'bg-cyan-500'],
                    
                    ['title' => 'Camp', 'url' => '/admin/pages', 'icon' => '⛺', 'color' => 'bg-red-500'],
                    ['title' => 'Sociale', 'url' => '/admin/pages', 'icon' => '❤️', 'color' => 'bg-purple-500'],
                    ['title' => 'Media', 'url' => '/admin/news', 'icon' => '📣', 'color' => 'bg-gray-500'],
                    ['title' => 'Shop', 'url' => '/admin/shop/products', 'icon' => '🛍️', 'color' => 'bg-pink-500'],
                    ['title' => 'Amministrazione', 'url' => '/admin/settings', 'icon' => '⚙️', 'color' => 'bg-slate-700'],
                ];
            @endphp

            @foreach($cards as $card)
                <a href="{{ url($card['url']) }}" class="relative overflow-hidden flex flex-col items-center justify-center p-6 bg-[#1b263b] hover:bg-[#253550] transition rounded-xl text-center shadow-md min-h-[160px] group">
                    <!-- Top color bar matching frontend -->
                    <div class="absolute top-0 left-0 right-0 h-1 {{ $card['color'] }} opacity-75 group-hover:opacity-100 transition"></div>
                    
                    <div class="text-4xl mb-4">{{ $card['icon'] }}</div>
                    <div class="text-white font-bold mb-1 leading-tight">{{ $card['title'] }}</div>
                </a>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
