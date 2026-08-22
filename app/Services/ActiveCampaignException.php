<?php

namespace App\Services;

use RuntimeException;

/**
 * Sincronizzazione con ActiveCampaign non riuscita. I lavori in coda la usano
 * per farsi ritentare invece di dare l'iscrizione per fatta.
 */
class ActiveCampaignException extends RuntimeException {}
