{{--
    Il riquadro «Cosa serve» del modulo delle grafiche: formato, misure, peso
    e durata da chiedere allo sponsor, prima di caricare il file.

    @param list<array{0: string, 1: string}> $voci  FormatiDelleCreativita::requisiti()
    @param string|null $modello  indirizzo del modello della cornice
--}}
<div class="fr">
    <style>
        .fr { border: 1px solid rgb(var(--primary-500) / .35); background: rgb(var(--primary-500) / .06); border-radius: 10px; padding: 12px 14px; font-size: 13px; }
        .fr-titolo { font-weight: 700; margin: 0 0 8px; color: rgb(var(--gray-950)); }
        .dark .fr-titolo { color: #fff; }
        .fr dl { display: grid; grid-template-columns: minmax(120px, max-content) 1fr; gap: 4px 14px; margin: 0; }
        .fr dt { color: rgb(var(--gray-500)); }
        .fr dd { margin: 0; font-weight: 600; font-variant-numeric: tabular-nums; }
        .fr-nota { margin: 10px 0 0; color: rgb(var(--gray-500)); font-size: 12px; }
        .fr a { color: rgb(var(--primary-600)); font-weight: 600; text-decoration: underline; }
        @media (max-width: 480px) { .fr dl { grid-template-columns: 1fr; } .fr dd { margin-bottom: 4px; } }
    </style>
    <p class="fr-titolo">Cosa serve</p>
    <dl>
        @foreach ($voci as [$voce, $valore])
            <dt>{{ $voce }}</dt>
            <dd>{{ $valore }}</dd>
        @endforeach
    </dl>
    <p class="fr-nota">
        Il pannello controlla il file al caricamento e, se non va, dice cosa correggere.
        @if ($modello)
            Da mandare allo sponsor: <a href="{{ $modello }}" download="modello-cornice-1920x1080.png">modello della cornice</a>.
        @endif
    </p>
</div>
