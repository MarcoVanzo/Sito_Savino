<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Nessuna zona di spedizione copre il paese scelto: l'ordine non si puo'
 * calcolare, non e' un errore di programmazione.
 */
class ShippingUnavailableException extends RuntimeException {}
