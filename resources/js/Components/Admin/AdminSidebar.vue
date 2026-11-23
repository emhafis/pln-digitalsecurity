<script>
let adminSidebarScrollTop = 0;
</script>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { XMarkIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';

const page = usePage();
const user = computed(() => page.props.auth?.user);

const emit = defineEmits(['close']);

const navRef = ref(null);

function saveScroll() {
    if (navRef.value) adminSidebarScrollTop = navRef.value.scrollTop;
}
function restoreScroll() {
    nextTick(() => {
        if (navRef.value) navRef.value.scrollTop = adminSidebarScrollTop;
    });
}

let removeStart;
let removeFinish;
onMounted(() => {
    restoreScroll();
    removeStart = router.on('start', saveScroll);
    removeFinish = router.on('finish', restoreScroll);
});
onBeforeUnmount(() => {
    saveScroll();
    if (removeStart) removeStart();
    if (removeFinish) removeFinish();
});

const mainNav = [
    { label: 'Dashboard', href: '/admin/dashboard' },
];

const operationalNav = computed(() => [
    { label: 'Periode Jadwal', href: '/admin/schedule-period' },
    { label: 'Kalender & Monitoring', href: '/admin/schedule-calendar' },
    { label: 'Monitoring Patroli', href: '/admin/patrol-session' },
    { label: 'Laporan Patroli', href: '/admin/patrol-report' },
    { label: 'Monitoring Kunjungan', href: '/admin/guest-book' },
    { label: user.value?.role === 'superadmin' ? 'Kelola QR Unit' : 'QR Unit', href: '/admin/guest-book/qr' },
    ...(user.value?.role === 'superadmin'
        ? [
            { label: 'Pengumuman', href: '/admin/announcements' },
            { label: 'Log Aktivitas', href: '/admin/activity-log' },
        ]
        : []),
]);

const attendanceNav = computed(() => [
    { label: 'Status Absensi', href: '/admin/attendance/readiness' },
    { label: 'Data Absensi', href: '/admin/attendance-record' },
    { label: 'Pengajuan Koreksi', href: '/admin/attendance-correction' },
    ...(user.value?.role === 'superadmin'
        ? [{ label: 'Pengaturan Absensi', href: '/admin/attendance-setting' }]
        : []),
]);

const assetMonitoringNav = [
    { label: 'Monitoring APAR', href: '/admin/monitoring/apar' },
    { label: 'Monitoring Hydrant', href: '/admin/monitoring/hydrant' },
];

const masterNav = [
    { label: 'Data Unit', href: '/admin/unit' },
    { label: 'Data Personel Security', href: '/admin/security' },
    { label: 'Data APAR', href: '/admin/apar' },
    { label: 'Data Hydrant', href: '/admin/hydrant' },
    { label: 'Data Pos Keamanan', href: '/admin/security-post' },
    { label: 'Data Titik Patroli', href: '/admin/patrol-point' },
    { label: 'Data Shift', href: '/admin/shift' },
    { label: 'Data Regu', href: '/admin/security-team' },
    { label: 'Data Pola Rotasi', href: '/admin/schedule-pattern' },
];

const managementNav = computed(() => [
    { label: 'Akun Security', role: 'security' },
    ...(user.value?.role === 'superadmin'
        ? [{ label: 'Akun Admin', role: 'admin' }]
        : []),
]);

const masterHrefs = masterNav.map((i) => i.href);
const onUserPage = computed(() => page.url.startsWith('/admin/user'));

function isActive(href) {
    const url = page.url.split('?')[0];
    if (href === '/admin') return url === '/admin';
    if (href === '/admin/guest-book') return url === '/admin/guest-book' || (url.startsWith('/admin/guest-book/') && !url.startsWith('/admin/guest-book/qr'));
    return url === href || url.startsWith(href + '/');
}

const masterActive = computed(() => masterHrefs.some((h) => isActive(h)));

const masterOpen = ref(false);
const managementOpen = ref(false);

watch(masterActive, (v) => { if (v) masterOpen.value = true; }, { immediate: true });
watch(onUserPage, (v) => { if (v) managementOpen.value = true; }, { immediate: true });

function isChildActive(role) {
    return onUserPage.value && page.url.includes(`role=${role}`);
}
</script>

<template>
    <div class="flex h-full flex-col bg-white">
        <div class="flex h-16 shrink-0 items-center gap-2.5 border-b border-gray-200 px-5">
            <img
                src="/images/digty-logo.png"
                alt="Digital Security by PLN UID Aceh"
                class="h-10 w-auto max-w-full shrink-0 object-contain"
            />
            <button
                type="button"
                class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 lg:hidden"
                aria-label="Tutup menu"
                @click="emit('close')"
            >
                <XMarkIcon class="h-5 w-5" />
            </button>
        </div>

        <nav ref="navRef" class="flex-1 space-y-0.5 overflow-y-auto scrollbar-hide px-3 py-4">
            <Link
                v-for="item in mainNav"
                :key="item.label"
                :href="item.href"
                class="relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors"
                :class="isActive(item.href) ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'"
                @click="emit('close')"
            >
                <span v-if="isActive(item.href)" class="absolute left-0 top-1/2 h-5 w-0.5 -translate-y-1/2 bg-blue-600" />
                {{ item.label }}
            </Link>

            <div class="!mt-5 px-3 pb-1 pt-1">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Operasional</p>
            </div>
            <Link
                v-for="item in operationalNav"
                :key="item.href"
                :href="item.href"
                class="relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors"
                :class="isActive(item.href) ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'"
                @click="emit('close')"
            >
                <span v-if="isActive(item.href)" class="absolute left-0 top-1/2 h-5 w-0.5 -translate-y-1/2 bg-blue-600" />
                {{ item.label }}
            </Link>

            <div class="!mt-5 px-3 pb-1 pt-1">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Absensi</p>
            </div>
            <Link
                v-for="item in attendanceNav"
                :key="item.label"
                :href="item.href"
                class="relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors"
                :class="isActive(item.href) ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'"
                @click="emit('close')"
            >
                <span v-if="isActive(item.href)" class="absolute left-0 top-1/2 h-5 w-0.5 -translate-y-1/2 bg-blue-600" />
                {{ item.label }}
            </Link>

            <div class="!mt-5 px-3 pb-1 pt-1">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Monitoring Aset</p>
            </div>
            <Link
                v-for="item in assetMonitoringNav"
                :key="item.href"
                :href="item.href"
                class="relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors"
                :class="isActive(item.href) ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'"
                @click="emit('close')"
            >
                <span v-if="isActive(item.href)" class="absolute left-0 top-1/2 h-5 w-0.5 -translate-y-1/2 bg-blue-600" />
                {{ item.label }}
            </Link>

            <div class="!mt-5 px-3 pb-1 pt-1">
                <button
                    type="button"
                    class="flex w-full items-center justify-between rounded-lg px-0 py-1 text-[11px] font-semibold uppercase tracking-wider transition-colors"
                    :class="masterActive ? 'text-blue-600' : 'text-gray-400 hover:text-gray-600'"
                    @click="masterOpen = !masterOpen"
                >
                    <span>Data Master</span>
                    <ChevronDownIcon class="h-4 w-4 transition-transform" :class="{ 'rotate-180': masterOpen }" />
                </button>
            </div>
            <template v-if="masterOpen">
                <Link
                    v-for="item in masterNav"
                    :key="item.label"
                    :href="item.href"
                    class="relative flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors"
                    :class="isActive(item.href) ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'"
                    @click="emit('close')"
                >
                    <span v-if="isActive(item.href)" class="absolute left-0 top-1/2 h-4 w-0.5 -translate-y-1/2 bg-blue-600" />
                    {{ item.label }}
                </Link>
            </template>

            <div class="!mt-5 px-3 pb-1 pt-1">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Akun</p>
            </div>
            <button
                type="button"
                class="relative flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors"
                :class="onUserPage ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'"
                @click="managementOpen = !managementOpen"
            >
                <span v-if="onUserPage" class="absolute left-0 top-1/2 h-5 w-0.5 -translate-y-1/2 bg-blue-600" />
                Manajemen User
                <ChevronDownIcon class="ml-auto h-4 w-4 transition-transform" :class="{ 'rotate-180': managementOpen }" />
            </button>
            <template v-if="managementOpen">
                <Link
                    v-for="child in managementNav"
                    :key="child.role"
                    :href="`/admin/user?role=${child.role}`"
                    class="relative flex items-center gap-3 rounded-lg py-2 pl-9 pr-3 text-sm transition-colors"
                    :class="isChildActive(child.role) ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'"
                    @click="emit('close')"
                >
                    <span v-if="isChildActive(child.role)" class="absolute left-4 top-1/2 h-4 w-0.5 -translate-y-1/2 bg-blue-600" />
                    {{ child.label }}
                </Link>
            </template>
        </nav>

        <div class="border-t border-gray-200 p-4">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-700">
                    {{ user?.name?.charAt(0)?.toUpperCase() }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm text-gray-900">{{ user?.name }}</p>
                    <p class="truncate text-xs text-gray-500">{{ user?.email }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
