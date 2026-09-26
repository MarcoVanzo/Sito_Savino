<?php

namespace App\Support\Accessibilita;

use Barryvdh\DomPDF\PDF;
use Dompdf\Adapter\CPDF;

/**
 * I PDF che generiamo noi (le condizioni allegate alla conferma d'ordine, la
 * ricevuta) resi accessibili per quanto dompdf consente. Sostituisce il
 * wrapper di laravel-dompdf (PdfAccessibileServiceProvider), quindi vale per
 * ogni `Pdf::loadView()` senza toccare chi lo chiama.
 *
 * - **Titolo** nei metadati, preso dal `<title>` del modello (lo fa già
 *   dompdf), e mostrato dal lettore al posto del nome del file
 *   (`DisplayDocTitle`, WCAG 2.4.2).
 * - **Lingua del documento** (`/Lang` nel catalogo, WCAG 3.1.1), presa da
 *   `<html lang>`: senza, uno screen reader legge il testo con la voce della
 *   lingua di sistema. dompdf non la scrive, quindi si aggiunge con un
 *   aggiornamento incrementale — una nuova versione del catalogo accodata al
 *   file con la sua tabella xref, come fa un editor PDF quando salva: il resto
 *   del file resta byte per byte quello di dompdf.
 *
 * Quello che dompdf non sa fare è il PDF taggato (struttura di titoli ed
 * elenchi per lo screen reader). Lo stesso testo delle condizioni è però
 * pubblicato come pagine HTML accessibili, che la dichiarazione di
 * accessibilità indica come alternativa.
 */
class PdfAccessibile extends PDF
{
    public function render(): void
    {
        parent::render();

        $tela = $this->getDomPDF()->getCanvas();
        if ($tela instanceof CPDF) {
            $tela->get_cpdf()->setPreferences('DisplayDocTitle', 1);
        }
    }

    /** @param array<string, int> $options */
    public function output(array $options = []): string
    {
        $pdf = parent::output($options);
        $lingua = $this->getDomPDF()->getDom()->documentElement?->getAttribute('lang') ?? '';

        return $lingua === '' ? $pdf : self::conLingua($pdf, $lingua);
    }

    /** Accoda al PDF una nuova versione del catalogo con `/Lang`. */
    public static function conLingua(string $pdf, string $lingua): string
    {
        $lingua = preg_replace('/[^A-Za-z-]/', '', $lingua) ?: 'it';

        if (! preg_match('/trailer\s*<<(.*?)>>\s*startxref\s*(\d+)\s*%%EOF\s*$/s', $pdf, $coda)
            || ! preg_match('/\/Root\s+(\d+)\s+0\s+R/', $coda[1], $radice)
            || ! preg_match('/\/Size\s+(\d+)/', $coda[1], $dimensione)) {
            return $pdf;
        }

        $id = (int) $radice[1];

        // L'ultima versione del catalogo: dopo un aggiornamento incrementale
        // ce ne sono due, e vale quella in fondo.
        if (! preg_match_all('/(?:^|\s)'.$id.'\s+0\s+obj\s*<<(.*?)>>\s*endobj/s', $pdf, $versioni)) {
            return $pdf;
        }
        $catalogo = (string) end($versioni[1]);
        if (str_contains($catalogo, '/Lang')) {
            return $pdf;
        }

        $info = preg_match('/\/Info\s+\d+\s+0\s+R/', $coda[1], $m) ? ' '.$m[0] : '';
        $idFile = preg_match('/\/ID\s*\[[^\]]*\]/', $coda[1], $m) ? ' '.$m[0] : '';

        $pdf = rtrim($pdf)."\n";
        $posizioneOggetto = strlen($pdf);
        $pdf .= $id." 0 obj\n<<".rtrim($catalogo)."\n/Lang (".$lingua.")\n>>\nendobj\n";
        $posizioneXref = strlen($pdf);
        $pdf .= "xref\n0 1\n0000000000 65535 f \n".$id." 1\n".sprintf('%010d', $posizioneOggetto)." 00000 n \n";
        $pdf .= "trailer\n<< /Size ".$dimensione[1].' /Root '.$id.' 0 R'.$info.$idFile.' /Prev '.$coda[2]." >>\nstartxref\n".$posizioneXref."\n%%EOF\n";

        return $pdf;
    }
}
