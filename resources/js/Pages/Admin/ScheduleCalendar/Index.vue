<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Badge from '@/Components/Admin/Badge.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import CalendarFilter from './Partials/CalendarFilter.vue';
import CalendarGrid from './Partials/CalendarGrid.vue';
import CalendarTable from './Partials/CalendarTable.vue';

const props = defineProps({
    month: { type: String, default: '' },
    month_label: { type: String, default: '' },
    prev_month: { type: String, default: '' },
    next_month: { type: String, default: '' },
    days: { type: Array, default: () => [] },
    leading_blanks: { type: Number, default: 0 },
    rows: { type: Array, default: () => [] },
    legend: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    posts: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    securities: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    statusOptions: { type: Array, default: () => [] },
    requires_unit_selection: { type: Boolean, default: false },
});

const month = ref(props.filters?.month ?? props.month);
const unitId = ref(props.filters?.unit_id ?? '');
const postId = ref(props.filters?.security_post_id ?? '');
const teamId = ref(props.filters?.security_team_id ?? '');
const securityId = ref(props.filters?.security_id ?? '');
const status = ref(props.filters?.status ?? '');
const view = ref(props.filters?.view ?? 'calendar');

const selected = ref(null);

const rowsByDate = computed(() => props.rows.reduce((acc, row) => {
    if (!acc[row.date]) {
        acc[row.date] = [];
    }
    acc[row.date].push(row);

    return acc;
}, {}));

const summary = computed(() => {
    const total = props.rows.length;
    const adjustment = props.rows.filter((row) => row.source === 'adjustment').length;
    const guards = new Set(props.rows.map((row) => row.security_id)).size;

    return [
        { label: 'Total Jadwal', value: total },
        { label: 'Satpam', value: guards },
        { label: 'Penyesuaian', value: adjustment },
        { label: 'Dihasilkan Sistem', value: total - adjustment },
    ];
});

