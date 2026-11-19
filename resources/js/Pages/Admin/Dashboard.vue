<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import {
    BuildingOffice2Icon,
    ShieldCheckIcon,
    UserGroupIcon,
    UsersIcon,
    MapPinIcon,
} from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatCard from '@/Components/Admin/StatCard.vue';
import Card from '@/Components/Admin/Card.vue';
import Badge from '@/Components/Admin/Badge.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import Button from '@/Components/Admin/Button.vue';

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
    securityPosts: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const recentActivities = [
    { name: 'Ahmad Rizki', action: 'login ke sistem', time: '2 menit lalu', status: 'blue' },
    { name: 'Siti Nurhaliza', action: 'memperbarui data pos jaga', time: '18 menit lalu', status: 'gray' },
    { name: 'Budi Santoso', action: 'menambah titik patroli baru', time: '1 jam lalu', status: 'emerald' },
    { name: 'Dewi Lestari', action: 'menonaktifkan akun satpam', time: '3 jam lalu', status: 'amber' },
];

const masterCounts = computed(() => [
    { label: 'Unit', value: 12, href: '/admin/unit' },
]);

const DEFAULT_CENTER = [5.56235750, 95.33476920];
const DEFAULT_ZOOM = 13;
const SINGLE_MARKER_ZOOM = 15;

function isValidCoordinate(lat, lng) {
    const la = Number(lat);
    const lo = Number(lng);
    if (!Number.isFinite(la) || !Number.isFinite(lo)) return false;
    if (la < -90 || la > 90 || lo < -180 || lo > 180) return false;
    return true;
}

const validPosts = computed(() =>
    (props.securityPosts || []).filter((p) => isValidCoordinate(p.latitude, p.longitude))
);

const mapStats = computed(() => {
    const total = (props.securityPosts || []).length;
    const withCoords = validPosts.value.length;
    return {
        total,
        withCoords,
        withoutCoords: Math.max(total - withCoords, 0),
    };
});

const mapEl = ref(null);
const showSkeleton = ref(true);
const showEmptyState = ref(false);
const skeletonFading = ref(false);

let map = null;
let resizeObserver = null;
let destroyed = false;
let initStarted = false;

function hasText(v) {
    return v !== null && v !== undefined && String(v).trim() !== '';
}

function createPopupContent(post) {
    const wrap = document.createElement('div');
    wrap.className = 'text-sm leading-snug';
    const title = document.createElement('p');
    title.className = 'font-semibold text-slate-900';
    title.textContent = hasText(post.name) ? String(post.name) : 'Pos Jaga';
    wrap.appendChild(title);
    if (hasText(post.address)) {
        const addr = document.createElement('p');
        addr.className = 'mt-1 text-xs text-slate-600';
        addr.textContent = String(post.address);
        wrap.appendChild(addr);
    }
    if (hasText(post.unit_name)) {
        const unit = document.createElement('p');
        unit.className = 'mt-1 text-xs text-slate-500';
        unit.textContent = `Unit: ${String(post.unit_name)}`;
        wrap.appendChild(unit);
    }
    return wrap;
}

function hideSkeleton() {
    if (!showSkeleton.value) return;
    skeletonFading.value = true;
    setTimeout(() => {
        if (destroyed) return;
        showSkeleton.value = false;
        skeletonFading.value = false;
    }, 300);
}

