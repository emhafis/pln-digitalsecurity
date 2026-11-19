<script setup>
import { Link } from '@inertiajs/vue3';
import {
    ClipboardDocumentCheckIcon,
    PencilSquareIcon,
    UsersIcon,
    ChartBarIcon,
    CloudArrowDownIcon,
    ChevronRightIcon,
} from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppHeader from '@/Components/App/AppHeader.vue';

import AttendanceCard from '@/Components/App/Home/AttendanceCard.vue';
import BottomNavigation from '@/Components/App/Home/BottomNavigation.vue';

const props = defineProps({
    security: { type: Object, default: null },
    operational_date: { type: Object, default: null },
    state: { type: String, default: '' },
    schedule: { type: Object, default: null },
    shift: { type: Object, default: null },
    post: { type: Object, default: null },
    attendance: { type: Object, default: null },
    windows: { type: Object, default: null },
});

const features = [
    { key: 'history', label: 'Riwayat Kehadiran', desc: 'Lihat catatan kehadiran', icon: ClipboardDocumentCheckIcon, href: '/app/attendance/history' },
    { key: 'correction', label: 'Pengajuan Koreksi', desc: 'Ajukan koreksi kehadiran', icon: PencilSquareIcon, href: '/app/attendance/correction' },
    { key: 'team', label: 'Tim Saya', desc: 'Informasi anggota regu', icon: UsersIcon, href: null },
    { key: 'recap', label: 'Rekap Kehadiran', desc: 'Ringkasan periode kerja', icon: ChartBarIcon, href: null },
    { key: 'offline', label: 'Kehadiran Offline', desc: 'Sinkronisasi data lokal', icon: CloudArrowDownIcon, href: null },
];
</script>

<template>
    <AppLayout>
        <div class="relative w-full bg-white">
            <AppHeader title="Kehadiran" backUrl="/app" />

            <main class="space-y-5 px-5 pb-28 pt-5">
                <AttendanceCard
                    variant="page"
                    :operational_date="props.operational_date"
                    :state="props.state"
                    :schedule="props.schedule"
                    :shift="props.shift"
                    :post="props.post"
                    :attendance="props.attendance"
                    :windows="props.windows"
                />

                <section>
                    <h3 class="mb-3 text-sm font-semibold text-[#263746]">Fitur Tersedia</h3>
                    <div class="divide-y divide-[#E8E8EC] overflow-hidden rounded-xl border border-[#E8E8EC] bg-white">
                        <component
                            :is="item.href ? Link : 'div'"
                            v-for="item in features"
                            :key="item.key"
                            :href="item.href || undefined"
                            class="flex w-full items-center gap-3 px-4 py-3 text-left"
                        >
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#E8F0F6] text-[#1D567F]">
                                <component :is="item.icon" class="h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-[#263746]">{{ item.label }}</p>
                                <p class="text-xs text-[#9299A2]">{{ item.desc }}</p>
                            </div>
                            <ChevronRightIcon class="h-4 w-4 shrink-0 text-[#9299A2]" />
                        </component>
                    </div>
                </section>
            </main>
        </div>

        <template #bottom>
            <BottomNavigation active="attendance" />
        </template>
    </AppLayout>
</template>
