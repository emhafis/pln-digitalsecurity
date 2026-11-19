<script setup>
import { ArchiveBoxIcon } from '@heroicons/vue/24/outline';

defineProps({
  emptyTitle: { type: String, default: 'Belum ada data' },
  emptyDescription: { type: String, default: 'Data akan muncul di sini setelah tersedia.' },
  hasRows: { type: Boolean, default: true },
});

const tableClass = [
  'w-full min-w-[480px] border-collapse text-left text-[13px] text-gray-700',
  '[&_thead]:bg-white',
  '[&_thead_th]:sticky [&_thead_th]:top-0 [&_thead_th]:z-10 [&_thead_th]:bg-white',
  '[&_thead_th]:shadow-[inset_0_-1px_0_#e5e7eb]',
  '[&_thead_th]:border-b [&_thead_th]:border-r [&_thead_th]:border-gray-200',
  '[&_thead_th]:px-3 [&_thead_th]:py-2 [&_thead_th]:font-medium [&_thead_th]:text-gray-500',
  '[&_thead_th:last-child]:border-r-0',
  '[&_tbody_td]:border-b [&_tbody_td]:border-r [&_tbody_td]:border-gray-200',
  '[&_tbody_td]:px-3 [&_tbody_td]:py-1.5 [&_tbody_td:last-child]:border-r-0',
  '[&_tbody_tr:last-child_td]:border-b-0',
  '[&_tbody_tr:nth-child(even)]:bg-gray-50',
  '[&_tbody_tr:hover]:bg-violet-50',
].join(' ');
</script>

<template>
  <div class="w-full overflow-hidden rounded-md border border-gray-200 bg-white">
    <div class="max-h-[75vh] overflow-auto">
      <table :class="tableClass">
        <thead>
          <tr>
            <slot name="head" />
          </tr>
        </thead>
        <tbody v-if="hasRows">
          <slot />
        </tbody>
      </table>
    </div>

    <div v-if="!hasRows" class="flex flex-col items-center justify-center px-6 py-12 text-center">
      <p class="mt-3 text-sm font-semibold text-gray-800">{{ emptyTitle }}</p>
      <p class="mt-1 max-w-sm text-xs text-gray-500">{{ emptyDescription }}</p>
      <div v-if="$slots['empty-action']" class="mt-4">
        <slot name="empty-action" />
      </div>
    </div>

    <div v-if="$slots.pagination"
      class="flex items-center justify-between gap-3 border-t border-gray-200 bg-white px-3 py-2">
      <slot name="pagination" />
    </div>
  </div>
</template>
