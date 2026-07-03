@php
    $record = $getRecord();
    $mediaItems = $record
        ? $record->getMedia('rosters_official')->merge($record->getMedia('rosters_action'))
        : collect();
@endphp

<div class="space-y-3">
    @if($mediaItems->isEmpty())
        <p class="text-sm text-gray-500 italic">Nessuna foto caricata.</p>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
            @foreach($mediaItems as $media)
                @php
                    $aiSynced = $media->getCustomProperty('ai_synced');
                    $aiSyncedAt = $media->getCustomProperty('ai_synced_at');
                    $aiSyncError = $media->getCustomProperty('ai_sync_error');
                    $isOfficial = $media->collection_name === 'rosters_official';
                @endphp
                <div class="relative group rounded-lg overflow-hidden border-2 transition-all
                    {{ $aiSynced === true ? 'border-success-500' : ($aiSynced === false ? 'border-danger-500' : 'border-gray-600') }}">
                    {{-- Thumbnail --}}
                    <img
                        src="{{ $media->getUrl($media->hasGeneratedConversion('thumb') ? 'thumb' : '') }}"
                        alt="{{ $media->file_name }}"
                        class="w-full h-28 object-cover"
                    />

                    {{-- Badge stato AI --}}
                    <div class="absolute top-1.5 right-1.5">
                        @if($aiSynced === true)
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-medium bg-success-500/90 text-white shadow">
                                <x-heroicon-m-check class="w-3 h-3" />
                                AI OK
                            </span>
                        @elseif($aiSynced === false)
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-medium bg-danger-500/90 text-white shadow">
                                <x-heroicon-m-x-mark class="w-3 h-3" />
                                Scartata
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-medium bg-gray-500/90 text-white shadow">
                                <x-heroicon-m-minus class="w-3 h-3" />
                                Non analizzata
                            </span>
                        @endif
                    </div>

                    {{-- Badge tipo foto --}}
                    <div class="absolute top-1.5 left-1.5">
                        @if($isOfficial)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-primary-500/90 text-white shadow">
                                Ufficiale
                            </span>
                        @else
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-gray-700/90 text-white shadow">
                                Azione
                            </span>
                        @endif
                    </div>

                    {{-- Info overlay on hover --}}
                    <div class="absolute bottom-0 left-0 right-0 bg-black/70 px-2 py-1.5 text-xs text-gray-300 opacity-0 group-hover:opacity-100 transition-opacity">
                        <p class="truncate font-medium">{{ $media->file_name }}</p>
                        @if($aiSynced === false && $aiSyncError)
                            <p class="text-danger-400 mt-0.5">Errore: {{ $aiSyncError }}</p>
                        @elseif($aiSyncedAt)
                            <p class="text-gray-500 mt-0.5">Sync: {{ \Carbon\Carbon::parse($aiSyncedAt)->format('d/m/Y H:i') }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Riepilogo --}}
        @php
            $synced = $mediaItems->filter(fn($m) => $m->getCustomProperty('ai_synced') === true)->count();
            $failed = $mediaItems->filter(fn($m) => $m->getCustomProperty('ai_synced') === false)->count();
            $pending = $mediaItems->count() - $synced - $failed;
        @endphp
        <div class="flex gap-4 text-xs text-gray-400 pt-2 border-t border-gray-700">
            <span class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-success-500"></span>
                {{ $synced }} addestrate
            </span>
            <span class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-danger-500"></span>
                {{ $failed }} scartate
            </span>
            <span class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-gray-500"></span>
                {{ $pending }} non analizzate
            </span>
        </div>
    @endif
</div>
