<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';

const props = defineProps({
    unit: { type: Object, required: true },
    can_manage: { type: Boolean, default: false },
});

const details = [
    { label: 'Kode Unit', value: props.unit.code ?? '-' },
    { label: 'Nama Unit', value: props.unit.name },
    { label: 'Level', value: props.unit.level },
    { label: 'Unit Induk', value: props.unit.parent },
    { label: 'Status', value: props.unit.status},
    { label: 'Dibuat', value: props.unit.created_at },
    { label: 'Diperbarui', value: props.unit.updated_at },
];

function destroy() {
    if (confirm(`Hapus unit "${props.unit.name}"?`)) {
        router.delete(`/admin/unit/${props.unit.id}`);
    }
}
</script>

<template>
    <AdminLayout title="Detail Unit">
        <div class="space-y-6">
            <PageHeader
                title="Unit"
                :breadcrumb="[
                    { label: 'Unit', href: '/admin/unit' },
                    { label: 'Detail' },
                ]"
            >
                <template #actions>
                    <template v-if="can_manage">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-md bg-red-600 px-4 py-2 text-xs text-white transition hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2"
                            @click="destroy"
                        >
                            Hapus
                        </button>
                        <Link
                            :href="`/admin/unit/${props.unit.id}/edit`"
                            class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-xs text-white transition hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                        >
                            Edit
                        </Link>
                    </template>
                    <Link
                        href="/admin/unit"
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
