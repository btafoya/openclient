<template>
  <div
    class="group cursor-pointer rounded-lg border bg-white p-3 shadow-sm transition-all hover:shadow-md dark:bg-gray-900"
    :class="getBorderClasses()"
    draggable="true"
    @dragstart="handleDragStart"
    @dragend="handleDragEnd"
  >
    <!-- Header -->
    <div class="mb-2 flex items-start justify-between">
      <h4 class="text-sm font-medium text-gray-900 dark:text-white line-clamp-2">
        {{ deal.name }}
      </h4>
      <div class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
        <button
          @click.stop="$emit('edit', deal)"
          class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
          title="Edit"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
        </button>
        <DropdownMenu
          :items="menuItems"
          @select="handleMenuSelect"
        >
          <button
            @click.stop
            class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
            title="More options"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
            </svg>
          </button>
        </DropdownMenu>
      </div>
    </div>

    <!-- Value -->
    <div class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">
      {{ formatCurrency(deal.value, deal.currency) }}
    </div>

    <!-- Client -->
    <div v-if="deal.client" class="mb-2 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
      <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
      </svg>
      {{ deal.client.name || deal.client_name }}
    </div>

    <!-- Description -->
    <p v-if="deal.description" class="mb-3 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">
      {{ deal.description }}
    </p>

    <!-- Footer -->
    <div class="flex flex-wrap items-center gap-2">
      <!-- Priority Badge -->
      <span
        :class="getPriorityClasses()"
        class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium"
      >
        {{ deal.priority || 'Medium' }}
      </span>

      <!-- Probability Badge -->
      <span
        :class="getProbabilityClasses()"
        class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium"
      >
        {{ deal.probability || 0 }}%
      </span>

      <!-- Expected Close Date -->
      <div v-if="deal.expected_close_date" class="flex items-center gap-1 text-xs" :class="getCloseDateClasses()">
        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        {{ formatDate(deal.expected_close_date) }}
      </div>

      <!-- Spacer -->
      <div class="flex-1"></div>

      <!-- Assigned Avatar -->
      <div v-if="deal.assigned_user_name" class="flex items-center gap-1" :title="deal.assigned_user_name">
        <div class="flex h-5 w-5 items-center justify-center rounded-full bg-brand-100 text-xs font-medium text-brand-700 dark:bg-brand-900/30 dark:text-brand-400">
          {{ getInitials(deal.assigned_user_name) }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import DropdownMenu from '@/components/common/DropdownMenu.vue'

const props = defineProps({
  deal: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['edit', 'delete', 'mark-won', 'mark-lost', 'convert-to-project', 'drag-start', 'drag-end'])

const menuItems = computed(() => [
  { label: 'Edit', value: 'edit', icon: 'edit' },
  { label: 'Mark as Won', value: 'mark-won', icon: 'check' },
  { label: 'Mark as Lost', value: 'mark-lost', icon: 'x' },
  { label: 'Convert to Project', value: 'convert', icon: 'folder' },
  { divider: true },
  { label: 'Delete', value: 'delete', icon: 'trash', class: 'text-red-600' }
])

function handleMenuSelect(item) {
  switch (item.value) {
    case 'edit':
      emit('edit', props.deal)
      break
    case 'mark-won':
      emit('mark-won', props.deal)
      break
    case 'mark-lost':
      emit('mark-lost', props.deal)
      break
    case 'convert':
      emit('convert-to-project', props.deal)
      break
    case 'delete':
      emit('delete', props.deal)
      break
  }
}

function handleDragStart(e) {
  e.dataTransfer.setData('deal-id', props.deal.id)
  e.dataTransfer.effectAllowed = 'move'
  emit('drag-start', props.deal)
}

function handleDragEnd() {
  emit('drag-end', props.deal)
}

function getBorderClasses() {
  const probabilityClasses = {
    won: 'border-green-300 dark:border-green-700 bg-green-50 dark:bg-green-900/10',
    lost: 'border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-900/10',
    high: 'border-blue-200 dark:border-blue-800',
    medium: 'border-gray-200 dark:border-gray-700',
    low: 'border-gray-200 dark:border-gray-700'
  }

  if (props.deal.probability === 100) return probabilityClasses.won
  if (props.deal.probability === 0 && props.deal.actual_close_date) return probabilityClasses.lost
  if (props.deal.probability >= 70) return probabilityClasses.high
  if (props.deal.probability >= 30) return probabilityClasses.medium
  return probabilityClasses.low
}

function getPriorityClasses() {
  const priorityClasses = {
    low: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
    medium: 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
    high: 'bg-orange-100 text-orange-700 dark:bg-orange-900/20 dark:text-orange-400'
  }
  return priorityClasses[props.deal.priority?.toLowerCase()] || priorityClasses.medium
}

function getProbabilityClasses() {
  const probability = props.deal.probability || 0
  if (probability === 100) return 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400'
  if (probability === 0) return 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400'
  if (probability >= 70) return 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400'
  if (probability >= 30) return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400'
  return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
}

function getCloseDateClasses() {
  if (!props.deal.expected_close_date) return 'text-gray-500 dark:text-gray-400'

  const closeDate = new Date(props.deal.expected_close_date)
  const today = new Date()
  today.setHours(0, 0, 0, 0)

  if (closeDate < today && props.deal.probability !== 100 && props.deal.probability !== 0) {
    return 'text-red-600 dark:text-red-400'
  }

  const nextWeek = new Date(today)
  nextWeek.setDate(nextWeek.getDate() + 7)

  if (closeDate <= nextWeek) {
    return 'text-orange-600 dark:text-orange-400'
  }

  return 'text-gray-500 dark:text-gray-400'
}

function formatCurrency(value, currency = 'USD') {
  if (!value) return '$0'
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency || 'USD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value)
}

function formatDate(dateString) {
  if (!dateString) return ''
  const date = new Date(dateString)
  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric'
  }).format(date)
}

function getInitials(name) {
  if (!name) return '?'
  return name
    .split(' ')
    .map(part => part[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
}
</script>
