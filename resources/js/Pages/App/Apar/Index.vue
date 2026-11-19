<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppHeader from '@/Components/App/AppHeader.vue';
import BottomNavigation from '@/Components/App/Home/BottomNavigation.vue';
import { MagnifyingGlassIcon, ChevronRightIcon, FireIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    items: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({}) },
    search: { type: String, default: '' },
});

const q = ref(props.search ?? '');
let debounce = null;

watch(q, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/app/apar', { search: q.value }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
});

function go(page) {
    router.get('/app/apar', { search: q.value, page }, { preserveState: true, preserveScroll: true, replace: true });
}
</script>

<template>
    <AppLayout>
        <div class="min-h-full bg-gray-50">
            <AppHeader title="APAR" description="Daftar APAR aktif di unit Anda" />

            <main class="space-y-4 px-4 pb-8 pt-4">
                <Link href="/app/scan" class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#1D567F] px-4 py-3 text-sm font-semibold text-white">
                    Scan QR APAR
                </Link>
                <div class="relative">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="q"
                        type="text"
                        placeholder="Cari kode, merek, lokasi..."
                        class="w-full rounded-lg border border-[#E8E8EC] bg-white py-2.5 pl-9 pr-3 text-sm text-gray-800 placeholder:text-gray-400 focus:border-[#38BDB5] focus:outline-none focus:ring-1 focus:ring-[#38BDB5]"
                    />
                </div>

                <div v-if="items.length === 0" class="rounded-xl border border-[#E8E8EC] bg-white p-6 text-center">
                    <FireIcon class="mx-auto h-10 w-10 text-[#9299A2]" />
                    <p class="mt-3 text-sm font-medium text-[#263746]">Belum ada APAR aktif</p>
                    <p class="mt-1 text-xs text-[#9299A2]">APAR aktif di unit Anda akan muncul di sini.</p>
                </div>

                
<div v-else class="space-y-2">
    <Link
        v-for="item in items"
        :key="item.id"
        :href="`/app/apar/${item.id}`"
        class="flex items-center justify-between gap-3 rounded-md border border-gray-200 bg-white px-3.5 py-3 transition-colors hover:bg-gray-50 active:bg-gray-100"
    >
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-gray-800">
                {{ item.apar_code }}
            </p>
            <p class="mt-0.5 truncate text-xs text-gray-500">
                {{ item.placement }}
            </p>
        </div>

        <span class="shrink-0 text-[11px] font-medium text-emerald-700">
            Aktif
        </span>
    </Link>

    <!-- Pagination -->
    <div
        v-if="meta.last_page > 1"
        class="flex items-center justify-between border-t border-gray-200 pt-3 text-xs text-gray-500"
    >
        <button
            type="button"
            class="rounded-md border border-gray-200 px-3 py-2 font-medium text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="meta.current_page <= 1"
            @click="go(meta.current_page - 1)"
        >
            Sebelumnya
        </button>

        <span class="tabular-nums">
            {{ meta.current_page }} / {{ meta.last_page }}
        </span>

        <button
            type="button"
            class="rounded-md border border-gray-200 px-3 py-2 font-medium text-gray-600 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="meta.current_page >= meta.last_page"
            @click="go(meta.current_page + 1)"
        >
            Berikutnya
        </button>
    </div>
</div>

            </main>
        </div>

        <template #bottom>
            <BottomNavigation active="apar" />
        </template>
    </AppLayout>
</template>
