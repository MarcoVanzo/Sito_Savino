<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Il carrello a cui la richiesta si riferisce non esiste piu': sessione
 * scaduta o carrello gia' convertito in ordine.
 */
class CartNotFoundException extends RuntimeException {}
