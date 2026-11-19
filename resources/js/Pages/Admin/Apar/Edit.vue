<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import AparForm from './Partials/AparForm.vue';

const props = defineProps({
    apar: { type: Object, required: true },
    units: { type: Array, default: () => [] },
});

const form = useForm({
    _method: 'put',
    unit_id: props.apar.unit_id ?? '',
    apar_type: props.apar.apar_type ?? '',
    brand: props.apar.brand ?? '',
    capacity: props.apar.capacity ?? '',
    placement: props.apar.placement ?? '',
    serial_number: props.apar.serial_number ?? '',
    procurement_date: props.apar.procurement_date ?? '',
    installation_date: props.apar.installation_date ?? '',
    recharge_date: props.apar.recharge_date ?? '',
    expiry_date: props.apar.expiry_date ?? '',
    is_active: props.apar.is_active ?? true,
    photo: null,
    remove_photo: false,
});

function submit() {
    form.post(`/admin/apar/${props.apar.id}`, {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <AdminLayout title="Edit APAR">
        <div class="space-y-6">
            <PageHeader
                title="Edit APAR"
                :breadcrumb="[
                    { label: 'Data APAR', href: '/admin/apar' },
                    { label: apar.apar_code, href: `/admin/apar/${apar.id}` },
                    { label: 'Edit' },
                ]"
            />

            <FlashMessage />

            <Card>
                <form @submit.prevent="submit" class="space-y-6">
                    <AparForm :form="form" :units="units" :is-edit="true" :existing-photo-url="apar.photo_url" />

                    <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-4">
                        <Link :href="`/admin/apar/${apar.id}`" class="text-sm text-gray-600 hover:text-gray-900">Batal</Link>
                        <Button type="submit" :disabled="form.processing">Simpan Perubahan</Button>
                    </div>
                </form>
            </Card>
        </div>
    </AdminLayout>
</template>
