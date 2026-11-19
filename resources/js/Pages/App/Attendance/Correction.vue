<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppHeader from '@/Components/App/AppHeader.vue';

import CorrectionForm from '@/Components/App/Attendance/CorrectionForm.vue';

const props = defineProps({
    has_security_profile: { type: Boolean, default: false },
    selected_record_id: { type: [Number, null], default: null },
    record: { type: Object, default: null },
    records: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    correctable_statuses: { type: Array, default: () => [] },
});
</script>

<template>
    <AppLayout>
        <div class="relative w-full bg-white">
            <AppHeader title="Pengajuan Koreksi Kehadiran" backUrl="/app/attendance/history" />

            <main class="space-y-4 px-5 pb-28 pt-5">
                <div v-if="!has_security_profile" class="rounded-xl border border-[#E8E8EC] bg-gray-50 p-4 text-sm text-[#263746]">
                    Profil Satpam belum tersedia. Hubungi admin untuk melengkapi data.
                </div>

                <template v-else>
                    <CorrectionForm
                        :record="record"
                        :records="records"
                        :filters="filters"
                        :selected_record_id="selected_record_id"
                        :correctable_statuses="correctable_statuses"
                    />
                    <div class="text-center">
                        <Link href="/app/attendance/history" class="text-xs font-medium text-[#1D567F] underline">
                            Kembali ke Riwayat Kehadiran
                        </Link>
                    </div>
                </template>
            </main>
        </div>
    </AppLayout>
</template>
