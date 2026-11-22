<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import PatrolPointForm from './Partials/PatrolPointForm.vue';

const props = defineProps({
    patrolPoint: { type: Object, required: true },
    securityPosts: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.patrolPoint.name ?? '',
    security_post_id: props.patrolPoint.security_post_id ?? null,
});

function submit() {
    form.put(`/admin/patrol-point/${props.patrolPoint.id}`);
}
</script>

<template>
    <AdminLayout title="Edit Titik Patroli">
        <div class="space-y-6">
            <PageHeader
                title="Titik Patroli"
                :breadcrumb="[
                    { label: 'Titik Patroli', href: '/admin/patrol-point' },
                    { label: 'Detail', href: `/admin/patrol-point/${props.patrolPoint.id}` },
                    { label: 'Edit Titik Patroli' },
                ]"
            />

            <FlashMessage />

              <Card>
                <form @submit.prevent="submit">
<PatrolPointForm :form="form" :security-posts="securityPosts" />

                <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <Link
                        :href="`/admin/patrol-point/post/${props.patrolPoint.security_post_id}`"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    >
                        Batal
                    </Link>
                    <Button
                        :disabled="form.processing"
                        type="submit"
                    >
                        Simpan Perubahan
                    </Button>
                </div>
                </form>
            </Card>
        </div>
    </AdminLayout>
</template>

