{{--
    Anteprima di una grafica della diretta, come la vede il tifoso.

    Le misure ripetono quelle del sito (resources/js/Support/direttaConSponsor.js,
    DirettaConSponsor.vue): la cornice 1920×1080 a riquadro intero, con il
    player al 75% in alto a sinistra sopra di lei (un file solo, come sul
    sito); sul telefono il player intero e la fascia sotto. Se cambiano lì,
    vanno cambiate anche qui.

    @param array $grafica  LiveCreative::perLAnteprima()
    @param int   $saltaDopo  secondi prima di «Salta» (0 = non si salta)
--}}
@php
    $video = ($grafica['tipo'] ?? 'immagine') === 'video';
    $cornice = ($grafica['uso'] ?? 'cornice') === 'cornice';
    $saltaDopo = (int) ($saltaDopo ?? 5);
@endphp

<div class="adt">
    <style>
        .adt { display: grid; gap: 16px; color: rgb(var(--gray-700)); font-size: 12px; }
        .dark .adt { color: rgb(var(--gray-300)); }
        .adt-schermi { display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start; }
        .adt-schermo { display: grid; gap: 6px; }
        .adt-etichetta { font-weight: 600; text-transform: uppercase; letter-spacing: .08em; font-size: 10px; color: rgb(var(--gray-500)); }
        .adt-computer { width: min(100%, 440px); }
        .adt-telefono { width: 200px; padding: 10px 8px 14px; border-radius: 22px; background: #0b0f17; }
        .adt-riquadro { position: relative; width: 100%; aspect-ratio: 16 / 9; overflow: hidden; border-radius: 8px; background: #000; }
        .adt-telefono .adt-riquadro { border-radius: 6px; }
        .adt-file { position: absolute; max-width: none; object-fit: fill; display: block; }
        .adt-pieno { inset: 0; width: 100%; height: 100%; object-fit: contain; position: absolute; }
        .adt-player { position: absolute; left: 0; top: 0; width: 75%; height: 75%; z-index: 1; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #12305a, #001a38); color: #fff; font-weight: 700; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; }
        .adt-player-intero { width: 100%; height: 100%; }
        .adt-sotto { inset: 0; width: 100%; height: 100%; }
        .adt-fascia-telefono .adt-file { width: 100%; height: 400%; left: 0; top: -300%; }
        .adt-fascia-telefono { position: relative; margin-top: 6px; overflow: hidden; border-radius: 6px; aspect-ratio: 1920 / 270; }
        .adt-fascia-telefono.adt-propria { aspect-ratio: auto; }
        .adt-fascia-telefono.adt-propria img { position: static; width: 100%; height: auto; display: block; }
        .adt-barra { margin-top: 6px; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 6px; padding: 6px 8px; border-radius: 8px; background: #003063; }
        .adt-barra span { border-radius: 5px; padding: 3px 7px; font-weight: 700; font-size: 10px; color: #fff; background: rgba(255,255,255,.12); white-space: nowrap; }
        .adt-barra .adt-salta { background: #fff; color: #003063; }
        .adt-nota { color: rgb(var(--gray-500)); font-size: 11px; }
    </style>

    @if ($cornice)
        <div class="adt-schermi">
            <div class="adt-schermo adt-computer">
                <span class="adt-etichetta">Computer</span>
                <div class="adt-riquadro">
                    @if ($video)
                        <video class="adt-file adt-sotto" src="{{ $grafica['url'] }}" muted autoplay loop playsinline></video>
                    @else
                        <img class="adt-file adt-sotto" src="{{ $grafica['url'] }}" alt="">
                    @endif
                    <div class="adt-player">La diretta</div>
                </div>
            </div>

            <div class="adt-schermo">
                <span class="adt-etichetta">Telefono</span>
                <div class="adt-telefono">
                    <div class="adt-riquadro">
                        <div class="adt-player adt-player-intero">La diretta</div>
                    </div>
                    @if (! empty($grafica['fascia_mobile']))
                        <div class="adt-fascia-telefono adt-propria"><img src="{{ $grafica['fascia_mobile'] }}" alt=""></div>
                    @else
                        <div class="adt-fascia-telefono">
                            @if ($video)
                                <video class="adt-file" src="{{ $grafica['url'] }}" muted autoplay loop playsinline></video>
                            @else
                                <img class="adt-file" src="{{ $grafica['url'] }}" alt="">
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <p class="adt-nota">A ogni ciclo compare per i secondi impostati nella diretta della gara; nella «Pausa tra i set» della regia ruota ogni {{ $grafica['durata'] }} secondi. La parte in alto a sinistra della cornice resta coperta dalla diretta.</p>
    @else
        <div class="adt-schermi">
            <div class="adt-schermo adt-computer">
                <span class="adt-etichetta">Prima della diretta</span>
                <div class="adt-riquadro">
                    @if ($video)
                        <video class="adt-pieno" src="{{ $grafica['url'] }}" muted autoplay loop playsinline></video>
                    @else
                        <img class="adt-pieno" src="{{ $grafica['url'] }}" alt="">
                    @endif
                </div>
                <div class="adt-barra">
                    @if ($video)<span>Attiva l'audio dello spot</span>@endif
                    @if (! empty($grafica['link']))<span>Visita lo sponsor</span>@endif
                    @if ($saltaDopo > 0)<span class="adt-salta">Puoi saltare tra {{ $saltaDopo }} s</span>@endif
                </div>
            </div>
        </div>
        <p class="adt-nota">Dura {{ $grafica['durata'] }} secondi{{ $video ? ', parte senza audio' : '' }}. Sul telefono è uguale, con i pulsanti sotto il video.</p>
    @endif
</div>
