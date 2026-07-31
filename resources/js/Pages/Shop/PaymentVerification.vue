<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useOgMeta } from '@/Composables/useOgMeta';
import { ref } from 'vue';

const $t = useTranslations();
const page = usePage();

defineProps({
    hasVerifiedPayment: { type: Boolean, default: false },
});

const ogMeta = useOgMeta({
    title: $t('payment_verification.og_title'),
    description: $t('payment_verification.og_description'),
});

const isLoading = ref(false);

const startVerification = () => {
    isLoading.value = true;
    router.post(route('account.payment-verification.store'));
};
</script>

<template>
    <Head>
        <title>{{ ogMeta.title }}</title>
        <meta name="description" :content="ogMeta.description" />
    </Head>
    <PublicLayout>
        <section class="bg-gray-950 min-h-[80vh] flex items-center justify-center py-20">
            <div class="max-w-lg mx-auto px-4 sm:px-6">
                <div class="bg-gray-900 rounded-2xl p-8 border border-gray-800 text-center">
                    <!-- Icon -->
                    <div class="w-20 h-20 mx-auto mb-6 bg-savino-gold/10 rounded-full flex items-center justify-center">
                        <svg class="w-10 h-10 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>

                    <h1 class="text-2xl font-black text-white uppercase tracking-tight mb-3">
                        {{ $t('payment_verification.title') }}
                    </h1>

                    <p class="text-gray-400 text-sm leading-relaxed mb-6">
                        {{ $t('payment_verification.intro') }}
                        <strong class="text-white">{{ $t('payment_verification.no_charge_emphasis') }}</strong>
                    </p>

                    <!-- Info boxes -->
                    <div class="space-y-3 mb-8 text-left">
                        <div class="flex items-start gap-3 bg-gray-800/50 rounded-lg p-3">
                            <svg class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <span class="text-gray-300 text-sm">{{ $t('payment_verification.point_no_charge') }}</span>
                        </div>
                        <div class="flex items-start gap-3 bg-gray-800/50 rounded-lg p-3">
                            <svg class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                            </svg>
                            <span class="text-gray-300 text-sm">{{ $t('payment_verification.point_secure') }}</span>
                        </div>
                        <div class="flex items-start gap-3 bg-gray-800/50 rounded-lg p-3">
                            <svg class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                            </svg>
                            <span class="text-gray-300 text-sm">{{ $t('payment_verification.point_once') }}</span>
                        </div>
                    </div>

                    <!-- CTA Button -->
                    <button type="button"
                        @click="startVerification"
                        :disabled="isLoading"
                        class="w-full bg-savino-gold text-gray-900 font-black text-sm uppercase tracking-wider py-4 rounded-lg hover:bg-savino-gold/90 transition-all duration-300 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg v-if="isLoading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                        </svg>
                        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                        {{ $t('payment_verification.cta') }}
                    </button>

                    <!-- Flash messages -->
                    <div v-if="page.props.flash?.error" class="mt-4 bg-red-900/30 border border-red-500/30 rounded-lg p-3 text-red-300 text-sm">
                        {{ page.props.flash.error }}
                    </div>
                    <div v-if="page.props.flash?.warning" class="mt-4 bg-amber-900/30 border border-amber-500/30 rounded-lg p-3 text-amber-300 text-sm">
                        {{ page.props.flash.warning }}
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
