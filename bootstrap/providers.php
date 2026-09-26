<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\PdfAccessibileServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    PdfAccessibileServiceProvider::class,
];
