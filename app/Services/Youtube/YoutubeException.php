<?php

namespace App\Services\Youtube;

use RuntimeException;

/**
 * YouTube non ha detto com'è il video: credenziali, rete, risposta o video
 * che non esiste (privato, link sbagliato).
 */
class YoutubeException extends RuntimeException {}
