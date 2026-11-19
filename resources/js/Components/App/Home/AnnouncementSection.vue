<script setup>
import { MegaphoneIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';

const announcements = [
    {
        id: 1,
        title: 'Informasi Perubahan Jadwal Patroli',
        summary: 'Penyesuaian jadwal patroli untuk regu malam berlaku mulai hari ini.',
        date: '14 Nov 2026',
    },
    {
        id: 2,
        title: 'Pengingat Briefing Regu',
        summary: 'Briefing regu dilaksanakan 15 menit sebelum pergantian shift.',
        date: '14 Nov 2026',
    },
    {
        id: 3,
        title: 'Pengecekan Titik Rawan',
        summary: 'Pastikan titik rawan area parkir dan gerbang diperiksa sesuai daftar.',
        date: '13 Nov 2026',
    },
];
</script>

<template>
    <section>
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-[#263746]">Pengumuman</h3>
            <button type="button" class="flex items-center gap-0.5 text-xs font-medium text-[#1D567F]">
                Lihat Semua
                <ChevronRightIcon class="h-4 w-4" />
            </button>
        </div>

        <div class="mt-3 space-y-3">
            <article
                v-for="item in announcements"
                :key="item.id"
                class="flex gap-3 rounded-xl border border-[#E8E8EC] bg-white p-3"
            >
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-[#1D567F]">
                    <MegaphoneIcon class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <h4 class="text-sm font-medium text-[#263746]">{{ item.title }}</h4>
                        <span class="shrink-0 text-[11px] text-[#9299A2]">{{ item.date }}</span>
                    </div>
                    <p class="mt-1 text-xs leading-relaxed text-[#9299A2]">{{ item.summary }}</p>
                </div>
            </article>
        </div>
    </section>
</template>
