import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { LOGO_VOLLEY_SRCSET } from '@/Constants/logos.js';

/**
 * L'HTML servito precarica il logo e la foto della scheda con `imagesrcset` e
 * `imagesizes` (App\Support\ImmaginiDaPrecaricare, ShopController): se non
 * sono identici a quelli dei componenti, il browser scarica l'immagine due
 * volte invece di una.
 */
const leggi = (percorso) => readFileSync(resolve(process.cwd(), percorso), 'utf8');

describe('immagini precaricate', () => {
    it('il logo precaricato e quello della testata hanno la stessa serie', () => {
        const php = leggi('app/Support/ImmaginiDaPrecaricare.php');
        const testata = leggi('resources/js/Layouts/PublicLayout.vue');

        expect(php).toContain(`LOGO_VOLLEY_SRCSET = '${LOGO_VOLLEY_SRCSET}'`);
        expect(php).toMatch(/LOGO_VOLLEY_SIZES = '\(min-width: 640px\) 125px, 64px'/);
        expect(testata).toContain('sizes="(min-width: 640px) 125px, 64px"');
    });

    it('la foto della scheda precaricata ha lo stesso sizes del componente', () => {
        const php = leggi('app/Http/Controllers/Shop/ShopController.php');
        const misure = php.match(/MISURE_DELLA_FOTO = '([^']+)'/)?.[1];

        expect(misure).toBeTruthy();
        expect(leggi('resources/js/Pages/Public/Shop/ProductDetail.vue')).toContain(`sizes="${misure}"`);
    });
});
