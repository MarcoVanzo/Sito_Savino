<script setup>
defineProps({
    subtitle: {
        type: String,
        default: ''
    },
    title: {
        type: String,
        required: true
    },
    description: {
        type: String,
        default: ''
    },
    pattern: {
        type: Boolean,
        default: false
    }
})
</script>

<template>
    <section class="relative min-h-[40vh] flex items-center justify-center overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-savino-blue to-gray-900"></div>
        <!-- Geometric Pattern Overlay (optional) -->
        <div
            v-if="pattern"
            class="absolute inset-0 opacity-[0.04]"
            style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M30 0L60 30L30 60L0 30Z&quot; fill=&quot;%23C5A55A&quot; fill-opacity=&quot;1&quot;/%3E%3C/svg%3E'); background-size: 60px 60px;"
        ></div>
        <div class="absolute inset-0 bg-gradient-to-t from-savino-blue/80 to-transparent"></div>
        <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
            <div v-if="subtitle" class="inline-block mb-6">
                <span class="text-savino-gold text-sm font-bold uppercase tracking-[0.3em]">{{ subtitle }}</span>
            </div>
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white uppercase tracking-tighter mb-6">
                {{ title }}
            </h1>
            <div class="w-16 h-1 bg-savino-gold mx-auto mt-4 mb-8"></div>
            <p v-if="description" class="text-white/70 text-lg md:text-xl max-w-3xl mx-auto leading-relaxed">
                {{ description }}
            </p>
            <!-- Slot for additional hero content -->
            <slot />
        </div>
    </section>
</template>
