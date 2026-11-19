<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';

const props = defineProps({
    security: { type: Object, required: true },
});

const details = [
    { label: 'No. Registrasi', value: props.security.registration_number ?? '-' },
    { label: 'Username', value: props.security.username ?? '-' },
    { label: 'Nama Lengkap', value: props.security.full_name },
    { label: 'No. HP', value: props.security.phone_number ?? '-' },
    { label: 'Alamat', value: props.security.address ?? '-' },
    { label: 'Tempat Lahir', value: props.security.birth_place ?? '-' },
    { label: 'Tanggal Lahir', value: props.security.birth_date ?? '-' },
    { label: 'No. KTA', value: props.security.kta_number ?? '-' },
    { label: 'Tanggal Terbit KTA', value: props.security.kta_issued_date ?? '-' },
    { label: 'Kedaluwarsa KTA', value: props.security.kta_expired_date ?? '-' },
    { label: 'Unit', value: props.security.unit_name },
    { label: 'Dibuat', value: props.security.created_at },
    { label: 'Diperbarui', value: props.security.updated_at },
];

function destroy() {
    if (confirm(`Hapus personel security "${props.security.full_name}"? Akun user terkait juga akan dihapus.`)) {
        router.delete(`/admin/security/${props.security.id}`);
    }
}
</script>

<template>
    <AdminLayout title="Detail Personel Security">
        <div class="space-y-6">
            <PageHeader
                title="Personel Security"
                :breadcrumb="[
                    { label: 'Personel Security', href: '/admin/security' },
                    { label: 'Detail' },
                ]"
            >
                <template #actions>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-red-600 px-4 py-2 text-xs text-white transition hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2"
                        @click="destroy"
                    >
                        Hapus
                    </button>
                    <Link
                        :href="`/admin/security/${props.security.id}/edit`"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-xs text-white transition hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                    >
                        Edit
                    </Link>
                    <Link
                        href="/admin/security"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    >
                        Kembali
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <Card>
                <table class="mt-4 w-full text-left">
                    <tbody>
                        <tr
                            v-for="item in details"
                            :key="item.label"
                            class="border-b border-gray-100 last:border-0"
                        >
                            <th class="w-48 py-2 pr-4 align-top text-xs font-medium text-gray-900">
                                {{ item.label }}
                            </th>
                            <td class="py-2 text-sm text-gray-700">
                                {{ item.value }}
                            </td>
                        </tr>

                        <tr class="border-b border-gray-100">
                            <th class="w-48 py-2 pr-4 align-top text-xs font-medium text-gray-900">
                                Foto Profil
                            </th>
                            <td class="py-2 text-sm text-gray-700">
                                <a
                                    v-if="props.security.photo_url"
                                    :href="props.security.photo_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-blue-600 hover:underline"
                                >
                                    Lihat Foto
                                </a>
                                <span v-else>-</span>
                            </td>
                        </tr>

                        <tr>
                            <th class="w-48 py-2 pr-4 align-top text-xs font-medium text-gray-900">
                                File KTA
                            </th>
                            <td class="py-2 text-sm text-gray-700">
                                <a
                                    v-if="props.security.kta_file_url"
                                    :href="props.security.kta_file_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-blue-600 hover:underline"
                                >
                                    Lihat File KTA
                                </a>
                                <span v-else>-</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </Card>
        </div>
    </AdminLayout>
</template>