async function initMap() {
    if (initStarted || destroyed) return;
    initStarted = true;

    if (validPosts.value.length === 0) {
        showEmptyState.value = true;
        showSkeleton.value = false;
        return;
    }

    try {
        const [leafletMod] = await Promise.all([
            import('leaflet'),
            import('leaflet/dist/leaflet.css'),
        ]);
        if (destroyed || !mapEl.value) return;
        const L = leafletMod.default ?? leafletMod;

        map = L.map(mapEl.value, {
            center: DEFAULT_CENTER,
            zoom: DEFAULT_ZOOM,
            zoomControl: true,
        });

        const tiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
        });

        let tileErrorCount = 0;
        tiles.on('tileerror', () => {
            tileErrorCount += 1;
            if (import.meta.env.DEV) {
                console.warn('Peta Pos Jaga: sebagian tile gagal dimuat.', tileErrorCount);
            }
            // Keep map/markers visible if some tiles fail; do not show UI error.
        });

        let revealScheduled = false;
        const scheduleReveal = () => {
            if (revealScheduled || destroyed) return;
            revealScheduled = true;
            // Reveal after map is actually ready/rendered.
            map.whenReady(() => {
                setTimeout(() => {
                    if (destroyed || !map) return;
                    try { map.invalidateSize(); } catch (_) {}
                    requestAnimationFrame(() => {
                        if (!destroyed) hideSkeleton();
                    });
                }, 80);
            });
        };
        tiles.once('load', scheduleReveal);
        tiles.addTo(map);
        // Fallback reveal in case 'load' is delayed/unreliable but map is usable.
        setTimeout(scheduleReveal, 2500);

        const icon = L.divIcon({
            className: 'security-post-marker-wrap',
            html: `<div class="security-post-marker" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="security-post-marker-icon">
                    <path fill-rule="evenodd" d="M12.516 2.17a.75.75 0 0 0-.532 0l-7.5 2.813A.75.75 0 0 0 4 5.688v5.25c0 4.28 2.72 7.893 6.86 9.67a3.02 3.02 0 0 0 2.28 0C17.28 18.83 20 15.218 20 10.938v-5.25a.75.75 0 0 0-.484-.705l-7.5-2.813Zm3.265 7.361a.75.75 0 1 0-1.062-1.062L11.25 11.94l-1.219-1.22a.75.75 0 0 0-1.061 1.061l1.75 1.75a.75.75 0 0 0 1.06 0l4-4Z" clip-rule="evenodd" />
                </svg>
            </div>`,
            iconSize: [32, 32],
            iconAnchor: [16, 32],
            popupAnchor: [0, -28],
        });

        const bounds = [];
        for (const post of validPosts.value) {
            const lat = Number(post.latitude);
            const lng = Number(post.longitude);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue;
            const marker = L.marker([lat, lng], { icon }).addTo(map);
            marker.bindPopup(createPopupContent(post), { maxWidth: 260 });
            bounds.push([lat, lng]);
        }

        if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [36, 36], maxZoom: 16 });
        } else if (bounds.length === 1) {
            map.setView(bounds[0], SINGLE_MARKER_ZOOM);
        } else {
            map.setView(DEFAULT_CENTER, DEFAULT_ZOOM);
        }

        await nextTick();
        if (map) {
            try { map.invalidateSize(); } catch (_) {}
        }

        if (typeof ResizeObserver !== 'undefined' && mapEl.value) {
            resizeObserver = new ResizeObserver(() => {
                try { map?.invalidateSize(); } catch (_) {}
            });
            resizeObserver.observe(mapEl.value);
        }
    } catch (error) {
        if (import.meta.env.DEV) {
            console.warn('Peta Pos Jaga gagal dimuat.', error);
        }
        // Keep skeleton shimmer as fallback, no user-facing error.
        showEmptyState.value = false;
        showSkeleton.value = true;
        skeletonFading.value = false;
    }
}

onMounted(() => {
    initMap();
});

onBeforeUnmount(() => {
    destroyed = true;
    if (resizeObserver) {
        try { resizeObserver.disconnect(); } catch (_) {}
        resizeObserver = null;
    }
    if (map) {
        try {
            map.off();
            map.remove();
        } catch (_) {}
        map = null;
    }
});
</script>

