<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import UserFilter from './Partials/UserFilter.vue';
import UserTable from './Partials/UserTable.vue';

const props = defineProps({
    users: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 }) },
    group: { type: String, default: 'security' },
    search: { type: String, default: '' },
    sort: { type: String, default: 'username' },
    dir: { type: String, default: 'asc' },
});

const search = ref(props.search);
const sort = ref({ by: props.sort, dir: props.dir });
let debounce = null;

const groupLabel = props.group === 'security' ? 'Akun Security' : 'Akun Admin';

function applyQuery(page = 1) {
    router.get('/admin/user', {
        role: props.group,
        search: search.value,
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
</script>

<template>
    <AdminLayout :title="groupLabel">
        <div class="space-y-6">
            <PageHeader
                :title="groupLabel"
                :breadcrumb="[
                    { label: 'Management User', href: `/admin/user?role=${props.group}` },
                    { label: groupLabel },
                ]"
            >
                <template #actions>
                    <Link
                        :href="`/admin/user/create?role=${props.group}`"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-xs text-white transition hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                    >
                        + Tambah User
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <Card>
                <UserFilter v-model="search" v-model:sort="sort" :count="meta.total" placeholder="Cari user..." />
                <UserTable :rows="users" :meta="meta" @page-change="go" />
            </Card>
        </div>
    </AdminLayout>
</template>
