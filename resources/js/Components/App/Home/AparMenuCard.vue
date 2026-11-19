<script setup>
import { Link } from '@inertiajs/vue3';
import { FireIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';
</script>

<template>
    <section class="rounded-xl border border-[#E8E8EC] bg-white p-4">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#E8F0F6] text-[#1D567F]">
                <FireIcon class="h-5 w-5" />
            </span>
            <div>
                <h3 class="text-sm font-semibold text-[#263746]">APAR</h3>
                <p class="mt-1 text-xs text-[#9299A2]">Periksa kondisi APAR dan lihat riwayat inspeksinya.</p>
            </div>
        </div>
        <Link href="/app/apar" class="mt-4 inline-flex w-full items-center justify-center gap-1 rounded-lg bg-[#1D567F] px-4 py-2.5 text-sm font-semibold text-white">
            Buka APAR
            <ChevronRightIcon class="h-4 w-4" />
        </Link>
    </section>
</template>
