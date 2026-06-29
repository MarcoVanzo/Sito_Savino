<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    page: {
        type: Object,
        default: () => ({})
    }
})

const form = useForm({
    name: '',
    email: '',
    subject: '',
    message: '',
    honeypot: '', // Anti-spam
})

const inertiaPage = usePage();
const settings = computed(() => inertiaPage.props.siteSettings ?? {});
const contact = computed(() => settings.value.contact ?? {});
const cd = computed(() => props.page?.content_data ?? {});

const flashSuccess = computed(() => inertiaPage.props.flash?.success)

function handleSubmit() {
    form.post(route('contatti.submit'), {
        preserveScroll: true,
        onSuccess: () => {
            // Form reset automatico dopo successo
        },
    })
}

function resetForm() {
    form.reset()
}

const contactInfo = computed(() => [
    {
        icon: 'email',
        title: 'Email',
        value: contact.value.email || 'info@savinodelbenescandicci.it',
        link: 'mailto:' + (contact.value.email || 'info@savinodelbenescandicci.it'),
        color: 'savino-blue'
    },
    {
        icon: 'phone',
        title: 'Telefono',
        value: contact.value.phone || '+39 055 123 4567',
        link: 'tel:' + (contact.value.phone_raw || contact.value.phone || '+390551234567').replace(/\s/g, ''),
        color: 'savino-gold'
    },
    {
        icon: 'location',
        title: 'Sede',
        value: contact.value.address || 'Palazzo Wanny, Via Allende 10, Firenze',
        link: null,
        color: 'savino-red'
    }
])
</script>

