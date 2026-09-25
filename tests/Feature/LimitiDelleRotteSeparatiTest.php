<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Un `throttle:N,M` senza il terzo parametro conta su una chiave che dipende
 * solo dall'IP (o dall'utente), non dalla rotta: tutti i limiti di quel tipo
 * condividono lo stesso contatore. Il 25/09/2026 lo ha reso visibile il tunnel
 * degli errori del browser (30 al minuto): cinque errori JavaScript bastavano
 * a mandare in 429 il checkout di un ospite (5 al minuto), e cinque aggiunte
 * al carrello facevano lo stesso anche prima.
 */
class LimitiDelleRotteSeparatiTest extends TestCase
{
    #[Test]
    public function ogni_limite_numerico_ha_il_suo_contatore(): void
    {
        $senzaNome = collect(Route::getRoutes()->getRoutes())
            // Il caricamento dei file di Livewire è del pacchetto e serve al
            // solo pannello, dove la chiave è l'utente autenticato.
            ->reject(fn ($rotta) => str_starts_with($rotta->uri(), 'livewire'))
            ->filter(fn ($rotta) => collect($rotta->gatherMiddleware())
                ->contains(fn ($m) => is_string($m) && preg_match('/^throttle:\d+,\d+$/', $m)))
            ->map(fn ($rotta) => implode('|', $rotta->methods()).' '.$rotta->uri())
            ->values()
            ->all();

        $this->assertSame([], $senzaNome, 'Limiti senza prefisso (throttle:N,M,<nome>)');
    }
}
