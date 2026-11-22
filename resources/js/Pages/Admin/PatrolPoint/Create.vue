<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import PatrolPointForm from './Partials/PatrolPointForm.vue';

const props = defineProps({
    securityPosts: { type: Array, default: () => [] },
    securityPostId: { type: [Number, String], default: null },
});

const form = useForm({
    name: '',
    security_post_id: props.securityPostId ?? null,
});

const backUrl = props.securityPostId
    ? `/admin/patrol-point/post/${props.securityPostId}`
    : '/admin/patrol-point';

function submit() {
    form.post('/admin/patrol-point');
}
</script>

<template>
    <AdminLayout title="Tambah Titik Patroli">
        <div class="space-y-6">
            <PageHeader
                title="Titik Patroli"
                :breadcrumb="[
                    { label: 'Titik Patroli', href: '/admin/patrol-point' },
                    { label: 'Tambah Titik Patroli' },
                ]"
            />

            <FlashMessage />

              <Card>
                <form @submit.prevent="submit">
<PatrolPointForm :form="form" :security-posts="securityPosts" />

                <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <Link
                        :href="backUrl"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    >
                        Batal
                    </Link>
                    <Button
                        :disabled="form.processing"
                        type="submit"
                    >
                        Simpan
                    </Button>
                </div>
                </form>
            </Card>
        </div>
    </AdminLayout>
</template>

