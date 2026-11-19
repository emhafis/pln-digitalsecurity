<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppHeader from '@/Components/App/AppHeader.vue';
import BottomNavigation from '@/Components/App/Home/BottomNavigation.vue';
import { ChevronLeftIcon, EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';

const showCurrent = ref(false);
const showNew = ref(false);
const showConfirm = ref(false);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/app/profile/password', { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>

<template>
    <AppLayout>
        <div class="min-h-full bg-gray-50">
            <AppHeader title="Ubah Password" description="Perbarui password akun Anda" backUrl="/app/profile" backLabel="Profil" />

            <main class="space-y-4 px-4 pb-8 pt-4">
                <div v-if="$page.props.flash?.status" class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                    {{ $page.props.flash.status }}
                </div>

                <section class="rounded-xl border border-[#E8E8EC] bg-white p-4">
                    <form @submit.prevent="submit" class="space-y-3">
                        <div>
                            <label class="mb-1 block text-xs text-[#9299A2]">Password Saat Ini</label>
                            <div class="relative">
                                <input :type="showCurrent ? 'text' : 'password'" v-model="form.current_password" class="w-full rounded-lg border border-[#E8E8EC] px-3 py-2 pr-10 text-sm text-[#263746] focus:border-[#1D567F] focus:outline-none" />
                                <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-[#9299A2]" @click="showCurrent = !showCurrent">
                                    <EyeIcon v-if="!showCurrent" class="h-5 w-5" />
                                    <EyeSlashIcon v-else class="h-5 w-5" />
                                </button>
                            </div>
                            <p v-if="form.errors.current_password" class="mt-1 text-xs text-red-600">{{ form.errors.current_password }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs text-[#9299A2]">Password Baru</label>
                            <div class="relative">
                                <input :type="showNew ? 'text' : 'password'" v-model="form.password" class="w-full rounded-lg border border-[#E8E8EC] px-3 py-2 pr-10 text-sm text-[#263746] focus:border-[#1D567F] focus:outline-none" />
                                <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-[#9299A2]" @click="showNew = !showNew">
                                    <EyeIcon v-if="!showNew" class="h-5 w-5" />
                                    <EyeSlashIcon v-else class="h-5 w-5" />
                                </button>
                            </div>
                            <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs text-[#9299A2]">Konfirmasi Password Baru</label>
                            <div class="relative">
                                <input :type="showConfirm ? 'text' : 'password'" v-model="form.password_confirmation" class="w-full rounded-lg border border-[#E8E8EC] px-3 py-2 pr-10 text-sm text-[#263746] focus:border-[#1D567F] focus:outline-none" />
                                <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-[#9299A2]" @click="showConfirm = !showConfirm">
                                    <EyeIcon v-if="!showConfirm" class="h-5 w-5" />
                                    <EyeSlashIcon v-else class="h-5 w-5" />
                                </button>
                            </div>
                        </div>

                        <button type="submit" :disabled="form.processing" class="flex w-full items-center justify-center rounded-xl bg-[#1D567F] px-4 py-3 text-sm font-semibold text-white disabled:opacity-60">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Password' }}
                        </button>
                    </form>
                </section>
            </main>
        </div>

        <template #bottom>
            <BottomNavigation active="profile" />
        </template>
    </AppLayout>
</template>
