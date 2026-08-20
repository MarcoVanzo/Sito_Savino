@extends('emails.layout')

@section('title', __('emails.auction_won.title', ['title' => $auction->title]))

@section('content')
    {{-- Heading --}}
    <h1 style="color: #003063; font-family: 'Montserrat', Arial, sans-serif; font-size: 24px; font-weight: 700; margin: 0 0 8px; text-align: center;">
        {{ __('emails.auction_won.heading') }}
    </h1>
    <p style="color: #666666; font-size: 14px; text-align: center; margin: 0 0 28px;">
        {{ __('emails.auction_won.intro') }}
    </p>

    {{-- Dettagli asta --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px; background-color: #f8f9fa; border-radius: 6px; overflow: hidden;">
        <tr>
            <td style="padding: 16px 20px; border-bottom: 1px solid #e9ecef;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">{{ __('emails.auction_won.auction') }}</td>
                        <td style="color: #1a1a1a; font-size: 15px; font-weight: 600; text-align: right;">{{ $auction->title }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 16px 20px; border-bottom: 1px solid #e9ecef;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">{{ __('emails.auction_won.winning_amount') }}</td>
                        {{-- $winningAmount è l'offerta del vincitore corrente (AuctionService::winningAmountFor):
                             current_bid non viene aggiornato alla riassegnazione ed è solo un fallback
                             per i job già in coda prima di questa modifica. --}}
                        <td style="color: #003063; font-size: 18px; font-weight: 700; text-align: right;">€{{ number_format($winningAmount ?? $auction->current_bid, 2, ',', '.') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 16px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">{{ __('emails.auction_won.payment_deadline') }}</td>
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
                    {{ __('emails.auction_won.cta') }}
                </a>
            </td>
        </tr>
    </table>

    {{-- Avviso --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #fff8e1; border-radius: 6px; border-left: 4px solid #F8269C;">
        <tr>
            <td style="padding: 14px 18px;">
                <p style="color: #7a6520; font-size: 13px; margin: 0; line-height: 1.5;">
                    <strong>{{ __('emails.auction_won.warning_label') }}</strong> {{ __('emails.auction_won.warning') }}
                </p>
            </td>
        </tr>
    </table>
@endsection
