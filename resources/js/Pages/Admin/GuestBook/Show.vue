<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Badge from '@/Components/Admin/Badge.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';

const props = defineProps({
    visit: { type: Object, required: true },
});

const isActive = computed(() => props.visit.status === 'active');

function checkoutManual() {
    if (!confirm('Konfirmasi check-out manual untuk kunjungan ini?')) return;
    router.post(`/admin/guest-book/${props.visit.id}/checkout`, {}, { preserveScroll: true });
}
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
                    <Link href="/admin/guest-book" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50">Kembali</Link>
                    <button v-if="isActive" type="button" class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-xs font-medium text-white transition hover:bg-blue-700" @click="checkoutManual">Check-out Manual</button>
                </template>
            </PageHeader>

            <FlashMessage />

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <Card class="lg:col-span-2">
                    <template #title>Informasi Tamu</template>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 text-sm">
                        <div><dt class="text-xs font-medium text-gray-500">Nama Lengkap</dt><dd class="mt-1 font-medium text-gray-800">{{ visit.full_name }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Nomor HP</dt><dd class="mt-1 font-medium text-gray-800">{{ visit.phone_number }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Instansi</dt><dd class="mt-1 font-medium text-gray-800">{{ visit.company_name }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Jabatan</dt><dd class="mt-1 font-medium text-gray-800">{{ visit.position }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Bertemu Dengan</dt><dd class="mt-1 font-medium text-gray-800">{{ visit.person_to_meet }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Nomor Kendaraan</dt><dd class="mt-1 font-medium text-gray-800">{{ visit.vehicle_plate_number || '-' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs font-medium text-gray-500">Tujuan Kunjungan</dt><dd class="mt-1 font-medium text-gray-800">{{ visit.visit_purpose }}</dd></div>
                    </dl>
                </Card>

                <Card>
                    <template #title>Status Kunjungan</template>
                    <div class="space-y-4 text-sm">
                        <div>
                            <div class="text-xs font-medium text-gray-500">Status</div>
                            <div class="mt-1"><Badge :variant="isActive ? 'amber' : 'emerald'">{{ visit.status_label }}</Badge></div>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-gray-500">Unit (Snapshot)</div>
                            <div class="mt-1 font-medium text-gray-800">{{ visit.unit_name }} <span v-if="visit.unit_code" class="text-xs text-gray-500">({{ visit.unit_code }})</span></div>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-gray-500">Check-in</div>
                            <div class="mt-1 font-medium text-gray-800">{{ visit.check_in_at || '-' }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-gray-500">Check-out</div>
                            <div class="mt-1 font-medium text-gray-800">{{ visit.check_out_at || '-' }}</div>
                        </div>
                        <div v-if="visit.checkout_source">
                            <div class="text-xs font-medium text-gray-500">Sumber Check-out</div>
                            <div class="mt-1 font-medium text-gray-800">{{ visit.checkout_source_label || visit.checkout_source }}</div>
                        </div>
                        <div v-if="visit.checked_out_by_name">
                            <div class="text-xs font-medium text-gray-500">Dicatat oleh</div>
                            <div class="mt-1 font-medium text-gray-800">{{ visit.checked_out_by_name }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-gray-500">Persetujuan Data</div>
                            <div class="mt-1 font-medium text-gray-800">{{ visit.consent_given ? 'Ya' : 'Tidak' }} <span v-if="visit.consent_at" class="text-xs text-gray-500">({{ visit.consent_at }})</span></div>
                        </div>
                    </div>
                </Card>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <template #title>Selfie</template>
                    <div v-if="visit.has_selfie" class="overflow-hidden rounded-md border border-gray-200 bg-gray-50">
                        <img :src="visit.selfie_url" alt="Selfie tamu" class="max-h-80 w-full object-contain" />
                    </div>
                    <p v-else class="text-sm text-gray-500">Tidak ada selfie.</p>
                </Card>
                <Card>
                    <template #title>Tanda Tangan</template>
                    <div v-if="visit.has_signature" class="overflow-hidden rounded-md border border-gray-200 bg-white">
                        <img :src="visit.signature_url" alt="Tanda tangan tamu" class="max-h-64 w-full object-contain" />
                    </div>
                    <p v-else class="text-sm text-gray-500">Tidak ada tanda tangan.</p>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
