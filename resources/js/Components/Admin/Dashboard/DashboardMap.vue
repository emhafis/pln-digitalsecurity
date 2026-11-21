
<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    posts: { type: Array, default: () => [] },
    indexUrl: { type: String, default: '/admin/security-post' },
});

// Indonesia
const INDONESIA_CENTER = [-2.5, 118];
const INDONESIA_ZOOM = 5;

const INDONESIA_BOUNDS = [
    [-11.2, 94.8],
    [6.2, 141.1],
];

const SINGLE_MARKER_ZOOM = 17;
const MULTIPLE_MARKER_MAX_ZOOM = 17;

const mapEl = ref(null);
const showSkeleton = ref(true);
const skeletonFading = ref(false);
const showEmptyState = ref(false);

let map = null;
let resizeObserver = null;
let destroyed = false;
let initStarted = false;

function isValidCoordinate(lat, lng) {
    const la = Number(lat);
    const lo = Number(lng);

    if (!Number.isFinite(la) || !Number.isFinite(lo)) return false;
    if (la < -90 || la > 90 || lo < -180 || lo > 180) return false;

    // Hanya koordinat dalam wilayah Indonesia.
    return (
        la >= INDONESIA_BOUNDS[0][0] &&
        la <= INDONESIA_BOUNDS[1][0] &&
        lo >= INDONESIA_BOUNDS[0][1] &&
        lo <= INDONESIA_BOUNDS[1][1]
    );
}

const validPosts = computed(() =>
    (props.posts || []).filter((post) =>
        isValidCoordinate(post.latitude, post.longitude)
    )
);

const mapStats = computed(() => {
    const total = props.posts.length;
    const withCoords = validPosts.value.length;

    return {
        total,
        withCoords,
        withoutCoords: Math.max(total - withCoords, 0),
    };
});

function hasText(value) {
    return value !== null &&
        value !== undefined &&
        String(value).trim() !== '';
}

function createPopupContent(post) {
    const wrap = document.createElement('div');
    wrap.className = 'text-sm leading-snug';

    const title = document.createElement('p');
    title.className = 'font-semibold text-slate-900';
    title.textContent = hasText(post.name) ? String(post.name) : 'Pos Jaga';
    wrap.appendChild(title);

    if (hasText(post.address)) {
        const address = document.createElement('p');
        address.className = 'mt-1 text-xs text-slate-600';
        address.textContent = String(post.address);
        wrap.appendChild(address);
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

// Mengatur zoom berdasarkan persebaran marker.
function applyInitialView(bounds, L) {
    if (!map) return;

    if (bounds.length > 1) {
        const markerBounds = L.latLngBounds(bounds);

        map.fitBounds(markerBounds, {
            paddingTopLeft: [55, 55],
            paddingBottomRight: [55, 55],
            maxZoom: MULTIPLE_MARKER_MAX_ZOOM,
            animate: false,
        });

        return;
    }

    if (bounds.length === 1) {
        map.setView(bounds[0], SINGLE_MARKER_ZOOM, {
            animate: false,
        });

        return;
    }

    // Tidak ada marker: tampilkan Indonesia.
    map.setView(INDONESIA_CENTER, INDONESIA_ZOOM, {
        animate: false,
    });
}

async function initMap() {
    if (initStarted || destroyed) return;

    initStarted = true;
    showEmptyState.value = validPosts.value.length === 0;

    try {
        const [leafletMod] = await Promise.all([
            import('leaflet'),
            import('leaflet/dist/leaflet.css'),
        ]);

        if (destroyed || !mapEl.value) return;

        const L = leafletMod.default ?? leafletMod;

        map = L.map(mapEl.value, {
            zoomControl: true,
            minZoom: 5,
            maxZoom: 19,
            maxBounds: INDONESIA_BOUNDS,
            maxBoundsViscosity: 1,
            scrollWheelZoom: true,
        });

        L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
            }
        ).addTo(map);

        // Posisi awal sebelum marker selesai dipasang.
        map.setView(INDONESIA_CENTER, INDONESIA_ZOOM, {
            animate: false,
        });

        const icon = L.divIcon({
            className: 'security-post-marker-wrap',
            html: `
                <div class="security-post-marker">
                    <svg xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        class="security-post-marker-icon">
                        <path fill-rule="evenodd"
                            d="M12.516 2.17a.75.75 0 0 0-.532 0l-7.5 2.813A.75.75 0 0 0 4 5.688v5.25c0 4.28 2.72 7.893 6.86 9.67a3.02 3.02 0 0 0 2.28 0C17.28 18.83 20 15.218 20 10.938v-5.25a.75.75 0 0 0-.484-.705l-7.5-2.813Zm3.265 7.361a.75.75 0 1 0-1.062-1.062L11.25 11.94l-1.219-1.22a.75.75 0 0 0-1.061 1.061l1.75 1.75a.75.75 0 0 0 1.06 0l4-4Z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
            `,
            iconSize: [40, 40],
            iconAnchor: [20, 20],
            popupAnchor: [0, -22],
        });

        const bounds = [];

        for (const post of validPosts.value) {
            const lat = Number(post.latitude);
            const lng = Number(post.longitude);

            const marker = L.marker([lat, lng], { icon }).addTo(map);

            marker.bindPopup(createPopupContent(post), {
                maxWidth: 280,
                closeButton: true,
            });

            bounds.push([lat, lng]);
        }

        await nextTick();

        if (destroyed || !map) return;

        // Pastikan ukuran container sudah benar sebelum menentukan zoom.
        map.invalidateSize();

        applyInitialView(bounds, L);

        // Koreksi ukuran saat layout selesai dirender.
        requestAnimationFrame(() => {
            if (destroyed || !map) return;

            map.invalidateSize();
            applyInitialView(bounds, L);
        });

        if (typeof ResizeObserver !== 'undefined') {
            resizeObserver = new ResizeObserver(() => {
                if (!map || destroyed) return;
                map.invalidateSize();
            });

            resizeObserver.observe(mapEl.value);
        }

        // Hilangkan skeleton setelah peta mulai dimuat.
        const tiles = map._layers
            ? Object.values(map._layers).find(
                (layer) => layer instanceof L.TileLayer
            )
            : null;

        if (tiles) {
            let revealed = false;

            const reveal = () => {
                if (revealed || destroyed) return;
                revealed = true;
                hideSkeleton();
            };

            tiles.once('load', reveal);
            tiles.once('tileerror', reveal);

            setTimeout(reveal, 3000);
        } else {
            setTimeout(hideSkeleton, 1000);
        }
    } catch (error) {
        console.error('DashboardMap failed to initialize', error);

        showSkeleton.value = false;
        showEmptyState.value = false;
    }
}

