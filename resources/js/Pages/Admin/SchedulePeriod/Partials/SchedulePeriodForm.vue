<script setup>
import { computed, ref, watch } from 'vue';
import InputLabel from '@/Components/Admin/InputLabel.vue';
import InputError from '@/Components/Admin/InputError.vue';
import TextInput from '@/Components/Admin/TextInput.vue';
import SearchSelect from '@/Components/Admin/SearchSelect.vue';
import Select from '@/Components/Admin/Select.vue';

const props = defineProps({
    form: { type: Object, required: true },
    units: { type: Array, default: () => [] },
    posts: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    patterns: { type: Array, default: () => [] },
    securities: { type: Array, default: () => [] },
    previousPeriods: { type: Array, default: () => [] },
    isEdit: { type: Boolean, default: false },
    hasSchedules: { type: Boolean, default: false },
    today: { type: String, default: '' },
});

const copyFromId = ref('');

const statusOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'active', label: 'Aktif' },
    { value: 'completed', label: 'Selesai' },
];

const monthOptions = [
    { value: 1, label: 'Januari' },
    { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' },
    { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' },
    { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },
    { value: 12, label: 'Desember' },
];

function syncPeriodName() {
    const month = Number(props.form.month || 1);
    const year = Number(props.form.year || props.today.slice(0, 4)) || 2000;
    const monthLabel = monthOptions.find((item) => Number(item.value) === month)?.label || '';
    props.form.name = `${monthLabel} ${year}`;
}

const initialDate = props.form.start_date || props.today || '';

if (! props.form.month || ! props.form.year) {
    props.form.month = Number(initialDate.slice(5, 7)) || 1;
    props.form.year = Number(initialDate.slice(0, 4)) || 2000;
}

syncPeriodName();

watch(() => [props.form.month, props.form.year], syncPeriodName);

const filteredPosts = computed(() => props.posts.filter(
    (item) => !props.form.unit_id || String(item.unit_id) === String(props.form.unit_id)
));

const filteredTeams = computed(() => {
    if (!props.form.unit_id || !props.form.security_post_id) {
        return [];
    }

    return props.teams.filter(
        (item) => String(item.security_post_id) === String(props.form.security_post_id)
            && (item.is_active || String(item.value) === String(props.form.security_team_id))
    );
});

const filteredPatterns = computed(() => props.patterns.filter(
    (item) => !props.form.unit_id || String(item.unit_id) === String(props.form.unit_id)
));

const securityOptions = computed(() => {
    const items = props.securities.map((item) => ({
        value: String(item.value),
        label: `${item.label} — ${item.unit_name ?? '-'}`,
        active_team_id: item.active_team_id,
    }));

    if (!props.form.security_team_id) {
        return items;
    }

    return items.filter((item) => String(item.active_team_id) === String(props.form.security_team_id));
});

const patternOptions = computed(() => [
    { value: '', label: 'Tanpa pola' },
    ...filteredPatterns.value.map((item) => ({
        value: String(item.value),
        label: item.label,
    })),
]);

const selectedTeam = computed(() => props.teams.find(
    (item) => String(item.value) === String(props.form.security_team_id)
));

const selectedUnitLabel = computed(
    () => props.units.find((item) => String(item.value) === String(props.form.unit_id))?.label ?? '-'
);

const selectedPostLabel = computed(
    () => props.posts.find((item) => String(item.value) === String(props.form.security_post_id))?.label ?? '-'
);

const selectedTeamLabel = computed(
    () => props.teams.find((item) => String(item.value) === String(props.form.security_team_id))?.label ?? '-'
);

const copyOptions = computed(() => props.previousPeriods
    .filter((item) => String(item.security_team_id) === String(props.form.security_team_id))
    .map((item) => ({
        value: String(item.id),
        label: `${item.name} (${item.start_date} s/d ${item.end_date})`,
    })));

const members = computed(() => (Array.isArray(props.form.members) ? props.form.members : []));

function patternFor(row) {
    return filteredPatterns.value.find((item) => String(item.value) === String(row.schedule_pattern_id));
}

function cycleLengthFor(row) {
    return Number(patternFor(row)?.cycle_length || 0);
}

function rotationDayFor(row) {
    const raw = Number(row.rotation_day ?? (Number(row.start_offset || 0) + 1));
    if (! Number.isFinite(raw) || raw < 1) {
        return 1;
    }

    const cycle = cycleLengthFor(row);
    if (cycle > 0 && raw > cycle) {
        return 1;
    }

    return Math.floor(raw);
}

function firstShiftFor(row) {
    const pattern = patternFor(row);
    const day = rotationDayFor(row);

    return pattern?.details?.find((detail) => Number(detail.day_order) === day) ?? null;
}

function formatPeriodDate(value) {
    if (! value) {
        return '-';
    }

    const date = new Date(String(value) + 'T00:00:00+07:00');

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'Asia/Jakarta',
    });
}

