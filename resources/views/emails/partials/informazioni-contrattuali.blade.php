{{--
    Le informazioni che la conferma d'ordine deve portare con sé.

    L'art. 51 c. 7 del Codice del consumo chiede che la conferma del contratto,
    su supporto durevole, contenga tutte le informazioni precontrattuali: chi
    vende, il diritto di recesso con il modulo tipo, la garanzia legale. Fino al
    25 settembre 2026 l'email riportava solo articoli e totale. Il testo intero
    delle condizioni viaggia in allegato (OrderConfirmation::attachments), qui
    c'è quello che il cliente deve poter leggere senza aprire nulla.

    $venditore arriva da CondizioniDiVendita::venditore().
--}}
@php
    $venditore = \App\Support\CondizioniDiVendita::venditore();
    $indirizzoRecesso = $venditore['email'] ?? $venditore['pec'];
    $titoloSezione = 'color: #003063; font-size: 15px; font-weight: 700; margin: 0 0 8px;';
    $testo = 'color: #555555; font-size: 13px; line-height: 1.6; margin: 0 0 10px;';
@endphp

<div style="margin-top: 32px; padding-top: 24px; border-top: 2px solid #003063;">
    <h2 style="color: #003063; font-size: 16px; font-weight: 700; margin: 0 0 16px;">
        {{ __('emails.contratto.heading') }}
    </h2>

    {{-- Chi vende --}}
    <h3 style="{{ $titoloSezione }}">{{ __('emails.contratto.seller_heading') }}</h3>
    <p style="{{ $testo }}">
        {{ $venditore['ragione_sociale'] }}<br>
        {{ $venditore['indirizzo'] }}<br>
        @if($venditore['piva']){{ __('emails.contratto.vat') }} {{ $venditore['piva'] }}@endif
        @if($venditore['cf']) — {{ __('emails.contratto.tax_code') }} {{ $venditore['cf'] }}@endif
        @if($venditore['rea']) — REA {{ $venditore['rea'] }}@endif
        @if($venditore['capitale']) — {{ __('emails.contratto.share_capital') }} {{ $venditore['capitale'] }}@endif
        <br>
        @if($venditore['email'])Email: <a href="mailto:{{ $venditore['email'] }}" style="color: #003063;">{{ $venditore['email'] }}</a>@endif
        @if($venditore['pec']) — PEC: <a href="mailto:{{ $venditore['pec'] }}" style="color: #003063;">{{ $venditore['pec'] }}</a>@endif
    </p>

    {{-- Recesso --}}
    <h3 style="{{ $titoloSezione }}">{{ __('emails.contratto.withdrawal_heading') }}</h3>
    <p style="{{ $testo }}">{{ __('emails.contratto.withdrawal_period') }}</p>
    <p style="{{ $testo }}">{{ __('emails.contratto.withdrawal_how', ['email' => $indirizzoRecesso, 'address' => $venditore['indirizzo']]) }}</p>
    <p style="{{ $testo }}">
        {{ __('emails.contratto.withdrawal_online') }}
        <a href="{{ url(($order->locale && $order->locale !== config('app.fallback_locale') ? '/'.$order->locale : '').'/recesso?ordine='.urlencode($order->order_number)) }}" style="color: #003063;">{{ __('emails.contratto.withdrawal_online_link') }}</a>
    </p>
    <p style="{{ $testo }}">{{ __('emails.contratto.withdrawal_effects') }}</p>
    <p style="{{ $testo }}"><strong>{{ __('emails.contratto.withdrawal_costs') }}</strong></p>
    <p style="{{ $testo }}">{{ __('emails.contratto.withdrawal_exclusions') }}</p>

    {{-- Modulo tipo: Allegato I, parte B del Codice del consumo --}}
    <div style="background-color: #f8f9fa; border: 1px dashed #cccccc; border-radius: 6px; padding: 14px 18px; margin: 0 0 16px;">
        <p style="color: #003063; font-size: 13px; font-weight: 700; margin: 0 0 8px;">{{ __('emails.contratto.form_heading') }}</p>
        <p style="color: #555555; font-size: 12px; line-height: 1.7; margin: 0;">
            {{ __('emails.contratto.form_to', ['seller' => $venditore['ragione_sociale'], 'address' => $venditore['indirizzo'], 'email' => $indirizzoRecesso]) }}<br>
            {{ __('emails.contratto.form_notice') }}<br>
            ……………………………………………<br>
            {{ __('emails.contratto.form_order', ['number' => $order->order_number]) }}<br>
            {{ __('emails.contratto.form_dates', ['date' => $order->created_at->format('d/m/Y')]) }}<br>
            {{ __('emails.contratto.form_name') }}<br>
            {{ __('emails.contratto.form_address') }}<br>
            {{ __('emails.contratto.form_signature') }}<br>
            {{ __('emails.contratto.form_date') }}<br>
            <em>{{ __('emails.contratto.form_delete') }}</em>
        </p>
    </div>

    {{-- Garanzia legale --}}
    <h3 style="{{ $titoloSezione }}">{{ __('emails.contratto.warranty_heading') }}</h3>
    <p style="{{ $testo }}">{{ __('emails.contratto.warranty_text') }}</p>
    {{-- L'avviso armonizzato UE (Reg. di esecuzione 2025/1960), che le linee
         guida della Commissione chiedono di ripetere nella conferma d'ordine:
         l'immagine ufficiale a colori e il link alla stessa pagina del QR. --}}
    @php($linguaAvviso = $order->locale === 'en' ? 'en' : 'it')
    <p style="margin: 0 0 10px; text-align: center;">
        <img src="{{ asset('images/garanzia/avviso-garanzia-legale-'.$linguaAvviso.'.png') }}" width="455" alt="{{ __('emails.contratto.warranty_notice_alt') }}" style="max-width: 100%; height: auto; border: 1px solid #e0e0e0;">
    </p>
    <p style="{{ $testo }} text-align: center;">
        <a href="{{ __('emails.contratto.warranty_notice_url') }}" style="color: #003063;">{{ __('emails.contratto.warranty_notice_url') }}</a>
    </p>

    {{-- Condizioni --}}
    <h3 style="{{ $titoloSezione }}">{{ __('emails.contratto.terms_heading') }}</h3>
    <p style="{{ $testo }}">
        {{ __('emails.contratto.terms_text', ['version' => $order->condizioni_versione ?? \App\Support\CondizioniDiVendita::VERSIONE]) }}
        <a href="{{ \App\Support\CondizioniDiVendita::indirizzo(\App\Support\CondizioniDiVendita::SLUG_CONDIZIONI, $order->locale) }}" style="color: #003063;">{{ __('emails.contratto.terms_link') }}</a>
        ·
        <a href="{{ \App\Support\CondizioniDiVendita::indirizzo(\App\Support\CondizioniDiVendita::SLUG_RECESSO, $order->locale) }}" style="color: #003063;">{{ __('emails.contratto.withdrawal_link') }}</a>
    </p>
</div>
