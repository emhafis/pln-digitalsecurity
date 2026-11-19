
<script setup>
import { Link } from '@inertiajs/vue3';
import {
    FingerPrintIcon,
    WrenchScrewdriverIcon,
    FireIcon,
    MapPinIcon,
    ClockIcon,
    UserCircleIcon,
} from '@heroicons/vue/24/outline';

const actions = [
    {
        label: 'Absensi',
        icon: FingerPrintIcon,
        href: '/app/attendance',
        color: 'text-[#1D567F]',
        background: 'bg-[#EAF1F7]',
    },
    {
        label: 'Hydrant',
        icon: WrenchScrewdriverIcon,
        href: '/app/hydrant',
        color: 'text-rose-600',
        background: 'bg-rose-50',
    },
    {
        label: 'APAR',
        icon: FireIcon,
        href: '/app/apar',
        color: 'text-[#1D567F]',
        background: 'bg-[#EAF1F7]',
    },
    {
        label: 'Patroli',
        icon: MapPinIcon,
        href: '/app/patrol',
        color: 'text-teal-600',
        background: 'bg-teal-50',
    },
    {
        label: 'Riwayat',
        icon: ClockIcon,
        href: '/app/attendance/history',
        color: 'text-indigo-600',
        background: 'bg-indigo-50',
    },
    {
        label: 'Profil',
        icon: UserCircleIcon,
        href: '/app/profile',
        color: 'text-rose-600',
        background: 'bg-rose-50',
    },
];
</script>

<template>
    <section>
        <div class="grid grid-cols-4 gap-x-2 gap-y-5">
            <Link
                v-for="action in actions"
                :key="action.label"
                :href="action.href"
                class="group flex min-w-0 flex-col items-center gap-2 rounded-lg py-1 transition-colors active:bg-gray-50"
            >
                <span
                    class="flex h-12 w-12 items-center justify-center rounded-2xl transition-transform duration-200 group-active:scale-95"
                    :class="action.background"
                >
                    <component
                        :is="action.icon"
                        class="h-6 w-6"
                        :class="action.color"
                        aria-hidden="true"
                    />
                </span>

                <span class="text-center text-xs font-medium text-gray-700">
                    {{ action.label }}
                </span>
            </Link>
        </div>
    </section>
</template>
