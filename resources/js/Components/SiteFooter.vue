<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <footer role="contentinfo" class="bg-gray-900 text-gray-400 py-12 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center">
            <div class="mb-4 md:mb-0">
                <span class="font-serif text-xl font-bold text-white">Savino Del Bene Volley</span>
                <p class="text-sm mt-2">© {{ new Date().getFullYear() }} Tutti i diritti riservati.</p>
            </div>
            <div class="flex items-center space-x-6">
                <a href="#" class="hover:text-white" aria-label="Facebook di Savino Del Bene Volley">Facebook</a>
                <a href="#" class="hover:text-white" aria-label="Instagram di Savino Del Bene Volley">Instagram</a>
                <a href="#" class="hover:text-white" aria-label="YouTube di Savino Del Bene Volley">YouTube</a>
                <span class="text-gray-700">|</span>
                <Link href="/privacy-policy" class="text-xs hover:text-white">Privacy Policy</Link>
                <Link href="/cookie-policy" class="text-xs hover:text-white">Cookie Policy</Link>
            </div>
        </div>
    </footer>
</template>
