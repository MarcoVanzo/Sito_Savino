<x-filament-widgets::widget>
    <x-filament::section class="overflow-hidden p-0">
        <div class="p-4 bg-gray-50 dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="bg-primary-100 dark:bg-primary-500/20 text-primary-600 dark:text-primary-400 p-2 rounded-lg">
                    <x-heroicon-o-clock class="w-5 h-5" />
                </div>
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Attività Recenti</h2>
            </div>
            
            <a href="{{ \App\Filament\Resources\ActivityLogResource::getUrl('index') }}" class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">
                Vedi tutto &rarr;
            </a>
        </div>
        
        @php $activities = $this->getActivities(); @endphp

        @if($activities->isEmpty())
            <div class="p-8 text-center text-gray-500 dark:text-gray-400 italic">
                Nessuna attività registrata.
            </div>
        @else
            <div class="p-4 space-y-4">
                @foreach($activities as $log)
                    <div class="flex items-start gap-4 p-3 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors border border-transparent hover:border-gray-100 dark:hover:border-gray-700">
                        {{-- Icona azione --}}
                        <div @class([
                            'flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center shadow-sm',
                            'bg-green-100 text-green-600 dark:bg-green-500/20 dark:text-green-400' => $log->action === 'created',
                            'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400' => $log->action === 'updated',
                            'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400' => in_array($log->action, ['deleted', 'force_deleted']),
                            'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400' => $log->action === 'restored',
                        ])>
                            <x-filament::icon :icon="$log->action_icon" class="w-5 h-5" />
                        </div>

                        {{-- Testo --}}
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-900 dark:text-white leading-relaxed">
                                <span class="font-bold">{{ $log->user?->name ?? 'Sistema' }}</span>
                                <span class="text-gray-500 dark:text-gray-400">
                                    ha eseguito
                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ strtolower($log->action_label) }}</span>
                                    su {{ $log->model_type_label }}
                                </span>
                                @if($log->model_label)
                                    <span class="font-medium text-primary-600 dark:text-primary-400 block sm:inline mt-1 sm:mt-0">"{{ Str::limit($log->model_label, 40) }}"</span>
                                @endif
                            </p>
                            <div class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500 mt-1.5 font-medium">
                                <x-heroicon-m-clock class="w-3.5 h-3.5" />
                                {{ $log->created_at->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
