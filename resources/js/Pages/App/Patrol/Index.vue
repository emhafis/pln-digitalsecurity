<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppHeader from '@/Components/App/AppHeader.vue';
import BottomNavigation from '@/Components/App/Home/BottomNavigation.vue';
import { MapPinIcon, ChevronRightIcon, ClockIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    todaySessions: { type: Array, default: () => [] },
    otherSessions: { type: Array, default: () => [] },
    otherSessionsMeta: {
        type: Object,
        default: () => ({ current_page: 1, last_page: 1, total: 0, has_more: false, status: '' }),
    },
    otherStatusOptions: { type: Array, default: () => [] },
    today: { type: String, default: '' },
});

const statusMap = {
    pending: { label: 'Menunggu', cls: 'bg-gray-100 text-gray-700 border-gray-200' },
    ready: { label: 'Siap Dimulai', cls: 'bg-[#E8F7F6] text-[#1D567F] border-[#BFE9E6]' },
    missed: { label: 'Terlewat', cls: 'bg-red-50 text-red-700 border-red-200' },
    active: { label: 'Berlangsung', cls: 'bg-blue-50 text-blue-700 border-blue-200' },
    completed: { label: 'Selesai', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    incomplete: { label: 'Tidak Lengkap', cls: 'bg-amber-50 text-amber-700 border-amber-200' },
    not_started: { label: 'Terlewat', cls: 'bg-red-50 text-red-700 border-red-200' },
};

function displayKey(session) {
    return session.display_status || session.status;
}

function statusInfo(session) {
    const info = statusMap[displayKey(session)] || { label: session.status, cls: 'bg-gray-100 text-gray-600 border-gray-200' };
    return { ...info, label: session.display_status_label || info.label };
}

function actionFor(session) {
    const key = displayKey(session);
    if (key === 'ready') return { label: 'Mulai Patroli', type: 'primary' };
    if (key === 'active') return { label: 'Lanjutkan Patroli', type: 'primary' };
    if (key === 'completed' || key === 'incomplete') return { label: 'Lihat Ringkasan', type: 'secondary' };
    return { label: 'Lihat Detail', type: 'secondary' };
}

const others = ref([...props.otherSessions]);
const meta = ref({ ...props.otherSessionsMeta });
const activeStatus = ref(props.otherSessionsMeta?.status || '');
const loading = ref(false);
const loadingMore = ref(false);
const sentinelRef = ref(null);
const supportsObserver = typeof window !== 'undefined' && 'IntersectionObserver' in window;
let observer = null;

const hasAny = computed(() => props.todaySessions.length > 0 || others.value.length > 0 || meta.value.total > 0);
const hasOthers = computed(() => others.value.length > 0 || meta.value.total > 0 || loading.value);

function observeSentinel(el) {
    if (!observer || !el) return;
    observer.observe(el);
}

function loadMore() {
    if (loading.value || loadingMore.value) return;
    if (!meta.value?.has_more) return;
    const nextPage = (meta.value.current_page || 1) + 1;
    loadingMore.value = true;
    router.get('/app/patrol', { status: activeStatus.value, page: nextPage }, {
        only: ['otherSessions', 'otherSessionsMeta'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: (page) => {
            const incoming = page.props.otherSessions || [];
            const existing = new Set(others.value.map((s) => s.id));
            others.value = [...others.value, ...incoming.filter((s) => !existing.has(s.id))];
            meta.value = page.props.otherSessionsMeta || { ...meta.value, has_more: false };
            nextTick(maybeLoadMore);
        },
        onFinish: () => {
            loadingMore.value = false;
        },
    });
}

function maybeLoadMore() {
    if (!sentinelRef.value || !supportsObserver) return;
    if (loading.value || loadingMore.value || !meta.value?.has_more) return;
    const rect = sentinelRef.value.getBoundingClientRect();
    if (rect.top <= window.innerHeight + 200) loadMore();
}

function applyStatus(key) {
    if (key === activeStatus.value || loading.value) return;
    activeStatus.value = key;
    loading.value = true;
    others.value = [];
    router.get('/app/patrol', { status: key, page: 1 }, {
        only: ['otherSessions', 'otherSessionsMeta'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: (page) => {
            others.value = page.props.otherSessions || [];
            meta.value = page.props.otherSessionsMeta || { current_page: 1, last_page: 1, total: 0, has_more: false, status: key };
            nextTick(maybeLoadMore);
        },
        onFinish: () => {
            loading.value = false;
        },
    });
}

watch(sentinelRef, (el, prev) => {
    if (!observer) return;
    if (prev) observer.unobserve(prev);
    observeSentinel(el);
});

onMounted(() => {
    if (!supportsObserver) return;
    observer = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) maybeLoadMore();
    }, { rootMargin: '200px 0px' });
    observeSentinel(sentinelRef.value);
});

