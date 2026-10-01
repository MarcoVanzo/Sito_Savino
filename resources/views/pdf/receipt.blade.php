<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Ricevuta {{ $order->order_number }}</title>
    {{--
        Il carattere è Montserrat, come il sito, incorporato nel PDF (installato
        per dompdf in resources/fonts/pdf, solo tondo e grassetto): con Helvetica
        il grassetto non viaggia nel file e ogni lettore lo disegnava a modo suo,
        e un peso intermedio (600) ricadeva su un carattere con le grazie.
        Quindi solo 400 e 700. Niente flex né grid: l'impaginazione è a tabelle.
    --}}
    <style>
        @page { margin: 0; }

        * { margin: 0; padding: 0; }

        body {
            font-family: 'Montserrat', 'Helvetica', sans-serif;
            font-size: 10px;
            color: #1f2937;
            line-height: 1.5;
        }

        .page { padding: 0 44px 120px; }

        table { width: 100%; border-collapse: collapse; }

        /* --- Testata --- */
        .band { background-color: #003063; }
        .band td { vertical-align: middle; padding: 26px 0; }
        .band .logo-cell { width: 96px; padding-left: 44px; }
        .logo-ring {
            width: 72px;
            height: 72px;
            background-color: #ffffff;
            border-radius: 36px;
            text-align: center;
        }
        .logo-ring img { width: 60px; height: 60px; margin-top: 6px; }
        .band .title-cell { padding-left: 18px; }
        .brand-name {
            color: #FA5FB6;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .doc-title {
            color: #ffffff;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .band .amount-cell { text-align: right; padding-right: 44px; }
        .amount-label {
            color: #c7d2e3;
            font-size: 9px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }
        .amount-value { color: #ffffff; font-size: 22px; font-weight: 700; }
        .accent { height: 5px; background-color: #D00778; }

        /* --- Riquadri informativi --- */
        .cards { margin-top: 26px; }
        .cards td.card {
            width: 33.33%;
            background-color: #F3F4F7;
            padding: 12px 14px;
            vertical-align: top;
        }
        .cards td.gap { width: 10px; }
        .label {
            color: #6b7280;
            font-size: 8.5px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }
        .card-value { color: #003063; font-size: 13px; font-weight: 700; margin-top: 3px; }
        .status {
            display: inline-block;
            margin-top: 4px;
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 8.5px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #ffffff;
            background-color: #003063;
        }
        .status-paid { background-color: #047857; }
        .status-pending { background-color: #B45309; }

        /* --- Cliente e spedizione --- */
        .people { margin-top: 26px; }
        .people td { width: 50%; vertical-align: top; padding-right: 20px; }
        .section-title {
            color: #003063;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding-bottom: 6px;
            margin-bottom: 8px;
            border-bottom: 2px solid #D00778;
            width: 40px;
            white-space: nowrap;
        }
        .people .name { font-size: 12px; font-weight: 700; color: #111827; }
        .people .line { color: #4b5563; }

        /* --- Articoli --- */
        .items { margin-top: 30px; }
        .items th {
            color: #6b7280;
            font-size: 8.5px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            text-align: left;
            padding: 0 10px 8px;
            border-bottom: 2px solid #003063;
        }
        .items td {
            padding: 12px 10px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        .items .num { text-align: right; white-space: nowrap; }
        .items .qty { text-align: center; }
        .item-name { font-size: 11.5px; font-weight: 700; color: #111827; }
        .item-detail { font-size: 9.5px; color: #6b7280; margin-top: 2px; }
        .item-total { font-weight: 700; color: #003063; }

        /* --- Totali --- */
        .totals-wrap { margin-top: 18px; }
        .totals-wrap td.spacer { width: 55%; }
        .totals td { padding: 6px 14px; }
        .totals .t-label { color: #4b5563; }
        .totals .t-value { text-align: right; color: #111827; }
        .totals .discount td { color: #B8066A; }
        .totals .grand td {
            background-color: #003063;
            color: #ffffff;
            font-weight: 700;
            padding: 11px 14px;
        }
        .totals .grand .t-label { font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; color: #ffffff; }
        .totals .grand .t-value { font-size: 16px; color: #ffffff; }
        .vat-note { text-align: right; color: #6b7280; font-size: 8.5px; padding: 6px 14px 0; }

        /* --- Piede --- */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 0 44px 26px;
        }
        .footer-rule { border-top: 1px solid #d1d5db; padding-top: 10px; }
        .footer-text { color: #4b5563; font-size: 9px; text-align: center; }
        .footer-legal { color: #6b7280; font-size: 8px; text-align: center; margin-top: 4px; }
        .footer-disclaimer {
            color: #003063;
            font-size: 8.5px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-align: center;
            margin-top: 6px;
        }
    </style>
</head>
<body>
    @php
        $euro = fn ($importo) => '€ '.number_format((float) $importo, 2, ',', '.');

        $logoPath = public_path('images/logo.png');
        $logoBase64 = file_exists($logoPath)
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath))
            : '';

        $customerName = $order->user?->name ?? $order->guest_name ?? '-';
        $customerEmail = $order->user?->email ?? $order->guest_email ?? '-';
        $customerPhone = $order->user?->phone ?? $order->guest_phone ?? null;
        $shippingAddress = $order->shipping_address;

        // Formato strutturato (nuovo), raw_address (migrato) e stringa (legacy).
        if (is_array($shippingAddress)) {
            if (isset($shippingAddress['first_name'])) {
                $addressLines = [
                    trim($shippingAddress['first_name'].' '.($shippingAddress['last_name'] ?? '')),
                    $shippingAddress['street'] ?? '',
                    trim(($shippingAddress['zip_code'] ?? '').' '.($shippingAddress['city'] ?? '')
                        .(isset($shippingAddress['province']) ? ' ('.$shippingAddress['province'].')' : '')),
                    $shippingAddress['country'] ?? '',
                ];
            } elseif (isset($shippingAddress['raw_address'])) {
                $addressLines = preg_split('/\R/', (string) $shippingAddress['raw_address']);
            } else {
                $addressLines = [collect($shippingAddress)->filter(fn ($v) => is_scalar($v))->implode(', ')];
            }
        } else {
            $addressLines = preg_split('/\R/', (string) ($shippingAddress ?? ''));
        }
        $addressLines = array_values(array_filter(array_map('trim', $addressLines), fn ($riga) => $riga !== ''));

        $subtotal = $order->items->sum(fn ($item) => $item->quantity * $item->price_at_time_of_purchase);

        $pagato = $order->paid_at !== null
            || in_array($order->status, [\App\Enums\OrderStatus::Paid, \App\Enums\OrderStatus::Shipped, \App\Enums\OrderStatus::Delivered], true);

        $venditore = \App\Support\CondizioniDiVendita::venditore();
        $footerText = \App\Models\SiteSetting::get('shop.receipt_footer_text');
    @endphp

    {{-- Testata --}}
    <table class="band" role="presentation">
        <tr>
            <td class="logo-cell">
                <div class="logo-ring">
                    @if($logoBase64)
                        <img src="{{ $logoBase64 }}" alt="Savino Del Bene Volley">
                    @endif
                </div>
            </td>
            <td class="title-cell">
                <div class="brand-name">Savino Del Bene Volley · Shop ufficiale</div>
                <div class="doc-title">Ricevuta di acquisto</div>
            </td>
            <td class="amount-cell">
                <div class="amount-label">Totale</div>
                <div class="amount-value">{{ $euro($order->total_price) }}</div>
            </td>
        </tr>
    </table>
    <div class="accent"></div>

    <div class="page">
        {{-- Riquadri: numero, data, pagamento --}}
        <table class="cards" role="presentation">
            <tr>
                <td class="card">
                    <div class="label">Numero ordine</div>
                    <div class="card-value">{{ $order->order_number }}</div>
                </td>
                <td class="gap"></td>
                <td class="card">
                    <div class="label">Data</div>
                    <div class="card-value">{{ $order->created_at->timezone(config('app.timezone'))->format('d/m/Y') }}</div>
                </td>
                <td class="gap"></td>
                <td class="card">
                    <div class="label">Pagamento</div>
                    <div class="card-value">{{ $order->payment_gateway?->getLabel() ?? 'N/D' }}</div>
                    <span class="status {{ $pagato ? 'status-paid' : 'status-pending' }}">{{ $pagato ? 'Pagato' : 'In attesa di pagamento' }}</span>
                </td>
            </tr>
        </table>

        {{-- Cliente e spedizione --}}
        <table class="people" role="presentation">
            <tr>
                <td>
                    <div class="section-title">Cliente</div>
                    <div class="name">{{ $customerName }}</div>
                    <div class="line">{{ $customerEmail }}</div>
                    @if($customerPhone)
                        <div class="line">{{ $customerPhone }}</div>
                    @endif
                </td>
                <td>
                    <div class="section-title">Spedizione a</div>
                    @forelse($addressLines as $indice => $riga)
                        <div class="{{ $indice === 0 ? 'name' : 'line' }}">{{ $riga }}</div>
                    @empty
                        <div class="line">—</div>
                    @endforelse
                </td>
            </tr>
        </table>

        {{-- Articoli --}}
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 52%;">Articolo</th>
                    <th class="qty" style="width: 10%; text-align: center;">Qtà</th>
                    <th class="num" style="width: 19%; text-align: right;">Prezzo</th>
                    <th class="num" style="width: 19%; text-align: right;">Totale</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>
                            <div class="item-name">{{ $item->product?->name ?? 'Prodotto rimosso' }}</div>
                            @if($item->variant)
                                <div class="item-detail">
                                    {{ collect(array_filter([$item->variant->size, $item->variant->color]))->implode(' / ') }}
                                </div>
                            @endif
                            @if($item->nome_personalizzazione)
                                <div class="item-detail">+ {{ $item->nome_personalizzazione }}</div>
                            @endif
                            @if($item->testo_stato_articolo)
                                <div class="item-detail">{{ __('emails.confirmation.item_condition') }}: {!! nl2br(e($item->testo_stato_articolo)) !!}</div>
                            @endif
                        </td>
                        <td class="qty">{{ $item->quantity }}</td>
                        <td class="num">{{ $euro($item->price_at_time_of_purchase) }}</td>
                        <td class="num item-total">{{ $euro($item->quantity * $item->price_at_time_of_purchase) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totali --}}
        <table class="totals-wrap" role="presentation">
            <tr>
                <td class="spacer"></td>
                <td>
                    <table class="totals" role="presentation">
                        <tr>
                            <td class="t-label">Subtotale</td>
                            <td class="t-value">{{ $euro($subtotal) }}</td>
                        </tr>
                        <tr>
                            <td class="t-label">Spedizione</td>
                            <td class="t-value">{{ $order->shipping_cost > 0 ? $euro($order->shipping_cost) : 'Gratuita' }}</td>
                        </tr>
                        @if($order->coupon_discount > 0)
                            <tr class="discount">
                                <td class="t-label">Sconto coupon{{ $order->coupon?->code ? ' ('.$order->coupon->code.')' : '' }}</td>
                                <td class="t-value">− {{ $euro($order->coupon_discount) }}</td>
                            </tr>
                        @endif
                        <tr class="grand">
                            <td class="t-label">Totale</td>
                            <td class="t-value">{{ $euro($order->total_price) }}</td>
                        </tr>
                    </table>
                    <div class="vat-note">Tutti i prezzi sono IVA inclusa</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Piede, fisso in fondo alla pagina --}}
    <div class="footer">
        <div class="footer-rule">
            @if($footerText)
                <p class="footer-text">{{ $footerText }}</p>
            @endif
            <p class="footer-legal">
                {{ $venditore['ragione_sociale'] }} · {{ $venditore['indirizzo'] }}@if($venditore['piva']) · P. IVA {{ $venditore['piva'] }}@endif
            </p>
            <p class="footer-disclaimer">Documento non valido ai fini fiscali</p>
        </div>
    </div>
</body>
</html>
