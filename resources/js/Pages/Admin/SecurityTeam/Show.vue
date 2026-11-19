<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Badge from '@/Components/Admin/Badge.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import InputLabel from '@/Components/Admin/InputLabel.vue';
import InputError from '@/Components/Admin/InputError.vue';
import TextInput from '@/Components/Admin/TextInput.vue';
import SearchSelect from '@/Components/Admin/SearchSelect.vue';
import SecurityCheckboxItem from '@/Components/Security/SecurityCheckboxItem.vue';

const props = defineProps({
    team: { type: Object, required: true },
    activeMembers: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
    securityOptions: { type: Array, default: () => [] },
    teamOptions: { type: Array, default: () => [] },
    canAddMember: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    postLocked: { type: Boolean, default: false },
});

const memberOpen = ref(false);
const memberMode = ref('add');
const selectedMember = ref(null);

const memberForm = useForm({
    security_ids: [],
    joined_at: today(),
    target_security_team_id: '',
});

const securitySearch = ref('');

const filteredSecurityOptions = computed(() => {
    const q = securitySearch.value.trim().toLowerCase();
    if (!q) return props.securityOptions;
    return props.securityOptions.filter((item) => String(item.label ?? '').toLowerCase().includes(q));
});

const selectedSecurityCount = computed(() => (memberForm.security_ids || []).length);

const endOpen = ref(false);
const endingMember = ref(null);
const endForm = useForm({ left_at: today() });

const details = computed(() => [
    { label: 'Kode Regu', value: props.team.code },
    { label: 'Nama Regu', value: props.team.name },
    { label: 'Unit', value: props.team.unit_name },
    { label: 'Pos Jaga', value: props.team.post_name },
    { label: 'Deskripsi', value: props.team.description || '-' },
    { label: 'Status', value: props.team.status, badge: true },
    { label: 'Anggota Aktif', value: `${props.team.active_members_count} orang` },
    { label: 'Total Riwayat Anggota', value: `${props.team.members_count} baris` },
    { label: 'Periode Jadwal', value: `${props.team.schedule_periods_count} periode` },
    { label: 'Dibuat', value: props.team.created_at },
    { label: 'Diperbarui', value: props.team.updated_at },
]);

const selectedSecurity = computed(
    () => props.securityOptions.find((item) => String(item.value) === String(memberForm.security_id)) ?? null,
);

const currentActiveTeamName = computed(() => {
    if (memberMode.value === 'move') {
        return props.team.name;
    }

    return selectedSecurity.value?.active_team_name ?? null;
});

const willMove = computed(() => {
    const target = memberForm.target_security_team_id;

    if (target !== '' && target !== null && String(target) !== String(props.team.id)) {
        return true;
    }

    if (memberMode.value === 'move') {
        return true;
    }

    return false;
});

const addDisabled = computed(() => {
    if (! memberForm.joined_at) {
        return true;
    }

    if (memberMode.value === 'add' && (memberForm.security_ids || []).length === 0) {
        return true;
    }

    if (memberMode.value === 'move') {
        return ! memberForm.target_security_team_id;
    }

    return false;
});

function today() {
    const now = new Date();
    const local = new Date(now.getTime() - now.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 10);
}

function openAdd() {
    memberMode.value = 'add';
    selectedMember.value = null;
    memberForm.reset();
    memberForm.clearErrors();
    memberForm.joined_at = today();
    memberForm.security_ids = [];
    securitySearch.value = '';
    memberOpen.value = true;
}

function openMove(member) {
    memberMode.value = 'move';
    selectedMember.value = member;
    memberForm.reset();
    memberForm.clearErrors();
    memberForm.security_id = member.security_id;
    memberForm.joined_at = today();
    memberOpen.value = true;
}

function closeMemberModal() {
    memberOpen.value = false;
}

function submitMember() {
    memberForm.post(`/admin/security-team/${props.team.id}/member`, {
        preserveScroll: true,
        onSuccess: closeMemberModal,
    });
}

function openEnd(member) {
    endingMember.value = member;
    endForm.reset();
    endForm.clearErrors();
    endForm.left_at = today();
    endOpen.value = true;
}

function closeEndModal() {
    endOpen.value = false;
}

function submitEnd() {
    endForm.put(`/admin/security-team/${props.team.id}/member/${endingMember.value.id}/end`, {
        preserveScroll: true,
        onSuccess: closeEndModal,
    });
}

