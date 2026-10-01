@extends('emails.layout')

@section('title', 'Nuovo ordine '.$order->order_number)

@section('content')
    @php
        $euro = fn ($importo) => '€ '.number_format((float) $importo, 2, ',', '.');
        $cliente = $order->user->name ?? $order->guest_name ?? '—';
        $email = $order->user->email ?? $order->guest_email;
        $telefono = $order->user->phone ?? $order->guest_phone ?? null;
        $indirizzo = $order->shipping_address;
        $righeIndirizzo = is_array($indirizzo)
            ? (isset($indirizzo['first_name'])
                ? array_filter([
                    trim(($indirizzo['first_name'] ?? '').' '.($indirizzo['last_name'] ?? '')),
                    $indirizzo['street'] ?? null,
                    trim(($indirizzo['zip_code'] ?? '').' '.($indirizzo['city'] ?? '').(isset($indirizzo['province']) ? ' ('.$indirizzo['province'].')' : '')),
                    $indirizzo['country'] ?? null,
                ])
                : preg_split('/\R/', (string) ($indirizzo['raw_address'] ?? collect($indirizzo)->filter(fn ($v) => is_scalar($v))->implode(', '))))
            : preg_split('/\R/', (string) ($indirizzo ?? ''));
        $pagato = $order->paid_at !== null;
        $etichetta = 'color: #6b7280; font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;';
    @endphp

    <h1 style="color: #003063; font-size: 22px; font-weight: 700; margin: 0 0 6px; text-align: center;">
        Nuovo ordine {{ $order->order_number }}
    </h1>
    <p style="color: #4b5563; font-size: 14px; text-align: center; margin: 0 0 24px;">
        @if($pagato)
            Pagato con {{ $order->payment_gateway?->getLabel() ?? 'N/D' }} il {{ $order->paid_at->timezone(config('app.timezone'))->format('d/m/Y \a\l\l\e H:i') }}.
        @else
            Scelto il {{ $order->payment_gateway?->getLabel() ?? 'pagamento differito' }}: <strong>la merce non va spedita finché l'accredito non è confermato</strong> dal pannello.
        @endif
    </p>

    {{-- Totale --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #003063; border-radius: 6px; margin-bottom: 24px;">
        <tr>
            <td style="padding: 16px 20px; color: #ffffff; font-size: 12px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;">Totale</td>
            <td align="right" style="padding: 16px 20px; color: #ffffff; font-size: 22px; font-weight: 700;">{{ $euro($order->total_price) }}</td>
        </tr>
    </table>

    {{-- Cliente e spedizione --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
        <tr>
            <td valign="top" width="50%" style="padding-right: 12px;">
                <p style="{{ $etichetta }} margin: 0 0 6px;">Cliente</p>
                <p style="margin: 0; font-size: 14px; color: #111827;"><strong>{{ $cliente }}</strong></p>
                @if($email)
                    <p style="margin: 0; font-size: 13px;"><a href="mailto:{{ $email }}" style="color: #003063;">{{ $email }}</a></p>
                @endif
                @if($telefono)
                    <p style="margin: 0; font-size: 13px; color: #4b5563;">{{ $telefono }}</p>
                @endif
            </td>
            <td valign="top" width="50%">
                <p style="{{ $etichetta }} margin: 0 0 6px;">Spedizione a</p>
                @forelse(array_values(array_filter(array_map('trim', $righeIndirizzo))) as $riga)
                    <p style="margin: 0; font-size: 13px; color: #4b5563;">{{ $riga }}</p>
                @empty
                    <p style="margin: 0; font-size: 13px; color: #4b5563;">—</p>
                @endforelse
            </td>
        </tr>
    </table>

    {{-- Articoli --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 8px; border-collapse: collapse;">
        <tr>
            <td style="{{ $etichetta }} padding: 0 0 8px; border-bottom: 2px solid #003063;">Articolo</td>
            <td align="center" style="{{ $etichetta }} padding: 0 0 8px; border-bottom: 2px solid #003063;">Qtà</td>
            <td align="right" style="{{ $etichetta }} padding: 0 0 8px; border-bottom: 2px solid #003063;">Totale</td>
        </tr>
        @foreach($order->items as $item)
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; font-size: 14px; color: #111827;">
                    <strong>{{ $item->product?->name ?? 'Prodotto rimosso' }}</strong>
                    @if($item->variant)
                        <br><span style="font-size: 12px; color: #6b7280;">{{ collect(array_filter([$item->variant->size, $item->variant->color]))->implode(' / ') }}</span>
                    @endif
                    @if($item->nome_personalizzazione)
                        <br><span style="font-size: 12px; color: #B8066A;">+ {{ $item->nome_personalizzazione }}</span>
                    @endif
                </td>
                <td align="center" style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; font-size: 14px;">{{ $item->quantity }}</td>
                <td align="right" style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; font-size: 14px; white-space: nowrap;">{{ $euro($item->quantity * $item->price_at_time_of_purchase) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="2" style="padding: 8px 0 0; font-size: 13px; color: #4b5563;">Spedizione</td>
            <td align="right" style="padding: 8px 0 0; font-size: 13px; color: #4b5563;">{{ $order->shipping_cost > 0 ? $euro($order->shipping_cost) : 'Gratuita' }}</td>
        </tr>
        @if($order->coupon_discount > 0)
            <tr>
                <td colspan="2" style="padding: 4px 0 0; font-size: 13px; color: #B8066A;">Sconto coupon</td>
                <td align="right" style="padding: 4px 0 0; font-size: 13px; color: #B8066A;">− {{ $euro($order->coupon_discount) }}</td>
            </tr>
        @endif
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 28px;">
        <tr>
            <td align="center">
                <a href="{{ \App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $order]) }}"
                   style="display: inline-block; background-color: #D00778; color: #ffffff; font-size: 14px; font-weight: 700; text-decoration: none; padding: 12px 28px; border-radius: 6px;">
                    Apri l'ordine nel pannello
                </a>
            </td>
        </tr>
    </table>

    <p style="font-size: 12px; color: #6b7280; text-align: center; margin: 20px 0 0;">
        La ricevuta è allegata in PDF. «Rispondi» scrive direttamente al cliente.
    </p>
@endsection
