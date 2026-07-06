<script setup>
import { ref, watch } from 'vue';

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
let debounceTimer = null;

watch(() => props.modelValue, (val) => {
    inputValue.value = val;
});

const onInput = (e) => {
    const val = e.target.value;
    inputValue.value = val;
    emit('update:modelValue', val);

    clearTimeout(debounceTimer);
    if (val.length < 3) {
        suggestions.value = [];
        showDropdown.value = false;
        return;
    }

    debounceTimer = setTimeout(() => fetchSuggestions(val), 400);
};

const fetchSuggestions = async (query) => {
    isLoading.value = true;
    try {
        const countryCode = props.country?.toLowerCase() || 'it';
        const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=${countryCode}&addressdetails=1&limit=5`;
        const res = await fetch(url, {
            headers: { 'Accept-Language': 'it' },
        });
        const data = await res.json();
        suggestions.value = data.map(item => ({
            display: item.display_name,
            street: [item.address?.road, item.address?.house_number].filter(Boolean).join(' ') || item.display_name.split(',')[0],
            city: item.address?.city || item.address?.town || item.address?.village || item.address?.municipality || '',
            zip_code: item.address?.postcode || '',
            province: item.address?.county || item.address?.state || '',
        }));
        showDropdown.value = suggestions.value.length > 0;
    } catch (e) {
        suggestions.value = [];
        showDropdown.value = false;
    } finally {
        isLoading.value = false;
    }
};

const selectSuggestion = (suggestion) => {
    inputValue.value = suggestion.street;
    emit('update:modelValue', suggestion.street);
    emit('address-selected', suggestion);
    showDropdown.value = false;
    suggestions.value = [];
};

const onBlur = () => {
    // Delay to allow click on suggestion
    setTimeout(() => {
        showDropdown.value = false;
    }, 200);
};
</script>

<template>
    <div class="relative">
        <input
            :id="id"
            :value="inputValue"
            @input="onInput"
            @blur="onBlur"
            @focus="inputValue.length >= 3 && suggestions.length > 0 && (showDropdown = true)"
            type="text"
            autocomplete="off"
            class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-colors text-sm"
            :placeholder="placeholder"
        />
        <!-- Loading indicator -->
        <div v-if="isLoading" class="absolute right-3 top-1/2 -translate-y-1/2">
            <svg class="animate-spin w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
        </div>
        <!-- Suggestions dropdown -->
        <div
            v-if="showDropdown && suggestions.length > 0"
            class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto"
        >
            <button
                v-for="(s, i) in suggestions"
                :key="i"
                type="button"
                @mousedown.prevent="selectSuggestion(s)"
                class="w-full px-4 py-3 text-left text-sm hover:bg-savino-blue/5 border-b border-gray-50 last:border-0 transition-colors"
            >
                <span class="font-medium text-gray-900">{{ s.street }}</span>
                <span class="block text-xs text-gray-500 mt-0.5">{{ [s.zip_code, s.city, s.province].filter(Boolean).join(', ') }}</span>
            </button>
        </div>
    </div>
</template>