function applyQuery() {
    router.get('/admin/schedule-calendar', {
        month: month.value || props.month,
        unit_id: unitId.value,
        security_post_id: postId.value,
        security_team_id: teamId.value,
        security_id: securityId.value,
        status: status.value,
        view: view.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

let debounce = null;

function scheduleApply() {
    clearTimeout(debounce);
    debounce = setTimeout(() => applyQuery(), 50);
}

function goMonth(value) {
    month.value = value;
}

function setView(value) {
    view.value = value;
}

function openDetail(row) {
    selected.value = row;
}

function closeDetail() {
    selected.value = null;
}

watch(month, scheduleApply);
watch(unitId, () => {
    postId.value = '';
    teamId.value = '';
    securityId.value = '';
    scheduleApply();
});
watch(postId, () => {
    teamId.value = '';
    securityId.value = '';
    scheduleApply();
});
watch(teamId, () => {
    securityId.value = '';
    scheduleApply();
});
watch(securityId, scheduleApply);
watch(status, scheduleApply);
watch(view, scheduleApply);
</script>

<template>
    <AdminLayout title="Kalender & Monitoring Jadwal">
        <div class="space-y-6">
            <PageHeader
                title="Kalender & Monitoring Jadwal"
                description="Pantau jadwal aktual satpam berdasarkan periode, unit, pos, regu, dan satpam."
                :breadcrumb="[
                    { label: 'Kalender & Monitoring', href: '/admin/schedule-calendar' },
                    { label: 'Penjadwalan' },
                ]"
            >
                <template #actions>
                    <Link
                        href="/admin/schedule-period"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    >
                        Periode Jadwal
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div v-for="item in summary" :key="item.label" class="rounded-xl border border-gray-200 bg-white px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ item.label }}</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ item.value }}</p>
                </div>
            </div>

            <Card>
                <div
                    v-if="requires_unit_selection"
                    class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-6 text-center text-sm text-amber-700"
                >
                    Silakan pilih unit untuk melihat kalender jadwal.
                </div>

                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-600 transition hover:bg-gray-50"
                            :aria-label="`Bulan sebelumnya, ${prev_month}`"
                            @click="goMonth(prev_month)"
                        >
                            &lsaquo;
                        </button>
                        <span class="min-w-[150px] text-center text-sm font-semibold text-gray-800">
                            {{ month_label }}
                        </span>
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-600 transition hover:bg-gray-50"
                            :aria-label="`Bulan berikutnya, ${next_month}`"
                            @click="goMonth(next_month)"
                        >
                            &rsaquo;
                        </button>
                    </div>

                    <div class="inline-flex rounded-md border border-gray-300 bg-white p-0.5">
                        <button
                            type="button"
                            class="rounded px-3 py-1 text-xs font-medium transition"
                            :class="view === 'calendar' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                            @click="setView('calendar')"
                        >
                            Kalender
                        </button>
                        <button
                            type="button"
                            class="rounded px-3 py-1 text-xs font-medium transition"
                            :class="view === 'list' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                            @click="setView('list')"
                        >
                            Daftar
                        </button>
                    </div>
                </div>

                <CalendarFilter
                    v-model:month="month"
                    v-model:unit="unitId"
                    v-model:post="postId"
                    v-model:team="teamId"
                    v-model:security="securityId"
                    v-model:status="status"
                    :units="units"
                    :posts="posts"
                    :teams="teams"
                    :securities="securities"
                    :status-options="statusOptions"
                    :count="rows.length"
                />

                <div v-if="!requires_unit_selection && legend.length" class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-md border border-gray-100 bg-gray-50/60 px-3 py-2">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Legenda</span>
                    <span v-for="shift in legend" :key="shift.id" class="flex items-center gap-1.5 text-xs text-gray-600">
                        <span class="inline-block h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: shift.color || '#94a3b8' }" />
                        {{ shift.name }}
                        <span v-if="shift.is_off" class="rounded bg-emerald-50 px-1 text-[10px] font-medium text-emerald-700">Libur</span>
                    </span>
                    <span class="flex items-center gap-1.5 text-xs text-gray-600">
                        <Badge variant="gray">R</Badge> Rotation
                    </span>
                    <span class="flex items-center gap-1.5 text-xs text-gray-600">
                        <Badge variant="amber">M</Badge> Manual
                    </span>
                </div>

                <template v-if="!requires_unit_selection">
                    <CalendarGrid
                        v-if="view === 'calendar'"
                        :days="days"
                        :leading-blanks="leading_blanks"
                        :rows-by-date="rowsByDate"
                        @select="openDetail"
                    />

                    <CalendarTable v-else :rows="rows" @select="openDetail" />

                    <p v-if="rows.length === 0" class="mt-4 rounded-md border border-dashed border-gray-200 px-4 py-6 text-center text-sm text-gray-500">
                        Tidak ada jadwal pada bulan dan filter yang dipilih.
                    </p>
                </template>
            </Card>
        </div>

        <div
            v-if="selected"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4"
            @click.self="closeDetail"
            @keydown.esc="closeDetail"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="schedule-detail-title"
                class="w-full max-w-lg rounded-lg bg-white p-5 shadow-xl"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 id="schedule-detail-title" class="text-sm font-semibold text-gray-800">Detail Jadwal</h3>
                        <p class="mt-1 text-xs text-gray-500">{{ selected.full_name }} — {{ selected.date_label }}</p>
                    </div>
                    <Badge :variant="selected.source === 'adjustment' ? 'amber' : 'gray'">
                        {{ selected.source === 'adjustment' ? 'Penyesuaian' : 'Dihasilkan Sistem' }}
                    </Badge>
                </div>

                <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <div class="rounded-md border border-gray-200 p-3">
                        <dt class="text-xs text-gray-500">Shift</dt>
                        <dd class="mt-1 font-medium text-gray-800">{{ selected.shift_name ?? '-' }}</dd>
                        <dd v-if="selected.shift_time" class="text-xs text-gray-500">{{ selected.shift_time }}</dd>
                        <dd v-if="selected.is_off" class="mt-1 text-xs font-medium text-emerald-700">Hari Libur</dd>
                    </div>
                    <div class="rounded-md border border-gray-200 p-3">
                        <dt class="text-xs text-gray-500">Periode</dt>
                        <dd class="mt-1 font-medium text-gray-800">{{ selected.period_name ?? '-' }}</dd>
                        <dd class="text-xs text-gray-500">{{ selected.period_status_label ?? '-' }}</dd>
                    </div>
                    <div class="rounded-md border border-gray-200 p-3">
                        <dt class="text-xs text-gray-500">Unit / Pos</dt>
                        <dd class="mt-1 text-gray-800">{{ selected.unit_name ?? '-' }}</dd>
                        <dd class="text-xs text-gray-500">{{ selected.post_name ?? '-' }}</dd>
                    </div>
                    <div class="rounded-md border border-gray-200 p-3">
                        <dt class="text-xs text-gray-500">Regu</dt>
                        <dd class="mt-1 text-gray-800">{{ selected.team_name ?? '-' }}</dd>
                    </div>
                    <div class="rounded-md border border-gray-200 p-3 sm:col-span-2">
                        <dt class="text-xs text-gray-500">Alasan Perubahan</dt>
                        <dd class="mt-1 text-gray-800">{{ selected.notes ?? '-' }}</dd>
                    </div>
                    <div class="rounded-md border border-gray-200 p-3 sm:col-span-2">
                        <dt class="text-xs text-gray-500">Terakhir Diubah</dt>
                        <dd class="mt-1 text-gray-800">
                            <template v-if="selected.updated_by_name">
                                {{ selected.updated_by_name }}
                                <span class="block text-xs text-gray-500">{{ selected.updated_at }}</span>
                            </template>
                            <template v-else>-</template>
                        </dd>
                    </div>
                </dl>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-2">
                    <p v-if="!selected.can_edit" class="text-xs text-gray-500">
                        Periode sudah selesai. Penyesuaian jadwal tidak tersedia.
                    </p>
                    <span v-else class="text-xs text-gray-500">
                        Penyesuaian dilakukan di halaman detail periode.
                    </span>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50"
                            @click="closeDetail"
                        >
                            Tutup
                        </button>
                        <Link
                            v-if="selected.can_edit"
                            :href="`/admin/schedule-period/${selected.period_id}`"
                            class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-xs text-white transition hover:bg-blue-700"
                        >
                            Sesuaikan Jadwal
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
