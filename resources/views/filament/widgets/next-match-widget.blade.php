<x-filament-widgets::widget>
    <x-filament::section class="overflow-hidden p-0 relative">
        <!-- Background Gradient and Details -->
        <div class="absolute inset-0 bg-gradient-to-br from-[#001833] to-[#003063] opacity-100 z-0"></div>
        <div class="absolute top-0 right-0 w-64 h-64 bg-[#bda871] rounded-full mix-blend-multiply filter blur-3xl opacity-20 z-0 animate-pulse-gold"></div>
        
        <div class="relative z-10 p-6">
            <div class="flex items-center justify-between mb-6 border-b border-white/10 pb-4">
                <div class="flex items-center gap-3">
                    <div class="bg-white/10 p-2 rounded-lg backdrop-blur-sm">
                        <x-heroicon-o-fire class="w-6 h-6 text-[#bda871]" />
                    </div>
                    <h2 class="text-xl font-bold text-white tracking-wide">Prossima Partita</h2>
                </div>
                
                @if($nextMatch)
                    <div class="bg-[#E0004D] text-white px-4 py-1.5 rounded-full text-sm font-bold shadow-lg shadow-red-900/50 uppercase tracking-widest border border-red-400/30">
                        {{ $nextMatch->competition_type }}
                    </div>
                @endif
            </div>
            
            @if($nextMatch)
                <div class="flex flex-col md:flex-row items-center justify-center gap-8 md:gap-16 py-4">
                    <!-- Home Team -->
                    <div class="flex flex-col items-center w-32">
                        @php $homeLogo = $nextMatch->homeTeam->getFirstMediaUrl('teams'); @endphp
                        @if($homeLogo)
                            <div class="w-24 h-24 bg-white rounded-full p-2 shadow-[0_0_20px_rgba(255,255,255,0.1)] mb-4 hover:scale-105 transition-transform">
                                <img src="{{ $homeLogo }}" alt="Logo Casa" class="w-full h-full object-contain">
                            </div>
                        @else
                            <div class="w-24 h-24 bg-gradient-to-br from-[#003063] to-[#001833] border-2 border-[#bda871] rounded-full flex items-center justify-center mb-4 shadow-lg">
                                <span class="text-white font-black text-4xl">{{ substr($nextMatch->homeTeam->name, 0, 1) }}</span>
                            </div>
                        @endif
                        <span class="font-bold text-white text-center text-lg">{{ $nextMatch->homeTeam->name }}</span>
                    </div>
                    
                    <!-- VS Badge -->
                    <div class="flex flex-col items-center">
                        <div class="text-2xl font-black text-[#bda871] mb-2 bg-[#001833] w-12 h-12 flex items-center justify-center rounded-full border border-white/10 shadow-lg z-10 relative">
                            VS
                        </div>
                        
                        <!-- Date & Time Info Box -->
                        <div class="bg-white/5 backdrop-blur-md border border-white/10 rounded-xl p-4 text-center mt-4 w-48 shadow-xl">
                            <div class="text-sm text-gray-400 uppercase tracking-wider mb-1">
                                {{ \Carbon\Carbon::parse($nextMatch->match_date)->translatedFormat('l d M') }}
                            </div>
                            <div class="text-3xl font-bold text-white">
                                {{ \Carbon\Carbon::parse($nextMatch->match_date)->format('H:i') }}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Away Team -->
                    <div class="flex flex-col items-center w-32">
                        @php $awayLogo = $nextMatch->awayTeam->getFirstMediaUrl('teams'); @endphp
                        @if($awayLogo)
                            <div class="w-24 h-24 bg-white rounded-full p-2 shadow-[0_0_20px_rgba(255,255,255,0.1)] mb-4 hover:scale-105 transition-transform">
                                <img src="{{ $awayLogo }}" alt="Logo Trasferta" class="w-full h-full object-contain">
                            </div>
                        @else
                            <div class="w-24 h-24 bg-gradient-to-br from-[#E0004D] to-[#990033] border-2 border-white/20 rounded-full flex items-center justify-center mb-4 shadow-lg">
                                <span class="text-white font-black text-4xl">{{ substr($nextMatch->awayTeam->name, 0, 1) }}</span>
                            </div>
                        @endif
                        <span class="font-bold text-white text-center text-lg">{{ $nextMatch->awayTeam->name }}</span>
                    </div>
                </div>
                
                @if($nextMatch->location)
                    <div class="mt-6 text-center text-gray-300 flex items-center justify-center gap-2 bg-black/20 py-3 rounded-xl border border-white/5">
                        <x-heroicon-s-map-pin class="w-5 h-5 text-[#bda871]" />
                        <span class="font-medium tracking-wide">{{ $nextMatch->location }}</span>
                    </div>
                @endif
            @else
                <div class="text-center text-gray-400 py-12 flex flex-col items-center">
                    <x-heroicon-o-calendar class="w-12 h-12 mb-3 text-gray-500 opacity-50" />
                    <span class="text-lg">Nessuna partita in programma al momento.</span>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
