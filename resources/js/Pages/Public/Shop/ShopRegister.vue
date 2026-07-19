<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useOgMeta } from '@/Composables/useOgMeta';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';

import { useTranslations } from '@/Composables/useTranslations.js';
import { ref, computed } from 'vue';

const $t = useTranslations();

const ogMeta = useOgMeta({
    title: $t('shop.register_title'),
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    privacy_accepted: false,
});

const showPassword = ref(false);
const showPasswordConfirm = ref(false);

const passwordStrength = computed(() => {
    const p = form.password;
    if (!p) return { score: 0, label: '', color: '' };
    let score = 0;
    if (p.length >= 8) score++;
    if (p.length >= 12) score++;
    if (/[A-Z]/.test(p)) score++;
    if (/[0-9]/.test(p)) score++;
    if (/[^A-Za-z0-9]/.test(p)) score++;

    if (score <= 1) return { score: 1, label: $t('shop.password_strength_weak') || 'Debole', color: 'bg-red-500' };
    if (score <= 2) return { score: 2, label: $t('shop.password_strength_fair') || 'Discreta', color: 'bg-orange-500' };
    if (score <= 3) return { score: 3, label: $t('shop.password_strength_good') || 'Buona', color: 'bg-yellow-500' };
    if (score <= 4) return { score: 4, label: $t('shop.password_strength_strong') || 'Forte', color: 'bg-emerald-500' };
    return { score: 5, label: $t('shop.password_strength_excellent') || 'Eccellente', color: 'bg-emerald-400' };
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
        <section class="relative min-h-[25vh] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
            <div class="absolute inset-0 opacity-[0.05]" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;80&quot; height=&quot;80&quot; viewBox=&quot;0 0 80 80&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 0h40v40H0zM40 40h40v40H40z&quot; fill=&quot;%23C5A55A&quot; fill-opacity=&quot;0.5&quot;/%3E%3C/svg%3E'); background-size: 80px 80px;"></div>
            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-16">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">Shop</span>
                <h1 class="text-4xl md:text-5xl font-black text-white uppercase tracking-tighter mt-4">
                    {{ $t('shop.register') }}
                </h1>
                <p class="mt-4 text-gray-400 max-w-2xl mx-auto">{{ $t('shop.register_description') }}</p>
            </div>
        </section>

        <!-- FORM SECTION -->
        <section class="bg-gray-900 py-16">
            <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Breadcrumbs -->
                <nav class="mb-8 text-sm">
                    <ol class="flex items-center gap-2 text-gray-500">
                        <li><Link :href="route('shop')" class="hover:text-savino-gold transition-colors">Shop</Link></li>
                        <li>/</li>
                        <li class="text-white">{{ $t('shop.register') }}</li>
                    </ol>
                </nav>

                <!-- Form Card -->
                <div class="bg-gray-800/50 border border-gray-700 rounded-2xl p-8 backdrop-blur-sm">
                    <!-- Card Header -->
                    <div class="text-center mb-8">
                        <div class="w-16 h-16 rounded-full bg-savino-gold/10 flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-savino-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                        </div>
                        <h2 class="text-white text-xl font-bold">{{ $t('shop.create_account') }}</h2>
                        <p class="text-gray-500 text-sm mt-1">{{ $t('shop.register_description') }}</p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-5">
                        <!-- Nome Completo -->
                        <div>
                            <label for="name" class="block text-xs font-bold uppercase tracking-[0.15em] text-gray-400 mb-2">
                                {{ $t('shop.full_name') }}
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                    </svg>
                                </div>
                                <input
                                    id="name"
                                    type="text"
                                    v-model="form.name"
                                    required
                                    autofocus
                                    autocomplete="name"
                                    class="w-full bg-gray-900/50 border border-gray-600 rounded-lg pl-11 pr-4 py-3 text-white placeholder-gray-500 focus:border-savino-gold focus:ring-1 focus:ring-savino-gold transition-colors duration-200"
                                    :placeholder="$t('shop.full_name')"
                                />
                            </div>
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-xs font-bold uppercase tracking-[0.15em] text-gray-400 mb-2">
                                {{ $t('shop.email') }}
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                    </svg>
                                </div>
                                <input
                                    id="email"
                                    type="email"
                                    v-model="form.email"
                                    required
                                    autocomplete="username"
                                    class="w-full bg-gray-900/50 border border-gray-600 rounded-lg pl-11 pr-4 py-3 text-white placeholder-gray-500 focus:border-savino-gold focus:ring-1 focus:ring-savino-gold transition-colors duration-200"
                                    placeholder="email@esempio.com"
                                />
                            </div>
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="password" class="block text-xs font-bold uppercase tracking-[0.15em] text-gray-400 mb-2">
                                {{ $t('shop.password') }}
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                    </svg>
                                </div>
                                <input
                                    id="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    v-model="form.password"
                                    required
                                    autocomplete="new-password"
                                    class="w-full bg-gray-900/50 border border-gray-600 rounded-lg pl-11 pr-12 py-3 text-white placeholder-gray-500 focus:border-savino-gold focus:ring-1 focus:ring-savino-gold transition-colors duration-200"
                                    placeholder="••••••••"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-gray-500 hover:text-gray-300 transition-colors"
                                >
                                    <svg v-if="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                </button>
                            </div>
                            <!-- Password Strength Indicator -->
                            <div v-if="form.password" class="mt-2.5">
                                <div class="flex gap-1.5">
                                    <div
                                        v-for="i in 5"
                                        :key="i"
                                        class="h-1 flex-1 rounded-full transition-all duration-300"
                                        :class="i <= passwordStrength.score ? passwordStrength.color : 'bg-gray-700'"
                                    ></div>
                                </div>
                                <p class="text-xs mt-1.5" :class="passwordStrength.color.replace('bg-', 'text-')">
                                    {{ passwordStrength.label }}
                                </p>
                            </div>
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <!-- Conferma Password -->
                        <div>
                            <label for="password_confirmation" class="block text-xs font-bold uppercase tracking-[0.15em] text-gray-400 mb-2">
                                {{ $t('shop.password_confirmation') }}
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                    </svg>
                                </div>
                                <input
                                    id="password_confirmation"
                                    :type="showPasswordConfirm ? 'text' : 'password'"
                                    v-model="form.password_confirmation"
                                    required
                                    autocomplete="new-password"
                                    class="w-full bg-gray-900/50 border border-gray-600 rounded-lg pl-11 pr-12 py-3 text-white placeholder-gray-500 focus:border-savino-gold focus:ring-1 focus:ring-savino-gold transition-colors duration-200"
                                    placeholder="••••••••"
                                />
                                <button
                                    type="button"
                                    @click="showPasswordConfirm = !showPasswordConfirm"
                                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-gray-500 hover:text-gray-300 transition-colors"
                                >
                                    <svg v-if="!showPasswordConfirm" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                </button>
                            </div>
                            <!-- Match indicator -->
                            <p
                                v-if="form.password_confirmation && form.password"
                                class="text-xs mt-1.5"
                                :class="form.password === form.password_confirmation ? 'text-emerald-400' : 'text-red-400'"
                            >
                                {{ form.password === form.password_confirmation ? $t('shop.password_match') || '✓ Le password coincidono' : $t('shop.password_mismatch') || '✗ Le password non coincidono' }}
                            </p>
                            <InputError class="mt-2" :message="form.errors.password_confirmation" />
                        </div>

                        <!-- Divider -->
                        <div class="border-t border-gray-700/50"></div>

                        <!-- Privacy Policy -->
                        <div>
                            <label for="privacy_accepted" class="flex items-start gap-3 cursor-pointer group">
                                <div class="mt-0.5">
                                    <Checkbox id="privacy_accepted" name="privacy_accepted" v-model:checked="form.privacy_accepted" required />
                                </div>
                                <span class="text-sm text-gray-400 leading-relaxed group-hover:text-gray-300 transition-colors">
                                    {{ $t('shop.accept_privacy_1') }}
                                    <a
                                        :href="route('pages.show', 'privacy-policy')"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-savino-gold hover:underline font-medium"
                                    >{{ $t('shop.accept_privacy_2') }}</a>
                                </span>
                            </label>
                            <InputError class="mt-2" :message="form.errors.privacy_accepted" />
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-2">
                            <button
                                type="submit"
                                class="w-full relative overflow-hidden py-3.5 px-6 rounded-lg text-base font-bold uppercase tracking-wider transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-savino-gold/50 focus:ring-offset-2 focus:ring-offset-gray-800"
                                :class="form.processing
                                    ? 'bg-gray-700 text-gray-400 cursor-not-allowed'
                                    : 'bg-gradient-to-r from-savino-gold to-yellow-600 text-gray-900 hover:from-yellow-500 hover:to-savino-gold hover:shadow-lg hover:shadow-savino-gold/20 active:scale-[0.98]'"
                                :disabled="form.processing"
                            >
                                <span v-if="form.processing" class="flex items-center justify-center gap-2">
                                    <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ $t('shop.creating_account') || 'Creazione in corso...' }}
                                </span>
                                <span v-else>{{ $t('shop.create_account') }}</span>
                            </button>
                        </div>

                        <!-- Login Link -->
                        <div class="text-center pt-4 pb-2">
                            <p class="text-sm text-gray-500">
                                {{ $t('shop.already_have_account') }}
                                <Link
                                    :href="route('login')"
                                    class="font-bold text-savino-gold hover:text-yellow-400 transition-colors"
                                >
                                    {{ $t('shop.login_now') }}
                                </Link>
                            </p>
                        </div>
                    </form>
                </div>

                <!-- Security Note -->
                <div class="mt-6 flex items-center justify-center gap-2 text-gray-600 text-xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                    <span>{{ $t('shop.secure_connection_note') || 'Connessione sicura · I tuoi dati sono protetti' }}</span>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