function updateRotationDay(row, value) {
    const cycle = cycleLengthFor(row);
    let day = Number(value);

    if (! Number.isFinite(day) || day < 1) {
        day = 1;
    }

    if (cycle > 0 && day > cycle) {
        day = cycle;
    }

    row.rotation_day = day;
    row.start_offset = day - 1;
}

const preview = computed(() => members.value.map((row, index) => {
    const firstShift = firstShiftFor(row);

    return {
        index: index + 1,
        security: securityLabel(row.security_id),
        pattern: patternLabel(row.schedule_pattern_id),
        rotationDay: row.schedule_pattern_id ? rotationDayFor(row) : '-',
        firstShift: row.schedule_pattern_id
            ? (firstShift?.shift_name ?? 'Belum tersedia')
            : '-',
    };
}));

function securityLabel(id) {
    return props.securities.find((item) => String(item.value) === String(id))?.full_name ?? '-';
}

function patternLabel(id) {
    return props.patterns.find((item) => String(item.value) === String(id))?.label ?? 'Tanpa pola';
}

function securityOptionsFor(index) {
    const selected = members.value
        .map((row, rowIndex) => (rowIndex === index ? null : String(row.security_id)))
        .filter((value) => value !== null && value !== '');

    return securityOptions.value.filter((option) => !selected.includes(option.value));
}

function addMember() {
    props.form.members.push({
        security_id: '',
        schedule_pattern_id: '',
        start_offset: 0,
        rotation_day: 1,
    });
}

function removeMember(index) {
    props.form.members.splice(index, 1);
}

function setPattern(row, value) {
    row.schedule_pattern_id = value;

    if (! value) {
        row.start_offset = 0;
        row.rotation_day = 1;
        return;
    }

    const pattern = filteredPatterns.value.find((item) => String(item.value) === String(value));
    const cycle = Number(pattern?.cycle_length || 0);

    if (! row.rotation_day || Number(row.rotation_day) < 1) {
        row.rotation_day = 1;
    }

    if (cycle > 0 && Number(row.rotation_day) > cycle) {
        row.rotation_day = 1;
    }

    row.start_offset = Number(row.rotation_day) - 1;
}

function isMembershipActive(membership, date) {
    if (! date) {
        return false;
    }

    if (membership.joined_at && membership.joined_at > date) {
        return false;
    }

    return ! membership.left_at || membership.left_at >= date;
}

function loadActiveMembers() {
    if (! selectedTeam.value || ! props.form.start_date) {
        return;
    }

        props.form.members = selectedTeam.value.memberships
            .filter((membership) => isMembershipActive(membership, props.form.start_date))
            .map((membership) => ({
                security_id: String(membership.security_id),
                schedule_pattern_id: '',
                start_offset: 0,
                rotation_day: 1,
            }));
}

function copyMembers() {
    const source = props.previousPeriods.find(
        (item) => String(item.id) === String(copyFromId.value)
    );

    if (! source) {
        return;
    }

    props.form.members = source.members.map((member) => ({
        security_id: String(member.security_id),
        schedule_pattern_id: member.schedule_pattern_id ? String(member.schedule_pattern_id) : '',
        start_offset: Number(member.start_offset),
        rotation_day: Number(member.start_offset) + 1,
    }));
}

watch(() => props.form.unit_id, (value, previous) => {
    if (previous === undefined || String(value) === String(previous)) {
        return;
    }

    props.form.security_post_id = null;
    props.form.security_team_id = null;
    props.form.members = [];
    copyFromId.value = '';
});

watch(() => props.form.security_post_id, (value, previous) => {
    if (previous === undefined || String(value) === String(previous)) {
        return;
    }

    props.form.security_team_id = null;
    props.form.members = [];
    copyFromId.value = '';
});

