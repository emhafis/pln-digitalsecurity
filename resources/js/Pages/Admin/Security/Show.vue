
<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import ImageWithFallback from '@/Components/Admin/ImageWithFallback.vue';

const props = defineProps({
    security: { type: Object, required: true },
});

const personalDetails = [
    { label: 'No. Registrasi', value: props.security.registration_number },
    { label: 'Username', value: props.security.username },
    { label: 'Nama Lengkap', value: props.security.full_name },
    { label: 'No. HP', value: props.security.phone_number },
    { label: 'Tempat Lahir', value: props.security.birth_place },
    { label: 'Tanggal Lahir', value: props.security.birth_date },
    { label: 'Alamat', value: props.security.address, full: true },
];

const ktaDetails = [
    { label: 'Nomor KTA', value: props.security.kta_number },
    { label: 'Tanggal Terbit', value: props.security.kta_issued_date },
    { label: 'Kedaluwarsa', value: props.security.kta_expired_date },
];

function display(value) {
    return value || '-';
}

function destroy() {
    if (
        confirm(
            `Hapus personel security "${props.security.full_name}"? Akun user terkait juga akan dihapus.`
        )
    ) {
        router.delete(`/admin/security/${props.security.id}`);
    }
}
</script>

<template>
    <AdminLayout title="Detail Personel Security">
        <div class="space-y-6">

            <!-- HEADER: DIPERTAHANKAN -->
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

            <!-- PROFILE -->
            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:p-7">

                    <a
                        v-if="security.photo_url"
                        :href="security.photo_url"
                        target="_blank"
                        rel="noopener"
                        class="w-fit shrink-0"
                    >
                        <ImageWithFallback
                            :src="security.photo_url"
                            alt="Foto personel"
                            class="h-28 w-28 rounded-lg border border-gray-200 object-cover"
                        />
                    </a>

                    <ImageWithFallback
                        v-else
                        src=""
                        alt="Foto personel"
                        class="h-28 w-28 shrink-0 rounded-lg border border-gray-200 object-cover"
                    />

                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-400">
                            Data personel
                        </p>

                        <h2 class="mt-2 text-xl font-semibold tracking-tight text-gray-900 sm:text-2xl">
                            {{ security.full_name }}
                        </h2>

                        <p class="mt-2 text-sm text-gray-500">
                            {{ display(security.registration_number) }}
                        </p>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs text-gray-600">
                                {{ display(security.unit_name) }}
                            </span>

                            <span class="inline-flex items-center rounded border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs text-gray-600">
                                @{{ display(security.username) }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- PERSONAL INFORMATION -->
            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 sm:px-6">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">
                            Informasi Pribadi
                        </h3>
                        <p class="mt-1 text-xs text-gray-500">
                            Identitas dan informasi kontak personel.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2">
                    <div
                        v-for="item in personalDetails"
                        :key="item.label"
                        :class="[
                            'border-b border-gray-100 px-5 py-4 sm:px-6',
                            item.full ? 'sm:col-span-2' : '',
                        ]"
                    >
                        <p class="text-xs text-gray-400">
                            {{ item.label }}
                        </p>

                        <p class="mt-1.5 whitespace-pre-line break-words text-sm leading-relaxed text-gray-800">
                            {{ display(item.value) }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- KTA -->
            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
                    <h3 class="text-sm font-semibold text-gray-900">
                        Informasi Kartu Tanda Anggota
                    </h3>
                    <p class="mt-1 text-xs text-gray-500">
                        Nomor dan masa berlaku kartu identitas security.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3">
                    <div
                        v-for="item in ktaDetails"
                        :key="item.label"
                        class="border-b border-gray-100 px-5 py-4 last:border-0 sm:border-b-0 sm:border-r sm:px-6 sm:last:border-r-0"
                    >
                        <p class="text-xs text-gray-400">
                            {{ item.label }}
                        </p>

                        <p class="mt-1.5 text-sm font-medium text-gray-800">
                            {{ display(item.value) }}
                        </p>
                    </div>
                </div>

                <div class="border-t border-gray-100 bg-gray-50/50 px-5 py-5 sm:px-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-800">
                                Dokumen KTA
                            </p>
                            <p class="mt-1 text-xs text-gray-500">
                                File kartu tanda anggota yang diunggah.
                            </p>
                        </div>

                        <a
                            v-if="security.kta_file_url"
                            :href="security.kta_file_url"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex w-fit items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.7"
                            >
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                <path d="M14 2v6h6M8 13h8M8 17h8" />
                            </svg>
                            Lihat File KTA
                        </a>

                        <span
                            v-else
                            class="text-xs text-gray-400"
                        >
                            Belum ada file KTA.
                        </span>
                    </div>
                </div>
            </section>

            <!-- RECORD INFORMATION -->
            <section class="rounded-lg border border-gray-200 bg-white px-5 py-4 sm:px-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs text-gray-400">
                            Dibuat
                        </p>
                        <p class="mt-1.5 text-xs text-gray-600">
                            {{ display(security.created_at) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-400">
                            Terakhir diperbarui
                        </p>
                        <p class="mt-1.5 text-xs text-gray-600">
                            {{ display(security.updated_at) }}
                        </p>
                    </div>
                </div>
            </section>

        </div>
    </AdminLayout>
</template>
