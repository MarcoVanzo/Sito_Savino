@php
    $user = filament()->auth()->user();
    $expiring = $user instanceof \App\Models\User && $user->passwordIsExpiringSoon();
    $days = $expiring ? $user->daysUntilPasswordExpires() : null;
@endphp

@if ($expiring)
    <div class="fi-banner flex items-center gap-3 border-b border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
        <x-filament::icon
            icon="heroicon-o-exclamation-triangle"
            class="h-5 w-5 shrink-0"
        />

        <span>
            @if ($days <= 1)
                La tua password scade <strong>entro oggi</strong>.
            @else
                La tua password scade fra <strong>{{ $days }} giorni</strong>.
            @endif
            Cambiala ora per non trovarti bloccato al prossimo accesso.
        </span>

        <a
            href="{{ route('password.change') }}"
            class="ml-auto shrink-0 font-semibold underline underline-offset-2 hover:no-underline"
        >
            Cambia password
        </a>
    </div>
@endif
