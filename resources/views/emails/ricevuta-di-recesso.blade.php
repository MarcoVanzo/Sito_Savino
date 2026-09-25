@extends('emails.layout')

@section('title', __('emails.recesso.title', ['number' => $richiesta->numero_ordine]))

@section('content')
    <h1 style="color: #003063; font-family: 'Montserrat', Arial, sans-serif; font-size: 24px; font-weight: 700; margin: 0 0 8px; text-align: center;">
        {{ __('emails.recesso.heading') }}
    </h1>
    <p style="color: #666666; font-size: 14px; text-align: center; margin: 0 0 28px;">
        {{ __('emails.recesso.intro') }}
    </p>

    {{-- Il contenuto della dichiarazione, con data e ora: e' la ricevuta
         che l'art. 54-bis del Codice del Consumo chiede di inviare subito. --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px; background-color: #f8f9fa; border-radius: 6px; overflow: hidden; font-size: 14px; color: #333333;">
        <tr><td style="padding: 14px 20px 4px; color: #666666; font-size: 12px; text-transform: uppercase; font-weight: 600;">{{ __('emails.recesso.statement_label') }}</td></tr>
        <tr><td style="padding: 0 20px 14px; line-height: 1.6;">{{ __('emails.recesso.statement', ['number' => $richiesta->numero_ordine]) }}</td></tr>
        <tr><td style="padding: 8px 20px; border-top: 1px solid #e9ecef;"><strong>{{ __('emails.recesso.name') }}:</strong> {{ $richiesta->nome }}</td></tr>
        <tr><td style="padding: 8px 20px;"><strong>{{ __('emails.recesso.email') }}:</strong> {{ $richiesta->email }}</td></tr>
        <tr><td style="padding: 8px 20px;"><strong>{{ __('emails.common.order_number') }}:</strong> {{ $richiesta->numero_ordine }}</td></tr>
        <tr><td style="padding: 8px 20px;"><strong>{{ __('emails.recesso.items') }}:</strong> {{ $richiesta->articoli ?: __('emails.recesso.all_items') }}</td></tr>
        <tr><td style="padding: 8px 20px 16px;"><strong>{{ __('emails.recesso.sent_at') }}:</strong> {{ $richiesta->inviata_il->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }} ({{ config('app.timezone') }})</td></tr>
    </table>

    <div style="background-color: #E8F0FA; border-left: 4px solid #003063; padding: 16px 20px; margin-bottom: 24px; border-radius: 0 6px 6px 0;">
        <p style="color: #333333; font-size: 14px; margin: 0 0 8px; line-height: 1.6;"><strong>{{ __('emails.recesso.next_label') }}</strong></p>
        <p style="color: #333333; font-size: 14px; margin: 0; line-height: 1.6;">
            {{ __('emails.recesso.next', ['address' => 'Pallavolo Scandicci Savino Del Bene, '.\App\Support\CondizioniDiVendita::venditore()['indirizzo']]) }}
        </p>
    </div>

    <p style="color: #555555; font-size: 14px; text-align: center; margin: 0;">
        {{ __('emails.recesso.questions') }}
    </p>
@endsection
