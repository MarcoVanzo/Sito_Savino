<?php

namespace App\Services;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ReceiptService
{
    /**
     * Genera il contenuto PDF della ricevuta come stringa di bytes.
     */
    public function generate(Order $order): string
    {
        $order->loadMissing('items.product');

        $pdf = Pdf::loadView('pdf.receipt', [
            'order' => $order,
        ]);

        return $pdf->output();
    }

    /**
     * Restituisce una risposta di download del PDF della ricevuta.
     * Filename: ricevuta-{order_number}.pdf
     */
    public function download(Order $order): SymfonyResponse
    {
        $order->loadMissing('items.product');

        $pdf = Pdf::loadView('pdf.receipt', [
            'order' => $order,
        ]);

        $filename = 'ricevuta-' . $order->order_number . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Restituisce una risposta inline per visualizzare il PDF nel browser.
     */
    public function stream(Order $order): SymfonyResponse
    {
        $order->loadMissing('items.product');

        $pdf = Pdf::loadView('pdf.receipt', [
            'order' => $order,
        ]);

        $filename = 'ricevuta-' . $order->order_number . '.pdf';

        return $pdf->stream($filename);
    }
}
