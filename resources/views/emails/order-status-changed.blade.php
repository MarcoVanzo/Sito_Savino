@extends('emails.layout')

@section('title', __('emails.status_changed.title', ['number' => $order->order_number]))

@section('content')
    {{-- Status-specific heading & message --}}
    @switch($order->status)
        @case(\App\Enums\OrderStatus::Processing)
            <h1 style="color: #003063; font-family: 'Montserrat', Arial, sans-serif; font-size: 24px; font-weight: 700; margin: 0 0 8px; text-align: center;">
                {{ __('emails.status_changed.processing_heading') }}
            </h1>
            <p style="color: #666666; font-size: 14px; text-align: center; margin: 0 0 28px;">
                {{ __('emails.status_changed.processing_intro') }}
            </p>
            @break
        @case(\App\Enums\OrderStatus::Delivered)
            <h1 style="color: #003063; font-family: 'Montserrat', Arial, sans-serif; font-size: 24px; font-weight: 700; margin: 0 0 8px; text-align: center;">
                {{ __('emails.status_changed.delivered_heading') }}
            </h1>
            <p style="color: #666666; font-size: 14px; text-align: center; margin: 0 0 28px;">
                {{ __('emails.status_changed.delivered_intro') }}
            </p>
            @break
        @case(\App\Enums\OrderStatus::Cancelled)
            <h1 style="color: #003063; font-family: 'Montserrat', Arial, sans-serif; font-size: 24px; font-weight: 700; margin: 0 0 8px; text-align: center;">
                {{ __('emails.status_changed.cancelled_heading') }}
            </h1>
            <p style="color: #666666; font-size: 14px; text-align: center; margin: 0 0 28px;">
                {{ __('emails.status_changed.cancelled_intro') }}
            </p>
            @break
        @case(\App\Enums\OrderStatus::Refunded)
            <h1 style="color: #003063; font-family: 'Montserrat', Arial, sans-serif; font-size: 24px; font-weight: 700; margin: 0 0 8px; text-align: center;">
                {{ __('emails.status_changed.refunded_heading') }}
            </h1>
            <p style="color: #666666; font-size: 14px; text-align: center; margin: 0 0 28px;">
                {{ __('emails.status_changed.refunded_intro') }}
            </p>
            @break
        @default
            <h1 style="color: #003063; font-family: 'Montserrat', Arial, sans-serif; font-size: 24px; font-weight: 700; margin: 0 0 8px; text-align: center;">
                {{ __('emails.status_changed.default_heading') }}
            </h1>
            <p style="color: #666666; font-size: 14px; text-align: center; margin: 0 0 28px;">
                {{ __('emails.status_changed.default_intro') }}
            </p>
    @endswitch

    {{-- Order Summary --}}
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
        <tr>
            <td style="padding: 12px 20px; border-bottom: 1px solid #e9ecef;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">{{ __('emails.common.order_date') }}</td>
                        <td align="right" style="color: #333333; font-size: 14px;">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 20px; border-bottom: 1px solid #e9ecef;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">{{ __('emails.common.status') }}</td>
                        <td align="right" style="color: #333333; font-size: 14px; font-weight: 600;">{{ $order->status->getLabel() }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">{{ __('emails.common.total') }}</td>
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
                    {{ __('emails.status_changed.cta_intro') }}
                </p>
                <a href="{{ config('app.url') }}/shop/ordine/{{ $order->order_number }}?token={{ $order->order_token }}"
                   style="display: inline-block; background-color: #003063; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: 700; font-size: 14px; letter-spacing: 0.3px;">
                    {{ __('emails.common.view_order') }}
                </a>
            </td>
        </tr>
    </table>

    {{-- Contact note for cancellations/refunds --}}
    @if(in_array($order->status, [\App\Enums\OrderStatus::Cancelled, \App\Enums\OrderStatus::Refunded]))
        <div style="margin-top: 24px; padding: 16px 20px; background-color: #FFF8E1; border-left: 4px solid #F8269C; border-radius: 0 6px 6px 0;">
            <p style="color: #333333; font-size: 14px; margin: 0; line-height: 1.6;">
                {{ __('emails.status_changed.contact_note') }}
                <a href="mailto:{{ \App\Models\SiteSetting::get('shop.contact_email', config('mail.from.address')) }}" style="color: #003063; text-decoration: none; font-weight: 600;">
                    {{ \App\Models\SiteSetting::get('shop.contact_email', config('mail.from.address')) }}
                </a>.
            </p>
        </div>
    @endif
@endsection
