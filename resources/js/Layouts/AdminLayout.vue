<script setup>
import { ref } from 'vue';
import AdminSidebar from '@/Components/Admin/AdminSidebar.vue';
import AdminTopbar from '@/Components/Admin/AdminTopbar.vue';

defineProps({
    title: {
        type: String,
        default: 'Dashboard',
    },
});

const sidebarOpen = ref(false);
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-black/40 lg:hidden"
            @click="sidebarOpen = false"
        />

        <aside
            class="fixed inset-y-0 left-0 z-50 w-64 -translate-x-full border-r border-gray-200 transition-transform duration-200 lg:translate-x-0"
            :class="{ '!translate-x-0': sidebarOpen }"
        >
            <AdminSidebar @close="sidebarOpen = false" />
        </aside>

        <div class="lg:pl-64">
            <AdminTopbar :title="title" @toggle-sidebar="sidebarOpen = !sidebarOpen" />

            <main class="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
