<template>
  <div class="flex flex-col">
    <!-- Pipeline Header -->
    <div class="mb-4 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <!-- Pipeline Selector -->
        <select
          v-model="selectedPipelineId"
          class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-900 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
        >
          <option v-for="pipeline in pipelines" :key="pipeline.id" :value="pipeline.id">
            {{ pipeline.name }}
          </option>
        </select>

        <!-- Pipeline Stats -->
        <div v-if="pipelineStats" class="flex items-center gap-4 text-sm">
          <div class="flex items-center gap-1">
            <span class="text-gray-500 dark:text-gray-400">Total:</span>
            <span class="font-medium text-gray-900 dark:text-white">
              {{ formatCurrency(pipelineStats.total_value) }}
            </span>
          </div>
          <div class="flex items-center gap-1">
            <span class="text-gray-500 dark:text-gray-400">Weighted:</span>
            <span class="font-medium text-green-600 dark:text-green-400">
              {{ formatCurrency(pipelineStats.weighted_value) }}
            </span>
          </div>
          <div class="flex items-center gap-1">
            <span class="text-gray-500 dark:text-gray-400">Win Rate:</span>
            <span class="font-medium text-blue-600 dark:text-blue-400">
              {{ Math.round(pipelineStats.win_rate || 0) }}%
            </span>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <!-- View Toggle -->
        <div class="flex rounded-lg border border-gray-300 dark:border-gray-600">
          <button
            @click="viewMode = 'board'"
            :class="[
              'px-3 py-1.5 text-sm',
              viewMode === 'board'
                ? 'bg-brand-500 text-white'
                : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
            ]"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
            </svg>
          </button>
          <button
            @click="viewMode = 'list'"
            :class="[
              'px-3 py-1.5 text-sm',
              viewMode === 'list'
                ? 'bg-brand-500 text-white'
                : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
            ]"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
            </svg>
          </button>
        </div>

        <!-- Add Deal Button -->
        <button
          @click="$emit('add-deal')"
          class="flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Add Deal
        </button>
      </div>
    </div>

    <!-- Board View -->
    <div
      v-if="viewMode === 'board'"
      class="flex gap-4 overflow-x-auto pb-4"
      style="min-height: calc(100vh - 250px);"
    >
      <StageColumn
        v-for="stageData in stagesWithDeals"
        :key="stageData.stage.id"
        :stage="stageData.stage"
        :deals="stageData.deals"
        @add-deal="$emit('add-deal', $event)"
        @edit-deal="$emit('edit-deal', $event)"
        @delete-deal="$emit('delete-deal', $event)"
        @mark-won="$emit('mark-won', $event)"
        @mark-lost="$emit('mark-lost', $event)"
        @convert-to-project="$emit('convert-to-project', $event)"
        @deal-dropped="handleDealDropped"
        @deal-drag-start="handleDealDragStart"
        @deal-drag-end="handleDealDragEnd"
      />
    </div>

    <!-- List View -->
    <div v-else class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
      <table class="w-full">
        <thead class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
              Deal
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
              Client
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
              Stage
            </th>
            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
              Value
            </th>
            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
              Probability
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
              Close Date
            </th>
            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          <tr
            v-for="deal in allDeals"
            :key="deal.id"
            class="hover:bg-gray-50 dark:hover:bg-gray-800"
          >
            <td class="whitespace-nowrap px-4 py-3">
              <div class="font-medium text-gray-900 dark:text-white">{{ deal.name }}</div>
              <div v-if="deal.description" class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs">
                {{ deal.description }}
              </div>
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
              {{ deal.client?.name || deal.client_name || '-' }}
            </td>
            <td class="whitespace-nowrap px-4 py-3">
              <span
                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium text-white"
                :style="{ backgroundColor: getStageColor(deal.stage_id) }"
              >
                {{ getStageName(deal.stage_id) }}
              </span>
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">
              {{ formatCurrency(deal.value, deal.currency) }}
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-center">
              <span
                :class="getProbabilityClasses(deal.probability)"
                class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium"
              >
                {{ deal.probability || 0 }}%
              </span>
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
              {{ deal.expected_close_date ? formatDate(deal.expected_close_date) : '-' }}
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-2">
                <button
                  @click="$emit('edit-deal', deal)"
                  class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                  title="Edit"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </button>
                <button
                  @click="$emit('delete-deal', deal)"
                  class="text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                  title="Delete"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="allDeals.length === 0">
            <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
              No deals found. Click "Add Deal" to create your first deal.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import StageColumn from './StageColumn.vue'

const props = defineProps({
  pipelines: {
    type: Array,
    default: () => []
  },
  initialPipelineId: {
    type: String,
    default: null
  },
  stagesWithDeals: {
    type: Array,
    default: () => []
  },
  pipelineStats: {
    type: Object,
    default: null
  }
})

const emit = defineEmits([
  'pipeline-change',
  'add-deal',
  'edit-deal',
  'delete-deal',
  'mark-won',
  'mark-lost',
  'convert-to-project',
  'move-deal'
])

const selectedPipelineId = ref(props.initialPipelineId)
const viewMode = ref('board')
const draggingDeal = ref(null)

// Watch for pipeline changes
watch(selectedPipelineId, (newId) => {
  emit('pipeline-change', newId)
})

// Watch for prop changes
watch(() => props.initialPipelineId, (newId) => {
  if (newId && newId !== selectedPipelineId.value) {
    selectedPipelineId.value = newId
  }
})

// Computed: All deals flattened for list view
const allDeals = computed(() => {
  return props.stagesWithDeals.flatMap(stageData => stageData.deals)
})

// Helper functions
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
    day: 'numeric',
    year: 'numeric'
  }).format(date)
}

function getStageColor(stageId) {
  const stageData = props.stagesWithDeals.find(s => s.stage.id === stageId)
  return stageData?.stage?.color || '#6b7280'
}

function getStageName(stageId) {
  const stageData = props.stagesWithDeals.find(s => s.stage.id === stageId)
  return stageData?.stage?.name || 'Unknown'
}

function getProbabilityClasses(probability) {
  const p = probability || 0
  if (p === 100) return 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400'
  if (p === 0) return 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400'
  if (p >= 70) return 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400'
  if (p >= 30) return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400'
  return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
}

// Drag and drop handlers
function handleDealDropped({ dealId, stageId }) {
  emit('move-deal', { dealId, stageId })
}

function handleDealDragStart(deal) {
  draggingDeal.value = deal
}

function handleDealDragEnd() {
  draggingDeal.value = null
}
</script>
