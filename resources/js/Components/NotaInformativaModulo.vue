<script setup>
/**
 * La riga d'informativa sotto i moduli che raccolgono dati per rispondere a
 * una richiesta: Contatti e Accrediti stampa.
 *
 * Non è una casella da spuntare: il trattamento si basa sulla richiesta stessa
 * (art. 6 §1 lett. b del GDPR), e un consenso chiesto dove non serve sarebbe
 * un consenso finto. Serve invece che l'informativa sia a portata di mano nel
 * momento in cui i dati si scrivono (art. 13): fino al 25 settembre 2026 i due
 * moduli li raccoglievano senza nessun rimando.
 */
defineProps({
    allineamento: { type: String, default: 'text-left' },
});
</script>

<template>
    <p :class="['text-xs text-gray-500 leading-relaxed', allineamento]">
        {{ $t('common.form_privacy_notice') }}
        <a :href="route('pages.show', 'privacy-policy')" target="_blank" rel="noopener noreferrer" class="text-savino-blue underline hover:text-savino-fucsia">{{ $t('common.form_privacy_link') }}</a>.
    </p>
</template>