function toggleActive() {
    const action = props.team.is_active ? 'Nonaktifkan' : 'Aktifkan';
    const note = props.team.is_active
        ? ' Regu yang nonaktif tidak dapat menerima anggota baru atau digunakan untuk periode jadwal baru, tetapi riwayat dan jadwal lama tidak berubah.'
        : '';

    if (confirm(`${action} regu "${props.team.name}"?${note}`)) {
        router.patch(`/admin/security-team/${props.team.id}/toggle`, {}, { preserveScroll: true });
    }
}

function destroy() {
    if (confirm(`Hapus regu "${props.team.name}"? Regu yang sudah memiliki riwayat anggota atau periode jadwal tidak dapat dihapus.`)) {
        router.delete(`/admin/security-team/${props.team.id}`);
    }
}
</script>

<template>
    <AdminLayout title="Detail Regu">
        <div class="space-y-6">
            <PageHeader
                title="Data Regu"
                :breadcrumb="[
                    { label: 'Data Regu', href: '/admin/security-team' },
                    { label: 'Detail' },
                ]"
            >
                <template #actions>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-xs text-white transition"
                        :class="props.team.is_active
                            ? 'bg-amber-500 hover:bg-amber-600'
                            : 'bg-emerald-600 hover:bg-emerald-700'"
                        @click="toggleActive"
                    >
                        {{ props.team.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-xs text-white transition"
                        :class="props.canDelete
                            ? 'bg-red-600 hover:bg-red-700'
                            : 'cursor-not-allowed bg-gray-300 text-gray-500'"
                        :disabled="!props.canDelete"
                        :title="props.canDelete
                            ? 'Hapus regu'
                            : 'Regu sudah memiliki riwayat anggota atau periode jadwal sehingga tidak dapat dihapus. Nonaktifkan saja.'"
                        @click="props.canDelete && destroy()"
                    >
                        Hapus
                    </button>
                    <Link
                        :href="`/admin/security-team/${props.team.id}/edit`"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-xs text-white transition hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                    >
                        Edit
                    </Link>
                    <Link
                        href="/admin/security-team"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    >
                        Kembali
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <div
                v-if="!props.team.is_active"
                class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                role="alert"
            >
                Regu ini nonaktif. Regu tidak dapat menerima anggota baru atau digunakan untuk periode jadwal baru.
                Riwayat keanggotaan dan jadwal lama tetap tersimpan.
            </div>

            <Card>
                <h2 class="text-sm font-semibold text-gray-800">Informasi Regu</h2>
                <table class="mt-4 w-full text-left">
                    <tbody>
                        <tr
                            v-for="item in details"
                            :key="item.label"
                            class="border-b border-gray-100 last:border-0"
                        >
                            <th class="w-56 py-2 pr-4 align-top text-xs font-medium text-gray-900">
                                {{ item.label }}
                            </th>
                            <td class="py-2 text-sm text-gray-700">
                                <Badge
                                    v-if="item.badge"
                                    :variant="props.team.is_active ? 'emerald' : 'gray'"
                                >
                                    {{ item.value }}
                                </Badge>
                                <template v-else>{{ item.value }}</template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </Card>

            <Card>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-800">Anggota Aktif</h2>
                        <p class="mt-0.5 text-xs text-gray-500">
                            Anggota dengan keanggotaan yang masih berjalan (belum memiliki tanggal berakhir).
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            v-if="!props.canAddMember"
                            class="text-xs text-gray-500"
                        >
                            Regu nonaktif — tidak dapat menerima anggota baru.
                        </span>
                        <Button :disabled="!props.canAddMember" @click="openAdd">
                            + Tambah Anggota
                        </Button>
                    </div>
                </div>

                <div class="mt-4">
                    <DataTable
                        :has-rows="activeMembers.length > 0"
                        empty-title="Belum ada anggota aktif"
                        empty-description="Tambahkan anggota melalui tombol Tambah Anggota."
                    >
                        <template #head>
                            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Nama</th>
                            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">NIK</th>
                            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Unit</th>
                            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Bergabung</th>
                            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Jadwal</th>
                            <th class="px-2 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Aksi</th>
                        </template>

                        <tr v-for="member in activeMembers" :key="member.id">
                            <td class="px-2 py-3.5 text-sm text-gray-700">{{ member.full_name }}</td>
                            <td class="px-2 py-3.5 text-sm text-gray-600">{{ member.registration_number ?? '-' }}</td>
                            <td class="px-2 py-3.5 text-sm text-gray-600">{{ member.unit_name }}</td>
                            <td class="px-2 py-3.5 text-sm text-gray-600">{{ member.joined_at }}</td>
                            <td class="px-2 py-3.5">
                                <Badge v-if="member.has_running_schedule" variant="amber">Ada jadwal draft/aktif</Badge>
                                <span v-else class="text-xs text-gray-400">-</span>
                            </td>
                            <td class="px-2 py-3.5 text-center">
                                <div class="flex flex-wrap items-center justify-center gap-1">
                                    <button
                                        type="button"
                                        class="rounded-md px-2 py-1 text-xs text-blue-600 transition hover:bg-blue-50 hover:text-blue-700 disabled:cursor-not-allowed disabled:text-gray-300"
                                        :disabled="teamOptions.length === 0"
                                        :title="teamOptions.length === 0
                                            ? 'Tidak ada regu tujuan aktif lain.'
                                            : 'Pindahkan anggota ke regu lain'"
                                        @click="openMove(member)"
                                    >
                                        Pindah
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md px-2 py-1 text-xs text-red-600 transition hover:bg-red-50 hover:text-red-700"
                                        @click="openEnd(member)"
                                    >
                                        Akhiri
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </DataTable>
                </div>
            </Card>

            <Card>
                <div>
                    <h2 class="text-sm font-semibold text-gray-800">Riwayat Keanggotaan</h2>
                    <p class="mt-0.5 text-xs text-gray-500">
                        Seluruh baris keanggotaan, termasuk yang sudah berakhir. Riwayat tidak dihapus.
                    </p>
                </div>

                <div class="mt-4">
                    <DataTable
                        :has-rows="history.length > 0"
                        empty-title="Belum ada riwayat keanggotaan"
                        empty-description="Riwayat akan muncul setelah anggota ditambahkan."
                    >
                        <template #head>
                            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Nama</th>
                            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Unit</th>
                            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Bergabung</th>
                            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Berakhir</th>
                            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Jadwal</th>
                        </template>

                        <tr v-for="member in history" :key="member.id">
                            <td class="px-2 py-3.5 text-sm text-gray-700">{{ member.full_name }}</td>
                            <td class="px-2 py-3.5 text-sm text-gray-600">{{ member.unit_name }}</td>
                            <td class="px-2 py-3.5 text-sm text-gray-600">{{ member.joined_at }}</td>
                            <td class="px-2 py-3.5 text-sm text-gray-600">{{ member.left_at ?? '-' }}</td>
                            <td class="px-2 py-3.5">
                                <Badge :variant="member.is_active ? 'emerald' : 'gray'">
                                    {{ member.is_active ? 'Aktif' : 'Berakhir' }}
                                </Badge>
                            </td>
                            <td class="px-2 py-3.5">
                                <Badge v-if="member.has_running_schedule" variant="amber">Ada jadwal draft/aktif</Badge>
                                <span v-else class="text-xs text-gray-400">-</span>
                            </td>
                        </tr>
                    </DataTable>
                </div>
            </Card>
        </div>

        <div
            v-if="memberOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4"
            @click.self="closeMemberModal"
            @keydown.esc="closeMemberModal"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="member-modal-title"
                class="w-full max-w-lg rounded-lg bg-white p-5 shadow-xl"
            >
                <h3 id="member-modal-title" class="text-sm font-semibold text-gray-800">
                    {{ memberMode === 'move' ? 'Pindahkan Anggota' : 'Tambah Anggota Regu' }}
                </h3>
                <p class="mt-1 text-xs text-gray-500">
                    {{ memberMode === 'move'
                        ? `Pindahkan ${selectedMember?.full_name} dari ${props.team.name}.`
                        : `Tambahkan satpam ke ${props.team.name}.` }}
                </p>

                <div class="mt-4 space-y-4">
                    <div v-if="memberMode === 'add'">
                        <InputLabel value="Satpam" :required="true" />
                        <TextInput v-model="securitySearch" placeholder="Cari nama satpam..." />
                        <div class="mt-2 max-h-64 space-y-2 overflow-y-auto rounded-md border border-gray-200 p-2">
                            <SecurityCheckboxItem
                                v-for="option in filteredSecurityOptions"
                                :key="option.value"
                                :label="option.label"
                                :unit-name="option.unit_name"
                                :checked="(memberForm.security_ids || []).includes(Number(option.value))"
                                :disabled="!option.eligible"
                                :disabled-reason="option.disabled_reason"
                                @update:checked="(checked) => {
                                    const id = Number(option.value);
                                    const ids = memberForm.security_ids || [];
                                    if (checked) {
                                        if (!ids.includes(id)) memberForm.security_ids = [...ids, id];
                                    } else {
                                        memberForm.security_ids = ids.filter((item) => item !== id);
                                    }
                                }"
                            />
                            <p v-if="filteredSecurityOptions.length === 0" class="px-2 py-4 text-center text-xs text-gray-500">
                                Satpam tidak ditemukan.
                            </p>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            Ditemukan: {{ filteredSecurityOptions.length }} | Dipilih: {{ selectedSecurityCount }}
                        </p>
                        <InputError :messages="memberForm.errors?.security_id" />
                        <InputError :messages="memberForm.errors?.security_ids" />
                    </div>

                    <div v-else>
                        <InputLabel value="Satpam" />
                        <TextInput :model-value="selectedMember?.full_name ?? '-'" disabled />
                        <InputError :messages="memberForm.errors?.security_id" />
                    </div>

                    <div>
                        <InputLabel value="Tanggal Bergabung" :required="true" />
                        <TextInput v-model="memberForm.joined_at" type="date" />
                        <p class="mt-1 text-xs text-gray-500">
                            Jika satpam masih aktif di regu lain, keanggotaan lama ditutup pada H-1 tanggal ini.
                        </p>
                        <InputError :messages="memberForm.errors?.joined_at" />
                    </div>

                    <div>
                        <InputLabel
                            :value="memberMode === 'move' ? 'Regu Tujuan' : 'Regu Tujuan (opsional)'"
                            :required="memberMode === 'move'"
                        />
                        <SearchSelect
                            v-model="memberForm.target_security_team_id"
                            :options="teamOptions"
                            :placeholder="memberMode === 'move' ? 'Pilih regu tujuan...' : 'Tetap di regu ini'"
                            :none-label="memberMode === 'move' ? '' : 'Tetap di regu ini'"
                            search-placeholder="Cari regu..."
                        />
                        <p v-if="teamOptions.length === 0" class="mt-1 text-xs text-amber-700">
                            Tidak ada regu aktif lain yang dapat menjadi tujuan.
                        </p>
                        <InputError :messages="memberForm.errors?.target_security_team_id" />
                    </div>

                    <div
                        v-if="willMove"
                        class="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-700"
                    >
                        Anggota akan dipindahkan. Keanggotaan pada regu lama ditutup, bukan dihapus.
                    </div>

                    <div
                        v-if="memberMode === 'move' && selectedMember?.has_running_schedule"
                        class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800"
                        role="alert"
                    >
                        Satpam ini masih memiliki jadwal pada periode draft/aktif. Jadwal dan snapshot periode tidak
                        diubah oleh perpindahan regu.
                    </div>

                    <div
                        v-else-if="memberMode === 'add' && selectedSecurity?.active_team_id
                            && String(selectedSecurity.active_team_id) === String(props.team.id)"
                        class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800"
                        role="alert"
                    >
                        Satpam ini masih aktif di regu ini. Menambahkan tanggal bergabung baru akan menutup
                        keanggotaan lama dan membuat baris keanggotaan baru.
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <Button variant="secondary" :disabled="memberForm.processing" @click="closeMemberModal">
                        Batal
                    </Button>
                    <Button :disabled="addDisabled || memberForm.processing" @click="submitMember">
                        {{ memberForm.processing ? 'Menyimpan...' : 'Simpan' }}
                    </Button>
                </div>
            </div>
        </div>

        <div
            v-if="endOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4"
            @click.self="closeEndModal"
            @keydown.esc="closeEndModal"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="end-modal-title"
                class="w-full max-w-md rounded-lg bg-white p-5 shadow-xl"
            >
                <h3 id="end-modal-title" class="text-sm font-semibold text-gray-800">Akhiri Keanggotaan</h3>
                <p class="mt-1 text-xs text-gray-500">
                    {{ endingMember?.full_name }} akan ditandai berakhir pada tanggal yang dipilih. Riwayat dan jadwal
                    lama tidak dihapus.
                </p>

                <div class="mt-4 space-y-4">
                    <div>
                        <InputLabel value="Tanggal Berakhir" :required="true" />
                        <TextInput
                            v-model="endForm.left_at"
                            type="date"
                            :min="endingMember?.joined_at"
                        />
                        <InputError :messages="endForm.errors?.left_at" />
                    </div>

                    <div
                        v-if="endingMember?.has_running_schedule"
                        class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800"
                        role="alert"
                    >
                        Satpam ini masih memiliki jadwal pada periode draft/aktif. Mengakhiri keanggotaan tidak
                        mengubah jadwal atau snapshot periode.
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <Button variant="secondary" :disabled="endForm.processing" @click="closeEndModal">
                        Batal
                    </Button>
                    <Button
                        variant="danger"
                        :disabled="!endForm.left_at || endForm.processing"
                        @click="submitEnd"
                    >
                        {{ endForm.processing ? 'Menyimpan...' : 'Akhiri Keanggotaan' }}
                    </Button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
