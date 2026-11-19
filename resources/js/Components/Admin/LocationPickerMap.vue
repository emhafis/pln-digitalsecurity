<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { ExclamationTriangleIcon, MapPinIcon } from '@heroicons/vue/24/outline';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

const latitude = defineModel('latitude', { type: [String, Number], default: '' });
const longitude = defineModel('longitude', { type: [String, Number], default: '' });

const props = defineProps({
    defaultLatitude: { type: Number, default: 5.5622464 },
    defaultLongitude: { type: Number, default: 95.3348073 },
    zoomWithMarker: { type: Number, default: 15 },
    zoomWithoutMarker: { type: Number, default: 13 },
});

const mapEl = ref(null);
const isReady = ref(false);
const fallbackMessage = ref('');

let leaflet = null;
let map = null;
let marker = null;
let resizeObserver = null;
let destroyed = false;

function parseCoordinate(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : null;
}

function formatCoordinate(value) {
    return Number(value).toFixed(7);
}

function isValidCoordinate(lat, lng) {
    return lat !== null
        && lng !== null
        && lat >= -90
        && lat <= 90
        && lng >= -180
        && lng <= 180;
}

function initialView() {
    const lat = parseCoordinate(latitude.value);
    const lng = parseCoordinate(longitude.value);

    if (isValidCoordinate(lat, lng)) {
        return {
            center: [lat, lng],
            zoom: props.zoomWithMarker,
            hasMarker: true,
        };
    }

    return {
        center: [props.defaultLatitude, props.defaultLongitude],
        zoom: props.zoomWithoutMarker,
        hasMarker: false,
    };
}

function emitCoordinates(latLng) {
    latitude.value = formatCoordinate(latLng.lat);
    longitude.value = formatCoordinate(latLng.lng);
}

function bindMarkerEvents(instance) {
    instance.on('drag', () => emitCoordinates(instance.getLatLng()));
    instance.on('dragend', () => emitCoordinates(instance.getLatLng()));
}

function createMarker(latLng) {
    if (!map) {
        return null;
    }

    const instance = leaflet.marker(latLng, { draggable: true }).addTo(map);
    bindMarkerEvents(instance);

    return instance;
}

function setMarker(latLng) {
    if (!marker) {
        marker = createMarker(latLng);

        return;
    }

    marker.setLatLng(latLng);
}

function syncFromInputs() {
    if (!isReady.value || !map) {
        return;
    }

    const lat = parseCoordinate(latitude.value);
    const lng = parseCoordinate(longitude.value);

    if (lat === null || lng === null) {
        if (marker) {
            marker.remove();
            marker = null;
        }

        return;
    }

    if (!isValidCoordinate(lat, lng)) {
        return;
    }

    const target = leaflet.latLng(lat, lng);

    if (!marker) {
        marker = createMarker(target);
        map.setView(target, props.zoomWithMarker);

        return;
    }

    const current = marker.getLatLng();
    const hasMoved = Math.abs(current.lat - lat) > 1e-7
        || Math.abs(current.lng - lng) > 1e-7;

    if (!hasMoved) {
        return;
    }

    marker.setLatLng(target);
    map.setView(target, props.zoomWithMarker);
}

function handleMapClick(event) {
    setMarker(event.latlng);
    emitCoordinates(event.latlng);
}

async function initMap() {
    if (destroyed || !mapEl.value || map) {
        return;
    }

    const view = initialView();

    map = leaflet.map(mapEl.value, {
        center: view.center,
        zoom: view.zoom,
        zoomControl: true,
    });

    const tiles = leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
    });

    tiles.on('tileerror', () => {
        fallbackMessage.value = 'Peta tidak dapat dimuat. Periksa koneksi internet Anda. Latitude dan Longitude tetap dapat diisi secara manual.';
    });
    tiles.addTo(map);

    map.on('click', handleMapClick);

    if (view.hasMarker) {
        marker = createMarker(leaflet.latLng(view.center[0], view.center[1]));
    }

    isReady.value = true;

    await nextTick();

    if (map) {
        map.invalidateSize();
    }

    if (typeof ResizeObserver !== 'undefined' && mapEl.value) {
        resizeObserver = new ResizeObserver(() => map?.invalidateSize());
        resizeObserver.observe(mapEl.value);
    }
}

onMounted(async () => {
    try {
        const module = await import('leaflet');
        leaflet = module.default ?? module;

        delete leaflet.Icon.Default.prototype._getIconUrl;
        leaflet.Icon.Default.mergeOptions({
            iconRetinaUrl: markerIcon2x,
            iconUrl: markerIcon,
            shadowUrl: markerShadow,
        });

        await initMap();
    } catch (error) {
        console.error('Leaflet failed to load', error);
        fallbackMessage.value = 'Peta tidak dapat dimuat. Periksa koneksi internet Anda. Latitude dan Longitude tetap dapat diisi secara manual.';
    }
});

watch([latitude, longitude], syncFromInputs);

onBeforeUnmount(() => {
    destroyed = true;

    if (resizeObserver) {
        resizeObserver.disconnect();
        resizeObserver = null;
    }

    if (marker) {
        marker.off();
        marker.remove();
        marker = null;
    }

    if (map) {
        map.off();
        map.remove();
        map = null;
    }

    isReady.value = false;
});
</script>

<template>
    <div class="space-y-2">
        <div class="relative overflow-hidden rounded-lg border border-gray-200 bg-gray-100">
            <div ref="mapEl" class="h-64 w-full sm:h-72 lg:h-80" />

            <div
                v-if="fallbackMessage"
                class="absolute inset-0 z-[1000] flex flex-col items-center justify-center bg-white/95 px-6 text-center"
                role="alert"
            >
                <ExclamationTriangleIcon class="h-8 w-8 text-amber-500" />
                <p class="mt-2 max-w-md text-sm font-medium text-gray-800">
                    Peta tidak tersedia
                </p>
                <p class="mt-1 max-w-md text-xs text-gray-500">
                    {{ fallbackMessage }}
                </p>
            </div>
        </div>

        <p class="flex items-center gap-1.5 text-xs text-gray-500">
            <MapPinIcon class="h-3.5 w-3.5 shrink-0" />
            Klik peta untuk memilih lokasi, atau geser marker untuk menyesuaikan koordinat.
        </p>
    </div>
</template>
