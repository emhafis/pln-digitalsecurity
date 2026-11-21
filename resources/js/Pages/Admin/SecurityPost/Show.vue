
<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';

const props = defineProps({
    securityPost: { type: Object, required: true },
});

const details = [
    { label: 'Kode Pos', value: props.securityPost.post_code ?? '-' },
    { label: 'Nama Pos', value: props.securityPost.name ?? '-' },
    { label: 'Unit', value: props.securityPost.unit_name ?? '-' },
    { label: 'Alamat', value: props.securityPost.address ?? '-' },
];

const coordinates = [
    { label: 'Latitude', value: props.securityPost.latitude ?? '-' },
    { label: 'Longitude', value: props.securityPost.longitude ?? '-' },
];

const timestamps = [
    { label: 'Dibuat pada', value: props.securityPost.created_at ?? '-' },
    { label: 'Terakhir diperbarui', value: props.securityPost.updated_at ?? '-' },
];

function destroy() {
    if (confirm(`Hapus post keamanan "${props.securityPost.name}"?`)) {
        router.delete(`/admin/security-post/${props.securityPost.id}`);
    }
}
</script>

<template>
    <AdminLayout title="Detail Post Keamanan">
        <div class="space-y-6">
            <PageHeader
                title="Post Keamanan"
                :breadcrumb="[
                    { label: 'Post Keamanan', href: '/admin/security-post' },
                    { label: 'Detail' },
                ]"
            >
                <template #actions>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-red-600 px-4 py-2 text-xs text-white transition hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2"
                        @click="destroy"
                    >
                        Hapus
                    </button>
                    <Link
                        :href="`/admin/security-post/${props.securityPost.id}/edit`"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-xs text-white transition hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                    >
                        Edit
                    </Link>
                    <Link
                        href="/admin/security-post"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    >
                        Kembali
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <!-- Identitas pos -->
            <Card>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-400">
                            Informasi pos keamanan
                        </p>

                        <h2 class="mt-2 text-xl font-semibold tracking-tight text-gray-900">
                            {{ securityPost.name }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            {{ securityPost.unit_name || 'Unit belum ditentukan' }}
                        </p>
                    </div>

                    <div class="shrink-0 self-start rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">
                            Kode Pos
                        </p>
                        <p class="mt-0.5 text-sm font-semibold tabular-nums text-gray-800">
                            {{ securityPost.post_code || '-' }}
                        </p>
                    </div>
                </div>
            </Card>

            <!-- Informasi utama -->
            <Card>
                <div class="border-b border-gray-100 pb-4">
                    <h3 class="text-sm font-semibold text-gray-900">
                        Informasi Umum
                    </h3>
                    <p class="mt-1 text-xs text-gray-500">
                        Data identitas dan lokasi pos keamanan.
                    </p>
                </div>

                <dl class="divide-y divide-gray-100">
                    <div
                        v-for="item in details"
                        :key="item.label"
                        class="grid grid-cols-1 gap-1 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:gap-6"
                    >
                        <dt class="text-xs font-medium text-gray-500">
                            {{ item.label }}
                        </dt>
                        <dd class="break-words text-sm leading-relaxed text-gray-800">
                            {{ item.value }}
                        </dd>
                    </div>
                </dl>
            </Card>

            <!-- Koordinat -->
            <Card>
                <div class="flex items-start justify-between gap-4 border-b border-gray-100 pb-4">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">
                            Koordinat Lokasi
                        </h3>
                        <p class="mt-1 text-xs text-gray-500">
                            Titik geografis pos keamanan.
                        </p>
                    </div>
                    <span class="rounded-md bg-gray-100 px-2.5 py-1 text-[10px] font-medium text-gray-600">
                        GPS
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-4 pt-4 sm:grid-cols-2">
                    <div
                        v-for="coordinate in coordinates"
                        :key="coordinate.label"
                        class="rounded-md border border-gray-200 px-4 py-3"
                    >
                        <p class="text-xs text-gray-500">
                            {{ coordinate.label }}
                        </p>
                        <p class="mt-2 break-all font-mono text-sm font-medium tabular-nums text-gray-900">
                            {{ coordinate.value }}
                        </p>
                    </div>
                </div>
            </Card>

            <!-- Riwayat -->
            <Card>
                <div class="border-b border-gray-100 pb-4">
                    <h3 class="text-sm font-semibold text-gray-900">
                        Riwayat Data
                    </h3>
                    <p class="mt-1 text-xs text-gray-500">
                        Informasi pencatatan dan perubahan data.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-5 pt-4 sm:grid-cols-2">
                    <div
                        v-for="timestamp in timestamps"
                        :key="timestamp.label"
                    >
                        <p class="text-xs text-gray-500">
                            {{ timestamp.label }}
                        </p>
                        <p class="mt-1.5 text-sm font-medium text-gray-800">
                            {{ timestamp.value }}
                        </p>
                    </div>
                </div>
            </Card>
        </div>
    </AdminLayout>
</template>
