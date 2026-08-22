<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La giacenza non basta per il movimento richiesto. Viene lanciata dentro la
 * transazione dell'ordine, che quindi non si chiude: e' la rete di sicurezza
 * contro la vendita sotto zero.
 */
class InsufficientStockException extends RuntimeException {}
