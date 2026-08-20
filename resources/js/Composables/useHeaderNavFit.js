import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

// Il menu orizzontale convive nell'header con due blocchi a larghezza fissa: i loghi
// a sinistra e le icone (lingua, account, carrello) a destra. Con un breakpoint fisso
// succedeva questo: sotto 1280px spariva tutta la navigazione (su un desktop Windows
// con scaling al 150% il viewport CSS scende sotto quella soglia e compariva
// l'hamburger), e a 1280px esatti le voci non ci stavano comunque e spingevano le
// icone fuori dal viewport. Qui la scelta è misurata: si calcola quanto spazio resta
// davvero e si sceglie il corpo più grande che ci sta, ripiegando sul drawer solo
// quando nemmeno il più piccolo entra.

// Larghezza del blocco lingua + account + carrello, margini inclusi. È una costante e
// non una misura del DOM perché quel blocco è nascosto proprio quando la navigazione
// non ci sta: misurarlo farebbe oscillare il calcolo fra i due stati.
const ACTIONS_WIDTH = 168;

// Sotto questa larghezza il drawer resta la scelta giusta anche se il menu ci stesse:
// è la fascia dei tablet, dove si naviga con il dito.
const MIN_DESKTOP_WIDTH = 1024;

// Corpi ammessi per le voci, dal più leggibile in giù. Sotto i 9px l'etichetta in
// maiuscolo non è più leggibile: meglio il drawer.
const FONT_STEPS = [13, 12, 11, 10, 9];

const REFERENCE_FONT_SIZE = 13;
// Spazio di rispetto fra il logo che sborda e la prima voce del menu.
const SAFETY_MARGIN = 24;

// Il padding orizzontale delle voci scende insieme al corpo del testo, altrimenti a
// 9px lo spazio fra le voci pesa più delle parole.
export function navItemPadding(fontSize) {
    if (fontSize >= 13) return 10;
    if (fontSize >= 11) return 6;
    return 3;
}

// I separatori "|" costano ~7px l'uno: si tengono solo quando c'è spazio abbondante.
export function navShowsSeparators(fontSize) {
    return fontSize >= 12;
}

function separatorWidth(fontSize) {
    return navShowsSeparators(fontSize) ? 7 : 0;
}

/**
 * Corpo più grande con cui le voci stanno nello spazio disponibile, `null` se non
 * ne entra nessuno (chi chiama mostra il drawer).
 */
export function pickNavFontSize(textWidthAtReference, itemCount, available) {
    if (itemCount === 0 || textWidthAtReference <= 0) return null;

    for (const fontSize of FONT_STEPS) {
        const text = (textWidthAtReference * fontSize) / REFERENCE_FONT_SIZE;
        const padding = itemCount * navItemPadding(fontSize) * 2;
        const separators = Math.max(0, itemCount - 1) * separatorWidth(fontSize);

        if (text + padding + separators <= available) return fontSize;
    }

    return null;
}

// Le etichette si misurano fuori schermo invece che sulle voci vere: quando la
// navigazione è nascosta (display:none) queste non hanno larghezza, e la misura
// dovrebbe funzionare anche in quello stato per poterne uscire.
function measureLabels(labels, fontFamily) {
    if (typeof document === 'undefined') return 0;

    const probe = document.createElement('div');
    probe.setAttribute('aria-hidden', 'true');
    probe.style.cssText = 'position:fixed;left:-9999px;top:0;visibility:hidden;white-space:nowrap;';
    probe.style.fontFamily = fontFamily;
    probe.style.fontWeight = '900';
    probe.style.fontSize = `${REFERENCE_FONT_SIZE}px`;
    probe.style.letterSpacing = '0.025em';
    probe.style.textTransform = 'uppercase';

    labels.forEach((label) => {
        const span = document.createElement('span');
        span.textContent = label;
        span.style.display = 'inline-block';
        probe.appendChild(span);
    });

    document.body.appendChild(probe);
    const total = [...probe.children].reduce((sum, span) => sum + span.getBoundingClientRect().width, 0);
    probe.remove();

    return total;
}

/**
 * @param {import('vue').Ref<string[]>} labels     etichette delle voci di primo livello
 * @param {import('vue').Ref<HTMLElement|null>} logoRef  blocco loghi (per larghezza riservata e riga header)
 */
export function useHeaderNavFit(labels, logoRef) {
    const hasWindow = typeof window !== 'undefined';

    // Primo render (il sito è solo client-side): stima dal viewport, poi si corregge
    // in onMounted prima che l'utente veda l'header.
    const desktopNav = ref(hasWindow ? window.innerWidth >= 1280 : true);
    const navFontSize = ref(REFERENCE_FONT_SIZE);

    let textWidth = 0;

    function remeasure() {
        const fontFamily = logoRef.value
            ? getComputedStyle(logoRef.value).fontFamily
            : 'Montserrat, sans-serif';

        textWidth = measureLabels(labels.value, fontFamily);
    }

    function update() {
        if (!hasWindow) return;

        if (window.innerWidth < MIN_DESKTOP_WIDTH) {
            desktopNav.value = false;
            return;
        }

        const row = logoRef.value?.parentElement;
        if (!row || textWidth <= 0) {
            // Nessuna misura utile (etichette vuote, jsdom, font non ancora pronti):
            // si ricade sul comportamento storico.
            desktopNav.value = window.innerWidth >= 1280;
            return;
        }

        // I loghi sono in posizione assoluta e sbordano dal box che li riserva: a
        // contare è il bordo destro di quello che si vede, non la larghezza riservata,
        // altrimenti il logo finisce sopra le prime voci del menu.
        const logoRect = logoRef.value.getBoundingClientRect();
        const painted = logoRef.value.firstElementChild?.getBoundingClientRect();
        const logoRight = Math.max(logoRect.right, painted?.right ?? 0);

        const available =
            row.getBoundingClientRect().right - logoRight - ACTIONS_WIDTH - SAFETY_MARGIN;

        const fontSize = pickNavFontSize(textWidth, labels.value.length, available);

        desktopNav.value = fontSize !== null;
        if (fontSize !== null) navFontSize.value = fontSize;
    }

    function remeasureAndUpdate() {
        remeasure();
        update();
    }

    let resizeTimer = null;
    function onResize() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(update, 120);
    }

    onMounted(() => {
        remeasureAndUpdate();
        window.addEventListener('resize', onResize);
        // Montserrat arriva dopo il primo layout e allarga le etichette: senza questo
        // giro il menu resterebbe dimensionato sul font di fallback.
        document.fonts?.ready?.then(remeasureAndUpdate).catch(() => {});
    });

    onBeforeUnmount(() => {
        window.removeEventListener('resize', onResize);
        clearTimeout(resizeTimer);
    });

    // Le etichette cambiano al cambio lingua.
    watch(labels, remeasureAndUpdate, { deep: true });

    return { desktopNav, navFontSize };
}
