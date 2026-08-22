<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * Errore nel dialogo con PayPal: token, ordine, cattura, rimborso o firma del
 * webhook. Chi la cattura sa che il guasto sta fuori dal sito.
 */
class PayPalException extends RuntimeException {}
