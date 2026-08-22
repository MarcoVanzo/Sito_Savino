<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Rimborso chiesto su un metodo di pagamento che il pannello non sa gestire
 * da solo (per esempio il bonifico): va fatto a mano.
 */
class UnsupportedPaymentGatewayException extends RuntimeException {}
