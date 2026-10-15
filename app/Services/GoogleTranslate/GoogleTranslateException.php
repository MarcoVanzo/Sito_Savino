<?php

namespace App\Services\GoogleTranslate;

use RuntimeException;

/**
 * Google Cloud Translation non ha tradotto: credenziali, rete o risposta.
 */
class GoogleTranslateException extends RuntimeException {}
