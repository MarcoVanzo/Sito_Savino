<script setup>
import { computed, ref, watch, onUnmounted } from 'vue';

// Gli attributi passati dal modulo (aria-invalid, aria-describedby, required…)
// vanno sul campo, non sul contenitore: e' il campo che lo screen reader legge
// e che vaiAlPrimoErrore cerca.
defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    id: { type: String, default: '' },
    country: { type: String, default: 'IT' },
});

const emit = defineEmits(['update:modelValue', 'address-selected']);

const inputValue = ref(props.modelValue);
const suggestions = ref([]);
const showDropdown = ref(false);
const isLoading = ref(false);
// Il suggerimento evidenziato con le frecce: il focus resta nel campo e lo
// screen reader lo segue da aria-activedescendant (combobox ARIA 1.2).
const attivo = ref(-1);
let debounceTimer = null;
// Le risposte possono arrivare fuori ordine: vale solo l'ultima richiesta,
// o una lenta sostituirebbe i suggerimenti che si stanno scorrendo.
let ultimaRichiesta = 0;

const idElenco = computed(() => `${props.id || 'indirizzo'}-suggerimenti`);
const idOpzione = (i) => `${idElenco.value}-${i}`;
const aperto = computed(() => showDropdown.value && suggestions.value.length > 0);

watch(() => props.modelValue, (val) => {
    inputValue.value = val;
});

const chiudi = () => {
    showDropdown.value = false;
    attivo.value = -1;
};

const onInput = (e) => {
    const val = e.target.value;
    inputValue.value = val;
    emit('update:modelValue', val);

    clearTimeout(debounceTimer);
    if (val.length < 3) {
        suggestions.value = [];
        chiudi();
        return;
    }

    debounceTimer = setTimeout(() => fetchSuggestions(val), 400);
};

const fetchSuggestions = async (query) => {
    const richiesta = ++ultimaRichiesta;
    isLoading.value = true;
    try {
        const countryCode = props.country?.toLowerCase() || 'it';
        const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=${countryCode}&addressdetails=1&limit=5`;
        const res = await fetch(url, {
            headers: { 'Accept-Language': 'it' },
        });
        const data = await res.json();
        if (richiesta !== ultimaRichiesta) return;
        suggestions.value = data.map(item => ({
            display: item.display_name,
            street: [item.address?.road, item.address?.house_number].filter(Boolean).join(' ') || item.display_name.split(',')[0],
            city: item.address?.city || item.address?.town || item.address?.village || item.address?.municipality || '',
            zip_code: item.address?.postcode || '',
            province: item.address?.county || item.address?.state || '',
        }));
        attivo.value = -1;
        showDropdown.value = suggestions.value.length > 0;
    } catch (error) {
        // Il suggeritore di indirizzi e' un aiuto, non un requisito: se il
        // servizio non risponde l'elenco resta vuoto e il campo si compila a mano.
        if (import.meta.env.DEV) console.debug('suggerimenti indirizzo non disponibili', error);
        if (richiesta !== ultimaRichiesta) return;
        suggestions.value = [];
        chiudi();
    } finally {
        if (richiesta === ultimaRichiesta) isLoading.value = false;
    }
};

const selectSuggestion = (suggestion) => {
    inputValue.value = suggestion.street;
    emit('update:modelValue', suggestion.street);
    emit('address-selected', suggestion);
    chiudi();
    suggestions.value = [];
};

const onKeydown = (e) => {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        if (!suggestions.value.length) return;
        e.preventDefault();
        if (!showDropdown.value) {
            showDropdown.value = true;
        }
        const n = suggestions.value.length;
        const passo = e.key === 'ArrowDown' ? 1 : -1;
        attivo.value = attivo.value === -1
            ? (passo === 1 ? 0 : n - 1)
            : (attivo.value + passo + n) % n;
        document.getElementById(idOpzione(attivo.value))?.scrollIntoView?.({ block: 'nearest' });
    } else if (e.key === 'Enter' && aperto.value && attivo.value >= 0) {
        e.preventDefault();
        selectSuggestion(suggestions.value[attivo.value]);
    } else if (e.key === 'Escape' && aperto.value) {
        // Chiude l'elenco senza perdere i suggerimenti: la freccia giu' lo
        // riapre. L'Esc non deve arrivare a chi chiude il resto della pagina.
        e.preventDefault();
        e.stopPropagation();
        chiudi();
    }
};

const onFocus = () => {
    if (inputValue.value.length >= 3 && suggestions.value.length > 0) {
        showDropdown.value = true;
    }
};

const onBlur = () => {
    // Il clic su un suggerimento non toglie il focus (mousedown.prevent):
    // qui si arriva solo uscendo dal campo.
    chiudi();
};

onUnmounted(() => {
    if (debounceTimer) clearTimeout(debounceTimer);
});
</script>

<template>
    <div class="relative">
        <input
            v-bind="$attrs"
            :id="id"
            :value="inputValue"
            @input="onInput"
            @keydown="onKeydown"
            @blur="onBlur"
            @focus="onFocus"
            type="text"
            role="combobox"
            autocomplete="address-line1"
            aria-autocomplete="list"
            :aria-expanded="aperto ? 'true' : 'false'"
            :aria-controls="idElenco"
            :aria-activedescendant="aperto && attivo >= 0 ? idOpzione(attivo) : undefined"
            class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
            :placeholder="placeholder"
        />
        <!-- Loading indicator -->
        <div v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2" aria-hidden="true">
            <svg class="animate-spin w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
        </div>
        <!-- Quanti suggerimenti ci sono, detto una volta: l'elenco da solo non
             si annuncia finche' non ci si entra con le frecce. -->
        <p class="sr-only" aria-live="polite">
            {{ aperto ? $t('shop_checkout.address_suggestions', { count: suggestions.length }) : '' }}
        </p>
        <!-- Suggestions dropdown -->
        <ul
            v-show="aperto"
            :id="idElenco"
            role="listbox"
            :aria-label="placeholder || undefined"
            class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto"
        >
            <li
                v-for="(s, i) in suggestions"
                :id="idOpzione(i)"
                :key="i"
                role="option"
                :aria-selected="attivo === i ? 'true' : 'false'"
                @mousedown.prevent="selectSuggestion(s)"
                class="w-full px-4 py-3 text-left text-sm cursor-pointer hover:bg-savino-blue/5 border-b border-gray-50 last:border-0 transition-colors"
                :class="{ 'bg-savino-blue/10': attivo === i }"
            >
                <span class="font-medium text-gray-900">{{ s.street }}</span>
                <span class="block text-xs text-gray-600 mt-0.5">{{ [s.zip_code, s.city, s.province].filter(Boolean).join(', ') }}</span>
            </li>
        </ul>
    </div>
</template>
