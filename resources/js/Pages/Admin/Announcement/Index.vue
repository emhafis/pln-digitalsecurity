<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import AnnouncementFilter from './Partials/AnnouncementFilter.vue';
import AnnouncementTable from './Partials/AnnouncementTable.vue';

const props = defineProps({
    announcements: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 }) },
    search: { type: String, default: '' },
    status: { type: String, default: '' },
    sort: { type: String, default: 'dibuat' },
    dir: { type: String, default: 'desc' },
});

const search = ref(props.search);
const status = ref(props.status);
let debounce = null;

function applyQuery(page = 1) {
    router.get('/admin/announcements', {
        search: search.value || undefined,
        status: status.value || undefined,
        sort: props.sort,
        dir: props.dir,
        page,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

watch(search, () => { clearTimeout(debounce); debounce = setTimeout(() => applyQuery(1), 300); });
watch(status, () => applyQuery(1));
</script>

<template>
    <AdminLayout title="Pengumuman">
        <div class="space-y-6">
            <PageHeader
                title="Pengumuman"
                description="Kelola pengumuman yang ditampilkan ke seluruh security di aplikasi."
                :breadcrumb="[{ label: 'Pengumuman', href: '/admin/announcements' }, { label: 'Main' }]"
            >
                <template #actions>
                    <Link
                        href="/admin/announcements/create"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-xs text-white transition hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                    >
                        + Tambah Pengumuman
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <Card>
                <AnnouncementFilter v-model="search" v-model:status="status" :count="meta.total" />
                <AnnouncementTable :rows="announcements" :meta="meta" @page-change="applyQuery" />
            </Card>
        </div>
    </AdminLayout>
</template>
