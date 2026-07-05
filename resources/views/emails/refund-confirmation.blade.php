@extends('emails.layout')

@section('title', 'Rimborso Ordine #' . $order->order_number)

@section('content')
    {{-- Heading --}}
    <h1 style="color: #003063; font-family: 'Montserrat', Arial, sans-serif; font-size: 24px; font-weight: 700; margin: 0 0 8px; text-align: center;">
        Rimborso confermato
    </h1>
    <p style="color: #666666; font-size: 14px; text-align: center; margin: 0 0 28px;">
        Il rimborso per il tuo ordine è stato elaborato.
    </p>

    {{-- Refund Details --}}
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
            <td style="padding: 16px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Importo rimborsato</td>
                        <td align="right" style="color: #2e7d32; font-size: 18px; font-weight: 700;">€{{ number_format($refundAmount, 2, ',', '.') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Timing Note --}}
    <div style="background-color: #E8F5E9; border-left: 4px solid #2e7d32; padding: 16px 20px; margin-bottom: 24px; border-radius: 0 6px 6px 0;">
        <p style="color: #333333; font-size: 14px; margin: 0; line-height: 1.6;">
            <strong>Tempistiche:</strong> il rimborso verrà accreditato sul metodo di pagamento originale entro <strong>5-10 giorni lavorativi</strong>.
            I tempi possono variare in base al tuo istituto bancario.
        </p>
    </div>

    {{-- Info --}}
    <p style="color: #555555; font-size: 14px; text-align: center; margin: 0;">
        Se hai domande sul rimborso, non esitare a contattarci.
    </p>
@endsection
