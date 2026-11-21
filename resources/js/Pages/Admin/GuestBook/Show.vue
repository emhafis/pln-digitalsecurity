
<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Badge from '@/Components/Admin/Badge.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import ImageWithFallback from '@/Components/Admin/ImageWithFallback.vue';

const props = defineProps({
    visit: { type: Object, required: true },
});

const isActive = computed(() => props.visit.status === 'active');

function checkoutManual() {
    if (!confirm('Konfirmasi check-out manual untuk kunjungan ini?')) return;

    router.post(
        `/admin/guest-book/${props.visit.id}/checkout`,
        {},
        { preserveScroll: true }
    );
}

const guestDetails = computed(() => [
    { label: 'Nomor HP', value: props.visit.phone_number },
    { label: 'Instansi', value: props.visit.company_name },
    { label: 'Jabatan', value: props.visit.position },
    { label: 'Bertemu dengan', value: props.visit.person_to_meet },
    { label: 'Nomor kendaraan', value: props.visit.vehicle_plate_number || '-' },
]);
</script>

<template>
    <AdminLayout title="Detail Kunjungan">
        <div class="space-y-6">
            <PageHeader
                title="Detail Kunjungan"
                description="Informasi kunjungan tamu."
                :breadcrumb="[
                    { label: 'Buku Tamu', href: '/admin/guest-book' },
                    { label: 'Detail Kunjungan' },
                ]"
            >
                <template #actions>
                    <Link
                        href="/admin/guest-book"
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50"
                    >
                        Kembali
                    </Link>

                    <button
                        v-if="isActive"
                        type="button"
                        class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-xs font-medium text-white transition hover:bg-blue-700"
                        @click="checkoutManual"
                    >
                        Check-out Manual
                    </button>
                </template>
            </PageHeader>

            <FlashMessage />

            <!-- Identitas dan informasi tamu -->
            <Card>
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-400">
                            Identitas Pengunjung
                        </p>

                        <h2 class="mt-2 text-xl font-semibold tracking-tight text-gray-900">
                            {{ visit.full_name }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            {{ visit.company_name || 'Instansi tidak dicantumkan' }}
                        </p>
                    </div>

                    <div class="shrink-0">
                        <Badge :variant="isActive ? 'amber' : 'emerald'">
                            {{ visit.status_label }}
                        </Badge>
                    </div>
                </div>

                <div class="mt-6 border-t border-gray-100 pt-5">
                    <div class="mb-4">
                        <h3 class="text-sm font-semibold text-gray-900">
                            Data Tamu
                        </h3>
                    </div>

                    <dl class="grid grid-cols-1 gap-x-10 sm:grid-cols-2">
                        <div
                            v-for="item in guestDetails"
                            :key="item.label"
                            class="border-t border-gray-100 py-3"
                        >
                            <dt class="text-xs text-gray-500">
                                {{ item.label }}
                            </dt>
                            <dd class="mt-1.5 break-words text-sm font-medium text-gray-800">
                                {{ item.value || '-' }}
                            </dd>
                        </div>

                        <div class="border-t border-gray-100 py-3 sm:col-span-2">
                            <dt class="text-xs text-gray-500">
                                Tujuan Kunjungan
                            </dt>
                            <dd class="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-gray-800">
                                {{ visit.visit_purpose || '-' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </Card>

            <!-- Informasi waktu dan pencatatan -->
            <Card>
                <div class="mb-5">
                    <h3 class="text-sm font-semibold text-gray-900">
                        Riwayat Kunjungan
                    </h3>
                    <p class="mt-1 text-xs text-gray-500">
                        Waktu kedatangan, kepulangan, dan informasi pencatatan.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div class="border-l-2 border-blue-500 pl-4">
                        <p class="text-xs font-medium text-gray-500">
                            Check-in
                        </p>
                        <p class="mt-2 text-base font-semibold text-gray-900">
                            {{ visit.check_in_at || '-' }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
                            Waktu kedatangan tamu
                        </p>
                    </div>

                    <div
                        class="border-l-2 pl-4"
                        :class="visit.check_out_at ? 'border-gray-400' : 'border-gray-200'"
                    >
                        <p class="text-xs font-medium text-gray-500">
                            Check-out
                        </p>
                        <p class="mt-2 text-base font-semibold text-gray-900">
                            {{ visit.check_out_at || 'Belum check-out' }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
                            Waktu meninggalkan lokasi
                        </p>
                    </div>
                </div>

                <div class="mt-6 border-t border-gray-100 pt-5">
                    <dl class="grid grid-cols-1 gap-x-10 sm:grid-cols-2">
                        <div class="py-2">
                            <dt class="text-xs text-gray-500">
                                Unit
                            </dt>
                            <dd class="mt-1.5 text-sm font-medium text-gray-800">
                                {{ visit.unit_name || '-' }}
                                <span v-if="visit.unit_code" class="text-xs font-normal text-gray-500">
                                    ({{ visit.unit_code }})
                                </span>
                            </dd>
                        </div>

                        <div v-if="visit.checkout_source" class="py-2">
                            <dt class="text-xs text-gray-500">
                                Sumber Check-out
                            </dt>
                            <dd class="mt-1.5 text-sm text-gray-800">
                                {{ visit.checkout_source_label || visit.checkout_source }}
                            </dd>
                        </div>

                        <div v-if="visit.checked_out_by_name" class="py-2">
                            <dt class="text-xs text-gray-500">
                                Dicatat oleh
                            </dt>
                            <dd class="mt-1.5 text-sm text-gray-800">
                                {{ visit.checked_out_by_name }}
                            </dd>
                        </div>

                        <div class="py-2">
                            <dt class="text-xs text-gray-500">
                                Persetujuan Data
                            </dt>
                            <dd class="mt-1.5 text-sm text-gray-800">
                                {{ visit.consent_given ? 'Disetujui' : 'Tidak disetujui' }}
                                <span v-if="visit.consent_at" class="text-xs text-gray-500">
                                    · {{ visit.consent_at }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </Card>

            <!-- Dokumentasi -->
            <section>
                <div class="mb-4">
                    <h3 class="text-sm font-semibold text-gray-900">
                        Dokumentasi Kunjungan
                    </h3>
                    <p class="mt-1 text-xs text-gray-500">
                        Foto identitas dan tanda tangan yang diberikan saat pendaftaran.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <Card>
                        <div class="mb-3 flex items-center justify-between">
                            <h4 class="text-sm font-medium text-gray-900">
                                Foto Pengunjung
                            </h4>
                            <span class="text-xs text-gray-400">
                                Selfie
                            </span>
                        </div>

                        <div
                            v-if="visit.has_selfie"
                            class="flex min-h-64 items-center justify-center overflow-hidden rounded-md bg-gray-50"
                        >
                            <ImageWithFallback
                                :src="visit.selfie_url"
                                alt="Selfie tamu"
                                class="max-h-96 w-full object-contain"
                            />
                        </div>

                        <div
                            v-else
                            class="flex min-h-48 items-center justify-center rounded-md border border-dashed border-gray-200 bg-gray-50"
                        >
                            <p class="text-sm text-gray-400">
                                Foto tidak tersedia
                            </p>
                        </div>
                    </Card>

                    <Card>
                        <div class="mb-3 flex items-center justify-between">
                            <h4 class="text-sm font-medium text-gray-900">
                                Tanda Tangan
                            </h4>
                            <span class="text-xs text-gray-400">
                                Persetujuan
                            </span>
                        </div>

                        <div
                            v-if="visit.has_signature"
                            class="flex min-h-64 items-center justify-center overflow-hidden rounded-md border border-gray-100 bg-white p-4"
                        >
                            <ImageWithFallback
                                :src="visit.signature_url"
                                alt="Tanda tangan tamu"
                                class="max-h-64 w-full object-contain"
                            />
                        </div>

                        <div
                            v-else
                            class="flex min-h-48 items-center justify-center rounded-md border border-dashed border-gray-200 bg-gray-50"
                        >
                            <p class="text-sm text-gray-400">
                                Tanda tangan tidak tersedia
                            </p>
                        </div>
                    </Card>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
