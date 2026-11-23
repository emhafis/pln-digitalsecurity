<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import DeleteSchedulePeriodModal from './Partials/DeleteSchedulePeriodModal.vue';
import SchedulePeriodFilter from './Partials/SchedulePeriodFilter.vue';
import SchedulePeriodTable from './Partials/SchedulePeriodTable.vue';

const props = defineProps({
    periods: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 }) },
    search: { type: String, default: '' },
    sort: { type: String, default: 'start_date' },
    dir: { type: String, default: 'desc' },
    status: { type: String, default: '' },
    start_date: { type: String, default: '' },
    end_date: { type: String, default: '' },
    units: { type: Array, default: () => [] },
    posts: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    unit_id: { type: [String, Number], default: '' },
    security_post_id: { type: [String, Number], default: '' },
    security_team_id: { type: [String, Number], default: '' },
});

const search = ref(props.search);
const sort = ref({ by: props.sort, dir: props.dir });
const unitId = ref(props.unit_id ?? '');
const postId = ref(props.security_post_id ?? '');
const teamId = ref(props.security_team_id ?? '');
const status = ref(props.status ?? '');
const startDate = ref(props.start_date ?? '');
const endDate = ref(props.end_date ?? '');
let debounce = null;

const page = usePage();
const bulkCreated = computed(() => page.props.flash?.bulk_created ?? []);

const deleteModalOpen = ref(false);
const deleteLoading = ref(false);
const deleteSummary = ref(null);
const deleteError = ref('');
const deletingPeriod = ref(null);
const deleteForm = useForm({ password: '' });

function applyQuery(page = 1) {
    router.get('/admin/schedule-period', {
        search: search.value,
        unit_id: unitId.value,
        security_post_id: postId.value,
        security_team_id: teamId.value,
        status: status.value,
        start_date: startDate.value,
        end_date: endDate.value,
        sort: sort.value.by,
        dir: sort.value.dir,
        page,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function go(page) {
    applyQuery(page);
}

watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => applyQuery(1), 300);
});

watch(sort, () => applyQuery(1));
watch(unitId, () => {
    postId.value = '';
    teamId.value = '';
    applyQuery(1);
});
watch(postId, () => {
    teamId.value = '';
    applyQuery(1);
});
watch(teamId, () => applyQuery(1));
watch(status, () => applyQuery(1));
watch(startDate, () => applyQuery(1));
watch(endDate, () => applyQuery(1));

async function openDeleteModal(row) {
    deletingPeriod.value = row;
    deleteModalOpen.value = true;
    deleteLoading.value = true;
    deleteSummary.value = null;
    deleteError.value = '';
    deleteForm.reset();
    deleteForm.clearErrors();

    try {
        const response = await axios.get(`/admin/schedule-period/${row.id}/delete-summary`);
        deleteSummary.value = response.data;
        if (response.data?.is_locked) {
            deleteError.value = response.data.locked_reason || 'Periode tidak dapat dihapus karena sudah selesai atau dikunci.';
        }
    } catch (error) {
        deleteError.value = error?.response?.data?.message || 'Gagal memuat rincian data yang akan dihapus.';
    } finally {
        deleteLoading.value = false;
    }
}

function closeDeleteModal() {
    if (deleteForm.processing) return;
    deleteModalOpen.value = false;
    deletingPeriod.value = null;
    deleteSummary.value = null;
    deleteError.value = '';
    deleteForm.reset();
    deleteForm.clearErrors();
}

function submitDelete() {
    if (!deletingPeriod.value || deleteForm.processing) return;
    deleteError.value = '';
    deleteForm.clearErrors();

    deleteForm.delete(`/admin/schedule-period/${deletingPeriod.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteModalOpen.value = false;
            deletingPeriod.value = null;
            deleteSummary.value = null;
            deleteForm.reset();
            applyQuery(1);
        },
        onError: (errors) => {
            if (errors?.period) deleteError.value = errors.period;
        },
    });
}
</script>

<template>
    <AdminLayout title="Periode Jadwal">
        <div class="space-y-6">
            <PageHeader
                title="Periode Jadwal"
                description="Kelola periode jadwal satpam dan snapshot anggota regu."
                :breadcrumb="[
                    { label: 'Periode Jadwal', href: '/admin/schedule-period' },
                    { label: 'Main' },
                ]"
            >
                <template #actions>
                <Link
                    href="/admin/schedule-period/bulk-create"
                    class="inline-flex items-center justify-center gap-2 rounded-md border border-blue-600 bg-white px-4 py-2 text-xs text-blue-600 transition hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                >
                    Atur Jadwal per Pos
                </Link>
                <Link
                    href="/admin/schedule-period/create"
                    class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-xs text-white transition hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                >
                    + Tambah Periode
                </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <Card
                v-if="bulkCreated.length > 0"
                :title="`${bulkCreated.length} periode jadwal berhasil dibuat`"
                description="Ringkasan periode yang baru saja dibuat dari pengaturan jadwal per pos jaga."
            >
                <ul class="divide-y divide-gray-100">
                    <li
                        v-for="period in bulkCreated"
                        :key="period.id"
                        class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div class="min-w-0 text-xs text-gray-700">
                            <p class="text-sm font-semibold text-gray-800">{{ period.name }}</p>
                            <p class="mt-1">
                                <span class="font-medium">Regu:</span> {{ period.team_name }}
                                <span class="mx-1">•</span>
                                {{ period.start_date }} s/d {{ period.end_date }}
                                <span class="mx-1">•</span>
                                {{ period.status_label }}
                            </p>
                        </div>
                        <Link
                            :href="period.show_url"
                            class="shrink-0 text-xs font-medium text-blue-600 hover:text-blue-800"
                        >
                            Lihat detail
                        </Link>
                    </li>
                </ul>
            </Card>

            <Card>
                <SchedulePeriodFilter
                    v-model="search"
                    v-model:sort="sort"
                    v-model:unit="unitId"
                    v-model:post="postId"
                    v-model:team="teamId"
                    v-model:status="status"
                    v-model:start-date="startDate"
                    v-model:end-date="endDate"
                    :units="units"
                    :posts="posts"
                    :teams="teams"
                    :count="meta.total"
                />
                <SchedulePeriodTable :rows="periods" :meta="meta" @page-change="go" @delete="openDeleteModal" />
            </Card>

            <DeleteSchedulePeriodModal
                :open="deleteModalOpen"
                :loading="deleteLoading"
                :processing="deleteForm.processing"
                :summary="deleteSummary"
                :password="deleteForm.password"
                :error="deleteError"
                :password-error="deleteForm.errors?.password"
                :period-error="deleteForm.errors?.period"
                @update:password="deleteForm.password = $event"
                @close="closeDeleteModal"
                @confirm="submitDelete"
            />
        </div>
    </AdminLayout>
</template>
