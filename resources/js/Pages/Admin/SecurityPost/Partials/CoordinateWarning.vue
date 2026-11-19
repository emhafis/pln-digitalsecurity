<script setup>
import { Link } from '@inertiajs/vue3';
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

defineProps({
    count: { type: Number, default: 0 },
    posts: { type: Array, default: () => [] },
});
</script>

<template>
    <div
        v-if="count > 0"
        class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3"
        role="status"
    >
        <div class="flex items-start gap-3">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                <ExclamationTriangleIcon class="h-4 w-4" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-amber-900">Koordinat Pos Jaga Belum Lengkap</p>
                <p class="mt-0.5 text-xs text-amber-800">
                    <span class="font-semibold">{{ count }} Pos Jaga</span> perlu diperbaiki koordinatnya.
                </p>
                <p class="mt-1 text-xs text-amber-700">
                    Koordinat diperlukan untuk perhitungan jarak presensi patroli.
                </p>

                <ul class="mt-3 max-h-40 space-y-1 overflow-y-auto pr-1 text-xs">
                    <li v-for="post in posts" :key="post.id" class="flex flex-wrap items-center gap-x-1.5">
                        <Link
                            :href="`/admin/security-post/${post.id}/edit`"
                            class="font-medium text-amber-900 underline decoration-amber-300 underline-offset-2 transition hover:text-amber-700"
                        >
                            {{ post.name }}
                        </Link>
                        <span class="text-amber-700">- {{ post.unit_name }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
