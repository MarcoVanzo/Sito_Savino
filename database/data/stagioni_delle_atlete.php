<?php

/**
 * Le stagioni passate in cui ogni atleta giocava nel Savino Del Bene, per anno
 * d'apertura (2025 = 2025/26). Le legge App\Support\StagioniDelleAtlete per non
 * lasciare tag del riconoscimento dei volti su foto di stagioni in cui
 * l'atleta era altrove.
 *
 * - La stagione in corso non serve: viene dalle rose del pannello.
 * - Elenco vuoto = arrivata quest'anno, nessuna stagione passata.
 * - Atleta assente = non si sa: i suoi tag non vengono giudicati. Un'atleta
 *   nuova con stagioni passate va aggiunta qui.
 *
 * Fonte: le voci di it.wikipedia delle atlete e delle stagioni del club
 * (Pallavolo_Scandicci_Savino_Del_Bene_AAAA-AAAA), dalla 2017-18 alla 2025-26,
 * controllate il 3/10/2026. L'archivio fotografico parte dal 2017.
 */
return [
    'Linda Nwakalor' => [2023, 2024, 2025],
    // Non la sorella Lucia, al Savino dal 2017 al 2020: i tag di Caterina su
    // quelle foto sono proprio lo scambio che questo file corregge.
    'Caterina Bosetti' => [2025],
    'Emma Graziani' => [2024, 2025],
    'Avery Skinner' => [2025],
    'Maja Ognjenović' => [2023, 2024, 2025],
    // Anche nel 2015/16, prima dell'archivio.
    'Sara Alberti' => [2015, 2021, 2022, 2023],
    'Martina Armini' => [2023],
    'Lise Van Hecke' => [],
    'Maja Aleksić' => [],
    "Sofia D'Odorico" => [],
    'Imma Sirressi' => [],
    'Julia Bergmann' => [],
    'Chidera Eze' => [],
    'Kiera Van Ryk' => [],
];
