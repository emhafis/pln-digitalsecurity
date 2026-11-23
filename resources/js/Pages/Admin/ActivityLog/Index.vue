<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import InputLabel from '@/Components/Admin/InputLabel.vue';

const props = defineProps({
    logs: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 }) },
    filters: { type: Object, default: () => ({}) },
    modules: { type: Array, default: () => [] },
    actions: { type: Array, default: () => [] },
});

const search = ref(props.filters.search ?? '');
const module = ref(props.filters.module ?? '');
const action = ref(props.filters.action ?? '');
const startDate = ref(props.filters.start_date ?? '');
const endDate = ref(props.filters.end_date ?? '');
let debounce = null;

const detailOpen = ref(false);
const detailLoading = ref(false);
const detail = ref(null);

function applyQuery(page = 1) {
    router.get('/admin/activity-log', {
        search: search.value,
        module: module.value,
        action: action.value,
        start_date: startDate.value,
        end_date: endDate.value,
        page,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

watch(search, () => { clearTimeout(debounce); debounce = setTimeout(() => applyQuery(1), 300); });
watch([module, action, startDate, endDate], () => applyQuery(1));

function actionLabel(value) {
    return ({ created: 'Dibuat', updated: 'Diperbarui', deleted: 'Dihapus', generated: 'Digenerate', login: 'Login', logout: 'Logout', approved: 'Disetujui', rejected: 'Ditolak' }[value]) || value;
}

async function openDetail(log) {
    detailOpen.value = true;
    detailLoading.value = true;
    detail.value = null;
    try {
        const res = await axios.get(`/admin/activity-log/${log.id}`);
        detail.value = res.data;
    } finally {
        detailLoading.value = false;
    }
}

function closeDetail() {
    if (detailLoading.value) return;
    detailOpen.value = false;
    detail.value = null;
}

function valuePairs(oldValues, newValues) {
    const keys = Array.from(new Set([...(Object.keys(oldValues || {})), ...(Object.keys(newValues || {}))]));
    return keys.map((key) => ({ key, old: oldValues?.[key], new: newValues?.[key] }));
}

function formatValue(value) {
    if (value === null || value === undefined || value === '') return '-';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
}
</script>

<template>
    <AdminLayout title="Log Aktivitas">
        <div class="space-y-6">
            <PageHeader
                title="Log Aktivitas"
                description="Riwayat aktivitas penting pada DSMS."
                :breadcrumb="[{ label: 'Log Aktivitas', href: '/admin/activity-log' }, { label: 'Main' }]"
            />

            <Card>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                    <div class="md:col-span-2">
                        <InputLabel for="log-search" value="Cari user/deskripsi" />
                        <input id="log-search" v-model="search" type="text" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm" placeholder="Nama user atau deskripsi..." />
                    </div>
                    <div>
                        <InputLabel for="log-module" value="Modul" />
                        <select id="log-module" v-model="module" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <option value="">Semua modul</option>
                            <option v-for="m in modules" :key="m" :value="m">{{ m }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel for="log-action" value="Aktivitas" />
                        <select id="log-action" v-model="action" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <option value="">Semua aktivitas</option>
                            <option v-for="a in actions" :key="a" :value="a">{{ actionLabel(a) }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2 md:col-span-1">
                        <div>
                            <InputLabel for="log-start" value="Dari" />
                            <input id="log-start" v-model="startDate" type="date" class="mt-1 w-full rounded-md border border-gray-300 px-2 py-2 text-sm" />
                        </div>
                        <div>
                            <InputLabel for="log-end" value="Sampai" />
                            <input id="log-end" v-model="endDate" type="date" class="mt-1 w-full rounded-md border border-gray-300 px-2 py-2 text-sm" />
                        </div>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto rounded-md border border-gray-200">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Waktu</th>
                                <th class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">User</th>
                                <th class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Modul</th>
                                <th class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Aktivitas</th>
                                <th class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Deskripsi</th>
                                <th class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Unit</th>
                                <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="log in logs" :key="log.id" class="border-t border-gray-100">
                                <td class="px-3 py-2 text-xs text-gray-600 whitespace-nowrap">{{ log.created_at_label }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ log.user_name }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ log.module }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ log.action_label }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ log.description }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ log.unit_name }}</td>
                                <td class="px-3 py-2 text-center">
                                    <button type="button" class="rounded-md px-2 py-1 text-xs text-blue-600 hover:bg-blue-50" @click="openDetail(log)">Detail</button>
                                </td>
                            </tr>
                            <tr v-if="logs.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada log aktivitas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="meta.last_page > 1" class="mt-4 flex items-center justify-between text-xs text-gray-600">
                    <div>Menampilkan {{ meta.from }}-{{ meta.to }} dari {{ meta.total }}</div>
                    <div class="flex gap-2">
                        <Button variant="secondary" :disabled="meta.current_page <= 1" @click="applyQuery(meta.current_page - 1)">Prev</Button>
                        <Button variant="secondary" :disabled="meta.current_page >= meta.last_page" @click="applyQuery(meta.current_page + 1)">Next</Button>
                    </div>
                </div>
            </Card>
        </div>

        <div v-if="detailOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4 sm:p-6" @click.self="closeDetail">
            <div class="flex w-full max-w-3xl max-h-[85vh] flex-col overflow-hidden rounded-lg bg-white shadow-xl" role="dialog" aria-modal="true">
                <div class="flex items-start justify-between border-b border-gray-200 px-4 py-3 sm:px-5">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Detail Log Aktivitas</h3>
                        <p class="text-xs text-gray-500">{{ detail?.created_at_label || '-' }}</p>
                    </div>
                    <button type="button" class="rounded-md p-1 text-gray-500 hover:bg-gray-100" @click="closeDetail">✕</button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5 text-sm">
                    <div v-if="detailLoading" class="text-center text-gray-500 py-8">Memuat detail...</div>
                    <template v-else-if="detail">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 text-xs">
                            <div><span class="text-gray-500">User:</span> <span class="font-medium text-gray-800">{{ detail.user_name }}</span></div>
                            <div><span class="text-gray-500">Waktu:</span> <span class="font-medium text-gray-800">{{ detail.created_at_label }}</span></div>
                            <div><span class="text-gray-500">Modul:</span> <span class="font-medium text-gray-800">{{ detail.module }}</span></div>
                            <div><span class="text-gray-500">Aktivitas:</span> <span class="font-medium text-gray-800">{{ detail.action_label }}</span></div>
                            <div><span class="text-gray-500">Unit:</span> <span class="font-medium text-gray-800">{{ detail.unit_name }}</span></div>
                            <div><span class="text-gray-500">IP:</span> <span class="font-medium text-gray-800">{{ detail.ip_address || '-' }}</span></div>
                            <div class="sm:col-span-2"><span class="text-gray-500">Deskripsi:</span> <span class="font-medium text-gray-800">{{ detail.description }}</span></div>
                        </div>

                        <div class="mt-4">
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Perubahan Data</h4>
                            <div v-if="valuePairs(detail.old_values, detail.new_values).length" class="mt-2 overflow-hidden rounded-md border border-gray-200">
                                <table class="w-full text-xs">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-semibold text-gray-500">Field</th>
                                            <th class="px-3 py-2 text-left font-semibold text-gray-500">Data Lama</th>
                                            <th class="px-3 py-2 text-left font-semibold text-gray-500">Data Baru</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="pair in valuePairs(detail.old_values, detail.new_values)" :key="pair.key" class="border-t border-gray-100 align-top">
                                            <td class="px-3 py-1.5 font-medium text-gray-700">{{ pair.key }}</td>
                                            <td class="px-3 py-1.5 text-gray-600 break-all">{{ formatValue(pair.old) }}</td>
                                            <td class="px-3 py-1.5 text-gray-600 break-all">{{ formatValue(pair.new) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-else class="mt-2 text-xs text-gray-500">Tidak ada perubahan data detail.</p>
                        </div>
                    </template>
                </div>
                <div class="border-t border-gray-200 px-4 py-3 sm:px-5 flex justify-end">
                    <Button variant="secondary" @click="closeDetail">Tutup</Button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
