<?php

namespace App\Support;

/**
 * Informativa sulle comunicazioni promozionali e informativa fornitori, con i
 * testi in `database/data/informative_da_documento.php`.
 *
 * Erano due PDF in Documenti Legali, gli unici link del footer che aprivano un
 * file invece di una pagina. Ora nascono come le pagine dello shop — una volta
 * sola, e da lì sono della redazione — quindi la creazione è quella di
 * `PagineLegaliDelloShop`: cambia solo il file dei testi.
 */
class InformativeDaDocumento extends PagineLegaliDelloShop
{
    public const PROMOZIONALE = 'informativa-comunicazioni-promozionali';

    public const FORNITORI = 'informativa-fornitori';

    public static function tutte(): array
    {
        return require database_path('data/informative_da_documento.php');
    }
}
