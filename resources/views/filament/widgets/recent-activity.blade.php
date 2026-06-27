<x-filament-widgets::widget>
    <x-filament::section heading="Ultime Attività" icon="heroicon-o-clock" collapsible>
        @php $activities = $this->getActivities(); @endphp

        @if($activities->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400 italic">Nessuna attività registrata.</p>
        @else
            <div class="space-y-3">
                @foreach($activities as $log)
                    <div class="flex items-start gap-3 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-white/5 transition">
                        {{-- Icona azione --}}
                        <div @class([
                            'mt-0.5 flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center',
                            'bg-green-100 text-green-600 dark:bg-green-500/20 dark:text-green-400' => $log->action === 'created',
                            'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400' => $log->action === 'updated',
                            'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400' => in_array($log->action, ['deleted', 'force_deleted']),
                            'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400' => $log->action === 'restored',
                        ])>
                            <x-filament::icon :icon="$log->action_icon" class="w-4 h-4" />
                        </div>

                        {{-- Testo --}}
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-900 dark:text-white">
                                <span class="font-medium">{{ $log->user?->name ?? 'Sistema' }}</span>
                                <span class="text-gray-500 dark:text-gray-400">
                                    ha eseguito
                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ strtolower($log->action_label) }}</span>
                                    su {{ $log->model_type_label }}
                                </span>
                                @if($log->model_label)
                                    <span class="text-gray-700 dark:text-gray-300 font-medium">"{{ Str::limit($log->model_label, 30) }}"</span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                {{ $log->created_at->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-white/10">
                <a href="{{ \App\Filament\Resources\ActivityLogResource::getUrl('index') }}"
                   class="text-sm text-primary-600 hover:text-primary-500 dark:text-primary-400 font-medium">
                    Vedi tutto il registro →
                </a>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
