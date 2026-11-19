<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppHeader from '@/Components/App/AppHeader.vue';
import BottomNavigation from '@/Components/App/Home/BottomNavigation.vue';
import { ChevronLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    apar: { type: Object, required: true },
    items: { type: Array, default: () => [] },
    meta: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const form = ref({
    search: props.filters.search || '',
    result: props.filters.result || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
});

function apply() {
    router.get(`/app/apar/${props.apar.id}/inspections`, {
        search: form.value.search || undefined,
        result: form.value.result || undefined,
        date_from: form.value.date_from || undefined,
        date_to: form.value.date_to || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function reset() {
    form.value = { search: '', result: '', date_from: '', date_to: '' };
    apply();
}

function go(page) {
    router.get(`/app/apar/${props.apar.id}/inspections`, { ...form.value, page }, { preserveState: true, preserveScroll: true, replace: true });
}


const hasFilters = computed(() => !!(form.value.search || form.value.result || form.value.date_from || form.value.date_to));
</script>

<template>
    <AppLayout>
        <div class="min-h-full bg-gray-50">
            <AppHeader title="Riwayat Inspeksi" :description="apar.apar_code" :backUrl="`/app/apar/${apar.id}`" backLabel="Detail APAR" />

            <main class="space-y-4 px-4 pb-8 pt-4">
                <section class="rounded-xl border border-[#E8E8EC] bg-white p-4 space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-sm font-semibold text-[#263746]">Filter</h2>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-[#9299A2]">Cari (kode APAR / petugas)</label>
                        <input v-model="form.search" type="text" class="w-full rounded-lg border border-[#E8E8EC] px-3 py-2 text-sm" placeholder="Kode atau nama petugas" @keyup.enter="apply" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs text-[#9299A2]">Hasil</label>
                            <select v-model="form.result" class="w-full rounded-lg border border-[#E8E8EC] px-3 py-2 text-sm">
                                <option value="">Semua</option>
                                <option value="ok">Baik (OK)</option>
                                <option value="not_ok">Tidak Baik</option>
                            </select>
                        </div>
                        <div></div>
                        <div>
                            <label class="mb-1 block text-xs text-[#9299A2]">Dari</label>
                            <input v-model="form.date_from" type="date" class="w-full rounded-lg border border-[#E8E8EC] px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-[#9299A2]">Sampai</label>
                            <input v-model="form.date_to" type="date" class="w-full rounded-lg border border-[#E8E8EC] px-3 py-2 text-sm" />
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="flex-1 rounded-lg bg-[#1D567F] px-3 py-2 text-sm font-medium text-white" @click="apply">Terapkan</button>
                        <button v-if="hasFilters" type="button" class="rounded-lg border border-[#E8E8EC] px-3 py-2 text-sm text-[#263746]" @click="reset">Reset</button>
                    </div>
                </section>

                <div v-if="items.length === 0" class="rounded-xl border border-[#E8E8EC] bg-white p-6 text-center text-sm text-[#9299A2]">
                    Belum ada riwayat inspeksi.
                </div>

                <div v-else class="space-y-3">
                    <Link v-for="item in items" :key="item.id" :href="item.detail_url" class="block rounded-xl border border-[#E8E8EC] bg-white p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-[#263746]">{{ item.inspected_at || '-' }}</p>
                                <p class="mt-0.5 text-xs text-[#9299A2]">{{ item.apar_code }}  {{ item.officer_name }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium" :class="item.result === 'ok' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                                {{ item.result_label }}
                            </span>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs text-[#9299A2]">
                            <span>Unit: {{ item.unit_name }}</span>
                            <span>Foto: {{ item.photos_count }}</span>
                        </div>
                    </Link>

                    <div v-if="meta.last_page > 1" class="flex items-center justify-between pt-1 text-xs text-gray-600">
                        <button type="button" class="rounded-md border border-gray-300 px-3 py-1.5 disabled:opacity-40" :disabled="meta.current_page <= 1" @click="go(meta.current_page - 1)">Sebelumnya</button>
                        <span>{{ meta.current_page }} / {{ meta.last_page }}</span>
                        <button type="button" class="rounded-md border border-gray-300 px-3 py-1.5 disabled:opacity-40" :disabled="meta.current_page >= meta.last_page" @click="go(meta.current_page + 1)">Berikutnya</button>
                    </div>
                </div>
            </main>
        </div>

        <template #bottom>
            <BottomNavigation active="apar" />
        </template>
    </AppLayout>
</template>
