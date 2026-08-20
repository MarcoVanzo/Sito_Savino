<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useTranslations } from '@/Composables/useTranslations.js';
import LOGOS from '@/Constants/logos.js';

const $t = useTranslations();

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head><title>{{ $t('shop.login_title') }}</title></Head>

    <div class="min-h-screen flex flex-col items-center justify-center bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900 px-4 py-12">

        <!-- Background pattern -->
        <div class="fixed inset-0 opacity-[0.03] pointer-events-none" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;80&quot; height=&quot;80&quot; viewBox=&quot;0 0 80 80&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M0 0h40v40H0zM40 40h40v40H40z&quot; fill=&quot;%23C5A55A&quot; fill-opacity=&quot;0.5&quot;/%3E%3C/svg%3E'); background-size: 80px 80px;"></div>

        <!-- Logo -->
        <Link :href="route('home')" class="mb-8 relative z-10 transition-transform duration-300 hover:scale-105">
            <img :src="LOGOS.VOLLEY_WHITE" alt="Savino Del Bene Volley" class="h-24 w-auto drop-shadow-[0_10px_20px_rgba(0,0,0,0.5)]" />
        </Link>

        <!-- Card -->
        <div class="w-full max-w-md relative z-10">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-black text-white uppercase tracking-tighter">
                    {{ $t('shop.login_title') }}
                </h1>
                <div class="w-12 h-1 bg-savino-fucsia mx-auto mt-3 mb-4"></div>
                <p class="text-white/50 text-sm">{{ $t('shop.login_subtitle') }}</p>
            </div>

            <!-- Status message (e.g. password reset) -->
            <div v-if="status" class="mb-4 text-sm font-medium text-emerald-400 bg-emerald-400/10 border border-emerald-400/20 rounded-lg px-4 py-3 text-center">
                {{ status }}
            </div>

            <!-- Form Card -->
            <div class="bg-white rounded-2xl shadow-[0_20px_60px_rgba(0,0,0,0.4)] overflow-hidden border border-white/10 p-8">
                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <InputLabel for="email" :value="$t('shop.email')" />
                        <TextInput
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                            autofocus
                            autocomplete="username"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="password" :value="$t('shop.password')" />
                        <TextInput
                            id="password"
                            type="password"
                            class="mt-1 block w-full"
                            v-model="form.password"
                            required
                            autocomplete="current-password"
                        />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div class="flex items-center justify-between">
                        <label for="remember" class="flex items-center">
                            <Checkbox id="remember" name="remember" v-model:checked="form.remember" />
                            <span class="ml-2 text-sm text-gray-600">{{ $t('shop.remember_me') }}</span>
                        </label>

                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-sm text-savino-fucsia hover:text-savino-blue transition-colors font-medium"
                        >
                            {{ $t('shop.forgot_password') }}
                        </Link>
                    </div>

                    <div class="pt-2">
                        <button
                            type="submit"
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-sm text-base font-bold uppercase tracking-wider text-white bg-savino-blue hover:bg-savino-blue/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-savino-fucsia transition-all duration-200"
                            :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                            :disabled="form.processing"
                        >
                            {{ $t('shop.login') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Register link -->
            <div class="text-center mt-6 text-sm text-white/60">
                {{ $t('shop.no_account') }}
                <Link :href="route('shop.register')" class="font-bold text-savino-fucsia hover:text-white transition-colors ml-1">
                    {{ $t('shop.register_link') }}
                </Link>
            </div>
        </div>
    </div>
</template>
