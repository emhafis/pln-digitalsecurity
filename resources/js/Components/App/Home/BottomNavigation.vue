<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    HomeIcon,
    FingerPrintIcon,
    MapPinIcon,
    UserIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    active: { type: String, default: 'home' },
});

const menus = computed(() => [
    { key: 'home', label: 'Beranda', icon: HomeIcon, href: '/app' },
    { key: 'attendance', label: 'Absensi', icon: FingerPrintIcon, href: '/app/attendance' },
    { key: 'patrol', label: 'Patroli', icon: MapPinIcon, href: '/app/patrol' },
    { key: 'profile', label: 'Profil', icon: UserIcon, href: '/app/profile' },
]);
</script>

<template>
    <nav
        class="fixed bottom-0 left-1/2 z-30 w-full max-w-md -translate-x-1/2 border-t border-[#E8E8EC] bg-white"
        style="padding-bottom: env(safe-area-inset-bottom)"
    >
        <div class="flex h-[72px] items-center justify-around px-2">
            <Link
                v-for="menu in menus"
                :key="menu.key"
                :href="menu.href"
                class="flex flex-1 flex-col items-center justify-center gap-1 py-2"
                :class="props.active === menu.key ? 'text-[#38BDB5]' : 'text-[#9299A2]'"
            >
                <component :is="menu.icon" class="h-6 w-6" />
                <span class="text-[11px] font-medium">{{ menu.label }}</span>
            </Link>
        </div>
    </nav>
</template>
