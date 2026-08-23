<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Un file non si e' potuto leggere, scrivere o convertire. Riguarda il
 * trasferimento su Spaces e l'analisi delle foto della gallery.
 */
class MediaProcessingException extends RuntimeException {}
