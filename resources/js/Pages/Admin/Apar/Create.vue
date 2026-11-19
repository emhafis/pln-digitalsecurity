<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import AparForm from './Partials/AparForm.vue';

const props = defineProps({
    units: { type: Array, default: () => [] },
});

const form = useForm({
    unit_id: '',
    apar_type: '',
    brand: '',
    capacity: '',
    placement: '',
    serial_number: '',
    procurement_date: '',
    installation_date: '',
    recharge_date: '',
    expiry_date: '',
    is_active: true,
    photo: null,
});

function submit() {
    form.post('/admin/apar', {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <AdminLayout title="Tambah APAR">
        <div class="space-y-6">
            <PageHeader
                title="Tambah APAR"
                :breadcrumb="[
                    { label: 'Data APAR', href: '/admin/apar' },
                    { label: 'Tambah' },
                ]"
            />

            <FlashMessage />

            <Card>
                <form @submit.prevent="submit" class="space-y-6">
                    <AparForm :form="form" :units="units" />

                    <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-4">
                        <Link href="/admin/apar" class="text-sm text-gray-600 hover:text-gray-900">Batal</Link>
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </Card>
        </div>
    </AdminLayout>
</template>
