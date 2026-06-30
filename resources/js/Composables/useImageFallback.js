import LOGOS from '@/Constants/logos.js';

/**
 * Gestisce il fallback per le immagini rotte.
 * Quando un <img> non riesce a caricarsi, mostra il logo come placeholder.
 *
 * Utilizzo nel template:
 *   <img :src="imageUrl" @error="onImgError" />
 *
 * Oppure con fallback personalizzato:
 *   <img :src="imageUrl" @error="(e) => onImgError(e, '/images/placeholder.jpg')" />
 */
export function useImageFallback(defaultFallback = LOGOS.VOLLEY) {
    const onImgError = (event, fallback = null) => {
        const img = event.target;
        const fallbackSrc = fallback || defaultFallback;

        // Evita loop infiniti: se il fallback stesso fallisce, nascondi l'immagine
        if (img.src.endsWith(fallbackSrc) || img.dataset.fallbackApplied) {
            img.style.display = 'none';
            return;
        }

        img.dataset.fallbackApplied = 'true';
        img.src = fallbackSrc;
    };

    return { onImgError };
}
