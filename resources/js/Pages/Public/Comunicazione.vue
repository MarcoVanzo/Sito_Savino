<script setup>
import { useTranslations } from '@/Composables/useTranslations.js';
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useSanitize } from '@/Composables/useSanitize'
import { useOgMeta } from '@/Composables/useOgMeta'

const $t = useTranslations();

defineOptions({ layout: PublicLayout })

const props = defineProps({
    page: {
        type: Object,
        default: () => ({})
    },
    // Le prossime gare in casa: la richiesta di accredito si riferisce a una di
    // quelle, non a un testo libero.
    upcomingHomeGames: {
        type: Array,
        default: () => []
    }
})

const { sanitize } = useSanitize()
const safeContent = computed(() => sanitize(props.page?.content))

const inertiaPage = usePage()
const settings = computed(() => inertiaPage.props.siteSettings ?? {})
const contact = computed(() => settings.value.contact ?? {})
const cd = computed(() => props.page?.content_data ?? {})

// Richiesta di accredito stampa: finisce in "Richieste Accrediti" nel pannello
// e a press@. Prima si mandava una mail scritta a mano.
const accreditationSent = ref(false)

const accreditationForm = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    outlet: '',
    role: 'giornalista',
    match: '',
    notes: '',
    honeypot: '',
})

function submitAccreditation() {
    accreditationForm.post(route('comunicazione.accrediti.submit'), {
        preserveScroll: true,
        onSuccess: () => {
            accreditationSent.value = true
            accreditationForm.reset()
        },
    })
}

// Materiale stampa: un elenco libero, non quattro caselle fisse. Servono il
// logo, il brand book e una cartella per ogni gara, e quante sono lo decide
// il calendario. Le voci senza file non si mostrano: un riquadro che sembra
// scaricabile e non lo e' e' peggio di un riquadro che non c'e'.
const pressKitItems = computed(() => {
    const elenco = cd.value.press_kits;

    if (!Array.isArray(elenco)) return [];

    return elenco.filter(voce => typeof voce?.file === 'string' && voce.file.trim() !== '');
});

// La sezione accrediti vive sulla sua pagina: su Cartelle Stampa lasciava in
// mezzo un modulo che li' non c'entra niente.
const showAccreditation = computed(() => props.page?.slug !== 'cartelle-stampa');

const contacts = computed(() => [
    {
        role: cd.value.contact_1_role || $t('comunicazione.contact_role_press'),
        name: cd.value.contact_1_name || $t('comunicazione.contact_name_press'),
        email: cd.value.contact_1_email || contact.value.press_email || null,
        phone: cd.value.contact_1_phone || null
    },
    {
        role: cd.value.contact_2_role || $t('comunicazione.contact_role_social'),
        name: cd.value.contact_2_name || $t('comunicazione.contact_name_social'),
        email: cd.value.contact_2_email || contact.value.social_email || null,
        phone: cd.value.contact_2_phone || null
    },
    {
        role: cd.value.contact_3_role || $t('comunicazione.contact_role_media'),
        name: cd.value.contact_3_name || $t('comunicazione.contact_name_media'),
        email: cd.value.contact_3_email || contact.value.media_email || null,
        phone: cd.value.contact_3_phone || null
    }
])

const ogMeta = useOgMeta({
    title: props.page?.title ?? $t('comunicazione.og_title'),
    description: props.page?.meta_description || $t('comunicazione.og_description'),
})
</script>

