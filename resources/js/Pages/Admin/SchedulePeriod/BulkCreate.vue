<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import InputLabel from '@/Components/Admin/InputLabel.vue';
import InputError from '@/Components/Admin/InputError.vue';
import TextInput from '@/Components/Admin/TextInput.vue';
import SearchSelect from '@/Components/Admin/SearchSelect.vue';
import Select from '@/Components/Admin/Select.vue';

const props = defineProps({
    units: { type: Array, default: () => [] },
    posts: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    patterns: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    today: { type: String, default: '' },
});

const page = usePage();
const flash = computed(() => page.props.flash || {});

const statusOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'active', label: 'Aktif' },
    { value: 'completed', label: 'Selesai' },
];

const initialDate = props.filters.start_date || props.today || '';
const initialMonth = Number(initialDate.slice(5, 7)) || 1;
const initialYear = Number(initialDate.slice(0, 4)) || 2000;

const form = useForm({
    unit_id: props.filters.unit_id || (props.units.length === 1 ? props.units[0].value : null),
    security_post_id: props.filters.security_post_id || null,
    month: initialMonth,
    year: initialYear,
    name: '',
    start_date: props.filters.start_date || '',
    end_date: '',
    status: 'draft',
    teams: [],
});

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

function pad(value) {
    return String(value).padStart(2, '0');
}

