<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';
import { useImageFallback } from '@/Composables/useImageFallback.js';
import LOGOS from '@/Constants/logos.js';

const { onImgError } = useImageFallback();

const props = defineProps({
    navigation: {
        type: Array,
        required: true,
    },
});

const page = usePage();

// --- Keyboard accessibility ---
const openIndex = ref(-1);

function toggleDropdown(index) {
    if (props.navigation[index]?.children?.length > 0) {
        openIndex.value = openIndex.value === index ? -1 : index;
    }
}

function closeDropdown() {
    openIndex.value = -1;
}

function handleKeydown(event, index) {
    const hasSubmenu = props.navigation[index]?.children?.length > 0;

    switch (event.key) {
        case 'Enter':
        case ' ':
            if (hasSubmenu) {
                event.preventDefault();
                toggleDropdown(index);
            }
            break;
        case 'Escape':
            event.preventDefault();
            closeDropdown();
            // Return focus to the trigger
            const trigger = navRef.value?.querySelector(`[data-menu-index="${index}"] a`);
            trigger?.focus();
            break;
        case 'ArrowDown':
            if (hasSubmenu && openIndex.value === index) {
                event.preventDefault();
                // Focus first link in dropdown
                const firstLink = navRef.value?.querySelector(`[data-dropdown-index="${index}"] a`);
                firstLink?.focus();
            }
            break;
    }
}

function handleDropdownKeydown(event, parentIndex) {
    if (event.key === 'Escape') {
        event.preventDefault();
        closeDropdown();
        const trigger = navRef.value?.querySelector(`[data-menu-index="${parentIndex}"] a`);
        trigger?.focus();
    }
}

// Close dropdown when clicking outside
function handleClickOutside(event) {
    if (navRef.value && !navRef.value.contains(event.target)) {
        closeDropdown();
    }
}

// Dynamic positioning for dropdown cards.
// We calculate the pixel `left` of each dropdown relative to the
// header wrapper (max-w-7xl container) so the card never overflows
// the viewport.  Using a `fixed`-like approach via the nav's own
// coordinate system avoids issues with overflow-x-hidden on ancestors.

const navRef = ref(null);
const dropdownStyles = ref({});

function recalcPositions() {
    if (!navRef.value) return;

    // The dropdown is position:absolute. Its containing block is the
    // nearest positioned ancestor — the max-w-7xl div with `relative`
    // in PublicLayout.  Walk up the DOM to find it.
    let containingBlock = navRef.value.parentElement;
    while (containingBlock && getComputedStyle(containingBlock).position === 'static') {
        containingBlock = containingBlock.parentElement;
    }
    if (!containingBlock) containingBlock = document.documentElement;

    const cbRect = containingBlock.getBoundingClientRect();
    const viewportW = window.innerWidth;
    const cardW = 720; // matches w-[720px] in the template

    props.navigation.forEach((item, index) => {
        if (!(item.children && item.children.length > 0)) return;

        // Find the menu-item DOM node via data attribute
        const itemEl = navRef.value.querySelector(`[data-menu-index="${index}"]`);
        if (!itemEl) return;

        const itemRect = itemEl.getBoundingClientRect();
        const itemCenterX = itemRect.left + itemRect.width / 2;

        // Ideal: center the card under the item
        let idealLeft = itemCenterX - cardW / 2;

        // Clamp so it doesn't overflow the viewport (16px gutter)
        const minLeft = 16;
        const maxLeft = viewportW - cardW - 16;
        idealLeft = Math.max(minLeft, Math.min(maxLeft, idealLeft));

        // Convert from viewport coords to containing-block-relative coords
        const relativeLeft = idealLeft - cbRect.left;

        dropdownStyles.value[index] = {
            left: `${relativeLeft}px`,
            right: 'auto',
        };
    });
}

let resizeTimer = null;
function debouncedRecalc() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(recalcPositions, 150);
}

onMounted(() => {
    nextTick(recalcPositions);
    window.addEventListener('resize', debouncedRecalc);
    document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', debouncedRecalc);
    document.removeEventListener('click', handleClickOutside);
    clearTimeout(resizeTimer);
});
</script>

