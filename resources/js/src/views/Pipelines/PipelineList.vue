<template>
  <AdminLayout>
    <div class="space-y-6">
      <!-- Page Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Pipelines</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Manage your sales pipelines and deal stages
          </p>
        </div>
        <div class="flex gap-3">
          <router-link
            to="/pipelines/create"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            New Pipeline
          </router-link>
        </div>
      </div>

      <!-- Search -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
        <div class="flex-1">
          <div class="relative">
            <input
              v-model="searchTerm"
              type="text"
              placeholder="Search pipelines..."
              class="w-full px-4 py-2 pl-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
              @input="handleSearch"
            />
            <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="pipelineStore.loading" class="p-8 text-center">
        <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading pipelines...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="pipelineStore.error" class="p-8 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/20">
          <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </div>
        <p class="mt-4 text-sm text-gray-900 dark:text-white font-medium">{{ pipelineStore.error }}</p>
        <button
          @click="loadPipelines"
          class="mt-4 px-4 py-2 text-sm font-medium text-brand-600 hover:text-brand-700"
        >
          Try Again
        </button>
      </div>

      <!-- Empty State -->
      <div v-else-if="displayedPipelines.length === 0" class="p-8 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800">
          <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
        </div>
        <p class="mt-4 text-sm text-gray-900 dark:text-white font-medium">No pipelines found</p>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          {{ searchTerm ? 'Try adjusting your search' : 'Get started by creating your first pipeline' }}
        </p>
        <router-link
          v-if="!searchTerm"
          to="/pipelines/create"
          class="inline-flex items-center gap-2 mt-4 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Create Your First Pipeline
        </router-link>
      </div>

      <!-- Pipelines Grid -->
      <div v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="pipeline in displayedPipelines"
          :key="pipeline.id"
          class="bg-white dark:bg-white/[0.03] rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden"
        >
          <!-- Pipeline Header -->
          <div class="p-5 border-b border-gray-200 dark:border-gray-800">
            <div class="flex items-start justify-between">
              <div>
                <h3 class="font-semibold text-gray-900 dark:text-white">
                  {{ pipeline.name }}
                </h3>
                <p v-if="pipeline.description" class="mt-1 text-sm text-gray-500 dark:text-gray-400 line-clamp-2">
                  {{ pipeline.description }}
                </p>
              </div>
              <div class="flex items-center gap-1">
                <button
                  @click="viewPipeline(pipeline)"
                  class="p-1.5 text-gray-600 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400"
                  title="View Deals"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </button>
                <router-link
                  :to="`/pipelines/${pipeline.id}/edit`"
                  class="p-1.5 text-gray-600 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400"
                  title="Edit Pipeline"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </router-link>
                <button
                  @click="confirmDelete(pipeline)"
                  class="p-1.5 text-gray-600 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
                  title="Delete Pipeline"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- Pipeline Stages Preview -->
          <div class="p-5">
            <div class="flex items-center gap-2 mb-3">
              <span class="text-sm text-gray-500 dark:text-gray-400">Stages</span>
              <span class="text-xs bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 px-2 py-0.5 rounded">
                {{ pipeline.stages?.length || 0 }}
              </span>
            </div>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="stage in (pipeline.stages || []).slice(0, 5)"
                :key="stage.id"
                class="inline-flex items-center gap-1.5 px-2 py-1 text-xs rounded-full"
                :style="{ backgroundColor: stage.color + '20', color: stage.color }"
              >
                <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: stage.color }"></span>
                {{ stage.name }}
              </span>
              <span
                v-if="(pipeline.stages || []).length > 5"
                class="inline-flex items-center px-2 py-1 text-xs bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-full"
              >
                +{{ pipeline.stages.length - 5 }} more
              </span>
            </div>
          </div>

          <!-- Pipeline Stats -->
          <div class="px-5 py-3 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-800">
            <div class="flex items-center justify-between text-sm">
              <span class="text-gray-500 dark:text-gray-400">
                {{ pipeline.deal_count || 0 }} deals
              </span>
              <span class="text-gray-900 dark:text-white font-medium">
                ${{ formatCurrency(pipeline.total_value || 0) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePipelineStore } from '@/stores/pipelines'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const router = useRouter()
const pipelineStore = usePipelineStore()

const searchTerm = ref('')

// Computed
const displayedPipelines = computed(() => {
  return pipelineStore.filteredPipelines
})

// Methods
async function loadPipelines() {
  try {
    await pipelineStore.fetchPipelines(true)
  } catch (error) {
    console.error('Failed to load pipelines:', error)
  }
}

function handleSearch() {
  pipelineStore.setSearchTerm(searchTerm.value)
}

function viewPipeline(pipeline) {
  router.push(`/deals?pipeline_id=${pipeline.id}`)
}

async function confirmDelete(pipeline) {
  if (confirm(`Are you sure you want to delete "${pipeline.name}"? This action cannot be undone.`)) {
    try {
      await pipelineStore.deletePipeline(pipeline.id)
    } catch (error) {
      console.error('Failed to delete pipeline:', error)
      if (error.response?.data?.deal_count) {
        alert(`Cannot delete pipeline: It has ${error.response.data.deal_count} active deals.`)
      } else {
        alert(error.response?.data?.error || 'Failed to delete pipeline. Please try again.')
      }
    }
  }
}

function formatCurrency(value) {
  return Number(value).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

// Lifecycle
onMounted(() => {
  loadPipelines()
})
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
