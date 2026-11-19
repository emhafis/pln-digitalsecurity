
<script setup>
import { useForm } from '@inertiajs/vue3';

import TextInput from '@/Components/Admin/TextInput.vue';
import InputLabel from '@/Components/Admin/InputLabel.vue';
import InputError from '@/Components/Admin/InputError.vue';
import Button from '@/Components/Admin/Button.vue';

defineProps({
    canRegister: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <div class="min-h-screen grid grid-cols-1 md:grid-cols-2">

        <!-- LEFT SIDE DESKTOP -->
        <div class="hidden md:block bg-gray-100">
            <div class="min-h-screen content-center px-20">

                <div class="flex flex-col gap-4">

                    <!-- Logo -->
                    <div>
                        <img
                            src="/images/digty-logo.png"
                            alt="PLN Logo"
                            class="h-20 w-auto"
                        />
                    </div>

                    <!-- Description -->
                    <p class="text-md">
                        Sistem Manajemen Keamanan Digital untuk mendukung
                        pengelolaan, pemantauan, dan perlindungan informasi
                        secara terintegrasi guna menciptakan lingkungan kerja
                        yang aman.
                    </p>

                </div>

            </div>
        </div>

        <!-- RIGHT SIDE LOGIN -->
        <div
            class="min-h-screen flex flex-col justify-center
            items-center px-6"
        >

            <!-- MOBILE LOGO -->
            <div class="md:hidden mb-8 flex justify-center">
                <img
                    src="/images/digty-logo.png"
                    alt="PLN Logo"
                    class="h-16 w-auto"
                />
            </div>

            <!-- LOGIN HEADING -->
            <div class="flex flex-col items-center gap-1 mb-8">

                <h2 class="font-bold text-primary text-xl">
                    Login
                </h2>

                <p class="text-slate-700">
                    Masukkan Username dan Password
                </p>

            </div>

            <!-- LOGIN FORM -->
            <form
                @submit.prevent="submit"
                class="w-full max-w-sm"
            >

                <!-- USERNAME -->
                <div class="mb-4">

                    <InputLabel
                        for="username"
                        value="Username"
                        :required="true"
                        class="block text-sm font-medium text-slate-700"
                    />

                    <TextInput
                        id="username"
                        v-model="form.username"
                        type="text"
                        name="username"
                        required
                        autofocus
                        autocomplete="username"
                        :error="form.errors.username"
                        class="mt-1 block w-full mb-2"
                    />

                    <InputError
                        :messages="form.errors.username"
                    />

                </div>

                <!-- PASSWORD -->
                <div class="mb-5">

                    <InputLabel
                        for="password"
                        value="Password"
                        :required="true"
                        class="block text-sm font-medium text-slate-700"
                    />

                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        :error="form.errors.password"
                        class="mt-1 block w-full mb-4"
                    />

                    <InputError
                        :messages="form.errors.password"
                    />

                </div>

                <!-- REMEMBER ME -->
                <div class="mb-5">

                    <label class="inline-flex items-center cursor-pointer">

                        <input
                            v-model="form.remember"
                            type="checkbox"
                            name="remember"
                            class="scale-125 rounded border-gray-300
                            text-indigo-600 shadow-sm
                            focus:ring-indigo-500"
                        />

                        <span class="ml-2 text-xs text-gray-600">
                            Ingat saya 30 hari kedepan
                        </span>

                    </label>

                </div>

                <!-- LOGIN BUTTON -->
                <Button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full py-3"
                >
                    {{ form.processing ? 'Masuk...' : 'Masuk' }}
                </Button>

            </form>

        </div>

    </div>
</template>
