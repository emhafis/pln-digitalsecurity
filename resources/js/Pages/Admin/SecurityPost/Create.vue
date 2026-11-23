<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import SecurityPostForm from './Partials/SecurityPostForm.vue';

defineProps({
    units: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    address: '',
    latitude: '',
    longitude: '',
    unit_id: null,
});

function submit() {
    form.post('/admin/security-post');
}
</script>

<template>
    <AdminLayout title="Tambah Post Keamanan">
        <div class="space-y-6">
            <PageHeader
                title="Post Keamanan"
                :breadcrumb="[
                    { label: 'Post Keamanan', href: '/admin/security-post' },
                    { label: 'Tambah Post Keamanan' },
                ]"
            />

            <FlashMessage />

              <Card>
                <form @submit.prevent="submit">
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

