<script setup>
import { Link } from '@inertiajs/vue3';
import { MegaphoneIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';

defineProps({
    announcements: { type: Array, default: () => [] },
});
</script>

<template>
    <section>
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-[#263746]">Pengumuman</h3>
            <Link href="/app/announcements" class="flex items-center gap-0.5 text-xs font-medium text-[#1D567F]">
                Lihat Semua
                <ChevronRightIcon class="h-4 w-4" />
            </Link>
        </div>

        <div v-if="announcements.length" class="mt-3 space-y-3">
            <Link
                v-for="item in announcements"
                :key="item.id"
                :href="`/app/announcements/${item.id}`"
                class="flex gap-3 rounded-xl border border-[#E8E8EC] bg-white p-3 transition hover:border-[#1D567F]/30"
            >
                <img
                    v-if="item.image_url"
                    :src="item.image_url"
                    :alt="item.title"
                    class="h-14 w-14 shrink-0 rounded-lg object-cover"
                />
                <div v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-[#1D567F]">
                    <MegaphoneIcon class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <h4 class="line-clamp-2 text-sm font-medium text-[#263746]">{{ item.title }}</h4>
                        <span class="shrink-0 text-[11px] text-[#9299A2]">{{ item.date_label }}</span>
                    </div>
                    <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-[#9299A2]">{{ item.excerpt }}</p>
                </div>
            </Link>
        </div>

        <div v-else class="mt-3 rounded-xl border border-dashed border-[#E8E8EC] bg-gray-50 p-4 text-center text-xs text-[#9299A2]">
            Belum ada pengumuman.
        </div>
    </section>
</template>
