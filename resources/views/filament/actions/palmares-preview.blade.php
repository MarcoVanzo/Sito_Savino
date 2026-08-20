@php
    use App\Enums\PlayerHonourCategory;

    $groups = collect($honours ?? [])->groupBy('category');
    $medals = ['gold' => 'Oro', 'silver' => 'Argento', 'bronze' => 'Bronzo'];
@endphp

@if ($honours === null)
    <p class="text-sm text-danger-600">
        Su it.wikipedia.org non esiste nessuna voce intitolata «{{ $title }}».
    </p>
@elseif ($groups->isEmpty())
    <p class="text-sm text-warning-600">
        La voce «{{ $title }}» esiste ma non ha una sezione Palmarès leggibile.
    </p>
@else
    <div class="space-y-4 text-sm">
        <p class="text-gray-500 dark:text-gray-400">
            {{ count($honours) }} righe da <a href="https://it.wikipedia.org/wiki/{{ rawurlencode(str_replace(' ', '_', $title)) }}"
                target="_blank" rel="noopener" class="underline">{{ $title }}</a>
        </p>

        @foreach (PlayerHonourCategory::cases() as $category)
            @continue(! $groups->has($category->value))

            <div>
                <h4 class="font-semibold text-gray-700 dark:text-gray-200">
                    {{ $category->getLabel() }}
                    <span class="font-normal text-gray-400">({{ $groups[$category->value]->count() }})</span>
                </h4>
                <ul class="mt-1 space-y-0.5">
                    @foreach ($groups[$category->value] as $honour)
                        <li class="flex gap-2 text-gray-600 dark:text-gray-300">
                            @if ($honour['medal'] ?? null)
                                <span class="shrink-0 font-medium">{{ $medals[$honour['medal']] ?? $honour['medal'] }}</span>
                            @endif
                            <span>{{ $honour['competition'] }}</span>
                            @if ($honour['edition'] ?? null)
                                <span class="text-gray-400">{{ $honour['edition'] }}</span>
                            @endif
                            @if ($honour['note'] ?? null)
                                <span class="text-gray-400">— {{ $honour['note'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
@endif
