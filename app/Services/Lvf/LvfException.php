<?php

namespace App\Services\Lvf;

use RuntimeException;

/**
 * Il sito della Lega non ha risposto, o ha risposto con qualcosa che non si
 * puo' leggere. Il sync la cattura e salta il giro, senza toccare i dati.
 */
class LvfException extends RuntimeException {}
