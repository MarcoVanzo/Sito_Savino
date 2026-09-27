<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Il sito non ha SSR: l'HTML che arriva a Google non contiene testo, lo
 * disegnano i bundle di Vite sotto /build/. Fino al 27/09/2026 robots.txt
 * vietava proprio /build/, e Googlebot — che per rendere una pagina scarica
 * solo le risorse consentite — avrebbe indicizzato pagine vuote appena il
 * dominio fosse passato a questo sito.
 */
class RobotsTxtTest extends TestCase
{
    #[Test]
    public function non_vieta_le_risorse_che_disegnano_la_pagina(): void
    {
        $regole = file_get_contents(__DIR__.'/../../public/robots.txt');

        $this->assertIsString($regole);
        $this->assertDoesNotMatchRegularExpression('#^\s*Disallow:\s*/build#mi', $regole);
        $this->assertDoesNotMatchRegularExpression('#^\s*Disallow:\s*/\s*$#mi', $regole);
    }
}
