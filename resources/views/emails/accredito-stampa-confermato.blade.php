@extends('emails.layout')

@section('title', __('emails.accredito.subject'))

@section('content')
    <h1 style="color: #003063; font-family: 'Montserrat', Arial, sans-serif; font-size: 24px; font-weight: 700; margin: 0 0 8px; text-align: center;">
        {{ __('emails.accredito.heading') }}
    </h1>
    <p style="color: #666666; font-size: 14px; text-align: center; margin: 0 0 28px;">
        {{ __('emails.accredito.intro', ['name' => $richiesta->name]) }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px; background-color: #f8f9fa; border-radius: 6px; overflow: hidden; font-size: 14px; color: #333333;">
        @if (! empty($dettagli['match']))
            <tr><td style="padding: 14px 20px 8px;"><strong>{{ __('emails.accredito.match') }}:</strong> {{ $dettagli['match'] }}</td></tr>
        @endif
        @if (! empty($dettagli['outlet']))
            <tr><td style="padding: 8px 20px;"><strong>{{ __('emails.accredito.outlet') }}:</strong> {{ $dettagli['outlet'] }}</td></tr>
        @endif
        @if (filled($ruolo))
            <tr><td style="padding: 8px 20px 14px;"><strong>{{ __('emails.accredito.role') }}:</strong> {{ $ruolo }}</td></tr>
        @endif
    </table>

    @if (filled($messaggio))
        <div style="background-color: #E8F0FA; border-left: 4px solid #003063; padding: 16px 20px; margin-bottom: 24px; border-radius: 0 6px 6px 0;">
            <p style="color: #333333; font-size: 14px; margin: 0; line-height: 1.6;">{!! nl2br(e($messaggio)) !!}</p>
        </div>
    @endif

    <p style="color: #555555; font-size: 14px; text-align: center; margin: 0;">
        {{ __('emails.accredito.questions', ['email' => $ufficioStampa]) }}
    </p>
@endsection
