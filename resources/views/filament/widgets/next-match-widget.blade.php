<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center gap-4 mb-4">
            <x-heroicon-o-fire class="w-6 h-6 text-danger-600" />
            <h2 class="text-lg font-bold">Prossima Partita</h2>
        </div>
        
        @if($nextMatch)
            <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-6 text-center border border-gray-200 dark:border-gray-800">
                <div class="text-sm text-gray-500 font-semibold mb-2 uppercase tracking-wide">
                    {{ $nextMatch->competition_type }}
                </div>
                
                <div class="flex items-center justify-center gap-8 mb-4">
                    <div class="flex flex-col items-center">
                        @if($nextMatch->homeTeam->logo_url)
                            <img src="{{ Storage::url($nextMatch->homeTeam->logo_url) }}" alt="Logo Casa" class="w-16 h-16 object-contain mb-2">
                        @else
                            <div class="w-16 h-16 bg-gray-200 dark:bg-gray-800 rounded-full flex items-center justify-center mb-2">
                                <span class="text-gray-500 font-bold text-xl">{{ substr($nextMatch->homeTeam->name, 0, 1) }}</span>
                            </div>
                        @endif
                        <span class="font-bold max-w-[120px] truncate" title="{{ $nextMatch->homeTeam->name }}">{{ $nextMatch->homeTeam->name }}</span>
                    </div>
                    
                    <div class="text-3xl font-black text-gray-300 dark:text-gray-700">VS</div>
                    
                    <div class="flex flex-col items-center">
                        @if($nextMatch->awayTeam->logo_url)
                            <img src="{{ Storage::url($nextMatch->awayTeam->logo_url) }}" alt="Logo Trasferta" class="w-16 h-16 object-contain mb-2">
                        @else
                            <div class="w-16 h-16 bg-gray-200 dark:bg-gray-800 rounded-full flex items-center justify-center mb-2">
                                <span class="text-gray-500 font-bold text-xl">{{ substr($nextMatch->awayTeam->name, 0, 1) }}</span>
                            </div>
                        @endif
                        <span class="font-bold max-w-[120px] truncate" title="{{ $nextMatch->awayTeam->name }}">{{ $nextMatch->awayTeam->name }}</span>
                    </div>
                </div>
                
                <div class="text-lg font-bold text-primary-600 dark:text-primary-400">
                    {{ \Carbon\Carbon::parse($nextMatch->match_date)->translatedFormat('l d F Y, H:i') }}
                </div>
                @if($nextMatch->location)
                    <div class="text-sm text-gray-500 mt-1">
                        <x-heroicon-m-map-pin class="w-4 h-4 inline" /> {{ $nextMatch->location }}
                    </div>
                @endif
            </div>
        @else
            <div class="text-center text-gray-500 py-8">
                Nessuna partita in programma al momento.
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
