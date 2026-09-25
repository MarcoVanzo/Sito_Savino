<script setup>
/**
 * Il pulsante che chiude l'ordine, nel checkout dello shop e in quello delle
 * aste.
 *
 * Sotto l'etichetta porta la dicitura «Ordine con obbligo di pagamento»: l'art.
 * 51 c. 2 del Codice del consumo chiede che il pulsante con cui il consumatore
 * si obbliga a pagare lo dica in modo inequivocabile, e se non lo fa il
 * contratto non lo vincola. «Conferma ordine» o «Paga ora» da soli non
 * bastano. La dicitura sta su una seconda riga più piccola per non appesantire
 * il pulsante, ma sta *sul* pulsante: spostarla accanto, in una nota, la
 * renderebbe di nuovo insufficiente.
 */
defineProps({
    etichetta: { type: String, required: true },
    inCorso: { type: Boolean, default: false },
    disabilitato: { type: Boolean, default: false },
});

defineEmits(['click']);
</script>

<template>
    <button
        type="button"
        :disabled="disabilitato"
        :aria-busy="inCorso ? 'true' : undefined"
        class="w-full mt-8 bg-savino-fucsia text-white font-bold uppercase tracking-wider text-sm px-4 py-3 rounded-lg hover:bg-savino-fucsia/90 transition-all duration-200 disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-3"
        @click="$emit('click')"
    >
        <svg v-if="inCorso" class="animate-spin w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
        <svg v-else class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
        <span class="flex flex-col items-center leading-tight">
            <span>{{ inCorso ? $t('shop_checkout.processing') : etichetta }}</span>
            <span class="mt-1 text-[11px] font-semibold normal-case tracking-normal opacity-75">
                {{ $t('shop_checkout.payment_obligation') }}
            </span>
        </span>
    </button>
</template>
