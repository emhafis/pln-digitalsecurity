
<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';
import DashboardSectionHeader from './DashboardSectionHeader.vue';

defineProps({
    assets: { type: Object, required: true },
});
</script>

<template>
    <section class="min-w-0 rounded-lg border border-[#DCE2DE] bg-white p-4 sm:p-5">
        <DashboardSectionHeader
            title="Aset keselamatan"
            description="Kondisi inventaris APAR dan Hydrant berdasarkan data inspeksi."
        />

        <div class="mt-5 grid gap-6 sm:grid-cols-2">
            <div class="min-w-0">
                <div class="flex items-center justify-between gap-2 border-b border-[#DCE2DE] pb-3">
                    <div>
                        <h3 class="text-sm font-semibold text-[#34434A]">APAR</h3>
                        <p class="mt-1 text-[11px] text-[#89918F]">
                            Alat Pemadam Api Ringan
                        </p>
                    </div>

                    <Link
                        :href="assets.links?.apar || '/admin/apar'"
                        class="inline-flex items-center gap-1 text-[11px] font-medium text-[#386B70] hover:text-[#16866B]"
                    >
                        Monitoring
                        <ArrowTopRightOnSquareIcon class="h-3.5 w-3.5" aria-hidden="true" />
                    </Link>
                </div>

                <div class="mt-4">
                    <p class="text-[11px] text-[#89918F]">Total perangkat</p>
                    <p class="mt-1 text-[32px] font-semibold leading-none tracking-tight text-[#34434A] tabular-nums">
                        {{ assets.apar?.total ?? 0 }}
                    </p>
                </div>

                <div class="mt-5 space-y-3">
                    <div class="flex justify-between text-xs">
                        <span class="text-[#78827F]">Aktif</span>
                        <span class="font-semibold text-[#16866B] tabular-nums">
                            {{ assets.apar?.active ?? 0 }}
                        </span>
                    </div>

                    <div class="flex justify-between text-xs">
                        <span class="text-[#78827F]">Nonaktif</span>
                        <span class="font-medium text-[#45534F] tabular-nums">
                            {{ assets.apar?.inactive ?? 0 }}
                        </span>
                    </div>

                    <div class="flex justify-between gap-2 text-xs">
                        <span class="text-[#78827F]">Inspeksi terakhir tidak OK</span>
                        <span class="font-semibold text-[#B94D4D] tabular-nums">
                            {{ assets.apar?.last_not_ok ?? 0 }}
                        </span>
                    </div>
                </div>

                <p class="mt-4 text-[10px] leading-relaxed text-[#A0A8A5]">
                    Berdasarkan hasil inspeksi terakhir yang tercatat.
                </p>
            </div>

            <div class="min-w-0 border-t border-[#DCE2DE] pt-5 sm:border-l sm:border-t-0 sm:pl-6 sm:pt-0">
                <div class="flex items-center justify-between gap-2 border-b border-[#DCE2DE] pb-3">
                    <div>
                        <h3 class="text-sm font-semibold text-[#34434A]">Hydrant</h3>
                        <p class="mt-1 text-[11px] text-[#89918F]">
                            Perangkat hydrant dan pompa
                        </p>
                    </div>

                    <Link
                        :href="assets.links?.hydrant || '/admin/hydrant'"
                        class="inline-flex items-center gap-1 text-[11px] font-medium text-[#386B70] hover:text-[#16866B]"
                    >
                        Monitoring
                        <ArrowTopRightOnSquareIcon class="h-3.5 w-3.5" aria-hidden="true" />
                    </Link>
                </div>

                <div class="mt-4">
                    <p class="text-[11px] text-[#89918F]">Total perangkat</p>
                    <p class="mt-1 text-[32px] font-semibold leading-none tracking-tight text-[#34434A] tabular-nums">
                        {{ assets.hydrant?.total ?? 0 }}
                    </p>
                </div>

                <div class="mt-5 space-y-3">
                    <div class="flex justify-between text-xs">
                        <span class="text-[#78827F]">Aktif</span>
                        <span class="font-semibold text-[#16866B] tabular-nums">
                            {{ assets.hydrant?.active ?? 0 }}
                        </span>
                    </div>

                    <div class="flex justify-between text-xs">
                        <span class="text-[#78827F]">Nonaktif</span>
                        <span class="font-medium text-[#45534F] tabular-nums">
                            {{ assets.hydrant?.inactive ?? 0 }}
                        </span>
                    </div>

                    <div class="flex justify-between gap-2 text-xs">
                        <span class="text-[#78827F]">Inspeksi terakhir tidak OK</span>
                        <span class="font-semibold text-[#B94D4D] tabular-nums">
                            {{ assets.hydrant?.last_not_ok ?? 0 }}
                        </span>
                    </div>
                </div>

                <p class="mt-4 text-[10px] leading-relaxed text-[#A0A8A5]">
                    Berdasarkan hasil inspeksi terakhir yang tercatat.
                </p>
            </div>
        </div>
    </section>
</template>