<template>
    <Head>
      <title>{{ ogMeta.title }}</title>
      <meta name="description" :content="ogMeta.description" />
      <meta property="og:title" :content="ogMeta.title" />
      <meta property="og:description" :content="ogMeta.description" />
      <meta property="og:image" :content="ogMeta.image" />
      <meta property="og:url" :content="ogMeta.url" />
      <meta property="og:type" :content="ogMeta.type" />
    </Head>

    <!-- Hero -->
    <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
        <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
            <span class="text-savino-fucsia text-sm font-bold uppercase tracking-[0.3em]">{{ cd.hero_badge || $t('comunicazione.hero_badge') }}</span>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">
                {{ page?.title ?? $t('comunicazione.og_title') }}
            </h1>
            <div class="w-16 h-1 bg-savino-fucsia mx-auto mt-4 mb-6"></div>
            <p class="text-white/70 text-lg max-w-2xl mx-auto">
                {{ cd.hero_subtitle || $t('comunicazione.hero_subtitle') }}
            </p>
        </div>
    </section>

    <!-- Press Accreditation -->
    <section v-if="showAccreditation" class="py-20 bg-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div>
                    <span class="text-savino-fucsia text-sm font-bold uppercase tracking-[0.2em]">{{ cd.accreditation_badge || $t('comunicazione.accreditation_badge') }}</span>
                    <h2 class="text-3xl md:text-4xl font-black text-gray-900 uppercase tracking-tight mt-2">
                        {{ cd.accreditation_title || $t('comunicazione.accreditation_title') }}
                    </h2>
                    <div class="w-12 h-1 bg-savino-fucsia mt-4 mb-6"></div>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        {{ cd.accreditation_text_1 || $t('comunicazione.accreditation_text_1') }}
                    </p>
                    <p class="text-gray-600 leading-relaxed mb-6">
                        {{ cd.accreditation_text_2 || $t('comunicazione.accreditation_text_2') }}
                    </p>
                    <!-- I tre passi elencavano come scrivere una mail: adesso la
                         richiesta si manda dal modulo qui accanto. Resta lo spazio per
                         le condizioni, che la redazione scrive dal pannello. -->
                    <div v-if="cd.accreditation_notes" class="bg-savino-blue/5 rounded-xl p-6 border border-savino-blue/10">
                        <p class="text-gray-600 text-sm leading-relaxed whitespace-pre-line">{{ cd.accreditation_notes }}</p>
                    </div>
                </div>
                <!-- Modulo di richiesta: prima al suo posto c'era un riquadro
                     decorativo, e la richiesta si mandava a mano per email. -->
                <div class="bg-gray-50 rounded-2xl border border-gray-100 p-6 sm:p-8">
                    <div v-if="accreditationSent" class="text-center py-10">
                        <div class="w-16 h-16 mx-auto mb-5 rounded-full bg-savino-blue/10 text-savino-blue flex items-center justify-center">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-black text-gray-900 uppercase tracking-tight mb-2">{{ $t('comunicazione.accreditation_form_sent_title') }}</h3>
                        <p class="text-gray-600 text-sm max-w-sm mx-auto">{{ $t('comunicazione.accreditation_form_sent_text') }}</p>
                    </div>

                    <form v-else class="space-y-4" @submit.prevent="submitAccreditation">
                        <h3 class="text-xl font-black text-gray-900 uppercase tracking-tight">{{ $t('comunicazione.accreditation_form_title') }}</h3>

                        <!-- Trappola anti-spam: invisibile a chi legge la pagina. -->
                        <input v-model="accreditationForm.honeypot" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="hidden" />

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label for="acc-first-name" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">{{ $t('comunicazione.accreditation_field_first_name') }}</label>
                                <input id="acc-first-name" v-model="accreditationForm.first_name" type="text" required class="w-full rounded-lg border-gray-200 text-sm focus:border-savino-blue focus:ring-savino-blue" />
                                <p v-if="accreditationForm.errors.first_name" class="text-red-600 text-xs mt-1">{{ accreditationForm.errors.first_name }}</p>
                            </div>
                            <div>
                                <label for="acc-last-name" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">{{ $t('comunicazione.accreditation_field_last_name') }}</label>
                                <input id="acc-last-name" v-model="accreditationForm.last_name" type="text" required class="w-full rounded-lg border-gray-200 text-sm focus:border-savino-blue focus:ring-savino-blue" />
                                <p v-if="accreditationForm.errors.last_name" class="text-red-600 text-xs mt-1">{{ accreditationForm.errors.last_name }}</p>
                            </div>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label for="acc-email" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">{{ $t('comunicazione.accreditation_field_email') }}</label>
                                <input id="acc-email" v-model="accreditationForm.email" type="email" required class="w-full rounded-lg border-gray-200 text-sm focus:border-savino-blue focus:ring-savino-blue" />
                                <p v-if="accreditationForm.errors.email" class="text-red-600 text-xs mt-1">{{ accreditationForm.errors.email }}</p>
                            </div>
                            <div>
                                <label for="acc-phone" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">{{ $t('comunicazione.accreditation_field_phone') }}</label>
                                <input id="acc-phone" v-model="accreditationForm.phone" type="tel" required class="w-full rounded-lg border-gray-200 text-sm focus:border-savino-blue focus:ring-savino-blue" />
                                <p v-if="accreditationForm.errors.phone" class="text-red-600 text-xs mt-1">{{ accreditationForm.errors.phone }}</p>
                            </div>
                        </div>

                        <div>
                            <label for="acc-outlet" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">{{ $t('comunicazione.accreditation_field_outlet') }}</label>
                            <input id="acc-outlet" v-model="accreditationForm.outlet" type="text" required class="w-full rounded-lg border-gray-200 text-sm focus:border-savino-blue focus:ring-savino-blue" />
                            <p v-if="accreditationForm.errors.outlet" class="text-red-600 text-xs mt-1">{{ accreditationForm.errors.outlet }}</p>
                        </div>

                        <div>
                            <label for="acc-role" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">{{ $t('comunicazione.accreditation_field_role') }}</label>
                            <select id="acc-role" v-model="accreditationForm.role" required class="w-full rounded-lg border-gray-200 text-sm focus:border-savino-blue focus:ring-savino-blue">
                                <option value="giornalista">{{ $t('comunicazione.accreditation_role_journalist') }}</option>
                                <option value="fotografo">{{ $t('comunicazione.accreditation_role_photographer') }}</option>
                                <option value="operatore">{{ $t('comunicazione.accreditation_role_operator') }}</option>
                            </select>
                            <p v-if="accreditationForm.errors.role" class="text-red-600 text-xs mt-1">{{ accreditationForm.errors.role }}</p>
                        </div>

                        <div>
                            <label for="acc-match" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">{{ $t('comunicazione.accreditation_field_match') }}</label>
                            <!-- Con il calendario in archivio si sceglie fra le prossime
                                 gare in casa; senza, resta il campo libero. -->
                            <select v-if="upcomingHomeGames.length" id="acc-match" v-model="accreditationForm.match" required class="w-full rounded-lg border-gray-200 text-sm focus:border-savino-blue focus:ring-savino-blue">
                                <option value="" disabled>{{ $t('comunicazione.accreditation_field_match_choose') }}</option>
                                <option v-for="gara in upcomingHomeGames" :key="gara.value" :value="gara.value">{{ gara.label }}</option>
                            </select>
                            <input v-else id="acc-match" v-model="accreditationForm.match" type="text" required :placeholder="$t('comunicazione.accreditation_field_match_placeholder')" class="w-full rounded-lg border-gray-200 text-sm focus:border-savino-blue focus:ring-savino-blue" />
                            <p v-if="accreditationForm.errors.match" class="text-red-600 text-xs mt-1">{{ accreditationForm.errors.match }}</p>
                        </div>

                        <div>
                            <label for="acc-notes" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">{{ $t('comunicazione.accreditation_field_notes') }}</label>
                            <textarea id="acc-notes" v-model="accreditationForm.notes" rows="3" class="w-full rounded-lg border-gray-200 text-sm focus:border-savino-blue focus:ring-savino-blue"></textarea>
                            <p v-if="accreditationForm.errors.notes" class="text-red-600 text-xs mt-1">{{ accreditationForm.errors.notes }}</p>
                        </div>

                        <button
                            type="submit"
                            :disabled="accreditationForm.processing"
                            class="w-full bg-savino-fucsia text-white font-bold uppercase tracking-wider text-sm py-3.5 rounded-lg hover:bg-savino-fucsia/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ accreditationForm.processing ? $t('comunicazione.accreditation_form_sending') : $t('comunicazione.accreditation_form_submit') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Press Kit Downloads -->
    <section v-if="pressKitItems.length > 0" class="py-20 bg-gray-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-savino-fucsia text-sm font-bold uppercase tracking-[0.2em]">{{ cd.press_kit_badge || 'Download' }}</span>
                <h2 class="text-3xl md:text-4xl font-black text-gray-900 uppercase tracking-tight mt-2">
                    {{ cd.press_kit_section_title || 'Press Kit' }}
                </h2>
                <div class="w-12 h-1 bg-savino-fucsia mx-auto mt-4"></div>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <a
                    v-for="(item, index) in pressKitItems"
                    :key="index"
                    :href="item.file"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="block bg-white rounded-xl p-6 shadow-sm border border-gray-100 group hover:shadow-lg transition-all duration-300 hover:-translate-y-1"
                >
                    <span v-if="item.icon" class="text-4xl block mb-4">{{ item.icon }}</span>
                    <h3 class="text-base font-bold text-gray-900 mb-2 group-hover:text-savino-blue transition-colors">
                        {{ item.title }}
                    </h3>
                    <p v-if="item.description" class="text-gray-600 text-sm leading-relaxed mb-4">
                        {{ item.description }}
                    </p>
                    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                        <span class="text-xs text-gray-400">{{ item.format }}</span>
                        <svg class="w-5 h-5 text-savino-blue group-hover:translate-y-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <!-- Press Contacts -->
    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-savino-fucsia text-sm font-bold uppercase tracking-[0.2em]">{{ cd.contacts_badge || $t('comunicazione.contacts_badge') }}</span>
                <h2 class="text-3xl md:text-4xl font-black text-gray-900 uppercase tracking-tight mt-2">
                    {{ cd.contacts_section_title || $t('comunicazione.contacts_section_title') }}
                </h2>
                <div class="w-12 h-1 bg-savino-fucsia mx-auto mt-4"></div>
            </div>
            <div class="space-y-6">
                <div
                    v-for="(contact, index) in contacts"
                    :key="index"
                    class="flex flex-col sm:flex-row items-start sm:items-center justify-between bg-gray-50 rounded-xl p-6 border border-gray-100"
                >
                    <div class="mb-3 sm:mb-0">
                        <span class="text-xs font-bold text-savino-fucsia uppercase tracking-wider">{{ contact.role }}</span>
                        <h4 class="font-bold text-gray-900 mt-1">{{ contact.name }}</h4>
                    </div>
                    <div class="flex flex-col sm:items-end gap-1">
                        <a v-if="contact.email" :href="'mailto:' + contact.email" class="text-savino-blue text-sm hover:underline">
                            {{ contact.email }}
                        </a>
                        <span v-if="contact.phone" class="text-gray-500 text-sm">{{ contact.phone }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Dynamic Content -->
    <section v-if="page?.content" class="py-20 bg-gray-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="prose prose-lg max-w-none" v-html="safeContent"></div>
        </div>
    </section>
</template>
