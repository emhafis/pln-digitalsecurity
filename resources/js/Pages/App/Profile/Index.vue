<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppHeader from '@/Components/App/AppHeader.vue';
import BottomNavigation from '@/Components/App/Home/BottomNavigation.vue';
import { ChevronLeftIcon, EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    user: { type: Object, required: true },
    security: { type: Object, required: true },
    unit: { type: Object, default: null },
    membership: { type: Object, default: null },
});

const showCurrent = ref(false);
const showNew = ref(false);
const showConfirm = ref(false);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submitPassword() {
    form.post('/app/profile/password', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
}

function logout() {
    router.post('/logout');
}

const initials = (props.security?.full_name || props.user?.name || '?')
    .split(' ')
    .map((w) => w[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();
</script>

<template>
    <AppLayout>
        <div class="min-h-full bg-gray-50">
            
<AppHeader title="Profil" description="Informasi akun dan pengaturan pribadi" />


            <main class="space-y-4 px-4 pb-8 pt-4">
                <!-- Header -->
                <section class="rounded-xl border border-[#E8E8EC] bg-white p-4">
                    <div class="flex items-center gap-3">
                        <div v-if="security.photo" class="h-14 w-14 overflow-hidden rounded-full">
                            <img :src="security.photo" alt="Foto profil" class="h-full w-full object-cover" />
                        </div>
                        <div v-else class="flex h-14 w-14 items-center justify-center rounded-full bg-[#E8F0F6] text-sm font-semibold text-[#1D567F]">
                            {{ initials }}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-[#263746]">{{ security.full_name || user.name }}</p>
                            <p class="truncate text-xs text-[#9299A2]">{{ user.username }}</p>
                            <span class="mt-1 inline-flex rounded-full border border-green-200 bg-green-50 px-2 py-0.5 text-[11px] font-medium text-green-700">
                                {{ user.is_active ? 'Aktif' : 'Tidak Aktif' }}
                            </span>
                        </div>
                    </div>
                </section>

                <!-- Informasi pribadi -->
                <section class="rounded-xl border border-[#E8E8EC] bg-white p-4">
                    <h2 class="mb-3 text-sm font-semibold text-[#263746]">Informasi Pribadi</h2>
                    <dl class="grid grid-cols-1 gap-3 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-[#9299A2]">Nama Lengkap</dt><dd class="font-medium text-[#263746] text-right">{{ security.full_name || user.name }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#9299A2]">NIK</dt><dd class="font-medium text-[#263746] text-right">{{ security.registration_number || '-' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#9299A2]">Nomor HP</dt><dd class="font-medium text-[#263746] text-right">{{ security.phone_number || '-' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#9299A2]">Email</dt><dd class="font-medium text-[#263746] text-right">{{ user.email || '-' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#9299A2]">Username</dt><dd class="font-medium text-[#263746] text-right">{{ user.username }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#9299A2]">Status Akun</dt><dd class="font-medium text-[#263746] text-right">{{ user.is_active ? 'Aktif' : 'Tidak Aktif' }}</dd></div>
                    </dl>
                </section>

                <!-- Keanggotaan -->
                <section class="rounded-xl border border-[#E8E8EC] bg-white p-4">
                    <h2 class="mb-3 text-sm font-semibold text-[#263746]">Informasi Keanggotaan</h2>
                    <dl class="grid grid-cols-1 gap-3 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-[#9299A2]">Unit Kerja</dt><dd class="font-medium text-[#263746] text-right">{{ unit?.unit_name || '-' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#9299A2]">Pos Jaga</dt><dd class="font-medium text-[#263746] text-right">{{ membership?.securityTeam?.securityPost?.name || '-' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#9299A2]">Regu</dt><dd class="font-medium text-[#263746] text-right">{{ membership?.securityTeam?.name || '-' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#9299A2]">Status Keanggotaan</dt><dd class="font-medium text-[#263746] text-right">{{ membership ? 'Aktif' : 'Tidak terdaftar' }}</dd></div>
                    </dl>
                </section>

                <!-- Keamanan Akun -->
                <section class="rounded-xl border border-[#E8E8EC] bg-white p-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#E8F0F6] text-[#1D567F]">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-[#263746]">Keamanan Akun</h2>
                            <p class="text-xs text-[#9299A2]">Kelola keamanan password akun Anda.</p>
                        </div>
                    </div>
                    <Link href="/app/profile/password" class="mt-4 flex w-full items-center justify-center rounded-xl bg-[#1D567F] px-4 py-3 text-sm font-semibold text-white">
                        Ubah Password
                    </Link>
                    <button type="button" class="mt-2 flex w-full items-center justify-center rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-600" @click="logout">
                        Logout
                    </button>
                </section>
            </main>
        </div>

        <template #bottom>
            <BottomNavigation active="profile" />
        </template>
    </AppLayout>
</template>
