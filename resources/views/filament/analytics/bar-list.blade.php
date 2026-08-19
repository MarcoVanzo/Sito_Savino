{{--
    Elenco a barre: etichetta, valore e quota sul totale.

    Le barre sono in percentuale sul valore più alto e non sul totale: con una
    voce dominante (quasi sempre "google" o "diretto") tutte le altre
    resterebbero invisibili. La percentuale scritta accanto al numero è invece
    quella vera, sul totale.
--}}
@php
    $items = $items ?? [];
    $labelKey = $labelKey ?? 'name';
    $valueKey = $valueKey ?? 'sessions';
    $suffix = $suffix ?? '';
    $empty = $empty ?? 'Nessun dato nel periodo';

    $values = array_map(fn ($item) => (int) ($item[$valueKey] ?? 0), $items);
    $total = array_sum($values);
    $max = $values === [] ? 0 : max($values);
@endphp

@if ($items === [])
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $empty }}</p>
@else
    <ul class="space-y-3">
        @foreach ($items as $item)
            @php
                $value = (int) ($item[$valueKey] ?? 0);
                $share = $total > 0 ? round($value / $total * 100) : 0;
                $width = $max > 0 ? max(2, round($value / $max * 100)) : 0;
            @endphp
            <li>
                <div class="flex items-baseline justify-between gap-3 text-sm">
                    <span class="truncate text-gray-700 dark:text-gray-200" title="{{ $item[$labelKey] ?? '' }}">
                        {{ $item[$labelKey] ?? '—' }}
                    </span>
                    <span class="shrink-0 font-medium tabular-nums text-gray-950 dark:text-white">
                        {{ number_format($value, 0, ',', '.') }}{{ $suffix }}
                        <span class="font-normal text-gray-400">{{ $share }}%</span>
                    </span>
                </div>
                <div class="mt-1 h-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-800">
                    <div class="h-1.5 rounded-full bg-primary-500" style="width: {{ $width }}%"></div>
                </div>
            </li>
        @endforeach
    </ul>
@endif
