<template>
  <div
    class="flex min-w-[300px] max-w-[350px] flex-col rounded-lg bg-gray-50 dark:bg-gray-800/50"
    :class="{ 'ring-2 ring-brand-500': isDragOver }"
    @dragover="handleDragOver"
    @dragleave="handleDragLeave"
    @drop="handleDrop"
  >
    <!-- Stage Header -->
    <div
      class="flex items-center justify-between rounded-t-lg px-4 py-3"
      :style="{ backgroundColor: stage.color || '#6b7280' }"
    >
      <div class="flex items-center gap-2">
        <h3 class="font-semibold text-white">{{ stage.name }}</h3>
        <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs font-medium text-white">
          {{ deals.length }}
        </span>
      </div>
      <div class="text-sm text-white/80">
        {{ formatCurrency(totalValue) }}
      </div>
    </div>

    <!-- Stage Info -->
    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-2 dark:border-gray-700">
      <div class="text-xs text-gray-500 dark:text-gray-400">
        {{ stage.probability || 0 }}% win probability
      </div>
      <button
        @click="$emit('add-deal', stage)"
        class="flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-900/20"
      >
        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Add Deal
      </button>
    </div>

    <!-- Deals Container -->
    <div class="flex-1 space-y-2 overflow-y-auto p-3" style="max-height: calc(100vh - 300px);">
      <DealCard
        v-for="deal in sortedDeals"
        :key="deal.id"
        :deal="deal"
        @edit="$emit('edit-deal', $event)"
        @delete="$emit('delete-deal', $event)"
        @mark-won="$emit('mark-won', $event)"
        @mark-lost="$emit('mark-lost', $event)"
        @convert-to-project="$emit('convert-to-project', $event)"
        @drag-start="handleDealDragStart"
        @drag-end="handleDealDragEnd"
      />

      <!-- Empty State -->
      <div
        v-if="deals.length === 0"
        class="flex flex-col items-center justify-center py-8 text-center text-gray-400 dark:text-gray-500"
      >
        <svg class="mb-2 h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
        <p class="text-sm">No deals in this stage</p>
        <button
          @click="$emit('add-deal', stage)"
          class="mt-2 text-xs text-brand-600 hover:text-brand-700 dark:text-brand-400"
        >
          Add the first deal
        </button>
      </div>

      <!-- Drop Indicator -->
      <div
        v-if="isDragOver"
        class="rounded-lg border-2 border-dashed border-brand-400 bg-brand-50/50 p-4 text-center text-sm text-brand-600 dark:bg-brand-900/20 dark:text-brand-400"
      >
        Drop deal here
      </div>
    </div>

    <!-- Stage Footer -->
    <div v-if="stage.is_won || stage.is_lost" class="border-t border-gray-200 px-4 py-2 dark:border-gray-700">
      <div class="flex items-center justify-center gap-1 text-xs">
        <span v-if="stage.is_won" class="text-green-600 dark:text-green-400">
          <svg class="inline h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Won Stage
        </span>
        <span v-else-if="stage.is_lost" class="text-red-600 dark:text-red-400">
          <svg class="inline h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Lost Stage
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import DealCard from './DealCard.vue'

const props = defineProps({
  stage: {
    type: Object,
    required: true
  },
  deals: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits([
  'add-deal',
  'edit-deal',
  'delete-deal',
  'mark-won',
  'mark-lost',
  'convert-to-project',
  'deal-dropped',
  'deal-drag-start',
  'deal-drag-end'
])

const isDragOver = ref(false)
const draggingDeal = ref(null)

const sortedDeals = computed(() => {
  return [...props.deals].sort((a, b) => {
    if (a.sort_order !== b.sort_order) {
      return (a.sort_order || 0) - (b.sort_order || 0)
    }
    return new Date(b.created_at) - new Date(a.created_at)
  })
})

const totalValue = computed(() => {
  return props.deals.reduce((sum, deal) => sum + (parseFloat(deal.value) || 0), 0)
})

function formatCurrency(value, currency = 'USD') {
  if (!value) return '$0'
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency,
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value)
}

function handleDragOver(e) {
  e.preventDefault()
  e.dataTransfer.dropEffect = 'move'
  isDragOver.value = true
}

function handleDragLeave(e) {
  if (!e.currentTarget.contains(e.relatedTarget)) {
    isDragOver.value = false
  }
}

function handleDrop(e) {
  e.preventDefault()
  isDragOver.value = false

  const dealId = e.dataTransfer.getData('deal-id')
  if (dealId) {
    emit('deal-dropped', {
      dealId,
      stageId: props.stage.id
    })
  }
}

function handleDealDragStart(deal) {
  draggingDeal.value = deal
  emit('deal-drag-start', deal)
}

function handleDealDragEnd(deal) {
  draggingDeal.value = null
  emit('deal-drag-end', deal)
}
</script>
