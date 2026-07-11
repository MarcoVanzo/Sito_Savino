<div
    x-data="{
        completed: false,
        current: 0,
        total: 0,
        progress: 0,
        photos: [],
        successCount: 0,
        failCount: 0,
        recordId: @js($recordId),

        async start() {
            const mediaList = await $wire.getMediaForSync(this.recordId);
            this.total = mediaList.length;

            if (this.total === 0) {
                this.completed = true;
                return;
            }

            await $wire.prepareForSync(this.recordId);

            this.photos = mediaList.map(m => ({
                ...m,
                status: 'pending',
                error: null,
            }));

            for (let i = 0; i < this.photos.length; i++) {
                this.photos[i].status = 'processing';
                this.current = i + 1;
                this.progress = Math.round((this.current / this.total) * 100);

                try {
                    const result = await $wire.syncSinglePhoto(this.recordId, this.photos[i].id);
                    if (result.success) {
                        this.photos[i].status = 'success';
                        this.successCount++;
                    } else {
                        this.photos[i].status = 'failed';
                        this.photos[i].error = result.error || 'Volto non rilevato';
                        this.failCount++;
                    }
                } catch (e) {
                    this.photos[i].status = 'failed';
                    this.photos[i].error = 'Errore di connessione';
                    this.failCount++;
                }
            }

            this.completed = true;
        }
    }"
    x-init="start()"
    class="py-2"
>
    {{-- Stato vuoto --}}
    <template x-if="completed && total === 0">
        <div class="text-center py-4">
            <x-heroicon-o-photo class="w-10 h-10 mx-auto text-gray-500 mb-2" />
            <p class="text-sm text-gray-400">Nessuna foto disponibile per questa atleta.</p>
            <p class="text-xs text-gray-500 mt-1">Carica almeno una foto ufficiale o in azione.</p>
        </div>
    </template>

    {{-- Contenuto principale --}}
    <template x-if="total > 0">
        <div>
            {{-- Header con barra progresso --}}
            <div class="mb-4">
                <div class="flex justify-between items-center mb-1.5 text-xs">
                    <span class="text-gray-400" x-text="completed
                        ? (failCount === 0 ? 'Addestramento completato!' : successCount + ' accettate · ' + failCount + ' scartate')
                        : 'Analisi foto ' + current + ' di ' + total + '...'"></span>
                    <span class="font-semibold" :class="completed && failCount === 0 ? 'text-success-400' : 'text-white'" x-text="progress + '%'"></span>
                </div>
                <div class="w-full bg-gray-700/50 rounded-full h-2 overflow-hidden">
                    <div
                        class="h-full rounded-full transition-all duration-500 ease-out"
                        :class="completed && failCount === 0 ? 'bg-success-500' : (completed && failCount > 0 ? 'bg-warning-500' : 'bg-primary-500')"
                        :style="'width: ' + progress + '%'"
                    ></div>
                </div>
            </div>

            {{-- Griglia foto --}}
            <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                <template x-for="(photo, index) in photos" :key="photo.id">
                    <div class="relative rounded-lg overflow-hidden border-2 transition-all duration-300"
                         :class="{
                            'border-gray-600/50 opacity-50': photo.status === 'pending',
                            'border-primary-500 ring-2 ring-primary-500/30': photo.status === 'processing',
                            'border-success-500': photo.status === 'success',
                            'border-danger-500': photo.status === 'failed',
                         }">
                        {{-- Thumbnail --}}
                        <img :src="photo.url" :alt="photo.name" class="w-full h-20 object-cover" />

                        {{-- Overlay stato --}}
                        <div class="absolute inset-0 flex items-center justify-center transition-all duration-300"
                             :class="{
                                'bg-black/40': photo.status === 'pending',
                                'bg-primary-900/50': photo.status === 'processing',
                                'bg-success-900/30': photo.status === 'success',
                                'bg-danger-900/50': photo.status === 'failed',
                             }">
                            {{-- Pending --}}
                            <template x-if="photo.status === 'pending'">
                                <span class="text-gray-400 text-xs">In attesa</span>
                            </template>
                            {{-- Processing --}}
                            <template x-if="photo.status === 'processing'">
                                <svg class="w-6 h-6 text-primary-400 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </template>
                            {{-- Success --}}
                            <template x-if="photo.status === 'success'">
                                <div class="bg-success-500 rounded-full p-1">
                                    <x-heroicon-m-check class="w-4 h-4 text-white" />
                                </div>
                            </template>
                            {{-- Failed --}}
                            <template x-if="photo.status === 'failed'">
                                <div class="text-center">
                                    <div class="bg-danger-500 rounded-full p-1 mx-auto w-fit">
                                        <x-heroicon-m-x-mark class="w-4 h-4 text-white" />
                                    </div>
                                    <p class="text-danger-300 text-[10px] mt-1 px-1 leading-tight" x-text="photo.error"></p>
                                </div>
                            </template>
                        </div>

                        {{-- Badge tipo --}}
                        <div class="absolute top-1 left-1">
                            <span class="text-[9px] px-1 py-0.5 rounded bg-black/60 text-white font-medium" x-text="photo.type"></span>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Bottone chiudi --}}
            <div x-show="completed" class="mt-4 text-center" x-cloak>
                <x-filament::button
                    @click="$wire.$refresh(); $wire.unmountTableAction()"
                    color="gray"
                    size="sm"
                >
                    Chiudi
                </x-filament::button>
            </div>
        </div>
    </template>
</div>