function daysInMonth(year, month) {
    if (month === 2) {
        const leap = (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0;
        return leap ? 29 : 28;
    }

    return [4, 6, 9, 11].includes(month) ? 30 : 31;
}

function syncPeriodDates() {
    const month = Number(form.month || 1);
    const year = Number(form.year || initialYear);
    const monthLabel = monthOptions.find((item) => Number(item.value) === month)?.label || '';
    form.name = `${monthLabel} ${year}`;
    form.start_date = `${year}-${pad(month)}-01`;
    form.end_date = `${year}-${pad(month)}-${pad(daysInMonth(year, month))}`;
}

watch([() => form.month, () => form.year], syncPeriodDates, { immediate: true });

const selectedTeamIds = ref([]);
const expandedTeams = ref([]);
const lastLoadedUnit = ref(props.filters.unit_id ?? '');
const lastLoadedPost = ref(props.filters.security_post_id ?? '');
const lastLoadedDate = ref(props.filters.start_date ?? '');

const filteredPosts = computed(() => props.posts);
const filteredPatterns = computed(() => props.patterns);

const selectedTeams = computed(() =>
    props.teams.filter((team) => selectedTeamIds.value.includes(Number(team.value)))
);

const selectedCount = computed(() => selectedTeamIds.value.length);

function postOptions() {
    return (props.posts || []).map((post) => ({
        value: String(post.value),
        label: post.label,
    }));
}

function teamOptions() {
    return (props.teams || []).map((team) => ({
        value: String(team.value),
        label: team.label,
    }));
}

function patternOptions() {
    return [
        { value: '', label: 'Tanpa pola' },
        ...filteredPatterns.value.map((pattern) => ({
            value: String(pattern.value),
            label: pattern.label,
        })),
    ];
}

function patternById(id) {
    return filteredPatterns.value.find((item) => String(item.value) === String(id)) || null;
}

function cycleLengthFor(patternId) {
    return Number(patternById(patternId)?.cycle_length || 0);
}

function firstShiftName(patternId, rotationDay) {
    const pattern = patternById(patternId);
    if (!pattern) return '-';
    const detail = (pattern.details || []).find((item) => Number(item.day_order) === Number(rotationDay));
    return detail?.shift_name || 'Belum tersedia';
}

function isActiveMembership(membership, date) {
    if (!date) return false;
    if (membership.joined_at && membership.joined_at > date) return false;
    return !membership.left_at || membership.left_at >= date;
}

function activeMembersFor(team, date) {
    return (team.memberships || [])
        .filter((membership) => isActiveMembership(membership, date))
        .map((membership) => ({
            security_id: String(membership.security_id),
            full_name: membership.full_name || '-',
            registration_number: membership.registration_number || '',
            schedule_pattern_id: '',
            rotation_day: 1,
            start_offset: 0,
        }));
}

function ensureTeamState(team) {
    const existing = form.teams.find((item) => Number(item.security_team_id) === Number(team.value));
    if (!existing) {
        form.teams.push({
            security_team_id: Number(team.value),
            members: activeMembersFor(team, form.start_date),
        });
    }
}

function removeTeamState(teamId) {
    form.teams = form.teams.filter((item) => Number(item.security_team_id) !== Number(teamId));
}

function teamState(teamId) {
    return form.teams.find((item) => Number(item.security_team_id) === Number(teamId)) || null;
}

function toggleTeam(team) {
    const id = Number(team.value);
    if (selectedTeamIds.value.includes(id)) {
        selectedTeamIds.value = selectedTeamIds.value.filter((item) => item !== id);
        removeTeamState(id);
    } else {
        selectedTeamIds.value = [...selectedTeamIds.value, id];
        ensureTeamState(team);
        if (!expandedTeams.value.includes(id)) expandedTeams.value.push(id);
    }
}

function selectAllTeams() {
    selectedTeamIds.value = props.teams.map((team) => Number(team.value));
    props.teams.forEach((team) => ensureTeamState(team));
    expandedTeams.value = [...selectedTeamIds.value];
}

function clearAllTeams() {
    selectedTeamIds.value = [];
    form.teams = [];
    expandedTeams.value = [];
}

function toggleExpand(teamId) {
    const id = Number(teamId);
    if (expandedTeams.value.includes(id)) {
        expandedTeams.value = expandedTeams.value.filter((item) => item !== id);
    } else {
        expandedTeams.value.push(id);
    }
}

function setPattern(member, value) {
    member.schedule_pattern_id = value;
    if (!value) {
        member.rotation_day = 1;
        member.start_offset = 0;
        return;
    }
    const cycle = cycleLengthFor(value);
    let day = Number(member.rotation_day || 1);
    if (!Number.isFinite(day) || day < 1) day = 1;
    if (cycle > 0 && day > cycle) day = 1;
    member.rotation_day = day;
    member.start_offset = day - 1;
}

function updateRotationDay(member, value) {
    const cycle = cycleLengthFor(member.schedule_pattern_id);
    let day = Number(value);
    if (!Number.isFinite(day) || day < 1) day = 1;
    if (cycle > 0 && day > cycle) day = cycle;
    member.rotation_day = day;
    member.start_offset = day - 1;
}

function submit() {
    form.transform((data) => ({
        unit_id: data.unit_id ? Number(data.unit_id) : null,
        security_post_id: data.security_post_id ? Number(data.security_post_id) : null,
        name: data.name,
        start_date: data.start_date,
        end_date: data.end_date,
        status: data.status,
        teams: (data.teams || [])
            .filter((team) => selectedTeamIds.value.includes(Number(team.security_team_id)))
            .map((team) => ({
                security_team_id: Number(team.security_team_id),
                members: (team.members || []).map((member) => ({
                    security_id: member.security_id ? Number(member.security_id) : null,
                    schedule_pattern_id: member.schedule_pattern_id ? Number(member.schedule_pattern_id) : null,
                    rotation_day: Number(member.rotation_day || 1),
                })),
            })),
    })).post('/admin/schedule-period/bulk');
}

function reloadDependent() {
    const unitId = form.unit_id || '';
    const postId = form.security_post_id || '';
    const date = form.start_date || '';
    if (String(unitId) === String(lastLoadedUnit.value) && String(postId) === String(lastLoadedPost.value) && String(date) === String(lastLoadedDate.value)) {
        return;
    }
    lastLoadedUnit.value = unitId;
    lastLoadedPost.value = postId;
    lastLoadedDate.value = date;

    // Reset selections when dependent filters change
    selectedTeamIds.value = [];
    form.teams = [];
    expandedTeams.value = [];

    if (!unitId) {
        return;
    }

    const query = { unit_id: unitId };
    if (postId) query.security_post_id = postId;
    if (date) query.start_date = date;

    window.location.assign(`/admin/schedule-period/bulk-create?${new URLSearchParams(query).toString()}`);
}

watch(() => form.unit_id, () => {
    form.security_post_id = null;
    selectedTeamIds.value = [];
    form.teams = [];
    expandedTeams.value = [];
    reloadDependent();
});

watch(() => form.security_post_id, () => {
    selectedTeamIds.value = [];
    form.teams = [];
    expandedTeams.value = [];
    reloadDependent();
});

watch(() => form.start_date, () => {
    selectedTeamIds.value.forEach((id) => {
        const team = props.teams.find((item) => Number(item.value) === Number(id));
        if (!team) return;
        const state = teamState(id);
        if (state) state.members = activeMembersFor(team, form.start_date);
    });
    reloadDependent();
});

watch(selectedTeams, (teams) => {
    teams.forEach((team) => ensureTeamState(team));
}, { immediate: true });
</script>

<template>
    <AdminLayout title="Atur Jadwal per Pos">
        <div class="space-y-6">
            <PageHeader
                title="Atur Jadwal per Pos"
                description="Buat periode jadwal untuk beberapa regu pada satu pos jaga sekaligus."
                :breadcrumb="[
                    { label: 'Periode Jadwal', href: '/admin/schedule-period' },
                    { label: 'Atur Jadwal per Pos' },
                ]"
            />

            <FlashMessage />

            <div v-if="flash.bulk_created" class="rounded-lg border border-green-200 bg-green-50 p-4">
                <h3 class="text-sm font-semibold text-green-800">
                    {{ flash.bulk_created.length }} periode jadwal berhasil dibuat
                </h3>
                <ul class="mt-3 space-y-2">
                    <li
                        v-for="period in flash.bulk_created"
                        :key="period.id"
                        class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-green-100 bg-white px-3 py-2"
                    >
                        <div class="text-xs text-gray-700">
                            <span class="font-semibold">{{ period.team_name }}</span>
                            <span class="mx-1">Â·</span>
                            <span>{{ period.name }}</span>
                            <span class="mx-1">Â·</span>
                            <span>{{ period.start_date }} s/d {{ period.end_date }}</span>
                            <span class="mx-1">Â·</span>
                            <span>{{ period.status_label }}</span>
                        </div>
                        <Link
                            :href="period.show_url"
                            class="text-xs font-medium text-blue-600 hover:text-blue-800"
                        >
                            Lihat detail
                        </Link>
                    </li>
                </ul>
            </div>

              <Card>
                <form @submit.prevent="submit">