onMounted(() => {
    initMap();
});

onBeforeUnmount(() => {
    destroyed = true;

    if (resizeObserver) {
        resizeObserver.disconnect();
        resizeObserver = null;
    }

    if (map) {
        map.remove();
        map = null;
    }
});
</script>

<template>
    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <!-- Header -->
        <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
            <div>
                <h2 class="text-base font-semibold text-gray-900">
                    Sebaran Pos Jaga
                </h2>
                <p class="mt-1 text-xs text-gray-500">
                    Lokasi geografis seluruh pos keamanan.
                </p>
            </div>

            <Link
                :href="indexUrl"
                class="inline-flex w-fit items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50"
            >
                Kelola lokasi
                <ArrowTopRightOnSquareIcon class="h-4 w-4" />
            </Link>
        </div>

        <!-- Ringkasan -->
        <div
            v-if="mapStats.total > 0"
            class="flex flex-wrap items-center gap-x-6 gap-y-2 border-y border-gray-100 px-4 py-3 sm:px-5"
        >
            <p class="text-xs text-gray-500">
                Total pos
                <span class="ml-1 font-semibold tabular-nums text-gray-900">
                    {{ mapStats.total }}
                </span>
            </p>

            <p class="text-xs text-gray-500">
                Terpetakan
                <span class="ml-1 font-semibold tabular-nums text-emerald-700">
                    {{ mapStats.withCoords }}
                </span>
            </p>

            <p class="text-xs text-gray-500">
                Belum terpetakan
                <span class="ml-1 font-semibold tabular-nums text-amber-700">
                    {{ mapStats.withoutCoords }}
                </span>
            </p>
        </div>

        <!-- Map -->
        <div class="relative h-[360px] overflow-hidden bg-gray-100 sm:h-[460px] lg:h-[540px]">
            <div ref="mapEl" class="h-full w-full"></div>

            <div
                v-if="showEmptyState"
                class="pointer-events-none absolute bottom-4 left-4 z-[500] max-w-xs rounded-md border border-gray-200 bg-white/95 px-4 py-3 shadow-sm"
            >
                <p class="text-sm font-medium text-gray-800">
                    Belum ada lokasi terpetakan
                </p>
                <p class="mt-1 text-xs leading-relaxed text-gray-500">
                    Peta menampilkan wilayah Indonesia. Tambahkan koordinat pos untuk menampilkan titik lokasi.
                </p>
            </div>

            <!-- Loading -->
            <div
                v-if="showSkeleton"
                class="pointer-events-none absolute inset-0 z-[1000] bg-gray-100 transition-opacity duration-300"
                :class="skeletonFading ? 'opacity-0' : 'opacity-100'"
            >
                <div class="map-shimmer absolute inset-0"></div>
            </div>
        </div>

        <!-- Footer -->
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 sm:px-5">
            <p class="text-[11px] text-gray-400">
                Map data &copy; OpenStreetMap contributors
            </p>

            <p class="text-[11px] text-gray-500">
                Klik titik untuk melihat informasi pos
            </p>
        </div>
    </section>
</template>

<style scoped>
@keyframes map-shimmer {
    from {
        transform: translateX(-100%);
    }

    to {
        transform: translateX(100%);
    }
}

.map-shimmer::before {
    content: '';
    position: absolute;
    inset: 0;
    transform: translateX(-100%);
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.65),
        transparent
    );
    animation: map-shimmer 1.5s infinite;
}

:deep(.security-post-marker-wrap) {
    background: transparent;
    border: none;
}

:deep(.security-post-marker) {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    background: #245568;
    border: 3px solid white;
    border-radius: 50%;
    box-shadow:
        0 2px 5px rgba(0, 0, 0, 0.22),
        0 0 0 5px rgba(36, 85, 104, 0.15);
}

:deep(.security-post-marker-icon) {
    width: 21px;
    height: 21px;
}
</style>