<template>
    <nav ref="navRef" role="navigation" aria-label="Navigazione principale" class="hidden lg:flex flex-1 justify-end items-center h-full">
        <div class="flex items-center h-full">
            <template v-for="(item, index) in navigation" :key="item.label">
                <div :data-menu-index="index" class="group h-full flex items-center" style="position: static;" @mouseenter="item.children?.length > 0 ? openIndex = index : null" @mouseleave="closeDropdown">
                    <Link 
                        :href="item.href" 
                        prefetch
                        class="text-[12px] xl:text-[14px] font-black tracking-wider uppercase transition-colors flex items-center h-full px-1.5 lg:px-2.5 whitespace-nowrap"
                        :class="[
                            $page.url.startsWith(item.href) ? 'text-white border-b-[3px] border-savino-gold pt-[3px]' : 'text-gray-400 hover:text-white border-b-[3px] border-transparent pt-[3px]',
                            item.isHighlight ? 'text-[#ED028C] hover:text-[#ff30a6]' : ''
                        ]"
                        :aria-haspopup="item.children?.length > 0 ? 'true' : undefined"
                        :aria-expanded="item.children?.length > 0 ? (openIndex === index).toString() : undefined"
                        @keydown="handleKeydown($event, index)"
                    >
                        {{ item.label }}
                    </Link>

                    <!-- Sottomenu Mega Dropdown — positioned via JS relative to <nav> -->
                    <div
                        v-if="item.children && item.children.length > 0"
                        :data-dropdown-index="index"
                        class="absolute top-full pt-4 transition-all duration-300 z-50"
                        :class="openIndex === index ? 'opacity-100 visible' : 'opacity-0 invisible'"
                        :style="dropdownStyles[index] || {}"
                        role="menu"
                        :aria-label="item.label + ' sottomenu'"
                        @keydown="handleDropdownKeydown($event, index)"
                    >
                        <div class="flex bg-white/95 backdrop-blur-xl shadow-[0_30px_60px_rgba(0,0,0,0.4)] border border-white/20 rounded-2xl overflow-hidden transition-transform duration-300 ease-out w-[720px] min-h-[320px]" :class="openIndex === index ? 'translate-y-0' : 'translate-y-4 group-hover:translate-y-0'">
                            <!-- Left side with links -->
                            <div class="w-3/5 p-10 flex flex-col">
                                <div class="mb-8">
                                    <h3 class="text-2xl font-black text-savino-blue uppercase tracking-tighter mb-2">{{ item.label }}</h3>
                                    <div class="w-16 h-1 bg-savino-gold"></div>
                                </div>
                                <div class="grid grid-cols-2 gap-4 flex-1 content-start">
                                    <Link 
                                        v-for="sub in item.children" 
                                        :key="sub.label"
                                        :href="sub.href"
                                        prefetch
                                        role="menuitem"
                                        class="flex flex-col p-4 rounded-xl border border-gray-100 hover:border-savino-gold/50 bg-gray-50 hover:bg-white hover:shadow-xl transition-all duration-300 group/link"
                                    >
                                        <span class="text-[14px] font-black text-savino-blue uppercase tracking-wider mb-1 flex justify-between items-center">
                                            {{ sub.label }}
                                            <svg class="w-5 h-5 text-savino-gold opacity-0 -translate-x-2 group-hover/link:opacity-100 group-hover/link:translate-x-0 transition-all duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                                        </span>
                                        <span class="text-xs text-gray-500 font-medium">{{ sub.description || 'Esplora la sezione dedicata' }}</span>
                                    </Link>
                                </div>
                            </div>
                            <!-- Right side — per-topic image with blue overlay -->
                            <div class="w-2/5 relative overflow-hidden">
                                <!-- Topic image (lazy-loaded) -->
                                <img 
                                    :src="item.menuImage || LOGOS.VOLLEY" 
                                    :alt="item.label"
                                    loading="lazy"
                                    class="absolute inset-0 w-full h-full object-cover scale-110 transition-transform duration-[6s] ease-out group-hover:scale-125"
                                    @error="onImgError"
                                />
                                <!-- Blue overlay — 70% opacity to let the photo show through -->
                                <div class="absolute inset-0 bg-gradient-to-br from-savino-blue/70 to-[#001a40]/80"></div>

                                <!-- Content on top of the image -->
                                <div class="relative z-10 p-10 flex flex-col justify-between h-full">


                                    <div>
                                        <div class="w-12 h-1 bg-savino-red mb-4"></div>
                                        <p class="text-white text-3xl font-serif font-bold italic leading-tight mb-2"><template v-for="(line, i) in (item.mottoTitle || 'Eccellenza<br/>nello Sport.').split('<br/>')" :key="i">{{ line }}<br v-if="i < (item.mottoTitle || 'Eccellenza<br/>nello Sport.').split('<br/>').length - 1" /></template></p>
                                        <p class="text-gray-300 text-sm font-medium">{{ item.mottoSubtitle || 'Passione, determinazione e orgoglio in ogni partita.' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Vertical separator | -->
                <div v-if="index < navigation.length - 1" class="text-white/20 select-none text-[10px] mx-1">|</div>
            </template>
        </div>
        

    </nav>
</template>