<div v-if="props.units.length > 0 && !form.unit_id" class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    Pilih unit terlebih dahulu untuk memuat pos jaga, regu, dan pola rotasi.
                </div>

                <div class="space-y-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Unit" :required="true" />
                            <SearchSelect
                                v-model="form.unit_id"
                                :options="props.units.map((item) => ({ value: String(item.value), label: item.label }))"
                                placeholder="Pilih unit..."
                                search-placeholder="Cari unit..."
                            />
                            <InputError :messages="form.errors?.unit_id" />
                        </div>

                        <div>
                            <InputLabel value="Pos Jaga" :required="true" />
                            <SearchSelect
                                v-model="form.security_post_id"
                                :options="postOptions()"
                                placeholder="Pilih pos jaga..."
                                search-placeholder="Cari pos..."
                                :disabled="!form.unit_id"
                            />
                            <InputError :messages="form.errors?.security_post_id" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Bulan" :required="true" />
                            <Select v-model="form.month" :options="monthOptions" />
                            <InputError :messages="form.errors?.month" />
                        </div>
                        <div>
                            <InputLabel value="Tahun" :required="true" />
                            <TextInput v-model.number="form.year" type="number" min="2000" max="2100" />
                            <InputError :messages="form.errors?.year" />
                        </div>
                        <div>
                            <InputLabel value="Status Awal" :required="true" />
                            <Select v-model="form.status" :options="statusOptions" />
                            <InputError :messages="form.errors?.status" />
                        </div>
                    </div>
                    <div class="rounded-md border border-blue-100 bg-blue-50/50 px-4 py-3 text-xs text-blue-800">
                        Ringkasan periode: {{ form.name }} ({{ form.start_date }} s/d {{ form.end_date }})
                    </div>

                    <div v-if="form.unit_id && form.security_post_id" class="space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold text-gray-800">
                                Regu Aktif pada Pos Jaga ({{ props.teams.length }})
                            </h3>
                            <div class="flex items-center gap-3 text-xs">
                                <span class="text-gray-500">
                                    Dipilih: {{ selectedCount }}
                                </span>
                                <button type="button" class="text-blue-600 hover:text-blue-800" @click="selectAllTeams">
                                    Pilih semua
                                </button>
                                <button type="button" class="text-gray-500 hover:text-gray-700" @click="clearAllTeams">
                                    Hapus semua
                                </button>
                            </div>
                        </div>

                        <div v-if="props.teams.length === 0" class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500">
                            Tidak ada regu aktif pada pos jaga ini.
                        </div>

                        <div class="space-y-2">
                            <label
                                v-for="team in props.teams"
                                :key="team.value"
                                class="flex cursor-pointer items-center gap-3 rounded-md border border-gray-200 px-3 py-2 hover:bg-gray-50"
                            >
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-blue-600"
                                    :checked="selectedTeamIds.includes(Number(team.value))"
                                    @change="toggleTeam(team)"
                                />
                                <span class="text-sm text-gray-800">{{ team.label }}</span>
                            </label>
                        </div>
                    </div>

                    <div v-if="selectedTeams.length > 0" class="space-y-4">
                        <h3 class="text-sm font-semibold text-gray-800">Pengaturan Anggota per Regu</h3>

                        <div
                            v-for="team in selectedTeams"
                            :key="team.value"
                            class="rounded-lg border border-gray-200"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center justify-between px-4 py-3 text-left"
                                @click="toggleExpand(team.value)"
                            >
                                <span class="text-sm font-semibold text-gray-800">{{ team.label }}</span>
                                <span class="text-xs text-gray-500">
                                    {{ (teamState(team.value)?.members || []).length }} anggota
                                </span>
                            </button>

                            <div v-if="expandedTeams.includes(Number(team.value))" class="border-t border-gray-100 p-4 space-y-3">
                                <InputError :messages="form.errors?.[`teams.${selectedTeams.findIndex(t => Number(t.value) === Number(team.value))}.security_team_id`]" />

                                <div
                                    v-for="(member, memberIndex) in (teamState(team.value)?.members || [])"
                                    :key="member.security_id + '-' + memberIndex"
                                    class="grid gap-3 rounded-md border border-gray-100 p-3 sm:grid-cols-4"
                                >
                                    <div class="sm:col-span-1">
                                        <InputLabel value="Anggota" />
                                        <TextInput :model-value="member.full_name" disabled />
                                    </div>

                                    <div>
                                        <InputLabel value="Pola Rotasi" />
                                        <Select
                                            v-model="member.schedule_pattern_id"
                                            :options="patternOptions()"
                                            @update:model-value="(value) => setPattern(member, value)"
                                        />
                                        <InputError :messages="form.errors?.[`teams.${selectedTeams.findIndex(t => Number(t.value) === Number(team.value))}.members.${memberIndex}.schedule_pattern_id`]" />
                                    </div>

                                    <div>
                                        <InputLabel value="Hari Rotasi Awal" />
                                        <TextInput
                                            v-model.number="member.rotation_day"
                                            type="number"
                                            min="1"
                                            :max="cycleLengthFor(member.schedule_pattern_id) || undefined"
                                            :disabled="!member.schedule_pattern_id"
                                            @update:model-value="(value) => updateRotationDay(member, value)"
                                        />
                                        <InputError :messages="form.errors?.[`teams.${selectedTeams.findIndex(t => Number(t.value) === Number(team.value))}.members.${memberIndex}.start_offset`]" />
                                    </div>

                                    <div>
                                        <InputLabel value="Preview Shift Pertama" />
                                        <TextInput
                                            :model-value="member.schedule_pattern_id ? firstShiftName(member.schedule_pattern_id, member.rotation_day) : '-'"
                                            disabled
                                        />
                                    </div>
                                </div>

                                <div
                                    v-if="(teamState(team.value)?.members || []).length === 0"
                                    class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500"
                                >
                                    Tidak ada anggota aktif pada tanggal mulai periode.
                                </div>
                            </div>
                        </div>
                    </div>

                    <InputError :messages="form.errors?.teams" />
                    <InputError :messages="form.errors?.start_date" v-if="form.errors?.start_date && selectedTeams.length > 0" />

                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <Link
                            href="/admin/schedule-period"
                            class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50"
                        >
                            Batal
                        </Link>
                        <Button :disabled="form.processing || selectedTeams.length === 0" type="submit">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                        </Button>
                    </div>
                </div>
                </form>
            </Card>
        </div>
    </AdminLayout>
</template>

