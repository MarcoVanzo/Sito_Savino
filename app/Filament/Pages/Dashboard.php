<?php

namespace App\Filament\Pages;

/**
 * Dashboard del pannello CMS.
 *
 * Usa la view nativa di Filament, che renderizza i widget registrati
 * (auto-discoverati da app/Filament/Widgets e ordinati con $sort).
 * In precedenza una view custom sostituiva l'intera pagina con una griglia
 * di link statici, impedendo ai widget di comparire.
 */
class Dashboard extends \Filament\Pages\Dashboard {}
