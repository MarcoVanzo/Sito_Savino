<x-filament-widgets::widget>
    <div class="fi-section relative overflow-hidden bg-gradient-to-r from-[#003063] to-[#001833] text-white p-8 animate-fade-in shadow-xl">
        <!-- Accent Line -->
        <div class="absolute top-0 left-0 w-1 h-full bg-[#bda871]"></div>
        
        <!-- Pattern Overlay -->
        <div class="absolute inset-0 opacity-10 pointer-events-none" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 24px 24px;"></div>
        
        <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6">
            <div>
                <h1 class="text-3xl font-bold tracking-tight mb-2">
                    {{ $greeting }}, <span class="text-[#bda871]">{{ $user?->name ?? 'Admin' }}</span>
                </h1>
                <p class="text-gray-300 text-lg">
                    Benvenuto nel pannello di controllo Savino Del Bene Volley.
                </p>
            </div>
            
            <div class="hidden md:flex flex-col items-end">
                <div class="text-sm font-medium text-gray-300 uppercase tracking-widest mb-1">
                    {{ \Carbon\Carbon::now()->translatedFormat('l') }}
                </div>
                <div class="text-2xl font-bold text-[#bda871]">
                    {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
