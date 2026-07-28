<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ogni componente passato a Inertia::render() deve esistere sotto
 * resources/js/Pages.
 *
 * Il resolver in resources/js/app.js usa un glob statico: se il file non c'è,
 * la pagina non si monta e l'utente resta davanti a uno schermo bianco. È
 * successo davvero con il checkout delle aste, il cui controller renderizzava
 * quattro componenti che non erano mai stati creati; nessun test se ne era
 * accorto perché le asserzioni Inertia non renderizzano i componenti Vue.
 */
class InertiaPagesExistTest extends TestCase
{
    #[Test]
    public function ogni_pagina_inertia_renderizzata_esiste(): void
    {
        $referenced = [];

        foreach ($this->phpFiles(app_path()) as $file) {
            $contents = file_get_contents($file);

            preg_match_all('/Inertia::render\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $inertia);
            preg_match_all('/\binertia\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $helper);

            foreach ([...$inertia[1], ...$helper[1]] as $component) {
                $referenced[$component] ??= $file;
            }
        }

        $this->assertNotEmpty($referenced, 'Nessun componente Inertia trovato: il test non sta verificando nulla.');

        $missing = [];

        foreach ($referenced as $component => $file) {
            $path = resource_path('js/Pages/'.$component.'.vue');

            if (! file_exists($path)) {
                $missing[] = sprintf('%s (usato in %s)', $component, str_replace(base_path().'/', '', $file));
            }
        }

        $this->assertSame(
            [],
            $missing,
            "Questi componenti sono renderizzati dai controller ma non esistono:\n- ".implode("\n- ", $missing)
        );
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $directory): array
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        $files = [];

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
