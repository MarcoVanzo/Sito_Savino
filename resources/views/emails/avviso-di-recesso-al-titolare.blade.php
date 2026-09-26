@extends('emails.layout')

@section('title', __('emails.recesso_titolare.subject', ['number' => $richiesta->numero_ordine]))

@section('content')
    {{-- Solo numero d'ordine e data: nome, email e testo di chi ha scritto nel
         modulo non arrivano al titolare dell'ordine (vedi AvvisoDiRecessoAlTitolare). --}}
    <h1 style="color: #003063; font-family: 'Montserrat', Arial, sans-serif; font-size: 24px; font-weight: 700; margin: 0 0 16px; text-align: center;">
        {{ __('emails.recesso_titolare.heading') }}
    </h1>
    <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 0 0 16px;">
        {{ __('emails.recesso_titolare.body', [
            'number' => $richiesta->numero_ordine,
            'date' => $richiesta->inviata_il->timezone(config('app.timezone'))->format('d/m/Y H:i'),
        ]) }}
    </p>
    <p style="color: #333333; font-size: 14px; line-height: 1.6; margin: 0;">
        {{ __('emails.recesso_titolare.not_you') }}
    </p>
@endsection
