{{--
    Condizioni di vendita e informativa sul recesso, allegate alla conferma
    d'ordine: è il supporto durevole che l'art. 51 c. 7 del Codice del consumo
    chiede. Il testo è quello di CondizioniDiVendita::perLAllegato(), lo stesso
    delle pagine del sito. DejaVu Sans perché i caratteri del modulo tipo
    (puntini di sospensione, lineette) in Helvetica non ci sono.
--}}
<!DOCTYPE html>
<html lang="{{ $lingua }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $pagine['condizioni-di-vendita']['titolo'] ?? '' }}</title>
    <style>
        @page { margin: 36px 44px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #1a1a1a; line-height: 1.5; }
        h1 { color: #003063; font-size: 16px; margin: 0 0 4px; }
        h2 { color: #003063; font-size: 14px; margin: 18px 0 6px; }
        h3 { color: #003063; font-size: 11.5px; margin: 12px 0 4px; }
        p { margin: 0 0 6px; }
        ul { margin: 0 0 6px 16px; padding: 0; }
        a { color: #003063; }
        blockquote { margin: 8px 0; padding: 8px 12px; border: 1px dashed #999999; }
        .ordine { color: #666666; font-size: 9.5px; margin-bottom: 14px; border-bottom: 2px solid #003063; padding-bottom: 8px; }
        .pagina { page-break-after: always; }
        .pagina:last-child { page-break-after: auto; }
    </style>
</head>
<body>
    <p class="ordine">
        {{ __('emails.contratto.pdf_header', ['number' => $order->order_number, 'date' => $order->created_at->format('d/m/Y'), 'version' => $order->condizioni_versione ?? \App\Support\CondizioniDiVendita::VERSIONE]) }}
    </p>

    @foreach($pagine as $pagina)
        <div class="pagina">
            {!! $pagina['contenuto'] !!}
        </div>
    @endforeach
</body>
</html>
