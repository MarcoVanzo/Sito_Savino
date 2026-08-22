<?php

namespace App\Services;

use RuntimeException;

/**
 * CompreFace non configurato o in errore. Il riconoscimento dei volti e' un
 * di piu' della gallery: chi chiama decide se fermarsi o proseguire.
 */
class FacialRecognitionException extends RuntimeException {}
