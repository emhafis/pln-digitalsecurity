
<script setup>
import { computed } from 'vue';
import {
    UsersIcon,
    UserGroupIcon,
    ShieldCheckIcon,
    Squares2X2Icon,
    ClipboardDocumentCheckIcon,
    CheckCircleIcon,
    CalendarDaysIcon,
    BuildingOffice2Icon,
} from '@heroicons/vue/24/outline';

import AdminLayout from '@/Layouts/AdminLayout.vue';
import DashboardHeader from '@/Components/Admin/Dashboard/DashboardHeader.vue';
import DashboardStatCard from '@/Components/Admin/Dashboard/DashboardStatCard.vue';
import DashboardMap from '@/Components/Admin/Dashboard/DashboardMap.vue';
import PatrolOverview from '@/Components/Admin/Dashboard/PatrolOverview.vue';
import AttendanceOverview from '@/Components/Admin/Dashboard/AttendanceOverview.vue';
import ScheduleOverview from '@/Components/Admin/Dashboard/ScheduleOverview.vue';
import AssetOverview from '@/Components/Admin/Dashboard/AssetOverview.vue';
import GuestBookOverview from '@/Components/Admin/Dashboard/GuestBookOverview.vue';
import RecentActivities from '@/Components/Admin/Dashboard/RecentActivities.vue';

const props = defineProps({
    filters: { type: Object, required: true },
    context: { type: Object, required: true },
    securityPosts: { type: Array, default: () => [] },
    personnel: { type: Object, required: true },
    patrol: { type: Object, required: true },
    attendance: { type: Object, required: true },
    schedule: { type: Object, required: true },
    assets: { type: Object, required: true },
    guestBook: { type: Object, required: true },
    activities: { type: Array, default: () => [] },
});

const kpiCards = computed(() => [
    {
        label: 'Satpam terdaftar',
        value: props.personnel.guards_total ?? 0,
        hint: `${props.personnel.guards_active ?? 0} akun aktif`,
        icon: UsersIcon,
        tone: 'navy',
    },
    {
        label: 'Satpam aktif',
        value: props.personnel.guards_active ?? 0,
        hint: 'Personel aktif',
        icon: UserGroupIcon,
        tone: 'green',
    },
    {
        label: 'Pos jaga',
        value: props.personnel.posts_total ?? 0,
        hint: 'Lokasi terdaftar',
        icon: ShieldCheckIcon,
        tone: 'neutral',
    },
    {
        label: 'Regu keamanan',
        value: props.personnel.teams_total ?? 0,
        hint: 'Regu terdaftar',
        icon: Squares2X2Icon,
        tone: 'neutral',
    },
    {
        label: 'Sesi patroli',
        value: props.patrol.today?.total ?? 0,
        hint: `${props.patrol.today?.active ?? 0} aktif · ${props.patrol.today?.pending ?? 0} menunggu`,
        icon: ClipboardDocumentCheckIcon,
        tone: 'navy',
    },
    {
        label: 'Patroli selesai',
        value: props.patrol.today?.completed ?? 0,
        hint: 'Penyelesaian hari ini',
        icon: CheckCircleIcon,
        tone: 'green',
    },
    {
        label: 'Jadwal hari ini',
        value: props.schedule.today?.total ?? 0,
        hint: 'Personel terjadwal',
        icon: CalendarDaysIcon,
        tone: 'neutral',
    },
    {
        label: 'Unit kerja',
        value: props.personnel.units_total ?? 0,
        hint: 'Dalam cakupan',
        icon: BuildingOffice2Icon,
        tone: 'neutral',
    },
]);
</script>

<template>
    <AdminLayout title="Dashboard">
        <div class="dashboard-page space-y-7 sm:space-y-9">

            <!-- Page heading -->
            <DashboardHeader
                :filters="filters"
                :context="context"
            />

            <!-- Personnel overview -->
            <section>
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <p class="section-eyebrow">01 / PERSONNEL</p>
                        <h2 class="section-title">Ringkasan personel</h2>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-x-4 gap-y-5 sm:grid-cols-4 lg:grid-cols-4">
                    <DashboardStatCard
                        v-for="card in kpiCards.slice(0, 4)"
                        :key="card.label"
                        v-bind="card"
                    />
                </div>
            </section>

            <!-- Security post map -->
            <section>
                <DashboardMap
                    :posts="securityPosts"
                    index-url="/admin/security-post"
                />
            </section>

            <!-- Daily operation -->
            <section>
                <div class="mb-4">
                    <p class="section-eyebrow">02 / DAILY OPERATIONS</p>
                    <h2 class="section-title">Operasional hari ini</h2>
                    <p class="section-description">
                        Kondisi patroli dan kehadiran personel di lapangan.
                    </p>
                </div>

                <div class="grid gap-5 xl:grid-cols-2">
                    <PatrolOverview :patrol="patrol" />
                    <AttendanceOverview :attendance="attendance" />
                </div>
            </section>

            <!-- Additional KPIs -->
            <section>
                <div class="mb-4">
                    <p class="section-eyebrow">03 / OPERATIONAL SUMMARY</p>
                    <h2 class="section-title">Indikator operasional</h2>
                </div>

                <div class="grid grid-cols-2 gap-x-4 gap-y-5 sm:grid-cols-4">
                    <DashboardStatCard
                        v-for="card in kpiCards.slice(4)"
                        :key="card.label"
                        v-bind="card"
                    />
                </div>
            </section>

            <!-- Schedule -->
            <section>
                <ScheduleOverview :schedule="schedule" />
            </section>

            <!-- Assets and visitors -->
            <section>
                <div class="mb-4">
                    <p class="section-eyebrow">04 / FACILITY & VISITORS</p>
                    <h2 class="section-title">Fasilitas dan kunjungan</h2>
                </div>

                <div class="grid gap-5 xl:grid-cols-2">
                    <AssetOverview :assets="assets" />
                    <GuestBookOverview :guest-book="guestBook" />
                </div>
            </section>

            <!-- Recent activities -->
            <section>
                <RecentActivities
                    :activities="activities"
                    :period-label="filters.period_label || 'periode terpilih'"
                />
            </section>

            <footer class="border-t border-[#E3E7E5] pt-4">
                <p class="text-[11px] tracking-wide text-[#89918F]">
                    SECURITY OPERATIONS MANAGEMENT
                </p>
            </footer>
        </div>
    </AdminLayout>
</template>

<style>
.dashboard-page {
    color: #202B35;
}

.section-eyebrow {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .13em;
    color: #89918F;
}

.section-title {
    margin-top: 5px;
    font-size: 18px;
    line-height: 1.4;
    font-weight: 650;
    letter-spacing: -.035em;
    color: #202B35;
}

.section-description {
    margin-top: 3px;
    font-size: 13px;
    color: #78827F;
}
</style>
