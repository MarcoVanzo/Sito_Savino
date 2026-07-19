@extends('emails.layout')

@section('content')
<h2 style="color: #003063; margin: 0 0 20px 0; font-size: 22px;">Promemoria: pagamento in attesa</h2>

<p>Il tuo ordine <strong>#{{ $order->order_number }}</strong> del {{ $order->created_at->format('d/m/Y') }} è ancora in attesa di pagamento.</p>

<p>Ti ricordiamo di effettuare il bonifico entro <strong>{{ \App\Models\SiteSetting::get('shop.bank_transfer_expiry_days', 7) }} giorni</strong> dalla data dell'ordine, altrimenti verrà automaticamente annullato.</p>

<table role="presentation" style="width: 100%; border-collapse: collapse; margin: 20px 0; background: #f8f9fa; border-radius: 8px;">
    <tr>
        <td style="padding: 12px 16px; border-bottom: 1px solid #e9ecef; font-weight: bold; color: #666;">IBAN</td>
        <td style="padding: 12px 16px; border-bottom: 1px solid #e9ecef;">{{ \App\Models\SiteSetting::get('shop.bank_transfer_iban') }}</td>
    </tr>
    <tr>
        <td style="padding: 12px 16px; border-bottom: 1px solid #e9ecef; font-weight: bold; color: #666;">Intestatario</td>
        <td style="padding: 12px 16px; border-bottom: 1px solid #e9ecef;">{{ \App\Models\SiteSetting::get('shop.bank_transfer_beneficiary') }}</td>
    </tr>
    <tr>
        <td style="padding: 12px 16px; font-weight: bold; color: #666;">Causale</td>
        <td style="padding: 12px 16px;">Ordine #{{ $order->order_number }}</td>
    </tr>
    <tr>
        <td style="padding: 12px 16px; font-weight: bold; color: #666;">Importo</td>
        <td style="padding: 12px 16px; font-weight: bold; color: #003063; font-size: 18px;">€{{ number_format($order->total_price, 2, ',', '.') }}</td>
    </tr>
</table>

<p style="text-align: center; margin: 30px 0;">
    <a href="{{ config('app.url') }}/shop/ordine/{{ $order->order_number }}?token={{ $order->order_token }}"
       style="display: inline-block; background: #003063; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;">
        Vedi il tuo ordine
    </a>
</p>
@endsection
