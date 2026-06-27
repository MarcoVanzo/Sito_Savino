<script setup>
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    navigation: {
        type: Array,
        required: true,
    },
});

const page = usePage();
</script>

<template>
    <nav role="navigation" aria-label="Navigazione principale" class="hidden lg:flex flex-1 justify-end items-center h-full">
        <div class="flex items-center h-full">
            <template v-for="(item, index) in navigation" :key="item.name">
                <div class="group static h-full flex items-center">
                    <Link 
                        :href="item.path" 
                        class="text-[12px] xl:text-[14px] font-black tracking-wider uppercase transition-colors flex items-center h-full px-2 lg:px-3 whitespace-nowrap"
                        :class="[
                            $page.url.startsWith(item.path) ? 'text-white border-b-[3px] border-savino-gold pt-[3px]' : 'text-gray-400 hover:text-white border-b-[3px] border-transparent pt-[3px]',
                            item.isHighlight ? 'text-[#ED028C] hover:text-[#ff30a6]' : ''
                        ]"
                    >
                        {{ item.name }}
                        <svg v-if="item.items && item.items.length > 0" class="w-3 h-3 ml-1.5 opacity-70 transition-transform duration-300 group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" /></svg>
                    </Link>

                    <!-- Sottomenu Mega Dropdown Glass -->
                    <div v-if="item.items && item.items.length > 0" class="absolute top-[85px] left-0 w-full pt-4 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-50 px-4 sm:px-6 lg:px-8">
                        <div class="flex bg-white/95 backdrop-blur-xl shadow-[0_30px_60px_rgba(0,0,0,0.4)] border border-white/20 rounded-2xl overflow-hidden translate-y-4 group-hover:translate-y-0 transition-transform duration-300 ease-out max-w-5xl mx-auto min-h-[350px]">
                            <!-- Left side with links -->
                            <div class="w-3/5 p-10 flex flex-col">
                                <div class="mb-8">
                                    <h3 class="text-2xl font-black text-savino-blue uppercase tracking-tighter mb-2">{{ item.name }}</h3>
                                    <div class="w-16 h-1 bg-savino-gold"></div>
                                </div>
                                <div class="grid grid-cols-2 gap-4 flex-1 content-start">
                                    <Link 
                                        v-for="sub in item.items" 
                                        :key="sub.name"
                                        :href="sub.href"
                                        class="flex flex-col p-4 rounded-xl border border-gray-100 hover:border-savino-gold/50 bg-gray-50 hover:bg-white hover:shadow-xl transition-all duration-300 group/link"
                                    >
                                        <span class="text-[14px] font-black text-savino-blue uppercase tracking-wider mb-1 flex justify-between items-center">
                                            {{ sub.name }}
                                            <svg class="w-5 h-5 text-savino-gold opacity-0 -translate-x-2 group-hover/link:opacity-100 group-hover/link:translate-x-0 transition-all duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                                        </span>
                                        <span class="text-xs text-gray-500 font-medium">Esplora la sezione dedicata</span>
                                    </Link>
                                </div>
                            </div>
                            <!-- Right side branding/visual -->
                            <div class="w-2/5 bg-gradient-to-br from-savino-blue to-[#001a40] relative p-10 flex flex-col justify-between overflow-hidden">
                                <div class="absolute inset-0 bg-[url('/images/logo.png')] opacity-[0.07] bg-no-repeat bg-center bg-contain scale-150 transform transition-transform duration-[10s] group-hover:scale-[1.8]"></div>
                                
                                <!-- Decorative elements -->
                                <div class="relative z-10 flex justify-end">
                                    <svg class="w-12 h-12 text-savino-gold opacity-50" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 22h20L12 2zm0 4.5l6.5 13h-13L12 6.5z"/></svg>
                                </div>

                                <div class="relative z-10">
                                    <div class="w-12 h-1 bg-savino-red mb-4"></div>
                                    <p class="text-white text-3xl font-serif font-bold italic leading-tight mb-2"><template v-for="(line, i) in (item.mottoTitle || 'Eccellenza<br/>nello Sport.').split('<br/>')" :key="i">{{ line }}<br v-if="i < (item.mottoTitle || 'Eccellenza<br/>nello Sport.').split('<br/>').length - 1" /></template></p>
                                    <p class="text-gray-300 text-sm font-medium">{{ item.mottoSubtitle || 'Passione, determinazione e orgoglio in ogni partita.' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Vertical separator | -->
                <div v-if="index < navigation.length - 1" class="text-white/20 select-none text-[10px] mx-1">|</div>
            </template>
        </div>
        
        <!-- Right Icons -->
        <div class="flex items-center gap-5 ml-8 border-l border-white/10 pl-8 h-[40px]">
            <!-- Globe Icon -->
            <button aria-label="Cambia lingua" class="text-gray-400 hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </button>
            <!-- Search Icon -->
            <button aria-label="Cerca" class="text-gray-400 hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </button>
            <!-- Hamburger Icon -->
            <button aria-label="Menu completo" class="text-gray-400 hover:text-white transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
        </div>
    </nav>
</template>
