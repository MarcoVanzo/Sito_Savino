@extends('emails.layout')

@section('title', 'Ordine #' . $order->order_number . ' annullato')

@section('content')
<h2 style="color: #003063; margin: 0 0 20px 0; font-size: 22px;">Ordine annullato</h2>

<p>Ci dispiace informarti che il tuo ordine <strong>#{{ $order->order_number }}</strong> del {{ $order->created_at->format('d/m/Y') }} è stato <strong>automaticamente annullato</strong> per mancato pagamento entro i termini previsti.</p>

<table style="width: 100%; border-collapse: collapse; margin: 20px 0; background: #f8f9fa; border-radius: 8px;">
    <tr>
        <td style="padding: 12px 16px; border-bottom: 1px solid #e9ecef; font-weight: bold; color: #666;">Ordine</td>
        <td style="padding: 12px 16px; border-bottom: 1px solid #e9ecef;">#{{ $order->order_number }}</td>
    </tr>
    <tr>
        <td style="padding: 12px 16px; border-bottom: 1px solid #e9ecef; font-weight: bold; color: #666;">Importo</td>
        <td style="padding: 12px 16px; border-bottom: 1px solid #e9ecef;">€{{ number_format($order->total_price, 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td style="padding: 12px 16px; font-weight: bold; color: #666;">Stato</td>
        <td style="padding: 12px 16px; color: #dc3545; font-weight: bold;">Annullato</td>
    </tr>
</table>

<p>Se desideri ancora acquistare i prodotti, puoi effettuare un nuovo ordine visitando il nostro shop.</p>

<p style="text-align: center; margin: 30px 0;">
    <a href="{{ config('app.url') }}/shop"
       style="display: inline-block; background: #003063; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;">
        Visita lo Shop
    </a>
</p>

<p style="color: #666; font-size: 13px;">Per qualsiasi domanda, contattaci a {{ \App\Models\SiteSetting::get('shop.contact_email', config('mail.from.address')) }}.</p>
@endsection
