<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import AppHeader from '@/Components/App/AppHeader.vue';
import BottomNavigation from '@/Components/App/Home/BottomNavigation.vue';
import { MegaphoneIcon } from '@heroicons/vue/24/outline';

defineProps({
    announcement: { type: Object, required: true },
});
</script>

<template>
    <AppLayout>
        <div class="relative w-full bg-white">
            <AppHeader title="Detail Pengumuman" back-url="/app/announcements" back-label="Pengumuman" />

            <main class="px-5 pb-28 pt-5">
                <article class="overflow-hidden rounded-xl border border-[#E8E8EC] bg-white">
                    <img v-if="announcement.image_url" :src="announcement.image_url" :alt="announcement.title" class="max-h-72 w-full object-cover" />
                    <div class="p-4">
                        <div class="flex items-start gap-3">
                            <div v-if="!announcement.image_url" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-[#1D567F]">
                                <MegaphoneIcon class="h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-[11px] text-[#9299A2]">{{ announcement.date_label }}</p>
                                <h1 class="mt-1 text-base font-semibold text-[#263746]">{{ announcement.title }}</h1>
                            </div>
                        </div>
                        <p class="mt-4 whitespace-pre-line text-sm leading-relaxed text-[#5F6B76]">{{ announcement.content }}</p>
                    </div>
                </article>
            </main>
        </div>

        <template #bottom>
            <BottomNavigation active="home" />
        </template>
    </AppLayout>
</template>
