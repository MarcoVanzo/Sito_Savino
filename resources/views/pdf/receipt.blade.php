<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Ricevuta {{ $order->order_number }}</title>
    <style>
        @page {
            margin: 30px 40px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            color: #1a1a1a;
            line-height: 1.5;
        }

        /* --- Header --- */
        .header {
            border-bottom: 3px solid #003063;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }

        .header-table {
            width: 100%;
        }

        .logo-cell {
            width: 50%;
            vertical-align: top;
        }

        .logo-cell img {
            max-height: 50px;
            width: auto;
        }

        .company-name {
            font-size: 16px;
            font-weight: 700;
            color: #003063;
            margin-top: 6px;
        }

        .receipt-info-cell {
            width: 50%;
            vertical-align: top;
            text-align: right;
        }

        .receipt-title {
            font-size: 20px;
            font-weight: 700;
            color: #003063;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .receipt-meta {
            font-size: 11px;
            color: #555555;
            line-height: 1.8;
        }

        .receipt-meta strong {
            color: #003063;
        }

        /* --- Customer Info --- */
        .customer-section {
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .section-label {
            font-size: 10px;
            font-weight: 700;
            color: #888888;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .customer-info {
            font-size: 12px;
            color: #333333;
            line-height: 1.7;
        }

        /* --- Items Table --- */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table thead th {
            background-color: #003063;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 12px;
            text-align: left;
        }

        .items-table thead th:nth-child(2),
        .items-table thead th:nth-child(3),
        .items-table thead th:nth-child(4) {
            text-align: right;
        }

        .items-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #eeeeee;
            font-size: 11px;
            vertical-align: top;
        }

        .items-table tbody td:nth-child(2),
        .items-table tbody td:nth-child(3),
        .items-table tbody td:nth-child(4) {
            text-align: right;
        }

        .item-name {
            font-weight: 600;
            color: #333333;
        }

        .item-variant {
            font-size: 10px;
            color: #888888;
            margin-top: 2px;
        }

        .items-table tbody tr:nth-child(even) {
            background-color: #fafafa;
        }

        /* --- Totals --- */
        .totals-wrapper {
            width: 100%;
            margin-bottom: 24px;
        }

        .totals-table {
            width: 260px;
            float: right;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 6px 12px;
            font-size: 11px;
        }

        .totals-table .label {
            color: #666666;
            text-align: left;
        }

        .totals-table .value {
            text-align: right;
            color: #333333;
            font-weight: 600;
        }

        .totals-table .discount .label,
        .totals-table .discount .value {
            color: #F8269C;
        }

        .totals-table .grand-total td {
            border-top: 2px solid #003063;
            padding-top: 10px;
            padding-bottom: 10px;
        }

        .totals-table .grand-total .label {
            font-size: 14px;
            font-weight: 700;
            color: #003063;
        }

        .totals-table .grand-total .value {
            font-size: 14px;
            font-weight: 700;
            color: #003063;
        }

        /* --- Footer --- */
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

        .vat-note {
            text-align: center;
            font-size: 10px;
            color: #888888;
            margin-bottom: 20px;
            font-style: italic;
        }

        .footer {
            border-top: 1px solid #dddddd;
            padding-top: 16px;
            text-align: center;
        }

        .footer-text {
            font-size: 10px;
            color: #888888;
            line-height: 1.6;
        }

        .footer-disclaimer {
            font-size: 9px;
            color: #aaaaaa;
            margin-top: 8px;
            font-style: italic;
        }
    </style>
</head>
<body>

    {{-- Header --}}
    <div class="header">
        <table role="presentation" class="header-table" cellpadding="0" cellspacing="0">
            <tr>
                <td class="logo-cell">
                    @php
                        $logoPath = public_path('images/logo.png');
                        $logoBase64 = '';
                        if (file_exists($logoPath)) {
                            $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
                        }
                    @endphp
                    @if($logoBase64)
                        <img src="{{ $logoBase64 }}" alt="Logo">
                    @endif
                    <div class="company-name">Savino Del Bene Volley</div>
                </td>
                <td class="receipt-info-cell">
                    <div class="receipt-title">Ricevuta di Acquisto</div>
                    <div class="receipt-meta">
                        <strong>Ricevuta N.</strong> {{ $order->order_number }}<br>
                        <strong>Data:</strong> {{ $order->created_at->format('d/m/Y') }}<br>
                        <strong>Pagamento:</strong> {{ $order->payment_gateway?->getLabel() ?? 'N/D' }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Customer Info --}}
    <div class="customer-section">
        <div class="section-label">Dati Cliente</div>
        <div class="customer-info">
            @php
                $customerName = $order->user?->name ?? $order->guest_name ?? '-';
                $customerEmail = $order->user?->email ?? $order->guest_email ?? '-';
                $customerPhone = $order->user?->phone ?? $order->guest_phone ?? null;
                $shippingAddress = $order->shipping_address;

                // Gestisce formato strutturato (nuovo), raw_address (migrato), e stringa plain (legacy)
                if (is_array($shippingAddress)) {
                    if (isset($shippingAddress['first_name'])) {
                        $addressString = $shippingAddress['first_name'] . ' ' . ($shippingAddress['last_name'] ?? '') . "\n"
                            . ($shippingAddress['street'] ?? '') . "\n"
                            . ($shippingAddress['zip_code'] ?? '') . ' ' . ($shippingAddress['city'] ?? '')
                            . (isset($shippingAddress['province']) ? ' (' . $shippingAddress['province'] . ')' : '');
                    } elseif (isset($shippingAddress['raw_address'])) {
                        $addressString = $shippingAddress['raw_address'];
                    } else {
                        $addressString = collect($shippingAddress)->filter()->implode(', ');
                    }
                } else {
                    $addressString = $shippingAddress ?? '';
                }
            @endphp
            <strong>{{ $customerName }}</strong><br>
            {{ $customerEmail }}
            @if($customerPhone)
                <br>{{ $customerPhone }}
            @endif
            @if($addressString)
                <br><br><strong>Indirizzo:</strong><br>
                {!! nl2br(e($addressString)) !!}
            @endif
        </div>
    </div>

    {{-- Items Table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 50%;">Descrizione</th>
                <th style="width: 10%;">Qtà</th>
                <th style="width: 20%;">Prezzo Unit.</th>
                <th style="width: 20%;">Totale</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>
                        <div class="item-name">{{ $item->product?->name ?? 'Prodotto rimosso' }}</div>
                        @if($item->variant)
                            <div class="item-variant">
                                {{ collect(array_filter([$item->variant->size, $item->variant->color]))->implode(' / ') }}
                            </div>
                        @endif
                    </td>
                    <td>{{ $item->quantity }}</td>
                    <td>€{{ number_format($item->price_at_time_of_purchase, 2, ',', '.') }}</td>
                    <td>€{{ number_format($item->quantity * $item->price_at_time_of_purchase, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    @php
        $subtotal = $order->items->sum(fn($item) => $item->quantity * $item->price_at_time_of_purchase);
    @endphp
    <div class="totals-wrapper clearfix">
        <table role="presentation" class="totals-table">
            <tr>
                <td class="label">Subtotale</td>
                <td class="value">€{{ number_format($subtotal, 2, ',', '.') }}</td>
            </tr>
            @if($order->shipping_cost > 0)
                <tr>
                    <td class="label">Spedizione</td>
                    <td class="value">€{{ number_format($order->shipping_cost, 2, ',', '.') }}</td>
                </tr>
            @endif
            @if($order->coupon_discount > 0)
                <tr class="discount">
                    <td class="label">Sconto coupon</td>
                    <td class="value">-€{{ number_format($order->coupon_discount, 2, ',', '.') }}</td>
                </tr>
            @endif
            <tr class="grand-total">
                <td class="label">TOTALE</td>
                <td class="value">€{{ number_format($order->total_price, 2, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    {{-- VAT Note --}}
    <p class="vat-note">Tutti i prezzi sono IVA inclusa</p>

    {{-- Footer --}}
    <div class="footer">
        @if(\App\Models\SiteSetting::get('shop.receipt_footer_text'))
            <p class="footer-text">
                {{ \App\Models\SiteSetting::get('shop.receipt_footer_text') }}
            </p>
        @endif
        <p class="footer-disclaimer">
            Documento non valido ai fini fiscali
        </p>
    </div>

</body>
</html>
