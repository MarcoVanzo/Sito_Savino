import { ref, onUnmounted } from 'vue';

/**
 * Composable per effetto tilt 3D sulle card al passaggio del mouse.
 * Crea un effetto di inclinazione prospettica che segue il cursore.
 *
 * @param {Object} options
 * @param {number} options.maxTilt - Angolo massimo di inclinazione in gradi, default 8
 * @param {number} options.perspective - Distanza prospettica in px, default 1000
 * @param {number} options.glareOpacity - Opacità massima del riflesso, default 0.15
 * @param {number} options.transitionDuration - Durata transizione in ms, default 400
 */
export function useTiltEffect(options = {}) {
    const {
        maxTilt = 8,
        perspective = 1000,
        glareOpacity = 0.15,
        transitionDuration = 400,
    } = options;

    const tiltStyle = ref({});
    const glareStyle = ref({});
    let rafId = null;

    /**
     * Applica l'effetto tilt a un elemento.
     * Restituisce i listener da aggiungere all'elemento.
     */
    const createTiltHandlers = (index) => {
        const key = `tilt-${index}`;

        return {
            onMousemove: (e) => {
                if (rafId) cancelAnimationFrame(rafId);

                rafId = requestAnimationFrame(() => {
                    const rect = e.currentTarget.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;

                    // Calcola angolo di inclinazione normalizzato (-1 to 1)
                    const rotateX = ((y - centerY) / centerY) * -maxTilt;
                    const rotateY = ((x - centerX) / centerX) * maxTilt;

                    // Posizione del riflesso
                    const glareX = (x / rect.width) * 100;
                    const glareY = (y / rect.height) * 100;

                    tiltStyle.value = {
                        ...tiltStyle.value,
                        [key]: {
                            transform: `perspective(${perspective}px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.02, 1.02, 1.02)`,
                            transition: `transform ${transitionDuration / 4}ms cubic-bezier(0.03, 0.98, 0.52, 0.99)`,
                        },
                    };

                    glareStyle.value = {
                        ...glareStyle.value,
                        [key]: {
                            background: `radial-gradient(circle at ${glareX}% ${glareY}%, rgba(255,255,255,${glareOpacity}), transparent 60%)`,
                            opacity: 1,
                        },
                    };
                });
            },

            onMouseleave: () => {
                if (rafId) cancelAnimationFrame(rafId);

                tiltStyle.value = {
                    ...tiltStyle.value,
                    [key]: {
                        transform: `perspective(${perspective}px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)`,
                        transition: `transform ${transitionDuration}ms cubic-bezier(0.03, 0.98, 0.52, 0.99)`,
                    },
                };

                glareStyle.value = {
                    ...glareStyle.value,
                    [key]: {
                        opacity: 0,
                        transition: `opacity ${transitionDuration}ms ease`,
                    },
                };
            },
        };
    };

    const getTiltStyle = (index) => tiltStyle.value[`tilt-${index}`] || {};
    const getGlareStyle = (index) => glareStyle.value[`tilt-${index}`] || {};

    onUnmounted(() => {
        if (rafId) cancelAnimationFrame(rafId);
    });

    return { createTiltHandlers, getTiltStyle, getGlareStyle };
}
