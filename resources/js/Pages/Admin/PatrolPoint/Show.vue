<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';

const props = defineProps({
    patrolPoint: { type: Object, required: true },
});

const details = [
    { label: 'QR Code', value: props.patrolPoint.qr_code ?? '-' },
    { label: 'Nama Titik', value: props.patrolPoint.name },
    { label: 'Pos Keamanan', value: props.patrolPoint.security_post_name },
    { label: 'Unit', value: props.patrolPoint.unit_name },
    { label: 'Dibuat', value: props.patrolPoint.created_at },
    { label: 'Diperbarui', value: props.patrolPoint.updated_at },
];

function destroy() {
    if (confirm(`Hapus titik patroli "${props.patrolPoint.name}"?`)) {
        router.delete(`/admin/patrol-point/${props.patrolPoint.id}`);
    }
}
</script>

<template>
    <AdminLayout title="Detail Titik Patroli">
        <div class="space-y-6">
            <PageHeader
                title="Titik Patroli"
                :breadcrumb="[
                    { label: 'Titik Patroli', href: '/admin/patrol-point' },
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
                        :href="`/admin/patrol-point/${props.patrolPoint.id}/edit`"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-xs text-white transition hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                    >
                        Edit
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
                <table class="mt-4 w-full text-left">
                    <tbody>
                        <tr
                            v-for="item in details"
                            :key="item.label"
                            class="border-b border-gray-100 last:border-0"
                        >
                            <th class="w-48 py-2 pr-4 align-top text-xs font-medium text-gray-900">
                                {{ item.label }}
                            </th>
                            <td class="py-2 text-sm text-gray-700">
                                {{ item.value }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </Card>
        </div>
    </AdminLayout>
</template>
