@extends('emails.layout')

@section('title', __('emails.cancelled.title', ['number' => $order->order_number]))

@section('content')
<h2 style="color: #003063; margin: 0 0 20px 0; font-size: 22px;">{{ __('emails.cancelled.heading') }}</h2>

<p>{!! __('emails.cancelled.intro', ['number' => $order->order_number, 'date' => $order->created_at->format('d/m/Y')]) !!}</p>

<table role="presentation" style="width: 100%; border-collapse: collapse; margin: 20px 0; background: #f8f9fa; border-radius: 8px;">
    <tr>
        <td style="padding: 12px 16px; border-bottom: 1px solid #e9ecef; font-weight: bold; color: #666;">{{ __('emails.common.order') }}</td>
        <td style="padding: 12px 16px; border-bottom: 1px solid #e9ecef;">#{{ $order->order_number }}</td>
    </tr>
    <tr>
        <td style="padding: 12px 16px; border-bottom: 1px solid #e9ecef; font-weight: bold; color: #666;">{{ __('emails.common.amount') }}</td>
        <td style="padding: 12px 16px; border-bottom: 1px solid #e9ecef;">€{{ number_format($order->total_price, 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td style="padding: 12px 16px; font-weight: bold; color: #666;">{{ __('emails.common.status') }}</td>
        <td style="padding: 12px 16px; color: #dc3545; font-weight: bold;">{{ __('emails.cancelled.status_value') }}</td>
    </tr>
</table>

<p>{{ __('emails.cancelled.retry') }}</p>

<p style="text-align: center; margin: 30px 0;">
    <a href="{{ config('app.url') }}/shop"
       style="display: inline-block; background: #003063; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;">
        {{ __('emails.cancelled.shop_button') }}
    </a>
</p>

<p style="color: #666; font-size: 13px;">{{ __('emails.cancelled.contact', ['email' => \App\Models\SiteSetting::get('shop.contact_email', config('mail.from.address'))]) }}</p>
@endsection
