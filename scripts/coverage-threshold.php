<?php

declare(strict_types=1);

/**
 * Soglie minime di copertura per le aree in cui un difetto costa denaro.
 *
 * Non è una misura di qualità complessiva: una percentuale sull'intero `app/`
 * si alza scrivendo test sui getter e non dice nulla su quanto è verificato il
 * percorso di pagamento. Qui si guardano solo le cartelle che toccano ordini,
 * carrello, aste e webhook dei gateway.
 *
 * Le soglie sono un cricchetto: partono appena sotto la copertura misurata al
 * momento dell'introduzione, così bloccano le regressioni senza fermare il
 * lavoro in corso. Vanno alzate quando la copertura sale, mai abbassate per
 * far passare una build — se una PR le fa scendere, mancano dei test.
 *
 * Uso:
 *   php scripts/coverage-threshold.php coverage.xml
 */
$thresholds = [
    // Checkout, carrello, aste: 35,8% al 31/07/2026.
    'app/Http/Controllers/Shop' => 34.0,
    // Webhook Stripe e PayPal: 71,4% al 31/07/2026.
    'app/Http/Controllers/Webhooks' => 70.0,
    // CheckoutService, CartService, AuctionService, BidService: 59,5%.
    'app/Services' => 58.0,
];

$reportPath = $argv[1] ?? 'coverage.xml';

if (! is_readable($reportPath)) {
    fwrite(STDERR, "Report di copertura non leggibile: {$reportPath}\n");
    exit(1);
}

$xml = simplexml_load_file($reportPath);

if ($xml === false) {
    fwrite(STDERR, "Report di copertura non interpretabile: {$reportPath}\n");
    exit(1);
}

/** @var array<string, array{statements: int, covered: int}> $totals */
$totals = array_fill_keys(array_keys($thresholds), ['statements' => 0, 'covered' => 0]);

foreach ($xml->xpath('//file') ?: [] as $file) {
    $name = (string) $file['name'];
    $metrics = $file->metrics;

    if ($metrics === null) {
        continue;
    }

    foreach ($thresholds as $prefix => $_) {
        if (! str_contains($name, '/'.$prefix.'/')) {
            continue;
        }

        $totals[$prefix]['statements'] += (int) $metrics['statements'];
        $totals[$prefix]['covered'] += (int) $metrics['coveredstatements'];
        break;
    }
}

$failed = false;

foreach ($thresholds as $prefix => $minimum) {
    $statements = $totals[$prefix]['statements'];

    if ($statements === 0) {
        // Nessun file trovato: quasi sempre un percorso rinominato senza
        // aggiornare questa lista. Passare in silenzio significherebbe
        // credere di avere una soglia che in realtà non controlla nulla.
        fwrite(STDERR, "✗ {$prefix}: nessun file nel report — percorso sbagliato?\n");
        $failed = true;

        continue;
    }

    $percent = $totals[$prefix]['covered'] / $statements * 100;
    $label = sprintf('%s: %.1f%% (minimo %.1f%%)', $prefix, $percent, $minimum);

    if ($percent + 0.05 < $minimum) {
        fwrite(STDERR, "✗ {$label}\n");
        $failed = true;

        continue;
    }

    fwrite(STDOUT, "✓ {$label}\n");
}

exit($failed ? 1 : 0);
