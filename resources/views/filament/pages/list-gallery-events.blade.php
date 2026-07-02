<x-filament-panels::page>
    {{-- Progress Bar per analisi AI batch --}}
    <div
        x-data="{
            progress: null,
            polling: null,

            async fetchProgress() {
                try {
                    const result = await $wire.getBatchProgress();
                    this.progress = result;

                    // Se non c'è batch o è terminata, stop polling
                    if (!result) {
                        this.stopPolling();
                        return;
                    }

                    if (result.finished || result.cancelled) {
                        this.stopPolling();
                    }
                } catch (e) {
                    this.stopPolling();
                }
            },

            startPolling() {
                if (this.polling) return;
                this.fetchProgress();
                this.polling = setInterval(() => this.fetchProgress(), 3000);
            },

            stopPolling() {
                if (this.polling) {
                    clearInterval(this.polling);
                    this.polling = null;
                }
            },

            async dismiss() {
                await $wire.dismissBatch();
                this.progress = null;
                this.stopPolling();
            },

            async cancel() {
                if (confirm('Sei sicuro di voler annullare l\'analisi in corso?')) {
                    await $wire.cancelBatch();
                    await this.fetchProgress();
                }
            },

            init() {
                // Primo check: avvia polling solo se c'è una batch attiva
                this.fetchProgress().then(() => {
                    if (this.progress && !this.progress.finished && !this.progress.cancelled) {
                        this.startPolling();
                    }
                });

                // Riavvia polling quando Livewire dispatcha l'evento (dopo lancio di una nuova batch)
                Livewire.on('batch-started', () => {
                    this.startPolling();
                });
            },

            destroy() {
                this.stopPolling();
            }
        }"
    >
        <template x-if="progress !== null">
            <div class="mb-6 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
                {{-- Header --}}
                <div class="px-4 py-3 sm:px-6 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        {{-- Icona animata --}}
                        <template x-if="!progress.finished && !progress.cancelled">
                            <div class="relative flex-shrink-0">
                                <x-heroicon-o-sparkles class="w-6 h-6 text-warning-500 animate-pulse" />
                                <span class="absolute -top-0.5 -right-0.5 flex h-2.5 w-2.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-warning-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-warning-500"></span>
                                </span>
                            </div>
                        </template>

                        <template x-if="progress.finished && !progress.cancelled && progress.failedJobs < progress.totalJobs">
                            <x-heroicon-o-check-circle class="w-6 h-6 text-success-500 flex-shrink-0" />
                        </template>

                        <template x-if="progress.finished && !progress.cancelled && progress.failedJobs >= progress.totalJobs">
                            <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-danger-500 flex-shrink-0" />
                        </template>

                        <template x-if="progress.cancelled">
                            <x-heroicon-o-x-circle class="w-6 h-6 text-danger-500 flex-shrink-0" />
                        </template>

                        {{-- Titolo e contatori --}}
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white truncate" x-text="progress.name"></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <span x-text="progress.processedJobs"></span> / <span x-text="progress.totalJobs"></span> foto analizzate
                                <template x-if="progress.failedJobs > 0">
                                    <span class="text-danger-600 dark:text-danger-400">
                                        — <span x-text="progress.failedJobs"></span> errori
                                    </span>
                                </template>
                                <span class="ml-2 text-gray-400">•</span>
                                <span class="ml-1" x-text="'Avviata alle ' + progress.createdAt"></span>
                            </p>
                        </div>
                    </div>

                    {{-- Azioni --}}
                    <div class="flex items-center gap-2 flex-shrink-0">
                        {{-- Percentuale --}}
                        <span
                            class="text-sm font-bold tabular-nums"
                            :class="{
                                'text-warning-600 dark:text-warning-400': !progress.finished && !progress.cancelled,
                                'text-success-600 dark:text-success-400': progress.finished && !progress.cancelled && progress.failedJobs < progress.totalJobs,
                                'text-danger-600 dark:text-danger-400': progress.cancelled || (progress.finished && progress.failedJobs >= progress.totalJobs),
                            }"
                            x-text="progress.progress + '%'"
                        ></span>

                        {{-- Pulsante Annulla (solo se in corso) --}}
                        <template x-if="!progress.finished && !progress.cancelled">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium text-danger-600 hover:text-danger-500 hover:bg-danger-50 dark:text-danger-400 dark:hover:text-danger-300 dark:hover:bg-danger-500/10 transition"
                                x-on:click="cancel()"
                            >
                                <x-heroicon-m-stop class="w-3.5 h-3.5" />
                                Annulla
                            </button>
                        </template>

                        {{-- Pulsante Chiudi (se finita o annullata) --}}
                        <template x-if="progress.finished || progress.cancelled">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:bg-white/5 transition"
                                x-on:click="dismiss()"
                            >
                                <x-heroicon-m-x-mark class="w-3.5 h-3.5" />
                                Chiudi
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Progress Bar --}}
                <div class="h-1.5 bg-gray-100 dark:bg-gray-800">
                    <div
                        class="h-full transition-all duration-700 ease-out rounded-r-full"
                        :class="{
                            'bg-gradient-to-r from-warning-500 to-warning-400': !progress.finished && !progress.cancelled,
                            'bg-gradient-to-r from-success-500 to-success-400': progress.finished && !progress.cancelled && progress.failedJobs < progress.totalJobs,
                            'bg-gradient-to-r from-danger-500 to-danger-400': progress.cancelled || (progress.finished && progress.failedJobs >= progress.totalJobs),
                        }"
                        :style="'width: ' + progress.progress + '%'"
                    ></div>
                </div>
            </div>
        </template>
    </div>

    {{-- Tabella standard di Filament --}}
    {{ $this->table }}
</x-filament-panels::page>