watch(() => props.form.security_team_id, (value, previous) => {
    if (previous === undefined || String(value) === String(previous)) {
        return;
    }

    props.form.members = [];
    copyFromId.value = '';
});
</script>

<template>
    <div class="space-y-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <InputLabel value="Unit" :required="true" />
                <TextInput v-if="hasSchedules" :model-value="selectedUnitLabel" disabled />
                <SearchSelect
                    v-else
                    v-model="form.unit_id"
                    :options="units"
                    placeholder="Pilih unit..."
                    search-placeholder="Cari unit..."
                />
                <InputError :messages="form.errors?.unit_id" />
            </div>

            <div>
                <InputLabel value="Pos Jaga" :required="true" />
                <TextInput v-if="hasSchedules" :model-value="selectedPostLabel" disabled />
                <SearchSelect
                    v-else
                    v-model="form.security_post_id"
                    :options="filteredPosts"
                    placeholder="Pilih pos jaga..."
                    search-placeholder="Cari pos..."
                />
                <InputError :messages="form.errors?.security_post_id" />
            </div>

            <div>
                <InputLabel value="Regu" :required="true" />
                <TextInput v-if="hasSchedules" :model-value="selectedTeamLabel" disabled />
                <SearchSelect
                    v-else
                    v-model="form.security_team_id"
                    :options="filteredTeams"
                    placeholder="Pilih regu..."
                    search-placeholder="Cari regu..."
                />
                <InputError :messages="form.errors?.security_team_id" />
            </div>

            <div>
                <InputLabel value="Bulan" :required="true" />
                <Select
                    v-model="form.month"
                    :options="monthOptions"
                    :disabled="hasSchedules"
                />
                <InputError :messages="form.errors?.month" />
            </div>

            <div>
                <InputLabel value="Tahun" :required="true" />
                <TextInput
                    v-model.number="form.year"
                    type="number"
                    min="2000"
                    max="2100"
                    :disabled="hasSchedules"
                />
                <InputError :messages="form.errors?.year" />
            </div>

            <div>
                <InputLabel value="Nama Periode" :required="true" />
                <TextInput id="name" v-model="form.name" placeholder="November 2026" disabled />
                <InputError :messages="form.errors?.name" />
            </div>

            <div>
                <InputLabel value="Tanggal Mulai" :required="true" />
                <TextInput id="start_date" v-model="form.start_date" type="date" :disabled="hasSchedules" />
                <InputError :messages="form.errors?.start_date" />
            </div>

            <div>
                <InputLabel value="Tanggal Selesai" :required="true" />
                <TextInput id="end_date" v-model="form.end_date" type="date" :disabled="hasSchedules" />
                <InputError :messages="form.errors?.end_date" />
            </div>

            <div>
                <InputLabel value="Status" :required="true" />
                <Select v-model="form.status" :options="statusOptions" />
                <InputError :messages="form.errors?.status" />
            </div>
        </div>

        <div v-if="hasSchedules" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
            Periode ini sudah memiliki {{ form.schedules_count ?? 0 }} jadwal. Hanya nama dan status periode
            yang dapat diubah; unit, pos, regu, tanggal, dan daftar anggota periode dikunci agar jadwal tetap konsisten.
        </div>
        
<div
    v-if="!hasSchedules"
    class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-gray-50/70 px-4 py-3 lg:flex-row lg:items-center"
>
    <div class="w-full">
        <InputLabel value="Salin dari Periode Sebelumnya" />

        <SearchSelect
            v-model="copyFromId"
            :options="copyOptions"
            placeholder="Pilih periode sumber..."
            none-label="Tidak menyalin"
            search-placeholder="Cari periode..."
        />

        <p class="mt-1 text-xs text-gray-500">
            Hanya daftar anggota periode, pola, dan offset yang disalin. Jadwal tidak disalin.
        </p>
    </div>

    <div class="flex shrink-0 items-center gap-2 lg:ml-auto">
        <button
            type="button"
            class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="!copyFromId"
            @click="copyMembers"
        >
            Salin Anggota
        </button>

        <button
            type="button"
            class="inline-flex items-center justify-center rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-700 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="!form.security_team_id || !form.start_date"
            @click="loadActiveMembers"
        >
            Muat Anggota Aktif
        </button>
    </div>
