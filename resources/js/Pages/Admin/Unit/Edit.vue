<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import UnitForm from './Partials/UnitForm.vue';

const props = defineProps({
    unit: { type: Object, required: true },
});

const form = useForm({
    unit_name: props.unit.unit_name ?? '',
    unit_level: props.unit.unit_level ?? 'uid',
    is_active: props.unit.is_active ?? true,
});

function submit() {
    form.put(`/admin/unit/${props.unit.id}`);
}
</script>

<template>
    <AdminLayout title="Edit Unit">
        <div class="space-y-6">
            <PageHeader
                title="Unit"
                :breadcrumb="[
                    { label: 'Unit', href: '/admin/unit' },
                    { label: 'Detail', href: `/admin/unit/${props.unit.id}` },
                    { label: 'Edit Unit' },
                ]"
            />

            <FlashMessage />

            <Card>
                <UnitForm :form="form" :show-status="true" />

                <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <Link
                        href="/admin/unit"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    >
                        Batal
                    </Link>
                    <Button
                        :disabled="form.processing"
                        @click="submit"
                    >
                        Simpan Perubahan
                    </Button>
                </div>
            </Card>
        </div>
    </AdminLayout>
</template>
