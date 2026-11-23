<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import ShiftForm from './Partials/ShiftForm.vue';

const props = defineProps({
    units: { type: Array, default: () => [] },
});

const form = useForm({
    unit_id: props.units.length === 1 ? props.units[0].value : null,
    name: '',
    type: 'working',
    start_time: '08:00',
    end_time: '16:00',
    end_day_offset: false,
    is_active: true,
});

function submit() {
    form.post('/admin/shift');
}
</script>

<template>
    <AdminLayout title="Tambah Shift Kerja">
        <div class="space-y-6">
            <PageHeader
                title="Shift Kerja"
                :breadcrumb="[
                    { label: 'Shift Kerja', href: '/admin/shift' },
                    { label: 'Tambah Shift' },
                ]"
            />

            <FlashMessage />

              <Card>
                <form @submit.prevent="submit">
<ShiftForm :form="form" :units="units" />

                <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <Link
                        href="/admin/shift"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    >
                        Batal
                    </Link>
                    <Button :disabled="form.processing" type="submit">
                        Simpan
                    </Button>
                </div>
                </form>
            </Card>
        </div>
    </AdminLayout>
</template>

