<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';

const props = defineProps({
    securityPost: { type: Object, required: true },
    patrolPoints: { type: Array, default: () => [] },
});

const info = computed(() => [
    { label: 'Kode Pos', value: props.securityPost.code ?? '-' },
    { label: 'Nama Pos Jaga', value: props.securityPost.name },
    { label: 'Unit', value: props.securityPost.unit_name },
    { label: 'Jumlah Titik Patroli', value: props.securityPost.patrol_points_count },
]);

function destroy(patrolPoint) {
    if (confirm(`Hapus titik patroli "${patrolPoint.name}"?`)) {
        router.delete(`/admin/patrol-point/${patrolPoint.id}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <AdminLayout title="Detail Pos Jaga">
        <div class="space-y-6">
            <PageHeader
                title="Detail Pos Jaga"
                :breadcrumb="[
                    { label: 'Titik Patroli', href: '/admin/patrol-point' },
                    { label: props.securityPost.name },
                ]"
            >
                <template #actions>
                    <a
                        v-if="props.securityPost.patrol_points_count > 0"
                        :href="`/admin/patrol-point/post/${props.securityPost.id}/qr.pdf`"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    >
                        Unduh QR
                    </a>
                    <button
                        v-else
                        type="button"
                        disabled
                        title="Belum memiliki titik patroli"
                        class="inline-flex cursor-not-allowed items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-400 opacity-60"
                    >
                        Unduh QR
                    </button>
                    <Link
                        :href="`/admin/patrol-point/create?security_post_id=${props.securityPost.id}`"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-xs font-medium text-white transition hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                    >
                        + Tambah Titik Patroli
                    </Link>
                    <Link
                        href="/admin/patrol-point"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    >
                        Kembali
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <Card>
                <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="item in info" :key="item.label">
                        <dt class="text-xs font-medium text-gray-500">{{ item.label }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-800">{{ item.value }}</dd>
                    </div>
                </dl>
            </Card>

            <Card>
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-800">Titik Patroli</h2>
                    <p class="text-xs text-gray-500">{{ patrolPoints.length }} titik</p>
                </div>

                <DataTable :has-rows="patrolPoints.length > 0">
                    <template #head>
                        <th class="w-16 px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">No</th>
                        <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Nama Titik Patroli</th>
                        <th class="px-2 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Aksi</th>
                    </template>

                    <tr v-for="(patrolPoint, index) in patrolPoints" :key="patrolPoint.id">
                        <td class="px-2 py-3.5 text-sm text-gray-500">{{ index + 1 }}</td>
                        <td class="px-2 py-3.5 text-sm text-gray-600">{{ patrolPoint.name }}</td>
                        <td class="px-2 py-3.5 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <Link
                                    :href="`/admin/patrol-point/${patrolPoint.id}/edit`"
                                    class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-gray-600 transition hover:bg-gray-100 hover:text-gray-900"
                                >
                                    Edit
                                </Link>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-red-600 transition hover:bg-red-50 hover:text-red-700"
                                    @click="destroy(patrolPoint)"
                                >
                                    Hapus
                                </button>
                            </div>
                        </td>
                    </tr>

                    <template #empty-action>
                        <Link
                            :href="`/admin/patrol-point/create?security_post_id=${props.securityPost.id}`"
                            class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-xs font-medium text-white transition hover:bg-blue-700"
                        >
                            + Tambah Titik Patroli
                        </Link>
                    </template>
                </DataTable>
            </Card>
        </div>
    </AdminLayout>
</template>
