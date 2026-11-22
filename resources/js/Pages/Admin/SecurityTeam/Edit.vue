<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import SecurityTeamForm from './Partials/SecurityTeamForm.vue';

const props = defineProps({
    team: { type: Object, required: true },
    units: { type: Array, default: () => [] },
    posts: { type: Array, default: () => [] },
    postLocked: { type: Boolean, default: false },
});

const form = useForm({
    unit_id: props.team.unit_id ?? '',
    security_post_id: props.team.security_post_id ?? '',
    name: props.team.name ?? '',
    description: props.team.description ?? '',
    is_active: Boolean(props.team.is_active),
});

function submit() {
    form.put(`/admin/security-team/${props.team.id}`);
}
</script>

<template>
    <AdminLayout title="Edit Regu">
        <div class="space-y-6">
            <PageHeader
                title="Data Regu"
                :breadcrumb="[
                    { label: 'Data Regu', href: '/admin/security-team' },
                    { label: 'Detail', href: `/admin/security-team/${props.team.id}` },
                    { label: 'Edit Regu' },
                ]"
            />

            <FlashMessage />

              <Card>
                <form @submit.prevent="submit">
<SecurityTeamForm
                    :form="form"
                    :units="units"
                    :posts="posts"
                    :unit-name="props.team.unit_name"
                    :post-locked="postLocked"
                    :identity-locked="postLocked"
                />

                <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <Link
                        href="/admin/security-team"
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