</div>

        <div class="rounded-lg border border-gray-200">
            <div class="flex flex-col gap-2 border-b border-gray-200 bg-gray-50/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-800">Daftar Anggota Periode</p>
                    <p class="mt-0.5 text-xs text-gray-500">
                        {{ members.length }} anggota. Daftar anggota periode ini tetap tersimpan meskipun keanggotaan pada data regu berubah.
                    </p>
                </div>
                <button
                    v-if="!hasSchedules"
                    type="button"
                    class="inline-flex items-center justify-center rounded-md bg-blue-600 px-3 py-1.5 text-xs text-white transition hover:bg-blue-700"
                    @click="addMember"
                >
                    + Tambah Anggota
                </button>
            </div>

            <div v-if="members.length === 0" class="px-4 py-8 text-center text-sm text-gray-500">
                Belum ada anggota. Pilih regu dan tanggal mulai, lalu muat anggota aktif atau tambah manual.
            </div>

            <div v-else class="divide-y divide-gray-100">
                <div
                    v-for="(row, index) in members"
                    :key="index"
                    class="grid gap-3 px-4 py-3 md:grid-cols-2 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1.3fr)_160px_48px] lg:items-start"
                >
                    <div>
                        <InputLabel :value="`Anggota ${index + 1}`" />
                        <SearchSelect
                            v-model="row.security_id"
                            :options="securityOptionsFor(index)"
                            placeholder="Pilih satpam..."
                            search-placeholder="Cari nama satpam..."
                            :disabled="hasSchedules"
                        />
                        <InputError :messages="form.errors['members.' + index + '.security_id']" />
                    </div>

                    <div>
                        <InputLabel value="Pola Rotasi" />
                        <Select
                            :model-value="row.schedule_pattern_id === null ? '' : String(row.schedule_pattern_id)"
                            :options="patternOptions"
                            :disabled="hasSchedules"
                            @update:model-value="setPattern(row, $event)"
                        />
                        <InputError :messages="form.errors['members.' + index + '.schedule_pattern_id']" />
                    </div>

                    <div>
                        <InputLabel value="Mulai dari Hari Rotasi" />
                        <TextInput
                            :model-value="rotationDayFor(row)"
                            type="number"
                            min="1"
                            :max="cycleLengthFor(row) || undefined"
                            :disabled="hasSchedules || !row.schedule_pattern_id"
                            @update:model-value="updateRotationDay(row, $event)"
                        />
                        <InputError :messages="form.errors['members.' + index + '.start_offset']" />
                        <p v-if="row.schedule_pattern_id && form.start_date" class="mt-1 text-xs text-gray-500">
                            {{ formatPeriodDate(form.start_date) }} — Hari Rotasi {{ rotationDayFor(row) }} — Shift
                            {{ firstShiftFor(row)?.shift_name ?? 'Belum tersedia' }}
                        </p>
                    </div>

                    <div class="lg:pt-6">
                        <button
                            v-if="!hasSchedules"
                            type="button"
                            class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-red-600 transition hover:bg-red-50 hover:text-red-700"
                            @click="removeMember(index)"
                        >
                            Hapus
                        </button>
                    </div>
                </div>
            </div>

            <InputError :messages="form.errors?.members" class="px-4 pb-3" />
        </div>

        <div class="rounded-lg border border-blue-100 bg-blue-50/50">
            <div class="border-b border-blue-100 px-4 py-3">
                <p class="text-sm font-semibold text-gray-800">Preview Daftar Anggota</p>
                <p class="mt-0.5 text-xs text-gray-500">Anggota yang akan disimpan pada periode ini.</p>
            </div>
            <div class="max-h-72 overflow-y-auto px-4 py-3">
                <div v-if="preview.length === 0" class="py-4 text-center text-sm text-gray-500">
                    Belum ada anggota pada preview.
                </div>
                <div
                    v-for="item in preview"
                    :key="item.index"
                    class="flex flex-col gap-1 border-b border-blue-100/60 py-2 last:border-0 sm:flex-row sm:items-center sm:gap-3"
                >
                    <span class="w-20 shrink-0 text-xs font-medium text-gray-500">Anggota {{ item.index }}</span>
                    <span class="text-sm text-gray-700">{{ item.security }}</span>
                    <span class="text-xs text-gray-500 sm:ml-auto">{{ item.pattern }}</span>
                    <span v-if="item.rotationDay !== '-'" class="text-xs text-gray-400">
                        Mulai Rotasi Hari {{ item.rotationDay }} — {{ item.firstShift }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
