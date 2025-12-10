<template>
  <AdminLayout>
    <div class="h-full flex flex-col">
      <!-- Page Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
        <div class="flex items-center gap-4">
          <router-link
            to="/pipelines"
            class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
          </router-link>
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ kanbanBoard?.pipeline?.name || 'Deals' }}
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              Drag and drop deals between stages
            </p>
          </div>
        </div>
        <div class="flex gap-3">
          <select
            v-model="selectedPipelineId"
            @change="loadKanban"
            class="px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
          >
            <option v-for="pipeline in pipelines" :key="pipeline.id" :value="pipeline.id">
              {{ pipeline.name }}
            </option>
          </select>
          <button
            @click="showCreateDeal = true"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            New Deal
          </button>
        </div>
      </div>

      <!-- Stats Bar -->
      <div v-if="stats" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-white/[0.03] rounded-lg border border-gray-200 dark:border-gray-800 p-4">
          <p class="text-sm text-gray-500 dark:text-gray-400">Total Deals</p>
          <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_deals }}</p>
        </div>
        <div class="bg-white dark:bg-white/[0.03] rounded-lg border border-gray-200 dark:border-gray-800 p-4">
          <p class="text-sm text-gray-500 dark:text-gray-400">Pipeline Value</p>
          <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">${{ formatCurrency(stats.total_value) }}</p>
        </div>
        <div class="bg-white dark:bg-white/[0.03] rounded-lg border border-gray-200 dark:border-gray-800 p-4">
          <p class="text-sm text-gray-500 dark:text-gray-400">Weighted Value</p>
          <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">${{ formatCurrency(stats.weighted_value) }}</p>
        </div>
        <div class="bg-white dark:bg-white/[0.03] rounded-lg border border-gray-200 dark:border-gray-800 p-4">
          <p class="text-sm text-gray-500 dark:text-gray-400">Win Rate</p>
          <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.win_rate }}%</p>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="dealStore.loading" class="flex-1 flex items-center justify-center">
        <div class="text-center">
          <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading deals...</p>
        </div>
      </div>

      <!-- Kanban Board -->
      <div v-else class="flex-1 overflow-x-auto">
        <div class="inline-flex gap-4 pb-4 min-w-full">
          <div
            v-for="column in kanbanBoard?.columns || []"
            :key="column.stage.id"
            class="flex-shrink-0 w-80"
          >
            <!-- Column Header -->
            <div
              class="flex items-center justify-between p-3 rounded-t-lg"
              :style="{ backgroundColor: column.stage.color + '20' }"
            >
              <div class="flex items-center gap-2">
                <span
                  class="w-3 h-3 rounded-full"
                  :style="{ backgroundColor: column.stage.color }"
                ></span>
                <span class="font-medium text-gray-900 dark:text-white">{{ column.stage.name }}</span>
                <span class="text-xs bg-white/50 dark:bg-gray-800/50 px-2 py-0.5 rounded-full text-gray-600 dark:text-gray-400">
                  {{ column.deal_count }}
                </span>
              </div>
              <span class="text-sm font-medium text-gray-600 dark:text-gray-400">
                ${{ formatCurrency(column.total_value) }}
              </span>
            </div>

            <!-- Column Content -->
            <div
              class="bg-gray-100 dark:bg-gray-900/50 rounded-b-lg p-2 min-h-[400px]"
              @dragover.prevent
              @drop="handleDrop($event, column.stage.id)"
            >
              <div
                v-for="deal in column.deals"
                :key="deal.id"
                draggable="true"
                @dragstart="handleDragStart($event, deal, column.stage.id)"
                @dragend="handleDragEnd"
                class="bg-white dark:bg-gray-800 rounded-lg p-3 mb-2 shadow-sm cursor-move hover:shadow-md transition-shadow border border-gray-200 dark:border-gray-700"
              >
                <div class="flex items-start justify-between">
                  <router-link
                    :to="`/deals/${deal.id}`"
                    class="font-medium text-gray-900 dark:text-white hover:text-brand-600 dark:hover:text-brand-400"
                  >
                    {{ deal.name }}
                  </router-link>
                  <span
                    v-if="deal.priority"
                    :class="getPriorityClasses(deal.priority)"
                    class="text-xs px-1.5 py-0.5 rounded"
                  >
                    {{ deal.priority }}
                  </span>
                </div>

                <div v-if="deal.client_name" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                  {{ deal.client_name }}
                </div>

                <div class="mt-2 flex items-center justify-between">
                  <span v-if="deal.value" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    ${{ formatCurrency(deal.value) }}
                  </span>
                  <span v-else class="text-sm text-gray-400">-</span>

                  <span
                    v-if="deal.expected_close_date"
                    :class="isOverdue(deal.expected_close_date) ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400'"
                    class="text-xs"
                  >
                    {{ formatDate(deal.expected_close_date) }}
                  </span>
                </div>

                <div v-if="deal.probability" class="mt-2">
                  <div class="flex items-center gap-2">
                    <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                      <div
                        class="h-1.5 rounded-full"
                        :class="getProbabilityColor(deal.probability)"
                        :style="{ width: `${deal.probability}%` }"
                      ></div>
                    </div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ deal.probability }}%</span>
                  </div>
                </div>
              </div>

              <!-- Empty State -->
              <div
                v-if="column.deals.length === 0"
                class="text-center py-8 text-gray-400 dark:text-gray-500"
              >
                <p class="text-sm">No deals</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Create Deal Modal -->
      <div
        v-if="showCreateDeal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        @click.self="showCreateDeal = false"
      >
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
          <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Create New Deal</h2>
          </div>
          <form @submit.prevent="createDeal" class="p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Deal Name *</label>
              <input
                v-model="newDeal.name"
                type="text"
                required
                class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Value ($)</label>
              <input
                v-model.number="newDeal.value"
                type="number"
                min="0"
                step="0.01"
                class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Expected Close Date</label>
              <input
                v-model="newDeal.expected_close_date"
                type="date"
                class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priority</label>
              <select
                v-model="newDeal.priority"
                class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
              >
                <option value="">None</option>
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
              <textarea
                v-model="newDeal.description"
                rows="3"
                class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
              ></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-4">
              <button
                type="button"
                @click="showCreateDeal = false"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="creatingDeal"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 disabled:opacity-50"
              >
                <span v-if="creatingDeal" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                {{ creatingDeal ? 'Creating...' : 'Create Deal' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { usePipelineStore } from '@/stores/pipelines'
import { useDealStore } from '@/stores/deals'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const route = useRoute()
const pipelineStore = usePipelineStore()
const dealStore = useDealStore()

const selectedPipelineId = ref(null)
const showCreateDeal = ref(false)
const creatingDeal = ref(false)
const stats = ref(null)
const draggedDeal = ref(null)
const draggedFromStage = ref(null)

const newDeal = reactive({
  name: '',
  value: null,
  expected_close_date: '',
  priority: '',
  description: ''
})

// Computed
const pipelines = computed(() => pipelineStore.pipelines)
const kanbanBoard = computed(() => dealStore.kanbanBoard)

// Methods
async function loadPipelines() {
  try {
    await pipelineStore.fetchPipelines(true)
    if (pipelines.value.length > 0) {
      selectedPipelineId.value = route.query.pipeline_id || pipelines.value[0].id
      await loadKanban()
    }
  } catch (error) {
    console.error('Failed to load pipelines:', error)
  }
}

async function loadKanban() {
  if (!selectedPipelineId.value) return

  try {
    await dealStore.fetchKanbanBoard(selectedPipelineId.value)
    stats.value = await dealStore.fetchDealStats(selectedPipelineId.value)
  } catch (error) {
    console.error('Failed to load kanban:', error)
  }
}

function handleDragStart(event, deal, stageId) {
  draggedDeal.value = deal
  draggedFromStage.value = stageId
  event.dataTransfer.effectAllowed = 'move'
  event.target.classList.add('opacity-50')
}

function handleDragEnd(event) {
  event.target.classList.remove('opacity-50')
}

async function handleDrop(event, targetStageId) {
  if (!draggedDeal.value || draggedFromStage.value === targetStageId) {
    return
  }

  const deal = draggedDeal.value
  const fromStageId = draggedFromStage.value

  // Optimistic update
  dealStore.updateKanbanLocally(fromStageId, targetStageId, deal.id, 0)

  try {
    await dealStore.moveDeal(deal.id, targetStageId)
    // Reload stats
    stats.value = await dealStore.fetchDealStats(selectedPipelineId.value)
  } catch (error) {
    console.error('Failed to move deal:', error)
    // Revert on error
    await loadKanban()
    alert('Failed to move deal. Please try again.')
  }

  draggedDeal.value = null
  draggedFromStage.value = null
}

async function createDeal() {
  if (!newDeal.name.trim()) {
    alert('Deal name is required')
    return
  }

  creatingDeal.value = true

  try {
    const data = {
      pipeline_id: selectedPipelineId.value,
      name: newDeal.name.trim(),
      value: newDeal.value || null,
      expected_close_date: newDeal.expected_close_date || null,
      priority: newDeal.priority || null,
      description: newDeal.description.trim() || null
    }

    await dealStore.createDeal(data)
    await loadKanban()

    // Reset form
    newDeal.name = ''
    newDeal.value = null
    newDeal.expected_close_date = ''
    newDeal.priority = ''
    newDeal.description = ''
    showCreateDeal.value = false
  } catch (error) {
    console.error('Failed to create deal:', error)
    alert(error.response?.data?.error || 'Failed to create deal. Please try again.')
  } finally {
    creatingDeal.value = false
  }
}

function formatCurrency(value) {
  return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

function isOverdue(dateStr) {
  if (!dateStr) return false
  return new Date(dateStr) < new Date()
}

function getPriorityClasses(priority) {
  const classes = {
    low: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    medium: 'bg-yellow-100 text-yellow-600 dark:bg-yellow-900/20 dark:text-yellow-400',
    high: 'bg-red-100 text-red-600 dark:bg-red-900/20 dark:text-red-400'
  }
  return classes[priority] || classes.low
}

function getProbabilityColor(probability) {
  if (probability >= 75) return 'bg-green-500'
  if (probability >= 50) return 'bg-yellow-500'
  if (probability >= 25) return 'bg-orange-500'
  return 'bg-gray-400'
}

// Watch for query param changes
watch(() => route.query.pipeline_id, (newId) => {
  if (newId && newId !== selectedPipelineId.value) {
    selectedPipelineId.value = newId
    loadKanban()
  }
})

// Lifecycle
onMounted(() => {
  loadPipelines()
})
</script>
