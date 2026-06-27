<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    posts: Object,
});
</script>

<template>
    <Head>
        <title>News — Savino Del Bene Volley</title>
        <meta name="description" content="Ultime notizie e comunicati stampa dalla Savino Del Bene Volley. Seguici per restare aggiornato su partite, trasferimenti e attività del club." />
    </Head>

    <PublicLayout>
        <!-- HERO -->
        <section class="relative min-h-[40vh] flex items-center bg-gradient-to-br from-gray-900 via-[#0B1521] to-gray-800">
            <div class="absolute inset-0 opacity-10" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;none&quot; fill-rule=&quot;evenodd&quot;%3E%3Cg fill=&quot;%23C5A55A&quot; fill-opacity=&quot;0.4&quot;%3E%3Cpath d=&quot;M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4z&quot;/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 w-full">
                <h1 class="text-4xl md:text-5xl font-black text-white uppercase tracking-tighter" style="font-family: 'Montserrat', sans-serif;">
                    News & Comunicati
                </h1>
                <div class="w-16 h-1 bg-savino-gold mt-4"></div>
                <p class="text-gray-300 mt-4 text-lg max-w-2xl">
                    Tutte le ultime notizie dal mondo Savino Del Bene Volley.
                </p>
            </div>
        </section>

        <!-- NEWS GRID -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-white">
            <div class="max-w-7xl mx-auto">
                <div v-if="posts?.data?.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <article
                        v-for="post in posts.data"
                        :key="post.id"
                        class="group bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100"
                    >
                        <div class="aspect-video bg-gray-200 overflow-hidden">
                            <img
                                v-if="post.media?.length"
                                :src="post.media[0]?.original_url"
                                :alt="post.title"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                            />
                            <div v-else class="w-full h-full bg-gradient-to-br from-savino-blue to-gray-700 flex items-center justify-center">
                                <span class="text-savino-gold text-5xl font-black opacity-30">SDB</span>
                            </div>
                        </div>
                        <div class="p-6">
                            <time v-if="post.published_at" class="text-xs font-semibold text-savino-gold uppercase tracking-wider">
                                {{ new Date(post.published_at).toLocaleDateString('it-IT', { day: 'numeric', month: 'long', year: 'numeric' }) }}
                            </time>
                            <h2 class="mt-2 text-lg font-bold text-savino-blue group-hover:text-savino-red transition-colors line-clamp-2">
                                <Link :href="`/news/${post.slug}`">
                                    {{ post.title }}
                                </Link>
                            </h2>
                            <p v-if="post.excerpt" class="mt-2 text-gray-600 text-sm line-clamp-3">
                                {{ post.excerpt }}
                            </p>
                            <Link
                                :href="`/news/${post.slug}`"
                                class="inline-flex items-center mt-4 text-sm font-bold text-savino-gold hover:text-savino-red transition-colors uppercase tracking-wider"
                            >
                                Leggi tutto →
                            </Link>
                        </div>
                    </article>
                </div>

                <!-- Empty state -->
                <div v-else class="text-center py-20">
                    <div class="text-6xl mb-4">📰</div>
                    <h2 class="text-2xl font-bold text-savino-blue mb-2">Nessuna notizia pubblicata</h2>
                    <p class="text-gray-500">Le notizie saranno disponibili a breve.</p>
                </div>

                <!-- Pagination -->
                <div v-if="posts?.links?.length > 3" class="mt-12 flex justify-center gap-2">
                    <template v-for="link in posts.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="px-4 py-2 text-sm font-medium border rounded-md transition-colors"
                            :class="link.active ? 'bg-savino-blue text-white border-savino-blue' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="px-4 py-2 text-sm text-gray-400 border border-gray-200 rounded-md"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
