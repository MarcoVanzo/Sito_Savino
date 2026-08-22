<?php

namespace App\Services\Wikipedia;

use RuntimeException;

/**
 * Wikipedia non raggiungibile o risposta non utilizzabile: l'import delle
 * biografie prosegue sulle altre voci.
 */
class WikipediaException extends RuntimeException {}
