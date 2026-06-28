import { ref, onUnmounted } from 'vue';

/**
 * Composable per animazione count-up dei numeri.
 * Anima un valore numerico da 0 al target usando requestAnimationFrame.
 *
 * @param {Object} options
 * @param {number} options.duration - Durata dell'animazione in ms, default 2000
 * @param {function} options.easing - Funzione di easing, default easeOutExpo
 */
export function useCountUp(options = {}) {
    const {
        duration = 2000,
        easing = (t) => (t === 1 ? 1 : 1 - Math.pow(2, -10 * t)), // easeOutExpo
    } = options;

    const displayValues = ref({});
    let animationFrameIds = {};

    /**
     * Parsa un valore stringa e restituisce il numero, il prefisso e il suffisso.
     * Supporta formati come "40+", "4.000+", "A1", "CEV"
     */
    const parseStatValue = (value) => {
        const str = String(value);

        // Trova la parte numerica (con punti come separatore migliaia)
        const match = str.match(/^([^\d]*?)([\d.]+)([^\d]*?)$/);

        if (match) {
            const prefix = match[1] || '';
            const numStr = match[2];
            const suffix = match[3] || '';

            // Rimuovi punti separatori migliaia per ottenere il numero
            const cleanNum = numStr.replace(/\./g, '');
            const num = parseInt(cleanNum, 10);

            if (!isNaN(num)) {
                return {
                    prefix,
                    number: num,
                    suffix,
                    // Mantieni il formato originale (con punti migliaia)
                    hasThousandSep: numStr.includes('.'),
                    originalFormat: numStr,
                };
            }
        }

        // Se non è un numero, restituisci il valore come stringa (es: "A1", "CEV")
        return { text: str, isText: true };
    };

    /**
     * Formatta un numero con separatore migliaia italiano.
     */
    const formatNumber = (num) => {
        return num.toLocaleString('it-IT');
    };

    /**
     * Avvia l'animazione count-up per un set di valori.
     * @param {Array} stats - Array di oggetti { value, label }
     */
    const startCountUp = (stats) => {
        // Cancella animazioni precedenti
        Object.values(animationFrameIds).forEach(id => cancelAnimationFrame(id));
        animationFrameIds = {};

        const result = {};

        stats.forEach((stat, index) => {
            const parsed = parseStatValue(stat.value);

            if (parsed.isText) {
                // Per valori non numerici, mostra direttamente con un fade
                result[index] = '';
                setTimeout(() => {
                    displayValues.value = { ...displayValues.value, [index]: parsed.text };
                }, index * 200 + 300);
                return;
            }

            // Inizializza a prefisso + 0 + suffisso
            result[index] = `${parsed.prefix}0${parsed.suffix}`;

            // Anima con ritardo stagger
            const delay = index * 150;

            setTimeout(() => {
                const startTime = performance.now();

                const animate = (currentTime) => {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    const easedProgress = easing(progress);

                    const currentValue = Math.round(easedProgress * parsed.number);
                    const formattedValue = parsed.hasThousandSep
                        ? formatNumber(currentValue)
                        : String(currentValue);

                    displayValues.value = {
                        ...displayValues.value,
                        [index]: `${parsed.prefix}${formattedValue}${parsed.suffix}`,
                    };

                    if (progress < 1) {
                        animationFrameIds[index] = requestAnimationFrame(animate);
                    }
                };

                animationFrameIds[index] = requestAnimationFrame(animate);
            }, delay);
        });

        displayValues.value = result;
    };

    onUnmounted(() => {
        Object.values(animationFrameIds).forEach(id => cancelAnimationFrame(id));
    });

    return { displayValues, startCountUp };
}
