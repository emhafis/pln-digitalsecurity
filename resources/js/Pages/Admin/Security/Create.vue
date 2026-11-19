<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import SecurityForm from './Partials/SecurityForm.vue';

defineProps({
    units: { type: Array, default: () => [] },
    canChooseUnit: { type: Boolean, default: false },
});

const form = useForm({
    full_name: '',
    phone_number: '',
    address: '',
    birth_place: '',
    birth_date: '',
    kta_number: '',
    kta_issued_date: '',
    kta_expired_date: '',
    unit_id: null,
    photo: null,
    kta_file: null,
});

function submit() {
    form.post('/admin/security', { forceFormData: true });
}
</script>

<template>
    <AdminLayout title="Tambah Personel Security">
        <div class="space-y-6">
            <PageHeader
                title="Personel Security"
                :breadcrumb="[
                    { label: 'Personel Security', href: '/admin/security' },
                    { label: 'Tambah Personel' },
                ]"
            />

            <FlashMessage />

            <Card>
                <SecurityForm
                    :form="form"
                    :units="units"
                    :can-choose-unit="canChooseUnit"
                />

                <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <Link
                        href="/admin/security"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    >
                        Batal
                    </Link>
                    <Button
                        :disabled="form.processing"
                        @click="submit"
                    >
                        Simpan
                    </Button>
                </div>
            </Card>
        </div>
    </AdminLayout>
</template>
