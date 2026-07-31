<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import { ref, computed } from 'vue';
import { useForm, usePage, Link } from '@inertiajs/vue3';

const $t = useTranslations();

const props = defineProps({
    variant: {
        type: String,
        default: 'hero',
        validator: (v) => ['hero', 'footer'].includes(v),
    },
});

const form = useForm({
    email: '',
    first_name: '',
    honeypot: '',
    privacy_accepted: false,
});

const submitted = ref(false);

const inertiaPage = usePage();
const flashSuccess = computed(() => inertiaPage.props.flash?.success);
const flashInfo = computed(() => inertiaPage.props.flash?.newsletter_info);

const isHero = computed(() => props.variant === 'hero');

const hasError = ref(false);

function handleSubmit() {
    hasError.value = false;
    form.post(route('newsletter.subscribe'), {
        preserveScroll: true,
        onSuccess: () => {
            submitted.value = true;
            form.reset();
        },
        onError: () => {
            hasError.value = true;
        },
    });
}
</script>

<template>
    <!-- HERO VARIANT -->
    <section
        v-if="isHero"
        class="relative py-20 sm:py-28 overflow-hidden"
        style="background: linear-gradient(135deg, #003063 0%, #001a38 40%, #0B1521 100%);"
    >
        <!-- Subtle pattern overlay -->
        <div class="absolute inset-0 opacity-[0.04]" style="background-image: repeating-linear-gradient(135deg, #C9A84C 0px, #C9A84C 1px, transparent 1px, transparent 50px);"></div>
        <!-- Radial glow -->
        <div class="absolute inset-0" style="background: radial-gradient(ellipse at 50% 50%, rgba(201,168,76,0.08) 0%, transparent 60%);"></div>

        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center" data-reveal>
            <!-- Envelope icon -->
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-savino-gold/10 border border-savino-gold/20 mb-6">
                <svg class="w-7 h-7 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                </svg>
            </div>

            <h2 class="text-3xl md:text-5xl font-black text-white uppercase tracking-tighter mb-3">
                {{ $t('newsletter.title') }}
            </h2>
            <p class="text-white/60 text-lg max-w-xl mx-auto mb-10">
                {{ $t('newsletter.subtitle') }}
            </p>

            <!-- Success State -->
            <div v-if="submitted && (flashSuccess || flashInfo)" class="newsletter-success-anim">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4" :class="flashInfo ? 'bg-blue-500/20' : 'bg-green-500/20'">
                    <svg v-if="!flashInfo" class="w-8 h-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <svg v-else class="w-8 h-8 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <p class="text-white text-lg font-bold">
                    {{ flashInfo || flashSuccess }}
                </p>
            </div>

            <!-- Form -->
            <form v-else @submit.prevent="handleSubmit" class="max-w-2xl mx-auto">
                <!-- Honeypot -->
                <div class="hidden" aria-hidden="true">
                    <input type="text" v-model="form.honeypot" name="honeypot" autocomplete="off" tabindex="-1" aria-label="Lascia vuoto" />
                </div>

                <div class="flex flex-col sm:flex-row gap-3 mb-4">
                    <input
                        v-model="form.email"
                        type="email"
                        :placeholder="$t('newsletter.placeholder_email')"
                        :aria-label="$t('newsletter.placeholder_email')"
                        required
                        class="flex-1 bg-white/10 border border-white/20 text-white placeholder-white/40 rounded-lg px-5 py-3.5 focus:border-savino-gold focus:ring-2 focus:ring-savino-gold/30 outline-none transition-all text-sm"
                        :class="{ 'border-red-400': form.errors.email }"
                    />
                    <input
                        v-model="form.first_name"
                        type="text"
                        :placeholder="$t('newsletter.placeholder_name')"
                        :aria-label="$t('newsletter.placeholder_name')"
                        class="sm:w-48 bg-white/10 border border-white/20 text-white placeholder-white/40 rounded-lg px-5 py-3.5 focus:border-savino-gold focus:ring-2 focus:ring-savino-gold/30 outline-none transition-all text-sm"
                    />
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="newsletter-cta-btn bg-savino-gold text-gray-900 font-bold text-sm uppercase tracking-wider px-8 py-3.5 rounded-lg hover:bg-white hover:text-savino-blue transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 whitespace-nowrap"
                    >
                        <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ $t('newsletter.subscribe') }}
                    </button>
                </div>

                <!-- Validation errors -->
                <p v-if="form.errors.email" class="text-red-400 text-xs text-left mb-3">{{ form.errors.email }}</p>
                <p v-if="form.errors.privacy_accepted" class="text-red-400 text-xs text-left mb-3">{{ form.errors.privacy_accepted }}</p>
                <p v-if="hasError && !form.errors.email && !form.errors.privacy_accepted" class="text-red-400 text-xs text-left mb-3">{{ $t('newsletter.error') }}</p>

                <!-- Privacy checkbox -->
                <label class="flex items-start gap-2 text-left cursor-pointer group">
                    <input
                        v-model="form.privacy_accepted"
                        type="checkbox"
                        class="mt-0.5 w-4 h-4 rounded border-white/30 bg-white/10 text-savino-gold focus:ring-savino-gold/30 focus:ring-offset-0"
                    />
                    <span class="text-white/50 text-xs leading-relaxed group-hover:text-white/70 transition-colors">
                        {{ $t('newsletter.privacy_consent') }}
                        <Link href="/privacy-policy" class="text-savino-gold hover:text-white underline underline-offset-2 transition-colors">
                            {{ $t('newsletter.privacy_link') }}
                        </Link>
                    </span>
                </label>
            </form>
        </div>
    </section>

    <!-- FOOTER VARIANT -->
    <div v-else>
        <h4 class="text-white text-xs font-bold uppercase tracking-[0.2em] mb-4">
            {{ $t('newsletter.footer_title') }}
        </h4>

        <!-- Success State -->
        <div v-if="submitted && (flashSuccess || flashInfo)">
            <div class="flex items-center gap-2">
                <svg v-if="!flashInfo" class="w-4 h-4 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <svg v-else class="w-4 h-4 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-gray-400 text-xs">{{ flashInfo || flashSuccess }}</p>
            </div>
        </div>

        <!-- Form -->
        <form v-else @submit.prevent="handleSubmit">
            <!-- Honeypot -->
            <div class="hidden" aria-hidden="true">
                <input type="text" v-model="form.honeypot" name="honeypot" autocomplete="off" tabindex="-1" aria-label="Lascia vuoto" />
            </div>

            <div class="flex gap-2 mb-3">
                <input
                    v-model="form.email"
                    type="email"
                    :placeholder="$t('newsletter.placeholder_email')"
                    :aria-label="$t('newsletter.placeholder_email')"
                    required
                    class="flex-1 min-w-0 bg-white/5 border border-white/10 text-white placeholder-white/30 rounded-lg px-3 py-2.5 text-sm focus:border-savino-gold focus:ring-1 focus:ring-savino-gold/30 outline-none transition-all"
                    :class="{ 'border-red-400': form.errors.email }"
                />
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="bg-savino-gold text-gray-900 font-bold text-xs uppercase tracking-wider px-4 py-2.5 rounded-lg hover:bg-white transition-colors duration-300 disabled:opacity-50 flex-shrink-0 flex items-center gap-1.5"
                >
                    <svg v-if="form.processing" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </button>
            </div>

            <p v-if="form.errors.email" class="text-red-400 text-[10px] mb-2">{{ form.errors.email }}</p>
            <p v-if="form.errors.privacy_accepted" class="text-red-400 text-[10px] mb-2">{{ form.errors.privacy_accepted }}</p>
            <p v-if="hasError && !form.errors.email && !form.errors.privacy_accepted" class="text-red-400 text-[10px] mb-2">{{ $t('newsletter.error') }}</p>

            <label class="flex items-start gap-2 cursor-pointer group">
                <input
                    v-model="form.privacy_accepted"
                    type="checkbox"
                    class="mt-0.5 w-3.5 h-3.5 rounded border-white/20 bg-white/5 text-savino-gold focus:ring-savino-gold/30 focus:ring-offset-0"
                />
                <span class="text-white/40 text-[10px] leading-relaxed group-hover:text-white/60 transition-colors">
                    {{ $t('newsletter.privacy_consent') }}
                    <Link href="/privacy-policy" class="text-savino-gold hover:text-white underline underline-offset-2 transition-colors">
                        {{ $t('newsletter.privacy_link') }}
                    </Link>
                </span>
            </label>
        </form>
    </div>
</template>

<style scoped>
.newsletter-cta-btn {
    position: relative;
    animation: newsletterGlow 2.5s ease-in-out infinite;
}

@keyframes newsletterGlow {
    0%, 100% { box-shadow: 0 0 15px rgba(201, 168, 76, 0.2), 0 0 30px rgba(201, 168, 76, 0.1); }
    50% { box-shadow: 0 0 25px rgba(201, 168, 76, 0.4), 0 0 50px rgba(201, 168, 76, 0.2); }
}

.newsletter-success-anim {
    animation: fadeInUp 0.5s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(15px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .newsletter-cta-btn {
        animation: none;
    }
    .newsletter-success-anim {
        animation: none;
    }
}
</style>
