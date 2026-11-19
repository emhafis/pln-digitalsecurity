<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import SecurityForm from './Partials/SecurityForm.vue';

const props = defineProps({
    security: { type: Object, required: true },
    units: { type: Array, default: () => [] },
    canChooseUnit: { type: Boolean, default: false },
});

const form = useForm({
    full_name: props.security.full_name ?? '',
    phone_number: props.security.phone_number ?? '',
    address: props.security.address ?? '',
    birth_place: props.security.birth_place ?? '',
    birth_date: props.security.birth_date ?? '',
    kta_number: props.security.kta_number ?? '',
    kta_issued_date: props.security.kta_issued_date ?? '',
    kta_expired_date: props.security.kta_expired_date ?? '',
    unit_id: props.security.unit_id ?? null,
    photo: null,
    kta_file: null,
});

function submit() {
    form.put(`/admin/security/${props.security.id}`, { forceFormData: true });
}
</script>

<template>
    <AdminLayout title="Edit Personel Security">
        <div class="space-y-6">
            <PageHeader
                title="Personel Security"
                :breadcrumb="[
                    { label: 'Personel Security', href: '/admin/security' },
                    { label: 'Detail', href: `/admin/security/${props.security.id}` },
                    { label: 'Edit Personel' },
                ]"
            />

            <FlashMessage />

            <Card>
                <SecurityForm
                    :form="form"
                    :units="units"
                    :can-choose-unit="canChooseUnit"
                    :existing-photo-url="props.security.photo_url ?? ''"
                    :existing-kta-url="props.security.kta_file_url ?? ''"
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
                        Simpan Perubahan
                    </Button>
                </div>
            </Card>
        </div>
    </AdminLayout>
</template>
