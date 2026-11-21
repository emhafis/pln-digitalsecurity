<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import AnnouncementForm from './Partials/AnnouncementForm.vue';

const form = useForm({
    title: '',
    content: '',
    image: null,
    remove_image: false,
    is_active: true,
    published_at: '',
});

function submit() {
    form.post('/admin/announcements', { forceFormData: true });
}
</script>

<template>
    <AdminLayout title="Tambah Pengumuman">
        <div class="space-y-6">
            <PageHeader
                title="Tambah Pengumuman"
                :breadcrumb="[{ label: 'Pengumuman', href: '/admin/announcements' }, { label: 'Tambah' }]"
            />
            <FlashMessage />
            <Card>
                <form @submit.prevent="submit" class="space-y-6">
                    <AnnouncementForm :form="form" />
                    <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-4">
                        <Link href="/admin/announcements" class="text-sm text-gray-600 hover:text-gray-900">Batal</Link>
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </Card>
        </div>
    </AdminLayout>
</template>
