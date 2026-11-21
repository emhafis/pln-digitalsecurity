
<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    linkHref: { type: String, default: '' },
    linkLabel: { type: String, default: 'Lihat semua' },
});
</script>

<template>
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h2 class="text-[16px] font-semibold tracking-[-.025em] text-[#26343C]">
                {{ title }}
            </h2>

            <p
                v-if="description"
                class="mt-1 text-[12px] leading-relaxed text-[#89918F]"
            >
                {{ description }}
            </p>
        </div>

        <Link
            v-if="linkHref"
            :href="linkHref"
            class="inline-flex shrink-0 items-center gap-1 text-xs font-medium text-[#386B70] transition hover:text-[#16866B]"
        >
            {{ linkLabel }}
            <ArrowTopRightOnSquareIcon class="h-3.5 w-3.5" aria-hidden="true" />
        </Link>

        <slot v-else name="action" />
    </div>
</template>
