/**
 * Lo stato della diretta con sponsor mentre la diretta è aperta.
 *
 * Rilegge il JSON di stato ogni dieci secondi, solo con la scheda in vista,
 * e lo sostituisce solo se la versione sale. Fra una lettura e l'altra il
 * conto lo fa l'orologio (una volta al secondo): il ciclo automatico non ha
 * bisogno di richieste. L'ora del server si chiede una volta sola, per
 * correggere l'orologio del dispositivo.
 *
 * Se il JSON non si legge non succede nulla: niente sponsor, la diretta va.
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { contenutoInOnda, scartoDellOrologio, statoPiuRecente } from '@/Support/cicloDellaCornice.js';

export const OGNI_QUANTO_RILEGGERE = 10_000;

export function useStatoDellaDiretta({ statoUrl, oraUrl, attivo }) {
    const stato = ref(null);
    const scarto = ref(0);
    const adesso = ref(Date.now());
    let lettura = null;
    let orologio = null;
    let oraChiesta = false;

    const rileggi = async () => {
        if (!statoUrl || document.visibilityState === 'hidden') {
            return;
        }

        try {
            const risposta = await fetch(statoUrl, { credentials: 'omit' });

            if (risposta.ok) {
                stato.value = statoPiuRecente(stato.value, await risposta.json());
            }
        } catch {
            // Rete o JSON rotto: si tiene quello che c'era.
        }
    };

    const chiediLOra = async () => {
        if (oraChiesta || !oraUrl) {
            return;
        }

        oraChiesta = true;

        try {
            const partenza = Date.now();
            const risposta = await fetch(oraUrl, { headers: { Accept: 'application/json' } });
            const arrivo = Date.now();

            if (risposta.ok) {
                const { ora } = await risposta.json();
                scarto.value = scartoDellOrologio(Number(ora), partenza, arrivo);
            }
        } catch {
            // Senza, vale l'orologio del dispositivo.
        }
    };

    const allaVisibilita = () => {
        if (document.visibilityState === 'visible') {
            rileggi();
        }
    };

    const ferma = () => {
        clearInterval(lettura);
        clearInterval(orologio);
        lettura = null;
        orologio = null;
        document.removeEventListener('visibilitychange', allaVisibilita);
    };

    const avvia = () => {
        if (!statoUrl || lettura !== null) {
            return;
        }

        chiediLOra();
        rileggi();
        lettura = setInterval(rileggi, OGNI_QUANTO_RILEGGERE);
        orologio = setInterval(() => {
            adesso.value = Date.now();
        }, 1000);
        document.addEventListener('visibilitychange', allaVisibilita);
    };

    watch(attivo, (si) => (si ? avvia() : ferma()), { immediate: true });
    onBeforeUnmount(ferma);

    const contenuto = computed(() => contenutoInOnda(stato.value, adesso.value + scarto.value));
    // Quello che la regia sta mandando (per i contatori: ciclo o spot a comando).
    const inPausa = computed(() => stato.value?.stato === 'pausa' && Date.parse(stato.value.fino_alle) > adesso.value + scarto.value);

    return { stato, contenuto, inPausa, rileggi };
}
