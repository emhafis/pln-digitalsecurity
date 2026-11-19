<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import ShiftForm from './Partials/ShiftForm.vue';

const props = defineProps({
    shift: { type: Object, required: true },
    units: { type: Array, default: () => [] },
});

const form = useForm({
    unit_id: props.shift.unit_id ?? null,
    name: props.shift.name ?? '',
    type: props.shift.type ?? 'working',
    start_time: props.shift.start_time ?? '',
    end_time: props.shift.end_time ?? '',
    end_day_offset: Boolean(props.shift.end_day_offset),
    is_active: Boolean(props.shift.is_active),
});

function submit() {
    form.put(`/admin/shift/${props.shift.id}`);
}
</script>

<template>
    <AdminLayout title="Edit Shift Kerja">
        <div class="space-y-6">
            <PageHeader
                title="Shift Kerja"
                :breadcrumb="[
                    { label: 'Shift Kerja', href: '/admin/shift' },
                    { label: 'Detail', href: `/admin/shift/${props.shift.id}` },
                    { label: 'Edit Shift' },
                ]"
            />

            <FlashMessage />

            <Card>
                <ShiftForm :form="form" :units="units" />

                <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <Link
                        href="/admin/shift"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    >
                        Batal
                    </Link>
                    <Button :disabled="form.processing" @click="submit">
                        Simpan Perubahan
                    </Button>
                </div>
            </Card>
        </div>
    </AdminLayout>
</template>
