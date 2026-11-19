<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import HydrantForm from './Partials/HydrantForm.vue';

const props = defineProps({
    hydrant: { type: Object, required: true },
    units: { type: Array, default: () => [] },
});

const form = useForm({
    _method: 'put',
    unit_id: props.hydrant.unit_id ?? '',
    name: props.hydrant.name ?? '',
    hydrant_type: props.hydrant.hydrant_type ?? '',
    brand: props.hydrant.brand ?? '',
    model: props.hydrant.model ?? '',
    serial_number: props.hydrant.serial_number ?? '',
    procurement_date: props.hydrant.procurement_date ?? '',
    installation_date: props.hydrant.installation_date ?? '',
    last_maintenance_date: props.hydrant.last_maintenance_date ?? '',
    placement: props.hydrant.placement ?? '',
    is_active: props.hydrant.is_active ?? true,
    photo: null,
    remove_photo: false,
});

function submit() {
    form.post(`/admin/hydrant/${props.hydrant.id}`, {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <AdminLayout title="Edit Hydrant">
        <div class="space-y-6">
            <PageHeader
                title="Edit Hydrant"
                :breadcrumb="[
                    { label: 'Data Hydrant', href: '/admin/hydrant' },
                    { label: hydrant.hydrant_code, href: `/admin/hydrant/${hydrant.id}` },
                    { label: 'Edit' },
                ]"
            />

            <FlashMessage />

            <Card>
                <form @submit.prevent="submit" class="space-y-6">
                    <HydrantForm
                        :form="form"
                        :units="units"
                        :is-edit="true"
                        :existing-photo-url="hydrant.photo_url"
                    />

                    <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-4">
                        <Link :href="`/admin/hydrant/${hydrant.id}`" class="text-sm text-gray-600 hover:text-gray-900">Batal</Link>
                        <Button type="submit" :disabled="form.processing">Simpan Perubahan</Button>
                    </div>
                </form>
            </Card>
        </div>
    </AdminLayout>
</template>