onBeforeUnmount(() => {
    if (observer) {
        observer.disconnect();
        observer = null;
    }
});
</script>

<template>
    <AppLayout>
        <div class="min-h-full bg-gray-50">
            <AppHeader title="Patroli" description="Daftar sesi patroli Anda" />

            <main class="space-y-6 px-4 pb-8 pt-4">
                <div v-if="!hasAny" class="rounded-xl border border-[#E8E8EC] bg-white p-6 text-center">
                    <MapPinIcon class="mx-auto h-10 w-10 text-[#9299A2]" />
                    <p class="mt-3 text-sm font-medium text-[#263746]">Belum ada sesi patroli</p>
                    <p class="mt-1 text-xs text-[#9299A2]">Sesi yang menjadi tugas Anda akan muncul di sini.</p>
                </div>

                <section v-if="todaySessions.length" class="space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-[#38BDB5]"></span>
                        <h2 class="text-sm font-semibold text-[#263746]">Hari Ini</h2>
                    </div>
                    <Link
                        v-for="s in todaySessions"
                        :key="s.id"
                        :href="`/app/patrol/${s.id}`"
                        class="block rounded-xl border border-[#E8E8EC] bg-white p-4 active:bg-gray-50"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-[#263746]">{{ s.post_name }}</p>
                                <p class="mt-0.5 text-xs text-[#9299A2]">Regu {{ s.team_name }}</p>
                            </div>
                            <span class="shrink-0 rounded-full border px-2 py-0.5 text-[11px] font-medium" :class="statusInfo(s).cls">
                                {{ statusInfo(s).label }}
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-[#9299A2]">
                            <div><span class="font-medium text-[#263746]">{{ s.work_date_label }}</span></div>
                            <div class="text-right">{{ s.shift_name }} {{ s.shift_time !== '-' ? `(${s.shift_time})` : '' }}</div>
                        </div>
                        <div class="mt-3">
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="text-[#9299A2]">Progres</span>
                                <span class="font-semibold text-[#1D567F]">{{ s.scanned_points }}/{{ s.required_points }} titik</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full bg-[#38BDB5]" :style="{ width: `${s.progress}%` }"></div>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between">
                            <span class="inline-flex items-center gap-1 text-xs font-medium" :class="actionFor(s).type === 'primary' ? 'text-[#1D567F]' : 'text-[#9299A2]'">
                                <CheckCircleIcon v-if="displayKey(s) === 'completed'" class="h-4 w-4 text-emerald-600" />
                                <ClockIcon v-else class="h-4 w-4" />
                                {{ actionFor(s).label }}
                            </span>
                            <ChevronRightIcon class="h-4 w-4 text-[#9299A2]" />
                        </div>
                    </Link>
                </section>

                <section v-if="hasOthers" class="space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-[#263746]">Jadwal Lainnya</h2>
                        <span v-if="meta.total" class="text-xs text-[#9299A2]">{{ meta.total }} jadwal</span>
                    </div>

                    <div v-if="otherStatusOptions.length" class="scrollbar-hide -mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
                        <button
                            type="button"
                            class="shrink-0 rounded-full px-3 py-1.5 text-xs font-medium transition-colors"
                            :class="activeStatus === '' ? 'bg-[#1D567F] text-white' : 'border border-[#E8E8EC] bg-white text-[#263746]'"
                            @click="applyStatus('')"
                        >
                            Semua
                        </button>
                        <button
                            v-for="opt in otherStatusOptions"
                            :key="opt.key"
                            type="button"
                            class="shrink-0 rounded-full px-3 py-1.5 text-xs font-medium transition-colors"
                            :class="activeStatus === opt.key ? 'bg-[#1D567F] text-white' : 'border border-[#E8E8EC] bg-white text-[#263746]'"
                            @click="applyStatus(opt.key)"
                        >
                            {{ opt.label }}
                            <span :class="activeStatus === opt.key ? 'text-white/70' : 'text-[#9299A2]'">({{ opt.count }})</span>
                        </button>
                    </div>

                    <div v-if="loading" class="flex flex-col items-center justify-center gap-2 rounded-xl border border-[#E8E8EC] bg-white p-8">
                        <span class="h-6 w-6 animate-spin rounded-full border-2 border-[#1D567F]/20 border-t-[#1D567F]"></span>
                        <p class="text-xs text-[#9299A2]">Memuat jadwal...</p>
                    </div>

                    <template v-else>
                        <Link
                            v-for="s in others"
                            :key="s.id"
                            :href="`/app/patrol/${s.id}`"
                            class="block rounded-xl border border-[#E8E8EC] bg-white p-4 active:bg-gray-50"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-[#263746]">{{ s.post_name }}</p>
                                    <p class="mt-0.5 text-xs text-[#9299A2]">Regu {{ s.team_name }}</p>
                                </div>
                                <span class="shrink-0 rounded-full border px-2 py-0.5 text-[11px] font-medium" :class="statusInfo(s).cls">
                                    {{ statusInfo(s).label }}
                                </span>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-[#9299A2]">
                                <div><span class="font-medium text-[#263746]">{{ s.work_date_label }}</span></div>
                                <div class="text-right">{{ s.shift_name }} {{ s.shift_time !== '-' ? `(${s.shift_time})` : '' }}</div>
                            </div>
                            <div class="mt-3">
                                <div class="mb-1 flex items-center justify-between text-xs">
                                    <span class="text-[#9299A2]">Progres</span>
                                    <span class="font-semibold text-[#1D567F]">{{ s.scanned_points }}/{{ s.required_points }} titik</span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-full rounded-full bg-[#38BDB5]" :style="{ width: `${s.progress}%` }"></div>
                                </div>
                            </div>
                            <div class="mt-3 flex items-center justify-between">
                                <span class="text-xs font-medium text-[#9299A2]">{{ actionFor(s).label }}</span>
                                <ChevronRightIcon class="h-4 w-4 text-[#9299A2]" />
                            </div>
                        </Link>

                        <div v-if="others.length === 0" class="rounded-xl border border-[#E8E8EC] bg-white p-6 text-center">
                            <p class="text-sm font-medium text-[#263746]">Tidak ada jadwal</p>
                            <p class="mt-1 text-xs text-[#9299A2]">Tidak ada jadwal dengan status ini.</p>
                            <button
                                v-if="activeStatus"
                                type="button"
                                class="mt-3 rounded-lg border border-[#E8E8EC] px-4 py-2 text-xs font-medium text-[#1D567F]"
                                @click="applyStatus('')"
                            >
                                Tampilkan Semua
                            </button>
                        </div>

                        <div v-if="others.length" ref="sentinelRef" class="flex flex-col items-center justify-center gap-2 py-3">
                            <template v-if="loadingMore">
                                <span class="h-5 w-5 animate-spin rounded-full border-2 border-[#1D567F]/20 border-t-[#1D567F]"></span>
                                <p class="text-xs text-[#9299A2]">Memuat data lagi...</p>
                            </template>
                            <button
                                v-else-if="meta.has_more && !supportsObserver"
                                type="button"
                                class="rounded-lg border border-[#E8E8EC] bg-white px-4 py-2 text-xs font-medium text-[#1D567F]"
                                @click="loadMore"
                            >
                                Muat Lebih Banyak
                            </button>
                            <p v-else-if="!meta.has_more" class="text-xs text-[#9299A2]">Semua jadwal sudah ditampilkan.</p>
                        </div>
                    </template>
                </section>
            </main>
        </div>

        <template #bottom>
            <BottomNavigation active="patrol" />
        </template>
    </AppLayout>
</template>
