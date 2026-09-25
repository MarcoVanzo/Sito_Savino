<script setup>
/**
 * La casella che il cliente spunta prima di ordinare, nel checkout dello shop
 * e in quello delle aste.
 *
 * Porta a tre pagine, non a una: le Condizioni di vendita e l'informativa sul
 * recesso vanno date prima che il cliente sia vincolato (art. 49 del Codice
 * del consumo), la Privacy Policy si legge e non si "accetta". Fino al 25
 * settembre 2026 la casella diceva «Accetto la Privacy Policy» e basta, e lo
 * shop vendeva senza nessuna condizione scritta.
 *
 * Il campo resta `privacy_accepted`: è quello che validano entrambi i
 * controller, e l'ordine registra la versione delle condizioni accettate
 * (`orders.condizioni_versione`).
 */
import AvvisoGaranziaLegale from '@/Components/Shop/AvvisoGaranziaLegale.vue';

const accettato = defineModel({ type: Boolean, default: false });

defineProps({
    errore: { type: String, default: null },
});
</script>

<template>
    <div>
        <label class="flex items-start gap-3 cursor-pointer">
            <input
                v-model="accettato"
                type="checkbox"
                class="mt-1 w-4 h-4 text-savino-blue border-gray-300 rounded focus:ring-savino-blue/20"
            />
            <span class="text-sm text-gray-600 leading-relaxed">
                {{ $t('shop_checkout.terms_accept_1') }}
                <a :href="route('pages.show', 'condizioni-di-vendita')" target="_blank" rel="noopener noreferrer" class="text-savino-blue underline hover:text-savino-blue/80">{{ $t('shop_checkout.terms_link') }}</a>
                {{ $t('shop_checkout.terms_accept_2') }}
                <a :href="route('pages.show', 'diritto-di-recesso')" target="_blank" rel="noopener noreferrer" class="text-savino-blue underline hover:text-savino-blue/80">{{ $t('shop_checkout.withdrawal_link') }}</a>{{ $t('shop_checkout.terms_accept_3') }}
                <a :href="route('pages.show', 'privacy-policy')" target="_blank" rel="noopener noreferrer" class="text-savino-blue underline hover:text-savino-blue/80">{{ $t('shop.accept_privacy_2') }}</a>.
            </span>
        </label>
        <p v-if="errore" class="mt-1 text-sm text-red-500">{{ errore }}</p>
        <!-- L'avviso UE sulla garanzia legale va mostrato prima dell'acquisto:
             il checkout e' uno dei punti indicati dalle linee guida. -->
        <div class="mt-3 text-xs text-gray-600"><AvvisoGaranziaLegale /></div>
    </div>
</template>
