<script setup>
import { ref, onMounted } from 'vue';

const showBanner = ref(false);

onMounted(() => {
    // Mostra il banner solo se non è già stato accettato
    if (!localStorage.getItem('cookie-consent')) {
        showBanner.value = true;
    }
});

const acceptCookies = () => {
    localStorage.setItem('cookie-consent', 'accepted');
    showBanner.value = false;
};

const rejectCookies = () => {
    localStorage.setItem('cookie-consent', 'rejected');
    showBanner.value = false;
};
</script>

<template>
    <transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-full opacity-0"
    >
        <div v-if="showBanner" class="fixed bottom-0 left-0 right-0 z-[100] p-4">
            <div class="max-w-4xl mx-auto bg-gray-900/95 backdrop-blur-lg text-white rounded-2xl shadow-[0_-10px_40px_rgba(0,0,0,0.3)] border border-white/10 p-6 flex flex-col sm:flex-row items-start sm:items-center gap-4">
                <div class="flex-1">
                    <h4 class="text-sm font-bold mb-1">🍪 Questo sito utilizza i cookie</h4>
                    <p class="text-xs text-gray-400 leading-relaxed">
                        Utilizziamo cookie tecnici necessari al funzionamento del sito. 
                        Continuando la navigazione acconsenti al loro utilizzo. 
                        Per maggiori informazioni consulta la nostra 
                        <a href="/privacy-policy" class="text-savino-gold hover:underline">Privacy Policy</a>.
                    </p>
                </div>
                <div class="flex gap-2 flex-shrink-0">
                    <button 
                        @click="rejectCookies"
                        class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-gray-400 hover:text-white border border-gray-600 hover:border-white/30 rounded-lg transition-all duration-200"
                    >
                        Rifiuta
                    </button>
                    <button 
                        @click="acceptCookies"
                        class="px-4 py-2 text-xs font-bold uppercase tracking-wider bg-savino-gold text-savino-blue hover:bg-yellow-400 rounded-lg transition-all duration-200 shadow-lg"
                    >
                        Accetta
                    </button>
                </div>
            </div>
        </div>
    </transition>
</template>
