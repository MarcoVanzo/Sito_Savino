<script setup>
import { ref, onUnmounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const messages = ref([]);
let nextId = 0;
// Set (non array): gli id vanno rimossi quando l'interval finisce, altrimenti
// la lista cresce senza limiti a ogni flash message della sessione.
const activeIntervals = new Set();

const typeConfig = {
    success: { bg: 'bg-green-900/90', border: 'border-l-green-500', text: 'text-green-400', progress: 'bg-green-500', icon: 'check' },
    error: { bg: 'bg-red-900/90', border: 'border-l-red-500', text: 'text-red-400', progress: 'bg-red-500', icon: 'x' },
    warning: { bg: 'bg-amber-900/90', border: 'border-l-amber-500', text: 'text-amber-400', progress: 'bg-amber-500', icon: 'warning' },
    info: { bg: 'bg-blue-900/90', border: 'border-l-blue-500', text: 'text-blue-400', progress: 'bg-blue-500', icon: 'info' },
};

const addMessage = (type, text) => {
    if (!text) return;
    const id = nextId++;
    messages.value.push({ id, type, text, progress: 100 });
    const stop = () => {
        clearInterval(interval);
        activeIntervals.delete(interval);
    };
    const interval = setInterval(() => {
        const msg = messages.value.find(m => m.id === id);
        if (msg) {
            msg.progress -= 2;
            if (msg.progress <= 0) {
                stop();
                dismiss(id);
            }
        } else {
            stop();
        }
    }, 100);
    activeIntervals.add(interval);
};

const dismiss = (id) => {
    messages.value = messages.value.filter(m => m.id !== id);
};

const checkFlash = () => {
    const flash = usePage().props.flash || {};
    ['success', 'error', 'warning', 'info'].forEach(type => {
        if (flash[type]) addMessage(type, flash[type]);
    });
};

checkFlash();

const removeListener = router.on('finish', () => {
    setTimeout(checkFlash, 50);
});

onUnmounted(() => {
    activeIntervals.forEach(clearInterval);
    activeIntervals.clear();
    if (removeListener) removeListener();
});
</script>

<template>
    <div class="fixed top-4 right-4 z-[100] flex flex-col gap-3 max-w-sm w-full pointer-events-none">
        <TransitionGroup
            enter-active-class="transition-all duration-300 ease-out"
            leave-active-class="transition-all duration-200 ease-in"
            enter-from-class="translate-x-full opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-from-class="translate-x-0 opacity-100"
            leave-to-class="translate-x-full opacity-0"
        >
            <div
                v-for="msg in messages"
                :key="msg.id"
                class="pointer-events-auto rounded-xl shadow-2xl backdrop-blur-sm border-l-4 overflow-hidden"
                :class="[typeConfig[msg.type].bg, typeConfig[msg.type].border]"
            >
                <div class="flex items-start gap-3 p-4">
                    <!-- Icon -->
                    <div class="flex-shrink-0 mt-0.5">
                        <!-- Check -->
                        <svg v-if="msg.type === 'success'" class="w-5 h-5" :class="typeConfig[msg.type].text" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <!-- X Circle -->
                        <svg v-else-if="msg.type === 'error'" class="w-5 h-5" :class="typeConfig[msg.type].text" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <!-- Warning Triangle -->
                        <svg v-else-if="msg.type === 'warning'" class="w-5 h-5" :class="typeConfig[msg.type].text" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                        <!-- Info Circle -->
                        <svg v-else class="w-5 h-5" :class="typeConfig[msg.type].text" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                        </svg>
                    </div>
                    <!-- Text -->
                    <p class="flex-1 text-sm font-medium text-white/90">{{ msg.text }}</p>
                    <!-- Dismiss -->
                    <button type="button" @click="dismiss(msg.id)" class="flex-shrink-0 text-white/50 hover:text-white transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <!-- Progress Bar -->
                <div class="h-0.5 w-full bg-white/10">
                    <div
                        class="h-full transition-all duration-100 ease-linear"
                        :class="typeConfig[msg.type].progress"
                        :style="{ width: msg.progress + '%' }"
                    ></div>
                </div>
            </div>
        </TransitionGroup>
    </div>
</template>
