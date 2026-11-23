<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import SchedulePeriodForm from './Partials/SchedulePeriodForm.vue';

const props = defineProps({
    period: { type: Object, required: true },
    units: { type: Array, default: () => [] },
    posts: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    patterns: { type: Array, default: () => [] },
    securities: { type: Array, default: () => [] },
    previousPeriods: { type: Array, default: () => [] },
    today: { type: String, default: '' },
});

const initialDate = props.period.start_date || props.today || '';

const form = useForm({
    unit_id: props.period.unit_id,
    security_post_id: props.period.security_post_id,
    security_team_id: props.period.security_team_id,
    month: Number(initialDate.slice(5, 7)) || 1,
    year: Number(initialDate.slice(0, 4)) || 2000,
    name: props.period.name,
    start_date: props.period.start_date,
    end_date: props.period.end_date,
    status: props.period.status,
    members: (props.period.members ?? []).map((member) => ({
        security_id: String(member.security_id),
        schedule_pattern_id: member.schedule_pattern_id ? String(member.schedule_pattern_id) : '',
        start_offset: Number(member.start_offset) || 0,
        rotation_day: (Number(member.start_offset) || 0) + 1,
    })),
});

function submit() {
    form.transform((data) => ({
        ...data,
        members: (data.members ?? []).map((member) => ({
            security_id: member.security_id ? Number(member.security_id) : null,
            schedule_pattern_id: member.schedule_pattern_id ? Number(member.schedule_pattern_id) : null,
            start_offset: Number(member.start_offset) || 0,
        })),
    })).put(`/admin/schedule-period/${props.period.id}`);
}
</script>

<template>
    <AdminLayout title="Edit Periode Jadwal">
        <div class="space-y-6">
            <PageHeader
                title="Periode Jadwal"
                :breadcrumb="[
                    { label: 'Periode Jadwal', href: '/admin/schedule-period' },
                    { label: 'Detail', href: `/admin/schedule-period/${props.period.id}` },
                    { label: 'Edit Periode' },
                ]"
            />

            <FlashMessage />

              <Card>
                <form @submit.prevent="submit">
<SchedulePeriodForm
                    :form="form"
                    :units="units"
                    :posts="posts"
                    :teams="teams"
                    :patterns="patterns"
                    :securities="securities"
                    :previous-periods="previousPeriods"
                    :today="today"
                    :is-edit="true"
                    :has-schedules="Boolean(period.has_schedules)"
                />

                <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <Link
                        :href="`/admin/schedule-period/${props.period.id}`"
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

