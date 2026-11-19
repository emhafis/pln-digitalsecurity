<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bars3Icon, BellIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';

defineProps({
    title: {
        type: String,
        default: 'Dashboard',
    },
});

const emit = defineEmits(['toggle-sidebar']);

const page = usePage();
const user = computed(() => page.props.auth.user);

const menuOpen = ref(false);

function logout() {
    router.post('/logout');
}
</script>

<template>
    <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-gray-200 bg-white px-4 sm:px-6">
        <button
            type="button"
            class="rounded-lg p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 lg:hidden"
            aria-label="Buka menu"
            @click="emit('toggle-sidebar')"
        >
            <Bars3Icon class="h-5 w-5" />
        </button>

        <div class="ml-auto flex items-center gap-2">
            <button
                type="button"
                class="relative rounded-lg p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700"
                aria-label="Notifikasi"
            >
            </button>

            <div class="relative">
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-lg p-1.5 transition hover:bg-gray-100"
                    @click="menuOpen = !menuOpen"
                >
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-700">
                        {{ user?.name?.charAt(0)?.toUpperCase() }}
                    </span>
                    <span class="hidden text-left sm:block">
                        <span class="block text-sm font-medium leading-tight text-gray-900">{{ user?.name }}</span>
                        <span class="block text-[11px] leading-tight text-gray-500">Administrator</span>
                    </span>
                    <ChevronDownIcon class="hidden h-4 w-4 text-gray-400 sm:block" />
                </button>

                <div
                    v-if="menuOpen"
                    class="absolute right-0 mt-2 w-56 rounded-lg border border-gray-200 bg-white py-1.5 shadow-md"
                >
                    <div class="border-b border-gray-100 px-4 py-2">
                        <p class="truncate text-sm font-medium text-gray-900">{{ user?.name }}</p>
                        <p class="truncate text-xs text-gray-500">{{ user?.email }}</p>
                    </div>
                    <Link
                        href="/admin"
                        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        @click="menuOpen = false"
                    >
                        Dashboard
                    </Link>
                    <button
                        type="button"
                        class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50"
                        @click="logout"
                    >
                        Log out
                    </button>
                </div>
            </div>
        </div>
    </header>
</template>
