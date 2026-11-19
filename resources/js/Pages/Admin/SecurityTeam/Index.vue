<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import SecurityTeamFilter from './Partials/SecurityTeamFilter.vue';
import SecurityTeamTable from './Partials/SecurityTeamTable.vue';

const props = defineProps({
    teams: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 }) },
    search: { type: String, default: '' },
    sort: { type: String, default: 'name' },
    dir: { type: String, default: 'asc' },
    is_active: { type: [String, Number], default: '' },
    units: { type: Array, default: () => [] },
    posts: { type: Array, default: () => [] },
    unit_id: { type: [String, Number], default: '' },
    security_post_id: { type: [String, Number], default: '' },
});

const search = ref(props.search);
const sort = ref({ by: props.sort, dir: props.dir });
const unitId = ref(props.unit_id ?? '');
const postId = ref(props.security_post_id ?? '');
const status = ref(props.is_active ?? '');
let debounce = null;

function applyQuery(page = 1) {
    router.get('/admin/security-team', {
        search: search.value,
        unit_id: unitId.value,
        security_post_id: postId.value,
        is_active: status.value,
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
    if (postId.value !== '') {
        postId.value = '';
        return;
    }

    applyQuery(1);
});
watch(postId, () => applyQuery(1));
watch(status, () => applyQuery(1));
</script>

<template>
    <AdminLayout title="Data Regu">
        <div class="space-y-6">
            <PageHeader
                title="Data Regu"
                description="Kelola regu keamanan per pos jaga beserta keanggotaannya."
                :breadcrumb="[
                    { label: 'Data Regu', href: '/admin/security-team' },
                    { label: 'Main' },
                ]"
            >
                <template #actions>
                    <Link
                        href="/admin/security-team/create"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-xs text-white transition hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                    >
                        + Tambah Regu
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <Card>
                <SecurityTeamFilter
                    v-model="search"
                    v-model:sort="sort"
                    v-model:unit="unitId"
                    v-model:post="postId"
                    v-model:status="status"
                    :units="units"
                    :posts="posts"
                    :count="meta.total"
                    placeholder="Cari kode atau nama regu..."
                />
                <SecurityTeamTable :rows="teams" :meta="meta" @page-change="go" />
            </Card>
        </div>
    </AdminLayout>
</template>
