@php
    $changes = $getRecord()->changes;
@endphp

@if(empty($changes))
    <p class="text-sm text-gray-500 dark:text-gray-400 italic">Nessun dettaglio disponibile.</p>
@else
    {{-- Creazione: mostra solo i nuovi valori --}}
    @if(isset($changes['new']) && !isset($changes['old']))
        <div class="overflow-x-auto">
            <table class="w-full text-sm border border-gray-200 dark:border-white/10 rounded-lg overflow-hidden">
                <thead>
                    <tr class="bg-gray-50 dark:bg-white/5">
                        <th class="px-4 py-2.5 text-left font-medium text-gray-600 dark:text-gray-300 w-1/3">Campo</th>
                        <th class="px-4 py-2.5 text-left font-medium text-gray-600 dark:text-gray-300">Valore</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach($changes['new'] as $field => $value)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02]">
                            <td class="px-4 py-2 font-mono text-xs text-gray-700 dark:text-gray-300 bg-gray-50/50 dark:bg-white/[0.02]">
                                {{ $field }}
                            </td>
                            <td class="px-4 py-2 text-green-700 dark:text-green-400">
                                {{ is_array($value) ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ($value ?? '—') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    {{-- Modifica: mostra old → new --}}
    @elseif(isset($changes['old']) && isset($changes['new']))
        <div class="overflow-x-auto">
            <table class="w-full text-sm border border-gray-200 dark:border-white/10 rounded-lg overflow-hidden">
                <thead>
                    <tr class="bg-gray-50 dark:bg-white/5">
                        <th class="px-4 py-2.5 text-left font-medium text-gray-600 dark:text-gray-300 w-1/4">Campo</th>
                        <th class="px-4 py-2.5 text-left font-medium text-gray-600 dark:text-gray-300 w-[37.5%]">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-red-400"></span>
                                Prima
                            </span>
                        </th>
                        <th class="px-4 py-2.5 text-left font-medium text-gray-600 dark:text-gray-300 w-[37.5%]">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-green-400"></span>
                                Dopo
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach($changes['old'] as $field => $oldValue)
                        @php $newValue = $changes['new'][$field] ?? null; @endphp
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02]">
                            <td class="px-4 py-2 font-mono text-xs text-gray-700 dark:text-gray-300 bg-gray-50/50 dark:bg-white/[0.02]">
                                {{ $field }}
                            </td>
                            <td class="px-4 py-2 bg-red-50/50 dark:bg-red-500/5 text-red-700 dark:text-red-400">
                                {{ is_array($oldValue) ? json_encode($oldValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ($oldValue ?? '—') }}
                            </td>
                            <td class="px-4 py-2 bg-green-50/50 dark:bg-green-500/5 text-green-700 dark:text-green-400">
                                {{ is_array($newValue) ? json_encode($newValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ($newValue ?? '—') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif
