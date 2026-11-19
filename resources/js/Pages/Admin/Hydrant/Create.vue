<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import HydrantForm from './Partials/HydrantForm.vue';

defineProps({
    units: { type: Array, default: () => [] },
});

const form = useForm({
    unit_id: '',
    name: '',
    hydrant_type: 'fire_pump_diesel',
    brand: '',
    model: '',
    serial_number: '',
    procurement_date: '',
    installation_date: '',
    last_maintenance_date: '',
    placement: '',
    is_active: true,
    photo: null,
});

function submit() {
    form.post('/admin/hydrant', {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <AdminLayout title="Tambah Hydrant">
        <div class="space-y-6">
            <PageHeader
                title="Tambah Hydrant"
                :breadcrumb="[
                    { label: 'Data Hydrant', href: '/admin/hydrant' },
                    { label: 'Tambah' },
                ]"
            />

            <FlashMessage />

            <Card>
                <form @submit.prevent="submit" class="space-y-6">
                    <HydrantForm :form="form" :units="units" />

                    <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-4">
                        <Link href="/admin/hydrant" class="text-sm text-gray-600 hover:text-gray-900">Batal</Link>
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </Card>
        </div>
    </AdminLayout>
</template>
