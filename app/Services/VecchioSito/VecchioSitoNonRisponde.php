<?php

namespace App\Services\VecchioSito;

use RuntimeException;

/**
 * Il vecchio sito non ha risposto come doveva.
 *
 * Ha un tipo suo perche' chi importa deve poterla distinguere da un errore di
 * programmazione: questa significa "riprova piu' tardi, l'archivio non e'
 * allineato", e finche' il dominio e' del vecchio sito vale la pena riprovare.
 * Dal 1 ottobre 2026 significa invece che il passaggio e' avvenuto e la
 * sorgente non esiste piu'.
 */
class VecchioSitoNonRisponde extends RuntimeException {}
