<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    page: {
        type: Object,
        default: null,
    }
});
</script>

<template>
        <Head>
        <title>{{ page?.meta_title || page?.title || 'Savino Del Bene Volley' }}</title>
        <meta v-if="page?.meta_description" name="description" :content="page.meta_description" />
    </Head>
    <PublicLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 font-bold text-2xl">Area SDB Youth</div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
