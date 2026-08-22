<?php

namespace App\Filament\Forms;

/**
 * Etichette che ogni template di pagina ripete.
 *
 * L'hero c'e' in tutte le pagine di sezione, la rubrica dei referenti in tre, e
 * i documenti scaricabili sono sempre PDF. Stavano dentro PageTemplateForms,
 * ma le usano anche le classi delle schede: qui le vedono tutte.
 */
class EtichetteDeiCampi
{
    public const HERO_BADGE = 'Etichetta Hero';

    public const HERO_SUBTITLE = 'Sottotitolo Hero';

    public const HERO_DESCRIPTION = 'Descrizione Hero';

    public const FULL_NAME = 'Nome Completo';

    public const BUTTON_TEXT = 'Testo del pulsante';

    public const SHORT_DESCRIPTION = 'Descrizione breve';

    public const PDF_MIME = 'application/pdf';
}
