
<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    CalendarDaysIcon,
    ArrowRightEndOnRectangleIcon,
    InformationCircleIcon,
} from '@heroicons/vue/24/outline';
import { ClockIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    operational_date: { type: Object, default: null },
    state: { type: String, default: '' },
    schedule: { type: Object, default: null },
    shift: { type: Object, default: null },
    post: { type: Object, default: null },
    attendance: { type: Object, default: null },
    windows: { type: Object, default: null },
    variant: { type: String, default: 'home' },
});

const submitting = ref(false);
const submitPhase = ref('');
const submitError = ref('');

const now = ref(new Date());
let timer;

onMounted(() => {
    timer = setInterval(() => {
        now.value = new Date();
    }, 30000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});

function toDate(value) {
    if (!value) return null;
    const d = new Date(value);
    return Number.isNaN(d.getTime()) ? null : d;
}

function formatTime(value) {
    if (!value) return '';
    const s = String(value);
    return s.length >= 5 ? s.slice(0, 5) : s;
}

const dateLabel = computed(() => props.operational_date?.label || '-');
const shiftName = computed(() => props.shift?.name || '-');

const shiftTimeLabel = computed(() => {
    if (props.windows?.scheduled_start_label && props.windows?.scheduled_end_label) {
        const start = props.windows.scheduled_start_label.split(', ').pop() || props.windows.scheduled_start_label;
        const end = props.windows.scheduled_end_label.split(', ').pop() || props.windows.scheduled_end_label;
        const suffix = props.shift?.end_day_offset ? ' (+1 hari)' : '';
        return `${start} - ${end}${suffix}`;
    }

    if (props.shift?.start_time && props.shift?.end_time) {
        const suffix = props.shift?.end_day_offset ? ' (+1 hari)' : '';
        return `${formatTime(props.shift.start_time)} - ${formatTime(props.shift.end_time)}${suffix}`;
    }

    return '-';
});

const postLabel = computed(() => props.post?.name || '-');
const unitLabel = computed(() => props.post?.unit_name || '');

const statusLabel = computed(() => {
    if (props.state === 'no_security_profile') return 'Profil belum tersedia';
    if (props.state === 'no_schedule') return 'Tidak ada jadwal';
    if (props.state === 'day_off') return props.attendance?.status_label || 'Libur';
    if (props.state === 'schedule_without_record') return 'Belum tersedia';
    if (props.attendance?.status_label) return props.attendance.status_label;
    return '-';
});

const statusTone = computed(() => {
    const s = props.attendance?.status;
    if (props.state === 'day_off' || s === 'day_off') return 'text-gray-600';
    if (s === 'completed') return 'text-emerald-700';
    if (s === 'absent') return 'text-rose-600';
    if (s === 'in_progress') return 'text-[#1D567F]';
    if (s === 'pending') return 'text-amber-600';
    return 'text-gray-700';
});

const statusDot = computed(() => {
    const s = props.attendance?.status;

    if (props.state === 'day_off' || s === 'day_off') return 'bg-gray-400';
    if (s === 'completed') return 'bg-emerald-500';
    if (s === 'absent') return 'bg-rose-500';
    if (s === 'in_progress') return 'bg-[#1D567F]';
    if (s === 'pending') return 'bg-amber-500';

    return 'bg-gray-400';
});

const checkInLabel = computed(() => props.attendance?.check_in_label || 'Belum absen');
const checkOutLabel = computed(() => props.attendance?.check_out_label || 'Belum absen');
const isLate = computed(() => !!props.attendance?.is_late);
const totalWorkLabel = computed(() => props.attendance?.total_work_label || null);

const showScheduleInfo = computed(() =>
    ['day_off', 'schedule_without_record', 'record_available'].includes(props.state)
);

const showAttendanceTimes = computed(() =>
    ['record_available', 'day_off', 'schedule_without_record'].includes(props.state)
);

const infoMessage = computed(() => {
    if (props.state === 'no_security_profile') return 'Profil Satpam belum tersedia. Hubungi admin untuk melengkapi data.';
    if (props.state === 'no_schedule') return 'Tidak ada jadwal kerja untuk tanggal ini.';
    if (props.state === 'day_off') return 'Hari ini Anda berstatus libur.';
    if (props.state === 'schedule_without_record') return 'Jadwal tersedia, data absensi belum dibuat.';
    return '';
});

const action = computed(() => {
    const state = props.state;
    const att = props.attendance;
    const win = props.windows;

    if (['no_security_profile', 'no_schedule', 'day_off', 'schedule_without_record'].includes(state)) {
        return { type: 'none' };
    }

    if (!att) return { type: 'none' };

    const status = att.status;

    if (status === 'completed') {
        return { type: 'info', text: 'Kehadiran selesai.' };
    }

    if (status === 'absent') {
        return { type: 'info', text: 'Anda tercatat tidak hadir.' };
    }

    if (status === 'day_off') {
        return { type: 'none' };
    }

    if (status === 'in_progress') {
        const outOpen = toDate(win?.check_out_opens_at);
        const outClose = toDate(win?.check_out_closes_at);
        const current = now.value;

        if (outOpen && outClose && current >= outOpen && current <= outClose) {
            return { type: 'button', label: 'Absen Keluar' };
        }

        if (outOpen && current < outOpen) {
            return { type: 'info', text: `Absen keluar dibuka pukul ${win?.check_out_opens_label || '-'}.` };
        }

        if (outClose && current > outClose) {
            return { type: 'info', text: 'Batas waktu absen keluar telah berakhir.' };
        }

        return { type: 'info', text: 'Anda sedang bertugas.' };
    }

    if (status === 'scheduled' || status === 'pending') {
        const inOpen = toDate(win?.check_in_opens_at);
        const inClose = toDate(win?.scheduled_end_at);
        const current = now.value;

        if (inOpen && current < inOpen) {
            return { type: 'info', text: `Absen masuk dibuka pukul ${win?.check_in_opens_label || '-'}.` };
        }

        if (inClose && current > inClose) {
            return { type: 'info', text: 'Waktu absen masuk telah berakhir.' };
        }

        if (!att.check_in_at) {
            return { type: 'button', label: 'Absen Masuk' };
        }
    }

    return { type: 'none' };
});

function onActionClick() {
    if (action.value.type !== 'button') return;

    if (action.value.label === 'Absen Masuk') {
        startAttendance('check-in');
    } else if (action.value.label === 'Absen Keluar') {
        startAttendance('check-out');
    }
}

function startAttendance(type) {
    if (submitting.value || !props.attendance?.id) return;

    submitError.value = '';

    if (!navigator.geolocation) {
        submitError.value = 'Perangkat tidak mendukung GPS atau lokasi tidak tersedia.';
        return;
    }

    const actionLabel = type === 'check-in' ? 'check-in' : 'check-out';

    submitting.value = true;
    submitPhase.value = 'Meminta izin lokasi...';

    navigator.geolocation.getCurrentPosition(
        (position) => {
            submitPhase.value = 'Mengirim data absensi...';

            router.post(`/app/attendance/${props.attendance.id}/${type}`, {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy: position.coords.accuracy,
            }, {
                preserveScroll: true,
                onError: (errors) => {
                    submitError.value = errors.location || errors.attendance || errors.latitude || errors.longitude || errors.accuracy || 'Terjadi kesalahan saat mengirim data absensi.';
                },
                onFinish: () => {
                    submitting.value = false;
                    submitPhase.value = '';
                },
            });
        },
        (error) => {
            submitting.value = false;
            submitPhase.value = '';

            if (error.code === 1) {
                submitError.value = `Izin lokasi ditolak. Aktifkan izin lokasi untuk melakukan ${actionLabel}.`;
            } else if (error.code === 2) {
                submitError.value = 'Lokasi perangkat tidak tersedia. Pastikan GPS aktif dan coba lagi.';
            } else if (error.code === 3) {
                submitError.value = 'Pengambilan lokasi melewati batas waktu. Coba lagi di area dengan sinyal GPS lebih baik.';
            } else {
                submitError.value = 'Gagal mengambil lokasi perangkat. Silakan coba lagi.';
            }
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
}

const cardClass = computed(() => (
    props.variant === 'home'
        ? 'relative z-20 -mt-25 mx-1 rounded-2xl bg-white p-5 border border-gray-200 shadow-gray-900/5'
        : 'rounded-2xl border border-gray-200 bg-white p-5'
));
</script>


<template>
    <section :class="cardClass">

        <!-- Tanggal dan status -->
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-xs text-gray-400">Kehadiran hari ini</p>
                <h2 class="mt-0.5 text-sm font-semibold text-gray-800">
                    {{ dateLabel }}
                </h2>
            </div>

            <span
                class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-gray-50 px-2.5 py-1 text-xs font-medium"
                :class="statusTone"
            >
                <span class="h-1.5 w-1.5 rounded-full" :class="statusDot"></span>
                {{ statusLabel }}
            </span>
        </div>

        <!-- Informasi jadwal -->
        <div v-if="showScheduleInfo" class="mt-3 rounded-lg bg-gray-50 px-3 py-2.5">
            <p class="text-sm font-semibold text-gray-800">
                {{ shiftName }}
                <span
                    v-if="shiftTimeLabel && shiftTimeLabel !== '-'"
                    class="font-normal text-gray-500"
                >
                    · {{ shiftTimeLabel }}
                </span>
            </p>

            <p v-if="postLabel && postLabel !== '-'" class="mt-1 text-xs text-gray-500">
                {{ postLabel }}
                <span v-if="unitLabel"> · {{ unitLabel }}</span>
            </p>
        </div>

        <!-- Pesan informasi -->
        <p
            v-if="infoMessage"
            class="mt-2 text-xs leading-relaxed text-gray-500"
        >
            {{ infoMessage }}
        </p>

        <!-- Waktu absensi -->
        <div class="mt-4 border-t border-gray-100 pt-3">

            <div class="flex items-center justify-between gap-2">
                <div>
                    <p class="text-xs text-gray-400">Absen masuk</p>
                    <p class="mt-1 text-base font-semibold text-gray-800">
                        {{ showAttendanceTimes ? checkInLabel : '-' }}
                    </p>

                    <p
                        v-if="isLate && showAttendanceTimes"
                        class="mt-0.5 text-[11px] font-medium text-rose-600"
                    >
                        Terlambat
                    </p>
                </div>

                <div class="h-9 w-px bg-gray-100"></div>

                <div>
                    <p class="text-xs text-gray-400">Absen keluar</p>
                    <p class="mt-1 text-base font-semibold text-gray-800">
                        {{ showAttendanceTimes ? checkOutLabel : '-' }}
                    </p>
                </div>

                <div
                    v-if="totalWorkLabel && showAttendanceTimes"
                    class="text-right"
                >
                    <p class="text-xs text-gray-400">Total kerja</p>
                    <p class="mt-1 text-sm font-semibold text-gray-800">
                        {{ totalWorkLabel }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Informasi tindakan -->
        <div
            v-if="action.type === 'info'"
            class="mt-3 flex items-start gap-2 rounded-lg bg-blue-50 px-3 py-2.5"
        >
            <InformationCircleIcon class="mt-0.5 h-4 w-4 shrink-0 text-[#1D567F]" />

            <p class="text-xs leading-relaxed text-[#1D567F]">
                {{ action.text }}
            </p>
        </div>

        <!-- Tombol absensi -->
        <div v-if="action.type === 'button'" class="mt-4">
            <button
                type="button"
                :disabled="submitting"
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-[#1D567F] px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#174768] active:bg-[#123D59] disabled:cursor-not-allowed disabled:opacity-60"
                @click="onActionClick"
            >
                <ArrowRightEndOnRectangleIcon class="h-5 w-5" />
                {{ submitting ? (submitPhase || 'Memproses...') : action.label }}
            </button>

            <p
                v-if="submitError"
                class="mt-2 text-xs leading-relaxed text-rose-600"
            >
                {{ submitError }}
            </p>
        </div>

    </section>
</template>

