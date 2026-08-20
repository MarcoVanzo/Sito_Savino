@extends('emails.layout')

@section('content')
<h2 style="color: #003063; margin: 0 0 20px 0; font-size: 22px;">{{ __('emails.payment_reminder.heading') }}</h2>

<p>{!! __('emails.payment_reminder.intro', ['number' => $order->order_number, 'date' => $order->created_at->format('d/m/Y')]) !!}</p>

<p>{!! __('emails.payment_reminder.deadline', ['days' => \App\Models\SiteSetting::get('shop.bank_transfer_expiry_days', 7)]) !!}</p>

<table role="presentation" style="width: 100%; border-collapse: collapse; margin: 20px 0; background: #f8f9fa; border-radius: 8px;">
    <tr>
        <td style="padding: 12px 16px; border-bottom: 1px solid #e9ecef; font-weight: bold; color: #666;">{{ __('emails.payment_reminder.iban') }}</td>
        <td style="padding: 12px 16px; border-bottom: 1px solid #e9ecef;">{{ \App\Models\SiteSetting::get('shop.bank_transfer_iban') }}</td>
    </tr>
    <tr>
        <td style="padding: 12px 16px; border-bottom: 1px solid #e9ecef; font-weight: bold; color: #666;">{{ __('emails.payment_reminder.beneficiary') }}</td>
        <td style="padding: 12px 16px; border-bottom: 1px solid #e9ecef;">{{ \App\Models\SiteSetting::get('shop.bank_transfer_beneficiary') }}</td>
    </tr>
    <tr>
        <td style="padding: 12px 16px; font-weight: bold; color: #666;">{{ __('emails.payment_reminder.reason') }}</td>
        <td style="padding: 12px 16px;">{{ __('emails.payment_reminder.reason_value', ['number' => $order->order_number]) }}</td>
    </tr>
    <tr>
        <td style="padding: 12px 16px; font-weight: bold; color: #666;">{{ __('emails.common.amount') }}</td>
        <td style="padding: 12px 16px; font-weight: bold; color: #003063; font-size: 18px;">€{{ number_format($order->total_price, 2, ',', '.') }}</td>
    </tr>
</table>

<p style="text-align: center; margin: 30px 0;">
    <a href="{{ config('app.url') }}/shop/ordine/{{ $order->order_number }}?token={{ $order->order_token }}"
       style="display: inline-block; background: #003063; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;">
        {{ __('emails.payment_reminder.cta') }}
    </a>
</p>
@endsection
