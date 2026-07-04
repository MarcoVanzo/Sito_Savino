<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useOgMeta } from '@/Composables/useOgMeta';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';

import { useTranslations } from '@/Composables/useTranslations.js';

const $t = useTranslations();

const ogMeta = useOgMeta({
    title: $t('shop.register_title') || 'Registrati allo Shop - Savino Del Bene Volley',
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    privacy_policy: false,
});

const submit = () => {
    form.post(route('shop.register.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head>
        <title>{{ ogMeta.title }}</title>
    </Head>

    <PublicLayout>
        <!-- HERO SECTION -->
        <section class="relative min-h-[30vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="absolute inset-0 opacity-[0.05]" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;80&quot; height=&quot;80&quot; viewBox=&quot;0 0 80 80&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 0h40v40H0zM40 40h40v40H40z&quot; fill=&quot;%23C5A55A&quot; fill-opacity=&quot;0.5&quot;/%3E%3C/svg%3E'); background-size: 80px 80px;"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-16">
                <h1 class="text-4xl md:text-5xl font-black text-white uppercase tracking-tighter">
                    {{ $t('shop.register') || 'Registrati' }}
                </h1>
                <p class="mt-4 text-gray-300 text-lg">{{ $t('shop.register_description') || 'Crea un account per gestire i tuoi ordini e accelerare il checkout.' }}</p>
            </div>
        </section>

        <!-- FORM SECTION -->
        <section class="py-16 bg-gray-50 flex justify-center">
            <div class="w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100 p-8">
                
                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <InputLabel for="name" :value="$t('shop.full_name') || 'Nome Completo'" />
                        <TextInput
                            id="name"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.name"
                            required
                            autofocus
                            autocomplete="name"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="email" :value="$t('shop.email') || 'Email'" />
                        <TextInput
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                            autocomplete="username"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="password" :value="$t('shop.password') || 'Password'" />
                        <TextInput
                            id="password"
                            type="password"
                            class="mt-1 block w-full"
                            v-model="form.password"
                            required
                            autocomplete="new-password"
                        />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div>
                        <InputLabel for="password_confirmation" :value="$t('shop.password_confirmation') || 'Conferma Password'" />
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            v-model="form.password_confirmation"
                            required
                            autocomplete="new-password"
                        />
                        <InputError class="mt-2" :message="form.errors.password_confirmation" />
                    </div>

                    <div class="block mt-4">
                        <label class="flex items-center">
                            <Checkbox name="privacy_policy" v-model:checked="form.privacy_policy" required />
                            <span class="ml-2 text-sm text-gray-600">
                                {{ $t('shop.accept_privacy_1') || 'Accetto la' }} <a href="/privacy-policy" target="_blank" class="text-savino-gold hover:underline">{{ $t('shop.accept_privacy_2') || 'Privacy Policy' }}</a>
                            </span>
                        </label>
                        <InputError class="mt-2" :message="form.errors.privacy_policy" />
                    </div>

                    <div class="pt-4">
                        <button 
                            type="submit" 
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-savino-blue hover:bg-savino-blue/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-savino-blue transition-colors duration-200"
                            :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                            :disabled="form.processing"
                        >
                            {{ $t('shop.create_account') || 'Crea Account' }}
                        </button>
                    </div>

                    <div class="text-center mt-6 text-sm text-gray-600">
                        {{ $t('shop.already_have_account') || 'Hai già un account?' }} 
                        <Link :href="route('shop.login')" class="font-medium text-savino-gold hover:text-savino-blue transition-colors">
                            {{ $t('shop.login_now') || 'Accedi ora' }}
                        </Link>
                    </div>
                </form>

            </div>
        </section>
    </PublicLayout>
</template>
