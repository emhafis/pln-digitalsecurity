<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import UserForm from './Partials/UserForm.vue';

const props = defineProps({
    group: { type: String, default: 'security' },
    units: { type: Array, default: () => [] },
});

const groupLabel = computed(() => (props.group === 'security' ? 'Akun Security' : 'Akun Admin'));

const form = useForm({
    username: '',
    name: '',
    email: '',
    unit_id: '',
    password: '',
    role: props.group === 'admin' ? 'admin' : 'security',
    is_active: true,
});

function submit() {
    form.post(`/admin/user?role=${props.group}`);
}
</script>

<template>
    <AdminLayout title="Tambah User">
        <div class="space-y-6">
            <PageHeader
                title="Tambah User"
                :breadcrumb="[
                    { label: 'Management User', href: `/admin/user?role=${props.group}` },
                    { label: groupLabel, href: `/admin/user?role=${props.group}` },
                    { label: 'Tambah User' },
                ]"
            />

            <FlashMessage />

            <Card>
                <UserForm :form="form" :group="props.group" :units="props.units" />

                <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 pt-4">
                    <Link
                        :href="`/admin/user?role=${props.group}`"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    >
                        Batal
                    </Link>
                    <Button
                        :disabled="form.processing"
                        @click="submit"
                    >
                        Simpan
                    </Button>
                </div>
            </Card>
        </div>
    </AdminLayout>
</template>
