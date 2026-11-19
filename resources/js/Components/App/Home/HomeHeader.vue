<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { ArrowRightOnRectangleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    security: { type: Object, default: null },
});

function logout() {
    router.post('/logout');
}

const org = 'PLN UID ACEH';
const role = 'Satpam';
const avatar = '/images/app/avatar-placeholder.png';

const displayName = computed(() => props.security?.full_name?.trim() || 'Satpam');

const initials = computed(() => {
    const name = displayName.value.trim();
    if (!name) return 'S';
    const parts = name.split(/\s+/).filter(Boolean);
    const first = parts[0]?.[0] || '';
    const second = parts[1]?.[0] || parts[0]?.[1] || '';
    return (first + second).toUpperCase();
});

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour >= 4 && hour < 11) return 'Selamat Pagi';
    if (hour >= 11 && hour < 15) return 'Selamat Siang';
    if (hour >= 15 && hour < 18) return 'Selamat Sore';
    return 'Selamat Malam';
});

function onImageError(event) {
    event.target.style.display = 'none';
}
</script>

<template>
    <section class="relative h-[280px] overflow-hidden bg-[#1D567F]">
        <img
            src="/images/app/home-header-bg.png"
            alt=""
            class="absolute inset-0 h-full w-full object-cover"
            @error="onImageError"
        />

        <div class="relative z-10 px-6 pt-8">
            <!-- Instansi dan Logout -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>

                    <p class="text-xs font-semibold tracking-wider text-white/90">
                        {{ org }}
                    </p>
                </div>

                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg border border-white/20 px-3 py-1.5 text-xs font-medium text-white/90 transition-colors hover:bg-white/10 active:bg-white/20"
                    @click="logout"
                >
                    <ArrowRightOnRectangleIcon class="h-4 w-4" />
                    Keluar
                </button>
            </div>

            <!-- Profil Pengguna -->
            <div class="mt-9 flex items-center gap-4">

                <!-- Avatar -->
                <div class="flex h-[58px] w-[58px] shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-white/30 bg-white text-lg font-semibold text-[#1D567F]">
                    <img
                        :src="avatar"
                        alt=""
                        class="h-full w-full object-cover"
                        @error="onImageError"
                    />
                    <span>{{ initials }}</span>
                </div>

                <!-- Nama dan Jabatan -->
                <div class="min-w-0">
                    <p class="text-sm text-white/75">
                        {{ greeting }}
                    </p>

                    <h1 class="truncate text-xl font-semibold tracking-tight text-white">
                        {{ displayName }}
                    </h1>

                    <!-- <p class="mt-1 text-xs font-medium text-white/75">
                        {{ role }}
                    </p> -->
                </div>
            </div>

        </div>
    </section>
</template>
