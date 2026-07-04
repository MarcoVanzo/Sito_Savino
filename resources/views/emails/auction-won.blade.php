@extends('emails.layout')

@section('title', 'Hai vinto l\'asta: ' . $auction->title)

@section('content')
    {{-- Heading --}}
    <h1 style="color: #003063; font-family: 'Montserrat', Arial, sans-serif; font-size: 24px; font-weight: 700; margin: 0 0 8px; text-align: center;">
        🎉 Congratulazioni, hai vinto l'asta!
    </h1>
    <p style="color: #666666; font-size: 14px; text-align: center; margin: 0 0 28px;">
        La tua offerta è risultata la vincente. Completa il pagamento per assicurarti il prodotto.
    </p>

    {{-- Dettagli asta --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px; background-color: #f8f9fa; border-radius: 6px; overflow: hidden;">
        <tr>
            <td style="padding: 16px 20px; border-bottom: 1px solid #e9ecef;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Asta</td>
                        <td style="color: #1a1a1a; font-size: 15px; font-weight: 600; text-align: right;">{{ $auction->title }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 16px 20px; border-bottom: 1px solid #e9ecef;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Importo vincente</td>
                        <td style="color: #003063; font-size: 18px; font-weight: 700; text-align: right;">€{{ number_format($auction->current_bid, 2, ',', '.') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 16px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Scadenza pagamento</td>
                        <td style="color: #DF338F; font-size: 15px; font-weight: 600; text-align: right;">{{ $auction->winner_checkout_deadline->format('d/m/Y H:i') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- CTA Button --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
        <tr>
            <td align="center">
                <a href="{{ config('app.url') }}/shop/checkout/asta/{{ $auction->winner_checkout_token }}"
                   style="display: inline-block; background-color: #003063; color: #ffffff; font-family: 'Montserrat', Arial, sans-serif; font-size: 16px; font-weight: 700; text-decoration: none; padding: 14px 40px; border-radius: 6px; letter-spacing: 0.5px;">
                    Completa il pagamento
                </a>
            </td>
        </tr>
    </table>

    {{-- Avviso --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #fff8e1; border-radius: 6px; border-left: 4px solid #C9A84C;">
        <tr>
            <td style="padding: 14px 18px;">
                <p style="color: #7a6520; font-size: 13px; margin: 0; line-height: 1.5;">
                    <strong>⚠️ Attenzione:</strong> Se non effettui il pagamento entro la scadenza indicata, l'asta verrà assegnata al prossimo offerente.
                </p>
            </td>
        </tr>
    </table>
@endsection
