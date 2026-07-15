<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import LOGOS from '@/Constants/logos.js';

const page = usePage();
const locale = computed(() => page.props.locale || 'it');

// Localized strings for maximum self-containment and stability
const locales = {
    it: {
        title: 'Modifica Password Obbligatoria',
        subtitle: 'Per motivi di sicurezza, devi modificare la password provvisoria al primo accesso.',
        current_password: 'Password Attuale',
        new_password: 'Nuova Password',
        confirm_password: 'Conferma Nuova Password',
        submit_button: 'Salva e Continua',
        logout_button: 'Disconnetti',
        warning_title: 'Attenzione',
        warning_desc: 'Questo account è stato creato da un amministratore. Devi impostare una nuova password personalizzata e privata per poter sbloccare l\'accesso alle funzionalità del sito e del pannello gestionale.',
        saving: 'Salvataggio in corso...',
    },
    en: {
        title: 'Password Change Required',
        subtitle: 'For security reasons, you must change your temporary password on your first login.',
        current_password: 'Current Password',
        new_password: 'New Password',
        confirm_password: 'Confirm New Password',
        submit_button: 'Save and Continue',
        logout_button: 'Log Out',
        warning_title: 'Attention',
        warning_desc: 'This account was created by an administrator. You must set a new personal and private password to unlock access to the website and administrative panel.',
        saving: 'Saving...',
    }
};

const t = computed(() => locales[locale.value] || locales.it);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.change.update'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const handleLogout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <Head><title>{{ t.title }}</title></Head>

    <div class="min-h-screen flex flex-col items-center justify-center bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900 px-4 py-12 relative overflow-hidden">
        
        <!-- Background pattern consistent with Login.vue -->
        <div class="fixed inset-0 opacity-[0.03] pointer-events-none" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;80&quot; height=&quot;80&quot; viewBox=&quot;0 0 80 80&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 0h40v40H0zM40 40h40v40H40z&quot; fill=&quot;%23C5A55A&quot; fill-opacity=&quot;0.5&quot;/%3E%3C/svg%3E'); background-size: 80px 80px;"></div>

        <!-- Animated glowing ambient circles -->
        <div class="absolute top-1/4 -left-32 w-96 h-96 bg-savino-red/10 rounded-full blur-3xl filter animate-pulse"></div>
        <div class="absolute bottom-1/4 -right-32 w-96 h-96 bg-savino-gold/10 rounded-full blur-3xl filter animate-pulse delay-700"></div>

        <!-- Logo -->
        <div class="mb-8 relative z-10">
            <img :src="LOGOS.VOLLEY_WHITE" alt="Savino Del Bene Volley" class="h-24 w-auto drop-shadow-[0_10px_20px_rgba(0,0,0,0.5)]" />
        </div>

        <!-- Card Container -->
        <div class="w-full max-w-lg relative z-10">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-black text-white uppercase tracking-tighter sm:text-4xl">
                    {{ t.title }}
                </h1>
                <div class="w-16 h-1 bg-savino-gold mx-auto mt-3 mb-4"></div>
                <p class="text-white/75 text-sm sm:text-base px-4">
                    {{ t.subtitle }}
                </p>
            </div>

            <!-- Security Warning Box (Premium Glassmorphic Banner) -->
            <div class="mb-6 bg-gradient-to-r from-savino-gold/10 to-transparent border-l-4 border-savino-gold rounded-r-xl p-5 backdrop-blur-md shadow-lg">
                <div class="flex items-start space-x-3">
                    <svg class="h-6 w-6 text-savino-gold flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                        <h4 class="text-base font-bold text-savino-gold uppercase tracking-wider">
                            {{ t.warning_title }}
                        </h4>
                        <p class="text-sm text-white/80 leading-relaxed mt-1">
                            {{ t.warning_desc }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Form Card -->
            <div class="bg-white/95 dark:bg-gray-800/95 backdrop-blur-lg rounded-2xl shadow-[0_20px_60px_rgba(0,0,0,0.5)] overflow-hidden border border-white/20 p-8 sm:p-10">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Current Password -->
                    <div>
                        <InputLabel forId="current_password" :value="t.current_password" class="text-gray-800 dark:text-gray-200" />
                        <TextInput
                            id="current_password"
                            type="password"
                            class="mt-1 block w-full transition-all focus:ring-savino-gold focus:border-savino-gold"
                            v-model="form.current_password"
                            required
                            autofocus
                            autocomplete="current-password"
                        />
                        <InputError class="mt-2" :message="form.errors.current_password" />
                    </div>

                    <!-- Divider -->
                    <div class="border-t border-gray-200 dark:border-gray-700 my-4"></div>

                    <!-- New Password -->
                    <div>
                        <InputLabel forId="password" :value="t.new_password" class="text-gray-800 dark:text-gray-200" />
                        <TextInput
                            id="password"
                            type="password"
                            class="mt-1 block w-full transition-all focus:ring-savino-blue focus:border-savino-blue"
                            v-model="form.password"
                            required
                            autocomplete="new-password"
                        />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <InputLabel forId="password_confirmation" :value="t.confirm_password" class="text-gray-800 dark:text-gray-200" />
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            class="mt-1 block w-full transition-all focus:ring-savino-blue focus:border-savino-blue"
                            v-model="form.password_confirmation"
                            required
                            autocomplete="new-password"
                        />
                        <InputError class="mt-2" :message="form.errors.password_confirmation" />
                    </div>

                    <!-- Actions -->
                    <div class="space-y-4 pt-2">
                        <!-- Submit Button -->
                        <button
                            type="submit"
                            class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-lg text-base font-black uppercase tracking-wider text-white bg-savino-blue hover:bg-savino-blue/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-savino-gold transition-all duration-200"
                            :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? t.saving : t.submit_button }}
                        </button>

                        <!-- Logout Button -->
                        <button
                            type="button"
                            @click="handleLogout"
                            class="w-full flex justify-center py-3 px-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl text-sm font-bold text-gray-500 dark:text-gray-400 hover:border-savino-red hover:text-savino-red transition-all duration-200"
                        >
                            {{ t.logout_button }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
