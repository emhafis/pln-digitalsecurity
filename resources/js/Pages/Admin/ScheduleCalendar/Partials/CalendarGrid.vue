<script setup>
defineProps({
  days: { type: Array, default: () => [] },
  leadingBlanks: { type: Number, default: 0 },
  rowsByDate: { type: Object, default: () => ({}) },
});

defineEmits(['select']);

const weekdayLabels = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
</script>

<template>
  <div class="overflow-x-auto">
    <div class="min-w-[880px]">
      <div class="grid grid-cols-7 gap-1.5">
        <div v-for="label in weekdayLabels" :key="label"
          class="rounded-md bg-gray-100 px-2 py-1.5 text-center text-[11px] font-semibold uppercase tracking-wider text-gray-500">
          {{ label }}
        </div>

        <div v-for="blank in leadingBlanks" :key="`blank-${blank}`" class="rounded-md bg-gray-50/40" />

        <div v-for="day in days" :key="day.date" class="min-h-[128px] rounded-md border border-gray-200 p-1.5"
          :class="day.is_weekend ? 'bg-gray-50/70' : 'bg-white'">
          <div class="mb-1 flex items-center justify-between px-0.5">
            <span
              class="inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[11px] font-semibold"
              :class="day.is_today ? 'bg-blue-600 text-white' : 'text-gray-600'">
              {{ day.day }}
            </span>
            <span class="text-[10px] text-gray-400">{{ day.weekday }}</span>
          </div>

          <div class="max-h-40 space-y-1 overflow-y-auto scrollbar-hide">
            <button v-for="row in (rowsByDate[day.date] || [])" :key="row.id" type="button"
              class="block w-full rounded bg-gray-50/80 px-1.5 py-1 text-left transition hover:bg-blue-50"
              :style="{ borderLeftColor: row.shift_color || '#94a3b8' }" @click="$emit('select', row)">
              <span class="flex items-center gap-1">
                <span class="min-w-0 flex-1 truncate text-[11px] font-medium text-gray-800">
                  {{ row.full_name }}
                </span>
                <span class="shrink-0 rounded-full px-1 text-[9px] font-semibold"
                  :class="row.source === 'adjustment' ? 'bg-amber-100 text-amber-700' : ''"
                  :title="row.source === 'adjustment' ? 'Penyesuaian' : 'Dihasilkan Sistem'">
                  {{ row.source === 'adjustment' ? 'Penyesuaian' : '' }}
                </span>
              </span>
              <span class="mt-0.5 flex items-center gap-1 text-[10px] text-gray-600">
                <span class="truncate">{{ row.shift_name ?? '-' }}</span>
                <span v-if="row.is_off"
                  class="shrink-0 rounded bg-red-50 px-1 text-[9px] font-medium text-red-700">
                  Libur
                </span>
              </span>
              <span v-if="row.shift_time" class="block text-[10px] text-gray-400">
                {{ row.shift_time }}
              </span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
