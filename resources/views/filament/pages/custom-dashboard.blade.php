<x-filament-panels::page>
    <div class="space-y-8">
        <!-- Titolo e Area Riservata -->
        <div class="text-center pb-4 border-b border-gray-200 dark:border-gray-800 border-dashed">
            <h2 class="text-sm font-bold tracking-widest text-gray-500 uppercase">
                🔒 AREA RISERVATA &mdash; PANNELLO DI AMMINISTRAZIONE (NON VISIBILE AL PUBBLICO)
            </h2>
        </div>

        <!-- Grid 4x3 -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
            @php
                $cards = [
                    ['title' => 'Dashboard', 'url' => '/admin', 'icon' => '📊'],
                    ['title' => 'Gestione News e Comunicati', 'url' => '/admin/news', 'icon' => '📰'],
                    ['title' => 'Gestione Squadre (A1, B1, Giovanili)', 'url' => '/admin/roster', 'icon' => '👥'],
                    ['title' => 'Gestione Gallery (AI Tagging)', 'url' => '/admin/gallery', 'icon' => '📸'],
                    
                    ['title' => 'Gestione Partite (Lega + CEV)', 'url' => '/admin/partite', 'icon' => '🏟️'],
                    ['title' => 'Gestione Shop (Prodotti e Magazzino)', 'url' => '/admin/shop/products', 'icon' => '🛍️'],
                    ['title' => 'Gestione Ordini e Spedizioni', 'url' => '/admin/shop/ordini', 'icon' => '📦'],
                    ['title' => 'Gestione Aste Benefiche', 'url' => '/admin/shop/aste', 'icon' => '🔨'],
                    
                    ['title' => 'Gestione Sponsor', 'url' => '/admin/sponsor', 'icon' => '🤝'],
                    ['title' => 'Gestione Accrediti e Form', 'url' => '/admin/forms', 'icon' => '🎫'],
                    ['title' => 'Log, Sconti & Password Utenti', 'url' => '/admin/log', 'icon' => '📋'],
                    ['title' => 'Impostazioni', 'url' => '/admin/settings', 'icon' => '⚙️'],
                ];
            @endphp

            @foreach($cards as $card)
                <a href="{{ url($card['url']) }}" class="flex flex-col items-center justify-center p-6 bg-[#1b263b] hover:bg-[#253550] transition rounded-xl text-center shadow-md min-h-[160px]">
                    <div class="text-3xl mb-3">{{ $card['icon'] }}</div>
                    <div class="text-white font-bold mb-1 leading-tight">{{ $card['title'] }}</div>
                    <div class="text-gray-400 font-mono text-xs">{{ $card['url'] }}/</div>
                </a>
            @endforeach
        </div>

        <!-- Legenda -->
        <div class="flex flex-wrap justify-center gap-6 mt-8 pt-6 border-t border-gray-200 dark:border-gray-800">
            @php
                $legend = [
                    ['label' => 'Stagione', 'color' => 'bg-pink-500'],
                    ['label' => 'Società & Com.', 'color' => 'bg-blue-900'],
                    ['label' => 'Ticketing', 'color' => 'bg-green-500'],
                    ['label' => 'Sponsor', 'color' => 'bg-yellow-500'],
                    ['label' => 'Youth', 'color' => 'bg-cyan-500'],
                    ['label' => 'Camp', 'color' => 'bg-red-500'],
                    ['label' => 'Sociale', 'color' => 'bg-purple-500'],
                    ['label' => 'Shop', 'color' => 'bg-pink-500'],
                ];
            @endphp

            @foreach($legend as $item)
                <div class="flex items-center space-x-2">
                    <span class="w-3 h-3 rounded-full {{ $item['color'] }}"></span>
                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ $item['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
