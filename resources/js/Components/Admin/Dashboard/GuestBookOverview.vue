
<script setup>
import { computed } from 'vue';
import Badge from '@/Components/Admin/Badge.vue';
import DashboardSectionHeader from './DashboardSectionHeader.vue';
import DashboardEmptyState from './DashboardEmptyState.vue';

const props = defineProps({
    guestBook: { type: Object, required: true },
});

const today = computed(() => props.guestBook?.today || {});
</script>

<template>
    <section class="min-w-0 rounded-lg border border-[#DCE2DE] bg-white p-4 sm:p-5">
        <DashboardSectionHeader
            title="Buku tamu digital"
            description="Aktivitas kunjungan tamu hari ini."
            :link-href="guestBook.links?.index"
            link-label="Buka buku tamu"
        />

        <div class="mt-6 grid grid-cols-3 gap-3">
            <div>
                <p class="text-[11px] leading-relaxed text-[#89918F]">
                    Total kunjungan
                </p>
                <p class="mt-2 text-[27px] font-semibold tracking-tight text-[#34434A] tabular-nums">
                    {{ today.total ?? 0 }}
                </p>
            </div>

            <div class="border-l border-[#E3E7E5] pl-3">
                <p class="text-[11px] leading-relaxed text-[#89918F]">
                    Di lokasi
                </p>
                <p class="mt-2 text-[27px] font-semibold tracking-tight text-[#386B70] tabular-nums">
                    {{ today.active ?? 0 }}
                </p>
            </div>

            <div class="border-l border-[#E3E7E5] pl-3">
                <p class="text-[11px] leading-relaxed text-[#89918F]">
                    Checkout
                </p>
                <p class="mt-2 text-[27px] font-semibold tracking-tight text-[#16866B] tabular-nums">
                    {{ today.checked_out ?? 0 }}
                </p>
            </div>
        </div>

        <div class="mt-6">
            <h3 class="text-xs font-semibold text-[#45534F]">
                Kunjungan terbaru
            </h3>

            <div v-if="!(guestBook.recent || []).length" class="mt-3">
                <DashboardEmptyState
                    title="Belum ada kunjungan"
                    description="Data kunjungan tamu akan ditampilkan setelah tercatat."
                />
            </div>

            <div v-else class="mt-3 overflow-x-auto">
                <table class="min-w-[520px] w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-[#E3E7E5] text-[10px] uppercase tracking-wider text-[#89918F]">
                            <th class="py-3 pr-3 font-semibold">Tamu</th>
                            <th class="py-3 pr-3 font-semibold">Unit tujuan</th>
                            <th class="py-3 pr-3 font-semibold">Check-in</th>
                            <th class="py-3 pr-3 font-semibold">Checkout</th>
                            <th class="py-3 text-right font-semibold">Status</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-[#EEF0EE]">
                        <tr
                            v-for="row in guestBook.recent"
                            :key="row.id"
                            class="transition hover:bg-[#F8F9F7]"
                        >
                            <td class="py-3 pr-3">
                                <p class="font-medium text-[#34434A]">
                                    {{ row.full_name || '-' }}
                                </p>
                                <p
                                    v-if="row.company_name"
                                    class="mt-1 text-[11px] text-[#89918F]"
                                >
                                    {{ row.company_name }}
                                </p>
                            </td>

                            <td class="py-3 pr-3 text-[#596762]">
                                {{ row.unit_name || '-' }}
                            </td>

                            <td class="py-3 pr-3 tabular-nums text-[#596762]">
                                {{ row.check_in_at || '-' }}
                            </td>

                            <td class="py-3 pr-3 tabular-nums text-[#596762]">
                                {{ row.check_out_at || '-' }}
                            </td>

                            <td class="py-3 text-right">
                                <Badge :variant="row.status === 'active' ? 'blue' : 'gray'">
                                    {{ row.status_label }}
                                </Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</template>
