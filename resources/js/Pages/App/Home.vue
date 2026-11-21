<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HomeHeader from '@/Components/App/Home/HomeHeader.vue';
import AttendanceCard from '@/Components/App/Home/AttendanceCard.vue';
import QuickActions from '@/Components/App/Home/QuickActions.vue';
import AnnouncementSection from '@/Components/App/Home/AnnouncementSection.vue';
import BottomNavigation from '@/Components/App/Home/BottomNavigation.vue';

const props = defineProps({
    security: { type: Object, default: null },
    operational_date: { type: Object, default: null },
    state: { type: String, default: '' },
    schedule: { type: Object, default: null },
    shift: { type: Object, default: null },
    post: { type: Object, default: null },
    attendance: { type: Object, default: null },
    windows: { type: Object, default: null },
    announcements: { type: Array, default: () => [] },
});
</script>

<template>
    <AppLayout>
        <div class="relative w-full">
            <HomeHeader :security="props.security" />

            <main class="relative -mt-8 space-y-6 px-5 pb-28">
                <AttendanceCard
                    :operational_date="props.operational_date"
                    :state="props.state"
                    :schedule="props.schedule"
                    :shift="props.shift"
                    :post="props.post"
                    :attendance="props.attendance"
                    :windows="props.windows"
                />
                <QuickActions />
                <AnnouncementSection :announcements="props.announcements" />
            </main>
        </div>

        <template #bottom>
            <BottomNavigation active="home" />
        </template>
    </AppLayout>
</template>
