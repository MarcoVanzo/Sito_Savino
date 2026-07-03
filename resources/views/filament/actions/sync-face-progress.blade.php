<div
    x-data="{
        started: false,
        completed: false,
        current: 0,
        total: 0,
        progress: 0,
        statusText: 'Pronto per l\'analisi...',
        successCount: 0,
        failCount: 0,
        currentFileName: '',
        recordId: @js($recordId),

        async start() {
            this.started = true;
            this.statusText = 'Recupero lista foto...';

            const mediaList = await $wire.getMediaForSync(this.recordId);
            this.total = mediaList.length;

            if (this.total === 0) {
                this.statusText = 'Nessuna foto trovata per questa atleta.';
                this.completed = true;
                return;
            }

            for (let i = 0; i < mediaList.length; i++) {
                this.current = i + 1;
                this.currentFileName = mediaList[i].name;
                this.statusText = 'Analizzando volto nella foto ' + this.current + '/' + this.total + '...';
                this.progress = Math.round((this.current / this.total) * 100);

                try {
                    const result = await $wire.syncSinglePhoto(this.recordId, mediaList[i].id);
                    if (result.success) {
                        this.successCount++;
                    } else {
                        this.failCount++;
                    }
                } catch (e) {
                    this.failCount++;
                }
            }

            this.completed = true;
            if (this.failCount === 0) {
                this.statusText = 'Addestramento completato con successo!';
            } else {
                this.statusText = this.successCount + ' foto sincronizzate, ' + this.failCount + ' fallite.';
            }
        }
    }"
    class="py-2"
>
    {{-- Stato iniziale: bottone Start --}}
    <div x-show="!started" class="text-center">
        <div class="mb-4">
            <x-heroicon-o-cpu-chip class="w-12 h-12 mx-auto text-primary-400" />
        </div>
        <p class="text-sm text-gray-400 mb-4">
            Verranno analizzate tutte le foto (ufficiale + in azione) per addestrare l'AI a riconoscere questa atleta.
        </p>
        <x-filament::button @click="start()" color="primary" icon="heroicon-o-play">
            Avvia Addestramento
        </x-filament::button>
    </div>

    {{-- Barra di progresso --}}
    <div x-show="started" x-cloak>
        {{-- Icona animata --}}
        <div class="flex justify-center mb-4">
            <div x-show="!completed" class="relative">
                <x-heroicon-o-cpu-chip class="w-10 h-10 text-primary-400 animate-pulse" />
            </div>
            <div x-show="completed && failCount === 0">
                <x-heroicon-o-check-circle class="w-10 h-10 text-success-500" />
            </div>
            <div x-show="completed && failCount > 0">
                <x-heroicon-o-exclamation-triangle class="w-10 h-10 text-warning-500" />
            </div>
        </div>

        {{-- Contatore --}}
        <div class="flex justify-between items-center mb-2 text-sm">
            <span class="text-gray-400" x-text="statusText"></span>
            <span class="font-semibold text-white" x-text="progress + '%'"></span>
        </div>

        {{-- Barra --}}
        <div class="w-full bg-gray-700 rounded-full h-3 overflow-hidden mb-3">
            <div
                class="h-full rounded-full transition-all duration-700 ease-out"
                :class="completed && failCount === 0 ? 'bg-success-500' : (completed && failCount > 0 ? 'bg-warning-500' : 'bg-primary-500')"
                :style="'width: ' + progress + '%'"
            ></div>
        </div>

        {{-- Nome file corrente --}}
        <div x-show="!completed" class="text-xs text-gray-500 text-center truncate">
            <span x-text="currentFileName"></span>
        </div>

        {{-- Risultato finale --}}
        <div x-show="completed" class="mt-4 text-center">
            <p class="text-sm mb-1">
                <span class="text-success-400 font-semibold" x-text="successCount"></span>
                <span class="text-gray-400">foto sincronizzate</span>
                <template x-if="failCount > 0">
                    <span>
                        <span class="text-gray-400"> · </span>
                        <span class="text-danger-400 font-semibold" x-text="failCount"></span>
                        <span class="text-gray-400">fallite</span>
                    </span>
                </template>
            </p>
            <x-filament::button
                @click="$wire.unmountTableAction()"
                color="gray"
                size="sm"
                class="mt-3"
            >
                Chiudi
            </x-filament::button>
        </div>
    </div>
</div>
