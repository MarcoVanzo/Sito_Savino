import { ref, onUnmounted } from 'vue';

/**
 * Composable per animazioni di reveal on scroll con stagger.
 * Usa IntersectionObserver per attivare le animazioni quando gli elementi
 * entrano nel viewport.
 *
 * @param {Object} options
 * @param {number} options.threshold - Soglia di visibilità (0-1), default 0.15
 * @param {number} options.staggerDelay - Ritardo tra elementi in ms, default 120
 * @param {string} options.rootMargin - Margine root, default '0px 0px -50px 0px'
 */
export function useIntersectionReveal(options = {}) {
    const {
        threshold = 0.15,
        staggerDelay = 120,
        rootMargin = '0px 0px -50px 0px',
    } = options;

    const observer = ref(null);

    const setupReveal = (containerEl) => {
        if (!containerEl || typeof IntersectionObserver === 'undefined') return;

        const elements = containerEl.querySelectorAll('[data-reveal]');
        if (!elements.length) return;

        // Imposta stato iniziale
        elements.forEach((el) => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'none'; // Nessuna transizione iniziale
        });

        observer.value = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        const container = entry.target;
                        const revealEls = container.querySelectorAll('[data-reveal]');

                        revealEls.forEach((el, index) => {
                            const delay = index * staggerDelay;
                            setTimeout(() => {
                                el.style.transition = `opacity 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) ${delay}ms, transform 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) ${delay}ms`;
                                el.style.opacity = '1';
                                el.style.transform = 'translateY(0)';
                            }, 50); // Piccolo ritardo per assicurarsi che la transition sia applicata
                        });

                        observer.value?.unobserve(container);
                    }
                });
            },
            { threshold, rootMargin }
        );

        // Osserva il container, non i singoli elementi
        observer.value.observe(containerEl);
    };

    onUnmounted(() => {
        observer.value?.disconnect();
    });

    return { setupReveal };
}

/**
 * Versione semplificata: singolo elemento con reveal.
 */
export function useSingleReveal(options = {}) {
    const {
        threshold = 0.2,
        rootMargin = '0px 0px -30px 0px',
    } = options;

    const isVisible = ref(false);
    let observer = null;

    const observe = (el) => {
        if (!el || typeof IntersectionObserver === 'undefined') {
            isVisible.value = true;
            return;
        }

        observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting) {
                    isVisible.value = true;
                    observer?.disconnect();
                }
            },
            { threshold, rootMargin }
        );

        observer.observe(el);
    };

    onUnmounted(() => {
        observer?.disconnect();
    });

    return { isVisible, observe };
}
