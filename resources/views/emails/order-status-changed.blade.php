@extends('emails.layout')

@section('title', 'Aggiornamento Ordine #' . $order->order_number)

@section('content')
    {{-- Status-specific heading & message --}}
    @switch($order->status)
        @case(\App\Enums\OrderStatus::Processing)
            <h1 style="color: #003063; font-family: 'Montserrat', Arial, sans-serif; font-size: 24px; font-weight: 700; margin: 0 0 8px; text-align: center;">
                Il tuo ordine è in lavorazione
            </h1>
            <p style="color: #666666; font-size: 14px; text-align: center; margin: 0 0 28px;">
                Abbiamo preso in carico il tuo ordine e lo stiamo preparando per la spedizione.
            </p>
            @break
        @case(\App\Enums\OrderStatus::Delivered)
            <h1 style="color: #003063; font-family: 'Montserrat', Arial, sans-serif; font-size: 24px; font-weight: 700; margin: 0 0 8px; text-align: center;">
                Ordine consegnato! ✅
            </h1>
            <p style="color: #666666; font-size: 14px; text-align: center; margin: 0 0 28px;">
                Il tuo ordine è stato consegnato con successo. Speriamo che tu sia soddisfatto del tuo acquisto!
            </p>
            @break
        @case(\App\Enums\OrderStatus::Cancelled)
            <h1 style="color: #003063; font-family: 'Montserrat', Arial, sans-serif; font-size: 24px; font-weight: 700; margin: 0 0 8px; text-align: center;">
                Ordine annullato
            </h1>
            <p style="color: #666666; font-size: 14px; text-align: center; margin: 0 0 28px;">
                Il tuo ordine è stato annullato. Se hai effettuato un pagamento, verrai contattato per il rimborso.
            </p>
            @break
        @case(\App\Enums\OrderStatus::Refunded)
            <h1 style="color: #003063; font-family: 'Montserrat', Arial, sans-serif; font-size: 24px; font-weight: 700; margin: 0 0 8px; text-align: center;">
                Rimborso elaborato
            </h1>
            <p style="color: #666666; font-size: 14px; text-align: center; margin: 0 0 28px;">
                Il rimborso per il tuo ordine è stato elaborato. L'importo verrà accreditato sul metodo di pagamento originale.
            </p>
            @break
        @default
            <h1 style="color: #003063; font-family: 'Montserrat', Arial, sans-serif; font-size: 24px; font-weight: 700; margin: 0 0 8px; text-align: center;">
                Aggiornamento sul tuo ordine
            </h1>
            <p style="color: #666666; font-size: 14px; text-align: center; margin: 0 0 28px;">
                Lo stato del tuo ordine è stato aggiornato.
            </p>
    @endswitch

    {{-- Order Summary --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px; background-color: #f8f9fa; border-radius: 6px; overflow: hidden;">
        <tr>
            <td style="padding: 16px 20px; border-bottom: 1px solid #e9ecef;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Numero Ordine</td>
                        <td align="right" style="color: #003063; font-size: 15px; font-weight: 700;">{{ $order->order_number }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 20px; border-bottom: 1px solid #e9ecef;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Data Ordine</td>
                        <td align="right" style="color: #333333; font-size: 14px;">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 20px; border-bottom: 1px solid #e9ecef;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Stato</td>
                        <td align="right" style="color: #333333; font-size: 14px; font-weight: 600;">{{ $order->status->getLabel() }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Totale</td>
                        <td align="right" style="color: #003063; font-size: 16px; font-weight: 700;">€{{ number_format($order->total_price, 2, ',', '.') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- CTA --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 8px 0 0;">
                <p style="color: #555555; font-size: 14px; margin: 0 0 16px;">
                    Puoi visualizzare i dettagli del tuo ordine in qualsiasi momento:
                </p>
                <a href="{{ config('app.url') }}/shop/ordine/{{ $order->order_number }}?token={{ $order->order_token }}"
                   style="display: inline-block; background-color: #003063; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: 700; font-size: 14px; letter-spacing: 0.3px;">
                    Vedi il tuo ordine →
                </a>
            </td>
        </tr>
    </table>

    {{-- Contact note for cancellations/refunds --}}
    @if(in_array($order->status, [\App\Enums\OrderStatus::Cancelled, \App\Enums\OrderStatus::Refunded]))
        <div style="margin-top: 24px; padding: 16px 20px; background-color: #FFF8E1; border-left: 4px solid #C9A84C; border-radius: 0 6px 6px 0;">
            <p style="color: #333333; font-size: 14px; margin: 0; line-height: 1.6;">
                Per qualsiasi domanda sul tuo ordine, non esitare a contattarci rispondendo a questa email o scrivendo a
                <a href="mailto:{{ \App\Models\SiteSetting::get('shop.contact_email', config('mail.from.address')) }}" style="color: #003063; text-decoration: none; font-weight: 600;">
                    {{ \App\Models\SiteSetting::get('shop.contact_email', config('mail.from.address')) }}
                </a>.
            </p>
        </div>
    @endif
@endsection
