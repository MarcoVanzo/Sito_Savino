<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import { ref, computed, nextTick } from 'vue';
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

// Due moduli possono stare nella stessa pagina (home e footer): gli id dei
// messaggi d'errore portano la variante.
const idErrore = (campo) => `newsletter-${props.variant}-errore-${campo}`;

// Dopo l'invio il modulo sparisce e con lui il pulsante che aveva il focus:
// il focus va al messaggio che lo sostituisce.
const esito = ref(null);

function handleSubmit() {
    hasError.value = false;
    form.post(route('newsletter.subscribe'), {
        preserveScroll: true,
        onSuccess: () => {
            submitted.value = true;
            form.reset();
            nextTick(() => esito.value?.focus());
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
        <div class="absolute inset-0 opacity-[0.04]" style="background-image: repeating-linear-gradient(135deg, #F8269C 0px, #F8269C 1px, transparent 1px, transparent 50px);"></div>
        <!-- Radial glow -->
        <div class="absolute inset-0" style="background: radial-gradient(ellipse at 50% 50%, rgba(201,168,76,0.08) 0%, transparent 60%);"></div>

        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center" data-reveal>
            <!-- Envelope icon -->
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-savino-fucsia/10 border border-savino-fucsia/20 mb-6">
                <svg class="w-7 h-7 text-savino-fucsia-chiaro" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                </svg>
            </div>

            <h2 class="text-3xl md:text-5xl font-black text-white uppercase tracking-tighter mb-3">
                {{ $t('newsletter.title') }}
            </h2>
            <p class="text-white/60 text-lg max-w-xl mx-auto mb-10">
                {{ $t('newsletter.subtitle') }}
            </p>

            <!-- Esito. L'iscrizione non e' ancora fatta finche' non si clicca il
                 link nell'email (doppio opt-in): niente spunta verde, una busta
                 e l'invito a guardare anche nello spam. Il "ci sei quasi" lo
                 dice gia' il toast di FlashMessages (flash.success): qui non si
                 ripete. `newsletter_info` (gia' iscritto) il toast non lo
                 mostra, e resta qui. -->
            <div
                v-if="submitted && (flashSuccess || flashInfo)"
                ref="esito"
                tabindex="-1"
                role="status"
                class="newsletter-success-anim focus:outline-none"
            >
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4 bg-white/10" aria-hidden="true">
                    <svg v-if="!flashInfo" class="w-8 h-8 text-savino-fucsia-chiaro" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                    </svg>
                    <svg v-else class="w-8 h-8 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <template v-if="flashInfo">
                    <p class="text-white text-lg font-bold">{{ flashInfo }}</p>
                </template>
                <template v-else>
                    <p class="text-white text-lg font-bold">{{ $t('newsletter.check_inbox_title') }}</p>
                    <p class="text-white/80 text-sm mt-2 max-w-md mx-auto">{{ $t('newsletter.check_inbox_text') }}</p>
                </template>
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
                        autocomplete="email"
                        :aria-invalid="form.errors.email ? 'true' : undefined"
                        :aria-describedby="form.errors.email ? idErrore('email') : undefined"
                        class="flex-1 bg-white/10 border border-white/20 text-white placeholder-white/40 rounded-lg px-5 py-3.5 focus:border-savino-fucsia focus:ring-2 focus:ring-savino-fucsia/30 outline-none transition-all text-sm"
                        :class="{ 'border-red-400': form.errors.email }"
                    />
                    <input
                        v-model="form.first_name"
                        type="text"
                        :placeholder="$t('newsletter.placeholder_name')"
                        :aria-label="$t('newsletter.placeholder_name')"
                        class="sm:w-48 bg-white/10 border border-white/20 text-white placeholder-white/40 rounded-lg px-5 py-3.5 focus:border-savino-fucsia focus:ring-2 focus:ring-savino-fucsia/30 outline-none transition-all text-sm"
                    />
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="newsletter-cta-btn bg-savino-fucsia text-white font-bold text-sm uppercase tracking-wider px-8 py-3.5 rounded-lg hover:bg-white hover:text-savino-blue transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 whitespace-nowrap"
                    >
                        <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ $t('newsletter.subscribe') }}
                    </button>
                </div>

                <!-- Validation errors -->
                <p v-if="form.errors.email" :id="idErrore('email')" role="alert" class="text-red-300 text-sm text-left mb-3">{{ form.errors.email }}</p>
                <p v-if="form.errors.privacy_accepted" :id="idErrore('privacy')" role="alert" class="text-red-300 text-sm text-left mb-3">{{ form.errors.privacy_accepted }}</p>
                <p v-if="hasError && !form.errors.email && !form.errors.privacy_accepted" role="alert" class="text-red-300 text-sm text-left mb-3">{{ $t('newsletter.error') }}</p>

                <!-- Privacy checkbox -->
                <label class="flex items-start gap-2 text-left cursor-pointer group">
                    <input
                        v-model="form.privacy_accepted"
                        type="checkbox"
                        :aria-invalid="form.errors.privacy_accepted ? 'true' : undefined"
                        :aria-describedby="form.errors.privacy_accepted ? idErrore('privacy') : undefined"
                        class="mt-0.5 w-4 h-4 rounded border-white/30 bg-white/10 text-savino-fucsia-chiaro focus:ring-savino-fucsia/30 focus:ring-offset-0"
                    />
                    <span class="text-white/50 text-xs leading-relaxed group-hover:text-white/70 transition-colors">
                        {{ $t('newsletter.privacy_consent') }}
                        <Link :href="route('pages.show', 'privacy-policy')" class="text-savino-fucsia-chiaro hover:text-white underline underline-offset-2 transition-colors">
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
        <div v-if="submitted && (flashSuccess || flashInfo)" ref="esito" tabindex="-1" role="status" class="focus:outline-none">
            <div class="flex items-start gap-2">
                <svg v-if="!flashInfo" class="w-4 h-4 mt-0.5 text-savino-fucsia-chiaro flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                </svg>
                <svg v-else class="w-4 h-4 mt-0.5 text-blue-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-gray-300 text-xs">
                    <template v-if="flashInfo">{{ flashInfo }}</template>
                    <template v-else><strong class="text-white">{{ $t('newsletter.check_inbox_title') }}.</strong> {{ $t('newsletter.check_inbox_text') }}</template>
                </p>
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
                    autocomplete="email"
                    :aria-invalid="form.errors.email ? 'true' : undefined"
                    :aria-describedby="form.errors.email ? idErrore('email') : undefined"
                    class="flex-1 min-w-0 bg-white/5 border border-white/10 text-white placeholder-white/30 rounded-lg px-3 py-2.5 text-sm focus:border-savino-fucsia focus:ring-1 focus:ring-savino-fucsia/30 outline-none transition-all"
                    :class="{ 'border-red-400': form.errors.email }"
                />
                <button
                    type="submit"
                    :disabled="form.processing"
                    :aria-label="$t('newsletter.subscribe')"
                    class="bg-savino-fucsia text-white font-bold text-xs uppercase tracking-wider px-4 py-2.5 rounded-lg hover:bg-white hover:text-savino-blue transition-colors duration-300 disabled:opacity-50 flex-shrink-0 flex items-center gap-1.5"
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

            <p v-if="form.errors.email" :id="idErrore('email')" role="alert" class="text-red-300 text-xs mb-2">{{ form.errors.email }}</p>
            <p v-if="form.errors.privacy_accepted" :id="idErrore('privacy')" role="alert" class="text-red-300 text-xs mb-2">{{ form.errors.privacy_accepted }}</p>
            <p v-if="hasError && !form.errors.email && !form.errors.privacy_accepted" role="alert" class="text-red-300 text-xs mb-2">{{ $t('newsletter.error') }}</p>

            <label class="flex items-start gap-2 cursor-pointer group">
                <input
                    v-model="form.privacy_accepted"
                    type="checkbox"
                    :aria-invalid="form.errors.privacy_accepted ? 'true' : undefined"
                    :aria-describedby="form.errors.privacy_accepted ? idErrore('privacy') : undefined"
                    class="mt-0.5 w-3.5 h-3.5 rounded border-white/20 bg-white/5 text-savino-fucsia-chiaro focus:ring-savino-fucsia/30 focus:ring-offset-0"
                />
                <span class="text-white/70 text-[10px] leading-relaxed group-hover:text-white/90 transition-colors">
                    {{ $t('newsletter.privacy_consent') }}
                    <Link :href="route('pages.show', 'privacy-policy')" class="text-savino-fucsia-chiaro hover:text-white underline underline-offset-2 transition-colors">
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

/* Il pulsante di pausa della home ferma anche questo bagliore (WCAG 2.2.2). */
.movimento-fermo .newsletter-cta-btn {
    animation-play-state: paused;
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
