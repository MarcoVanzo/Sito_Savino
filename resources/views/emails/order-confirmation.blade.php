@extends('emails.layout')

@section('title', 'Conferma Ordine #' . $order->order_number)

@section('content')
    {{-- Heading --}}
    <h1 style="color: #003063; font-family: 'Montserrat', Arial, sans-serif; font-size: 24px; font-weight: 700; margin: 0 0 8px; text-align: center;">
        Grazie per il tuo ordine!
    </h1>
    <p style="color: #666666; font-size: 14px; text-align: center; margin: 0 0 28px;">
        Abbiamo ricevuto il tuo ordine e lo stiamo elaborando.
    </p>

    {{-- Order Summary --}}
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
            <td style="padding: 12px 20px; border-bottom: 1px solid #e9ecef;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Data</td>
                        <td align="right" style="color: #333333; font-size: 14px;">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #888888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Stato</td>
                        <td align="right" style="color: #333333; font-size: 14px;">{{ $order->status->getLabel() }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Items Table --}}
    <h2 style="color: #003063; font-size: 16px; font-weight: 700; margin: 0 0 12px; padding-bottom: 8px; border-bottom: 2px solid #003063;">
        Articoli ordinati
    </h2>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
        <tr style="background-color: #f8f9fa;">
            <td style="padding: 10px 12px; font-size: 12px; font-weight: 700; color: #555555; text-transform: uppercase; letter-spacing: 0.5px;">Prodotto</td>
            <td align="center" style="padding: 10px 8px; font-size: 12px; font-weight: 700; color: #555555; text-transform: uppercase; letter-spacing: 0.5px;">Qtà</td>
            <td align="right" style="padding: 10px 8px; font-size: 12px; font-weight: 700; color: #555555; text-transform: uppercase; letter-spacing: 0.5px;">Prezzo</td>
            <td align="right" style="padding: 10px 12px; font-size: 12px; font-weight: 700; color: #555555; text-transform: uppercase; letter-spacing: 0.5px;">Totale</td>
        </tr>
        @foreach($order->items as $item)
            <tr style="border-bottom: 1px solid #eeeeee;">
                <td style="padding: 12px; font-size: 14px; color: #333333; border-bottom: 1px solid #eeeeee;">
                    {{ $item->product?->name ?? 'Prodotto rimosso' }}
                    @if($item->variant)
                        <br><span style="font-size: 12px; color: #888888;">
                            {{ collect(array_filter([$item->variant->size, $item->variant->color]))->implode(' / ') }}
                        </span>
                    @endif
                </td>
                <td align="center" style="padding: 12px 8px; font-size: 14px; color: #333333; border-bottom: 1px solid #eeeeee;">{{ $item->quantity }}</td>
                <td align="right" style="padding: 12px 8px; font-size: 14px; color: #333333; border-bottom: 1px solid #eeeeee;">€{{ number_format($item->price_at_time_of_purchase, 2, ',', '.') }}</td>
                <td align="right" style="padding: 12px; font-size: 14px; color: #333333; font-weight: 600; border-bottom: 1px solid #eeeeee;">€{{ number_format($item->quantity * $item->price_at_time_of_purchase, 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>

    {{-- Totals --}}
    @php
        $subtotal = $order->items->sum(fn($item) => $item->quantity * $item->price_at_time_of_purchase);
    @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 28px; background-color: #f8f9fa; border-radius: 6px;">
        <tr>
            <td style="padding: 12px 20px; border-bottom: 1px solid #e9ecef;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #555555; font-size: 14px;">Subtotale</td>
                        <td align="right" style="color: #333333; font-size: 14px;">€{{ number_format($subtotal, 2, ',', '.') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        @if($order->shipping_cost > 0)
            <tr>
                <td style="padding: 12px 20px; border-bottom: 1px solid #e9ecef;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="color: #555555; font-size: 14px;">Spedizione</td>
                            <td align="right" style="color: #333333; font-size: 14px;">€{{ number_format($order->shipping_cost, 2, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        @endif
        @if($order->coupon_discount > 0)
            <tr>
                <td style="padding: 12px 20px; border-bottom: 1px solid #e9ecef;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="color: #C9A84C; font-size: 14px;">Sconto coupon</td>
                            <td align="right" style="color: #C9A84C; font-size: 14px;">-€{{ number_format($order->coupon_discount, 2, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        @endif
        <tr>
            <td style="padding: 16px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="color: #003063; font-size: 18px; font-weight: 700;">Totale</td>
                        <td align="right" style="color: #003063; font-size: 18px; font-weight: 700;">€{{ number_format($order->total_price, 2, ',', '.') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Bank Transfer Instructions --}}
    @if($order->payment_gateway === \App\Enums\PaymentGateway::BankTransfer)
        <div style="background-color: #FFF8E1; border-left: 4px solid #C9A84C; padding: 16px 20px; margin-bottom: 24px; border-radius: 0 6px 6px 0;">
            <h3 style="color: #003063; font-size: 15px; font-weight: 700; margin: 0 0 10px;">
                📋 Istruzioni per il Bonifico Bancario
            </h3>
            <p style="color: #333333; font-size: 14px; margin: 0 0 8px; line-height: 1.6;">
                <strong>Beneficiario:</strong> {{ \App\Models\SiteSetting::get('shop.bank_transfer_beneficiary', 'Savino Del Bene Volley') }}<br>
                <strong>IBAN:</strong> {{ \App\Models\SiteSetting::get('shop.bank_transfer_iban', '-') }}<br>
                <strong>Causale:</strong> Ordine {{ $order->order_number }}
            </p>
            <p style="color: #888888; font-size: 13px; margin: 8px 0 0; font-style: italic;">
                Ti preghiamo di effettuare il pagamento entro 5 giorni lavorativi dalla data dell'ordine. In caso contrario, l'ordine verrà automaticamente annullato.
            </p>
        </div>
    @endif

    {{-- CTA --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 8px 0 0;">
                <p style="color: #555555; font-size: 14px; margin: 0 0 16px;">
                    Puoi seguire il tuo ordine in qualsiasi momento:
                </p>
                <a href="{{ config('app.url') }}/shop/ordine/{{ $order->order_number }}?token={{ $order->order_token }}"
                   style="display: inline-block; background-color: #003063; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: 700; font-size: 14px; letter-spacing: 0.3px;">
                    Vedi il tuo ordine →
                </a>
            </td>
        </tr>
    </table>
@endsection
