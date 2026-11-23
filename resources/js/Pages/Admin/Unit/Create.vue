<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import UnitForm from './Partials/UnitForm.vue';

const form = useForm({
    unit_name: 'UID ',
    unit_level: 'uid',
});

function submit() {
    form.post('/admin/unit');
}
</script>

<template>
    <AdminLayout title="Tambah Unit">
        <div class="space-y-6">
            <PageHeader
                title="Unit"
                :breadcrumb="[
                    { label: 'Unit', href: '/admin/unit' },
                    { label: 'Tambah Unit' },
                ]"
            />

            <FlashMessage />

              <Card>
                <form @submit.prevent="submit">
<UnitForm :form="form" />

                <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <Link
                        href="/admin/unit"
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

