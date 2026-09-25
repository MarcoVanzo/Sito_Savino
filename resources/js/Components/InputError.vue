<script setup>
defineProps({
    message: {
        type: String,
    },
    // Su una scheda scura il rosso 600 non si legge (circa 3:1 su gray-800):
    // i moduli su fondo scuro, come la registrazione dello shop, passano `scuro`.
    scuro: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div v-show="message" role="alert">
        <p class="text-sm" :class="scuro ? 'text-red-300' : 'text-red-600 dark:text-red-400'">
            {{ message }}
        </p>
    </div>
</template>