<template>
    <Head>
      <title>{{ (page?.title ?? 'Contatti') + ' — Savino Del Bene Volley' }}</title>
      <meta name="description" :content="cd.meta_description || 'Contatta la Savino Del Bene Volley. Sede, uffici, numeri utili e form di contatto.'" />
      <meta property="og:title" :content="(page?.title ?? 'Contatti') + ' — Savino Del Bene Volley'" />
      <meta property="og:description" :content="cd.meta_description || 'Contatta la Savino Del Bene Volley. Sede, uffici, numeri utili e form di contatto.'" />
      <meta property="og:image" :content="'/images/logo.png'" />
      <meta property="og:url" :content="$page.props.ziggy?.location || ''" />
      <meta property="og:type" content="website" />
    </Head>

    <PublicLayout>
        <!-- Hero -->
        <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">{{ cd.hero_subtitle || 'Resta in Contatto' }}</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mt-4">{{ page?.title ?? 'Contatti' }}</h1>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-6"></div>
                <p class="text-white/70 text-lg max-w-2xl mx-auto">{{ cd.hero_description || 'Scrivici, chiamaci o vieni a trovarci. Siamo qui per te.' }}</p>
            </div>
        </section>

        <!-- Contact Info Cards -->
        <section class="py-16 bg-gray-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16">
                    <div
                        v-for="info in contactInfo"
                        :key="info.title"
                        class="bg-white rounded-2xl shadow-md hover:shadow-xl transition-all duration-500 p-8 text-center border border-gray-100 hover:-translate-y-1"
                    >
                        <div
                            class="w-16 h-16 rounded-full mx-auto mb-5 flex items-center justify-center"
                            :class="{
                                'bg-savino-blue/10': info.color === 'savino-blue',
                                'bg-savino-gold/10': info.color === 'savino-gold',
                                'bg-savino-red/10': info.color === 'savino-red'
                            }"
                        >
                            <!-- Email Icon -->
                            <svg v-if="info.icon === 'email'" class="w-7 h-7" :class="{ 'text-savino-blue': info.color === 'savino-blue' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <!-- Phone Icon -->
                            <svg v-else-if="info.icon === 'phone'" class="w-7 h-7" :class="{ 'text-savino-gold': info.color === 'savino-gold' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                            <!-- Location Icon -->
                            <svg v-else class="w-7 h-7" :class="{ 'text-savino-red': info.color === 'savino-red' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-savino-gold uppercase tracking-wider mb-2">{{ info.title }}</h3>
                        <a
                            v-if="info.link"
                            :href="info.link"
                            class="text-gray-700 font-semibold hover:text-savino-blue transition-colors"
                           
                        >{{ info.value }}</a>
                        <p v-else class="text-gray-700 font-semibold">{{ info.value }}</p>
                    </div>
                </div>

                <!-- Contact Form & Map -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                    <!-- Form -->
                    <div class="bg-white rounded-2xl shadow-lg p-8 border border-gray-100">
                        <h2 class="text-2xl font-black text-gray-900 uppercase tracking-tight mb-2">{{ cd.form_title || 'Scrivici' }}</h2>
                        <div class="w-10 h-1 bg-savino-gold mb-6"></div>

                        <!-- Success State -->
                        <div v-if="flashSuccess" class="text-center py-12">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-green-100 flex items-center justify-center">
                                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">{{ flashSuccess }}</h3>
                            <p class="text-gray-500 mb-6">{{ cd.form_success_message || 'Ti risponderemo il prima possibile.' }}</p>
                            <button @click="resetForm" class="text-savino-blue font-bold hover:underline">{{ cd.form_reset_label || 'Invia un altro messaggio' }}</button>
                        </div>

                        <!-- Form -->
                        <form v-else @submit.prevent="handleSubmit" class="space-y-5">
                            <div v-if="Object.keys(form.errors).length > 0" class="bg-savino-red/10 border border-savino-red/30 rounded-lg p-3 text-savino-red text-sm">
                                <p v-for="(error, field) in form.errors" :key="field">{{ error }}</p>
                            </div>
                            <div>
                                <label for="contact-name" class="block text-sm font-bold text-gray-700 mb-1.5">{{ cd.form_label_name || 'Nome e Cognome' }} *</label>
                                <input
                                    id="contact-name"
                                    v-model="form.name"
                                    type="text"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-all"
                                   
                                    :placeholder="cd.form_placeholder_name || 'Il tuo nome'"
                                />
                            </div>
                            <div>
                                <label for="contact-email" class="block text-sm font-bold text-gray-700 mb-1.5">{{ cd.form_label_email || 'Email' }} *</label>
                                <input
                                    id="contact-email"
                                    v-model="form.email"
                                    type="email"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-all"
                                   
                                    :placeholder="cd.form_placeholder_email || 'La tua email'"
                                />
                            </div>
                            <div>
                                <label for="contact-subject" class="block text-sm font-bold text-gray-700 mb-1.5">{{ cd.form_label_subject || 'Oggetto' }}</label>
                                <input
                                    id="contact-subject"
                                    v-model="form.subject"
                                    type="text"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-all"
                                   
                                    :placeholder="cd.form_placeholder_subject || 'Oggetto del messaggio'"
                                />
                            </div>
                            <div>
                                <label for="contact-message" class="block text-sm font-bold text-gray-700 mb-1.5">{{ cd.form_label_message || 'Messaggio' }} *</label>
                                <textarea
                                    id="contact-message"
                                    v-model="form.message"
                                    rows="5"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:border-savino-blue focus:ring-2 focus:ring-savino-blue/20 outline-none transition-all resize-none"
                                   
                                    :placeholder="cd.form_placeholder_message || 'Scrivi il tuo messaggio...'"
                                ></textarea>
                            </div>
                            <div class="hidden" aria-hidden="true">
                                <input type="text" v-model="form.honeypot" tabindex="-1" autocomplete="off" />
                            </div>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full bg-savino-blue text-white py-3.5 rounded-lg font-bold uppercase tracking-wider hover:bg-savino-blue/90 transition-all duration-300 shadow-lg shadow-savino-blue/30 hover:shadow-xl hover:shadow-savino-blue/40 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span v-if="form.processing">{{ cd.form_sending_label || 'Invio in corso...' }}</span>
                                <span v-else>{{ cd.form_submit_label || 'Invia Messaggio' }}</span>
                            </button>
                        </form>
                    </div>

                    <!-- Google Maps -->
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
                        <div class="relative h-full min-h-[400px]">
                            <iframe
                                src="https://maps.google.com/maps?q=Palazzo+Wanny,+Via+Salvador+Allende,+Scandicci,+Firenze&t=&z=15&ie=UTF8&iwloc=&output=embed"
                                class="absolute inset-0 w-full h-full border-0"
                                allowfullscreen
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                title="Mappa Palazzo Wanny - Savino Del Bene Volley"
                            ></iframe>
                        </div>
                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-savino-red/10 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-savino-red" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-sm font-black text-gray-900 uppercase">{{ cd.map_title || contact.venue_name || 'Palazzo Wanny' }}</h3>
                                    <p class="text-gray-500 text-xs">{{ cd.map_address || contact.short_address || 'Via Allende 10, Firenze' }}</p>
                                </div>
                                <a
                                    href="https://www.google.com/maps/dir//Palazzo+Wanny,+Via+Salvador+Allende,+Firenze"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="ml-auto text-xs font-bold text-savino-blue hover:text-savino-blue/80 transition-colors uppercase tracking-wider"
                                >
                                    Indicazioni →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
