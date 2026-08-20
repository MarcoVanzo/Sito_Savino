<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useTranslations } from '@/Composables/useTranslations.js';

/**
 * Preavviso di scadenza password.
 *
 * La policy vale per tutti gli utenti, clienti shop compresi: loro il pannello
 * CMS non lo vedono mai, quindi questo banner è il loro unico avviso.
 */
const page = usePage();
const $t = useTranslations();

const expiry = computed(() => page.props.auth?.passwordExpiry ?? null);

const message = computed(() => {
    if (!expiry.value) return '';

    const days = expiry.value.days;

    if (days <= 1) {
        return $t('password_expiry.today');
    }

    return $t('password_expiry.days', { days });
});
</script>

<template>
    <div
        v-if="expiry"
        role="status"
        class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900"
    >
        <svg
            class="h-5 w-5 shrink-0"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            aria-hidden="true"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
            />
        </svg>

        <span>
            {{ message }} {{ $t('password_expiry.cta_hint') }}
        </span>

        <Link
            :href="route('password.change')"
            class="font-semibold underline underline-offset-2 hover:no-underline"
        >
            {{ $t('password_expiry.change') }}
        </Link>
    </div>
</template>
