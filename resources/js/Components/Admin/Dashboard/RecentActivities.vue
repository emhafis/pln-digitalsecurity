
<script setup>
import {
    ClipboardDocumentCheckIcon,
    ShieldCheckIcon,
    FireIcon,
    WrenchScrewdriverIcon,
    UserGroupIcon,
    ArrowLeftOnRectangleIcon,
    ArrowRightOnRectangleIcon,
} from '@heroicons/vue/24/outline';

import DashboardSectionHeader from './DashboardSectionHeader.vue';
import DashboardEmptyState from './DashboardEmptyState.vue';

defineProps({
    activities: { type: Array, default: () => [] },
    periodLabel: { type: String, default: 'Periode terpilih' },
});

const iconByType = {
    patrol: ShieldCheckIcon,
    attendance: ClipboardDocumentCheckIcon,
    inspection_apar: FireIcon,
    inspection_hydrant: WrenchScrewdriverIcon,
    guest_checkin: ArrowLeftOnRectangleIcon,
    guest_checkout: ArrowRightOnRectangleIcon,
};

function iconFor(type) {
    return iconByType[type] || UserGroupIcon;
}
</script>

<template>
    <section class="rounded-lg border border-[#DCE2DE] bg-white p-4 sm:p-5">
        <DashboardSectionHeader
            title="Aktivitas terbaru"
            :description="`Catatan operasional pada ${periodLabel.toLowerCase()}`"
        />

        <div v-if="!activities.length" class="mt-5">
            <DashboardEmptyState
                title="Belum ada aktivitas"
                description="Aktivitas operasional akan ditampilkan di sini."
            />
        </div>

        <div v-else class="mt-5">
            <article
                v-for="(item, idx) in activities"
                :key="item.id ?? `${item.type}-${item.at_label}-${idx}`"
                class="group relative flex gap-4 border-b border-[#EEF0EE] py-4 first:pt-0 last:border-0 last:pb-0"
            >
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[#DCE2DE] bg-[#F8F9F7] text-[#64736D]">
                    <component
                        :is="iconFor(item.type)"
                        class="h-4 w-4"
                    />
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs font-semibold text-[#34434A]">
                            {{ item.type_label }}
                        </p>

                        <time class="text-[11px] tabular-nums text-[#A0A8A5]">
                            {{ item.at_label }}
                        </time>
                    </div>

                    <p class="mt-1 text-xs text-[#78827F]">
                        {{ item.description }}
                    </p>

                    <p class="mt-1.5 text-[11px] text-[#A0A8A5]">
                        Pelaksana:
                        <span class="font-medium text-[#64736D]">
                            {{ item.actor || '-' }}
                        </span>
                    </p>
                </div>
            </article>
        </div>
    </section>
</template>