<template>
    <AdminLayout title="Dashboard">
        <div class="space-y-6">
            <PageHeader
                title="Dashboard"
                :description="`Selamat datang kembali, ${user?.name}.`"
            >
                <template #actions>
                    <Button variant="secondary">Unduh Laporan</Button>
                    <Button>+ Tambah Data</Button>
                </template>
            </PageHeader>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Total Unit"
                    :value="stats.units ?? 12"
                    trend="+2"
                    hint="bulan ini"
                    :icon="BuildingOffice2Icon"
                />
                <StatCard
                    label="Pos Jaga Aktif"
                    :value="stats.posts ?? 28"
                    trend="+3"
                    hint="bulan ini"
                    :icon="ShieldCheckIcon"
                />
                <StatCard
                    label="Satpam Terdaftar"
                    :value="stats.guards ?? 145"
                    trend="+12"
                    hint="bulan ini"
                    :icon="UserGroupIcon"
                />
                <StatCard
                    label="User Aktif"
                    :value="stats.users ?? 36"
                    trend="-2"
                    :trend-up="false"
                    hint="30 hari"
                    :icon="UsersIcon"
                />
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <Card
                    title="Ringkasan Data Master"
                    description="Jumlah data per master (placeholder)"
                    class="lg:col-span-2"
                >
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        <a
                            v-for="item in masterCounts"
                            :key="item.label"
                            :href="item.href"
                            class="group rounded-lg border border-gray-200 p-3 text-center transition hover:border-blue-300 hover:bg-blue-50/50"
                        >
                            <p class="text-xl font-semibold tabular-nums text-gray-900 group-hover:text-blue-700">
                                {{ item.value }}
                            </p>
                            <p class="mt-1 text-xs font-medium text-gray-500">{{ item.label }}</p>
                        </a>
                    </div>
                </Card>

                <Card title="Distribusi Peran" description="Komposisi user sistem">
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Administrator</span>
                                <span class="font-medium tabular-nums text-gray-900">8%</span>
                            </div>
                            <div class="mt-1.5 h-2 rounded-full bg-gray-100">
                                <div class="h-2 rounded-full bg-blue-600" style="width: 8%" />
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Operator</span>
                                <span class="font-medium tabular-nums text-gray-900">42%</span>
                            </div>
                            <div class="mt-1.5 h-2 rounded-full bg-gray-100">
                                <div class="h-2 rounded-full bg-emerald-500" style="width: 42%" />
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Satpam</span>
                                <span class="font-medium tabular-nums text-gray-900">38%</span>
                            </div>
                            <div class="mt-1.5 h-2 rounded-full bg-gray-100">
                                <div class="h-2 rounded-full bg-amber-500" style="width: 38%" />
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Lainnya</span>
                                <span class="font-medium tabular-nums text-gray-900">12%</span>
                            </div>
                            <div class="mt-1.5 h-2 rounded-full bg-gray-100">
                                <div class="h-2 rounded-full bg-gray-400" style="width: 12%" />
                            </div>
                        </div>
                    </div>
                </Card>
            </div>

            <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-slate-900">Peta Pos Jaga</h2>
                        <p class="mt-1 text-sm text-slate-500">Sebaran lokasi pos jaga pada wilayah kerja</p>
                        <div v-if="mapStats.total > 0" class="mt-3 flex flex-wrap gap-2">
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">Total: {{ mapStats.total }}</span>
                            <span class="inline-flex items-center rounded-full bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-700">Dengan koordinat: {{ mapStats.withCoords }}</span>
                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">Tanpa koordinat: {{ mapStats.withoutCoords }}</span>
                        </div>
                    </div>
                    <Link
                        href="/admin/security-post"
                        class="inline-flex shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2"
                    >
                        Lihat Semua
                    </Link>
                </div>

                <div class="relative mt-4 h-[300px] overflow-hidden rounded-xl border border-slate-100 bg-slate-50 sm:h-[360px] lg:h-[380px]">
                    <div v-show="!showSkeleton && !showEmptyState" ref="mapEl" class="h-full w-full"></div>

                    <div
                        v-if="showEmptyState"
                        class="absolute inset-0 z-10 flex flex-col items-center justify-center px-6 text-center"
                    >
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                            <MapPinIcon class="h-6 w-6" />
                        </div>
                        <p class="mt-3 max-w-sm text-sm font-medium text-slate-700">Tidak ada lokasi pos jaga yang memiliki koordinat.</p>
                    </div>

                    <div
                        v-if="showSkeleton"
                        class="absolute inset-0 z-20 overflow-hidden bg-slate-100 transition-opacity duration-300"
                        :class="skeletonFading ? 'opacity-0' : 'opacity-100'"
                        aria-hidden="true"
                    >
                        <div class="map-shimmer absolute inset-0"></div>
                        <div class="absolute left-3 top-3 space-y-2">
                            <div class="h-8 w-8 rounded-md bg-slate-200/80 shadow-sm"></div>
                            <div class="h-8 w-8 rounded-md bg-slate-200/80 shadow-sm"></div>
                        </div>
                        <div class="absolute left-[18%] top-[28%] h-8 w-8 rounded-full border-2 border-white bg-teal-200/70 shadow-sm"></div>
                        <div class="absolute left-[48%] top-[46%] h-8 w-8 rounded-full border-2 border-white bg-teal-200/70 shadow-sm"></div>
                        <div class="absolute left-[68%] top-[34%] h-8 w-8 rounded-full border-2 border-white bg-teal-200/70 shadow-sm"></div>
                    </div>
                </div>
            </section>

            <Card title="Aktivitas Terbaru" description="Log aktivitas sistem">
                <template #header>
                    <a href="#" class="text-xs font-medium text-blue-600 hover:text-blue-700">
                        Lihat semua
                    </a>
                </template>
                <DataTable :has-rows="recentActivities.length > 0">
                    <template #head>
                        <th class="px-1 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Pengguna</th>
                        <th class="px-1 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Aktivitas</th>
                        <th class="px-1 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Waktu</th>
                        <th class="px-1 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                    </template>
                    <tr v-for="activity in recentActivities" :key="activity.name + activity.time" class="hover:bg-gray-50/60">
                        <td class="px-1 py-3.5">
                            <div class="flex items-center gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600">
                                    {{ activity.name.charAt(0) }}
                                </span>
                                <span class="text-sm font-medium text-gray-900">{{ activity.name }}</span>
                            </div>
                        </td>
                        <td class="px-1 py-3.5 text-sm text-gray-600">{{ activity.action }}</td>
                        <td class="px-1 py-3.5 text-xs text-gray-500">{{ activity.time }}</td>
                        <td class="px-1 py-3.5 text-right">
                            <Badge :variant="activity.status">Aktif</Badge>
                        </td>
                    </tr>
                </DataTable>
            </Card>
        </div>
    </AdminLayout>
</template>

<style scoped>
:deep(.security-post-marker-wrap) {
    background: transparent;
    border: none;
}
:deep(.security-post-marker) {
    width: 32px;
    height: 32px;
    border-radius: 9999px;
    background: #0d9488;
    border: 2px solid #ffffff;
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
}
:deep(.security-post-marker-icon) {
    width: 17px;
    height: 17px;
}
</style>
