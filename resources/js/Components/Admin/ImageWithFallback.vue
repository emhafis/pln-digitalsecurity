<script setup>
import { ref, watch } from 'vue';
import { PhotoIcon } from '@heroicons/vue/24/outline';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    src: { type: String, default: '' },
    alt: { type: String, default: '' },
});

const failed = ref(false);

watch(
    () => props.src,
    () => {
        failed.value = false;
    },
);
</script>

<template>
    <img
        v-if="props.src && !failed"
        v-bind="$attrs"
        :src="props.src"
        :alt="props.alt"
        @error="failed = true"
    />
    <div
        v-else
        v-bind="$attrs"
        class="flex flex-col items-center justify-center gap-1 bg-gray-100 text-gray-400"
        role="img"
        :aria-label="props.alt || 'Foto tidak tersedia'"
    >
        <PhotoIcon class="h-6 w-6" />
        <span class="px-2 text-center text-[10px] font-medium leading-tight">Foto tidak tersedia</span>
    </div>
</template>
