<?php

namespace App\Services\MetaConversioni;

use RuntimeException;

/**
 * La Conversions API ha rifiutato l'evento o non e' raggiungibile: il job lo
 * ritenta, e all'ultimo tentativo finisce su Sentry.
 */
class MetaConversioniException extends RuntimeException {}
