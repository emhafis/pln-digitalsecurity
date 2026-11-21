<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppHeader from '@/Components/App/AppHeader.vue';
import BottomNavigation from '@/Components/App/Home/BottomNavigation.vue';
import { MegaphoneIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';

defineProps({
    announcements: { type: Array, default: () => [] },
});
</script>

<template>
    <AppLayout>
        <div class="relative w-full bg-white">
            <AppHeader title="Pengumuman" back-url="/app" back-label="Beranda" />

            <main class="space-y-3 px-5 pb-28 pt-5">
                <template v-if="announcements.length">
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
                                <h2 class="line-clamp-2 text-sm font-medium text-[#263746]">{{ item.title }}</h2>
                                <span class="shrink-0 text-[11px] text-[#9299A2]">{{ item.date_label }}</span>
                            </div>
                            <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-[#9299A2]">{{ item.excerpt }}</p>
                        </div>
                        <ChevronRightIcon class="h-4 w-4 shrink-0 self-center text-[#9299A2]" />
                    </Link>
                </template>

                <div v-else class="rounded-xl border border-dashed border-[#E8E8EC] bg-gray-50 p-6 text-center">
                    <p class="text-sm font-medium text-[#263746]">Belum ada pengumuman</p>
                    <p class="mt-1 text-xs text-[#9299A2]">Pengumuman aktif akan muncul di sini.</p>
                </div>
            </main>
        </div>

        <template #bottom>
            <BottomNavigation active="home" />
        </template>
    </AppLayout>
</template>
