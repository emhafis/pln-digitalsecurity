<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import SecurityPostForm from './Partials/SecurityPostForm.vue';

const props = defineProps({
    securityPost: { type: Object, required: true },
    units: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.securityPost.name ?? '',
    address: props.securityPost.address ?? '',
    latitude: props.securityPost.latitude ?? '',
    longitude: props.securityPost.longitude ?? '',
    unit_id: props.securityPost.unit_id ?? null,
});

function submit() {
    form.put(`/admin/security-post/${props.securityPost.id}`);
}
</script>

<template>
    <AdminLayout title="Edit Post Keamanan">
        <div class="space-y-6">
            <PageHeader
                title="Post Keamanan"
                :breadcrumb="[
                    { label: 'Post Keamanan', href: '/admin/security-post' },
                    { label: 'Detail', href: `/admin/security-post/${props.securityPost.id}` },
                    { label: 'Edit Post Keamanan' },
                ]"
            />

            <FlashMessage />

            <Card>
                <SecurityPostForm :form="form" :units="units" />

                <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <Link
                        href="/admin/security-post"
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
