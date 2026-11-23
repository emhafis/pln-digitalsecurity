<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import SchedulePatternForm from './Partials/SchedulePatternForm.vue';

const props = defineProps({
    pattern: { type: Object, required: true },
    units: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
});

const detailsByDay = new Map(
    (props.pattern.details ?? []).map((detail) => [Number(detail.day_order), detail])
);

const form = useForm({
    unit_id: props.pattern.unit_id,
    name: props.pattern.name,
    description: props.pattern.description ?? '',
    cycle_length: props.pattern.cycle_length,
    is_active: Boolean(props.pattern.is_active),
    details: Array.from({ length: props.pattern.cycle_length }, (_, index) => {
        const detail = detailsByDay.get(index + 1);

        return {
            day_order: index + 1,
            shift_id: detail ? String(detail.shift_id) : '',
        };
    }),
});

function submit() {
    form.put(`/admin/schedule-pattern/${props.pattern.id}`);
}
</script>

<template>
    <AdminLayout title="Edit Pola Rotasi">
        <div class="space-y-6">
            <PageHeader
                title="Pola Rotasi"
                :breadcrumb="[
                    { label: 'Pola Rotasi', href: '/admin/schedule-pattern' },
                    { label: 'Detail', href: `/admin/schedule-pattern/${props.pattern.id}` },
                    { label: 'Edit Pola' },
                ]"
            />

            <FlashMessage />

              <Card>
                <form @submit.prevent="submit">
<SchedulePatternForm
                    :form="form"
                    :units="units"
                    :shifts="shifts"
                    :is-used="Boolean(pattern.is_used)"
                    :period-members-count="pattern.period_members_count ?? 0"
                    :schedules-count="pattern.schedules_count ?? 0"
                />

                <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <Link
                        :href="`/admin/schedule-pattern/${props.pattern.id}`"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    >
                        Batal
                    </Link>
                    <Button :disabled="form.processing" type="submit">
                        Simpan Perubahan
                    </Button>
                </div>
                </form>
            </Card>
        </div>
    </AdminLayout>
</template>

