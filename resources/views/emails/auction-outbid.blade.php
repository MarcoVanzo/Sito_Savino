@extends('emails.layout')

@section('title', __('emails.auction_outbid.title', ['title' => $auction->title]))

@section('content')
    {{-- Heading --}}
    <h1 style="color: #003063; font-family: 'Montserrat', Arial, sans-serif; font-size: 24px; font-weight: 700; margin: 0 0 8px; text-align: center;">
        {{ __('emails.auction_outbid.heading') }}
    </h1>
    <p style="color: #666666; font-size: 14px; text-align: center; margin: 0 0 28px;">
        {{ __('emails.auction_outbid.intro') }}
    </p>

    {{-- Dettagli asta --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px; background-color: #f8f9fa; border-radius: 6px; overflow: hidden;">
        <tr>
            <td style="padding: 16px 20px; border-bottom: 1px solid #e9ecef;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">{{ __('emails.auction_outbid.auction') }}</td>
                        <td style="color: #1a1a1a; font-size: 15px; font-weight: 600; text-align: right;">{{ $auction->title }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 16px 20px; border-bottom: 1px solid #e9ecef;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">{{ __('emails.auction_outbid.highest_bid') }}</td>
                        <td style="color: #DF338F; font-size: 18px; font-weight: 700; text-align: right;">€{{ number_format($outbidBid->amount, 2, ',', '.') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 16px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">{{ __('emails.auction_outbid.time_left') }}</td>
                        <td style="color: #1a1a1a; font-size: 15px; font-weight: 600; text-align: right;">
                            @if($auction->end_date->isFuture())
                                {{ $auction->end_date->diffForHumans() }}
                            @else
                                {{ __('emails.auction_outbid.ended') }}
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- CTA Button --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
        <tr>
            <td align="center">
                @if($auction->end_date->isFuture())
                    <a href="{{ config('app.url') }}/shop/aste/{{ $auction->id }}"
                       style="display: inline-block; background-color: #DF338F; color: #ffffff; font-family: 'Montserrat', Arial, sans-serif; font-size: 16px; font-weight: 700; text-decoration: none; padding: 14px 40px; border-radius: 6px; letter-spacing: 0.5px;">
                        {{ __('emails.auction_outbid.cta') }}
                    </a>
                @endif
            </td>
        </tr>
    </table>

    {{-- Info --}}
    <p style="color: #888888; font-size: 13px; text-align: center; margin: 0; line-height: 1.5;">
        {{ __('emails.auction_outbid.footer') }}
    </p>
@endsection
