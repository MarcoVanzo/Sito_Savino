<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
    endDate: { type: String, required: true },
    isActive: { type: Boolean, default: true },
});

const emit = defineEmits(['ending-soon', 'ended']);

const now = ref(Date.now());
const wasExtended = ref(false);
const previousEndDate = ref(props.endDate);
let timer = null;

const targetTime = computed(() => new Date(props.endDate).getTime());

const remaining = computed(() => {
    const diff = targetTime.value - now.value;
    if (diff <= 0) return { days: 0, hours: 0, minutes: 0, seconds: 0, total: 0 };

    return {
        days: Math.floor(diff / (1000 * 60 * 60 * 24)),
        hours: Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)),
        minutes: Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60)),
        seconds: Math.floor((diff % (1000 * 60)) / 1000),
        total: diff,
    };
});

const isEnded = computed(() => remaining.value.total <= 0);
const isEndingSoon = computed(() => remaining.value.total > 0 && remaining.value.total <= 5 * 60 * 1000);
const isWarning = computed(() => remaining.value.total > 0 && remaining.value.total <= 30 * 60 * 1000);

const colorClass = computed(() => {
    if (isEnded.value) return 'text-gray-400';
    if (isEndingSoon.value) return 'text-red-400';
    if (isWarning.value) return 'text-amber-400';
    return 'text-emerald-400';
});

const bgClass = computed(() => {
    if (isEnded.value) return 'bg-gray-800/50';
    if (isEndingSoon.value) return 'bg-red-900/30 border border-red-500/30';
    if (isWarning.value) return 'bg-amber-900/20 border border-amber-500/20';
    return 'bg-emerald-900/20 border border-emerald-500/20';
});

const pad = (n) => String(n).padStart(2, '0');

// Detect anti-sniping extension
watch(() => props.endDate, (newVal) => {
    if (newVal !== previousEndDate.value) {
        wasExtended.value = true;
        previousEndDate.value = newVal;
        setTimeout(() => { wasExtended.value = false; }, 5000);
    }
});

// Emit events
watch(isEndingSoon, (val) => { if (val) emit('ending-soon'); });
watch(isEnded, (val) => { if (val) emit('ended'); });

onMounted(() => {
    timer = setInterval(() => { now.value = Date.now(); }, 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>

<template>
    <div :class="['rounded-xl px-4 py-3 transition-all duration-500', bgClass]">
        <!-- Extended flash -->
        <div
            v-if="wasExtended"
            class="text-amber-300 text-xs font-bold uppercase tracking-wider mb-2 flex items-center gap-1 animate-pulse"
        >
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
            </svg>
            {{ $t('auction.time_extended') || 'Tempo esteso!' }}
        </div>

        <div v-if="isEnded && !isActive" class="text-center">
            <span class="text-gray-400 font-bold text-sm uppercase tracking-wider">
                {{ $t('auction.ended') || 'Asta terminata' }}
            </span>
        </div>

        <div v-else class="flex items-center justify-center gap-3 sm:gap-4">
            <!-- Days -->
            <div v-if="remaining.days > 0" class="text-center">
                <span :class="['text-2xl sm:text-3xl font-black tabular-nums', colorClass]">
                    {{ remaining.days }}
                </span>
                <span class="block text-[10px] uppercase tracking-wider text-gray-400 mt-0.5">g</span>
            </div>

            <!-- Hours -->
            <div class="text-center">
                <span :class="['text-2xl sm:text-3xl font-black tabular-nums', colorClass]">
                    {{ pad(remaining.hours) }}
                </span>
                <span class="block text-[10px] uppercase tracking-wider text-gray-400 mt-0.5">h</span>
            </div>

            <span :class="['text-xl font-bold', colorClass, { 'animate-pulse': isEndingSoon }]">:</span>

            <!-- Minutes -->
            <div class="text-center">
                <span :class="['text-2xl sm:text-3xl font-black tabular-nums', colorClass]">
                    {{ pad(remaining.minutes) }}
                </span>
                <span class="block text-[10px] uppercase tracking-wider text-gray-400 mt-0.5">m</span>
            </div>

            <span :class="['text-xl font-bold', colorClass, { 'animate-pulse': isEndingSoon }]">:</span>

            <!-- Seconds -->
            <div class="text-center">
                <span :class="['text-2xl sm:text-3xl font-black tabular-nums', colorClass, { 'animate-pulse': isEndingSoon }]">
                    {{ pad(remaining.seconds) }}
                </span>
                <span class="block text-[10px] uppercase tracking-wider text-gray-400 mt-0.5">s</span>
            </div>
        </div>
    </div>
</template>
