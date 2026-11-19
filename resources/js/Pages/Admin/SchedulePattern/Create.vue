<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import SchedulePatternForm from './Partials/SchedulePatternForm.vue';

const props = defineProps({
    units: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
});

const initialCycleLength = 8;

const form = useForm({
    unit_id: props.units.length === 1 ? props.units[0].value : null,
    name: '',
    description: '',
    cycle_length: initialCycleLength,
    is_active: true,
    details: Array.from({ length: initialCycleLength }, (_, index) => ({
        day_order: index + 1,
        shift_id: '',
    })),
});

function submit() {
    form.post('/admin/schedule-pattern');
}
</script>

<template>
    <AdminLayout title="Tambah Pola Rotasi">
        <div class="space-y-6">
            <PageHeader
                title="Pola Rotasi"
                :breadcrumb="[
                    { label: 'Pola Rotasi', href: '/admin/schedule-pattern' },
                    { label: 'Tambah Pola' },
                ]"
            />

            <FlashMessage />

            <Card>
                <SchedulePatternForm :form="form" :units="units" :shifts="shifts" />

                <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <Link
                        href="/admin/schedule-pattern"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    >
                        Batal
                    </Link>
                    <Button :disabled="form.processing" @click="submit">
                        Simpan
                    </Button>
                </div>
            </Card>
        </div>
    </AdminLayout>
</template>
