@extends('emails.layout')

@section('title', __('emails.shipped.title', ['number' => $order->order_number]))

@section('content')
    {{-- Heading --}}
    <h1 style="color: #003063; font-family: 'Montserrat', Arial, sans-serif; font-size: 24px; font-weight: 700; margin: 0 0 8px; text-align: center;">
        {{ __('emails.shipped.heading') }}
    </h1>
    <p style="color: #666666; font-size: 14px; text-align: center; margin: 0 0 28px;">
        {{ __('emails.shipped.intro') }}
    </p>

    {{-- Shipment Details --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px; background-color: #f8f9fa; border-radius: 6px; overflow: hidden;">
        <tr>
            <td style="padding: 16px 20px; border-bottom: 1px solid #e9ecef;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">{{ __('emails.common.order_number') }}</td>
                        <td align="right" style="color: #003063; font-size: 15px; font-weight: 700;">{{ $order->order_number }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        @if($order->tracking_number)
            <tr>
                <td style="padding: 12px 20px; border-bottom: 1px solid #e9ecef;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">{{ __('emails.shipped.tracking_number') }}</td>
                            <td align="right" style="color: #333333; font-size: 14px; font-weight: 600;">{{ $order->tracking_number }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        @endif
        @if($order->shipped_at)
            <tr>
                <td style="padding: 12px 20px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">{{ __('emails.shipped.shipped_at') }}</td>
                            <td align="right" style="color: #333333; font-size: 14px;">{{ $order->shipped_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        @endif
    </table>

    {{-- Tracking Link --}}
    @if($order->tracking_url)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
            <tr>
                <td align="center">
                    <a href="{{ $order->tracking_url }}"
                       target="_blank"
                       style="display: inline-block; background-color: #C9A84C; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: 700; font-size: 14px; letter-spacing: 0.3px;">
                        {{ __('emails.shipped.track_button') }}
                    </a>
                </td>
            </tr>
        </table>
    @endif

    {{-- Estimated Delivery --}}
    <div style="background-color: #E8F4FD; border-left: 4px solid #003063; padding: 16px 20px; margin-bottom: 24px; border-radius: 0 6px 6px 0;">
        <p style="color: #333333; font-size: 14px; margin: 0; line-height: 1.6;">
            <strong>{{ __('emails.shipped.delivery_estimate_label') }}</strong> {{ __('emails.shipped.delivery_estimate') }}
        </p>
    </div>

    {{-- CTA --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 8px 0 0;">
                <p style="color: #555555; font-size: 14px; margin: 0 0 16px;">
                    {{ __('emails.shipped.cta_intro') }}
                </p>
                <a href="{{ config('app.url') }}/shop/ordine/{{ $order->order_number }}?token={{ $order->order_token }}"
                   style="display: inline-block; background-color: #003063; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: 700; font-size: 14px; letter-spacing: 0.3px;">
                    {{ __('emails.common.order_details') }}
                </a>
            </td>
        </tr>
    </table>
@endsection
