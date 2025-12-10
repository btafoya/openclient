<template>
  <AdminLayout>
    <div class="max-w-3xl mx-auto space-y-6">
      <!-- Page Header -->
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
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Pipeline</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Update pipeline settings and stages
          </p>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading && !pipeline" class="p-8 text-center">
        <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading pipeline...</p>
      </div>

      <!-- Form -->
      <form v-else @submit.prevent="handleSubmit" class="space-y-6">
        <!-- Basic Info -->
        <div class="bg-white dark:bg-white/[0.03] rounded-xl border border-gray-200 dark:border-gray-800 p-6">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Basic Information</h2>

          <div class="space-y-4">
            <div>
              <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Pipeline Name *
              </label>
              <input
                id="name"
                v-model="form.name"
                type="text"
                required
                class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                placeholder="e.g., Sales Pipeline"
              />
            </div>

            <div>
              <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Description
              </label>
              <textarea
                id="description"
                v-model="form.description"
                rows="3"
                class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                placeholder="Describe the purpose of this pipeline..."
              ></textarea>
            </div>

            <div class="flex items-center">
              <input
                id="is_active"
                v-model="form.is_active"
                type="checkbox"
                class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"
              />
              <label for="is_active" class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                Pipeline is active
              </label>
            </div>
          </div>
        </div>

        <!-- Stages -->
        <div class="bg-white dark:bg-white/[0.03] rounded-xl border border-gray-200 dark:border-gray-800 p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Stages</h2>
            <button
              type="button"
              @click="addStage"
              class="inline-flex items-center gap-1 text-sm text-brand-600 hover:text-brand-700"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              Add Stage
            </button>
          </div>

          <div class="space-y-3">
            <div
              v-for="(stage, index) in form.stages"
              :key="stage.id || index"
              class="flex items-start gap-3 p-4 bg-gray-50 dark:bg-gray-900/50 rounded-lg"
            >
              <!-- Drag Handle -->
              <div class="flex-shrink-0 cursor-move text-gray-400 mt-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                </svg>
              </div>

              <!-- Color Picker -->
              <div class="flex-shrink-0">
                <input
                  type="color"
                  v-model="stage.color"
                  class="w-8 h-8 rounded cursor-pointer border-0"
                />
              </div>

              <!-- Stage Details -->
              <div class="flex-1 space-y-3">
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Name</label>
                    <input
                      v-model="stage.name"
                      type="text"
                      required
                      class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                      placeholder="Stage name"
                    />
                  </div>
                  <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Probability (%)</label>
                    <input
                      v-model.number="stage.probability"
                      type="number"
                      min="0"
                      max="100"
                      class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                    />
                  </div>
                </div>
                <div class="flex items-center gap-4">
                  <label class="inline-flex items-center">
                    <input
                      type="checkbox"
                      v-model="stage.is_won"
                      @change="handleWonChange(index)"
                      class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                    />
                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Won Stage</span>
                  </label>
                  <label class="inline-flex items-center">
                    <input
                      type="checkbox"
                      v-model="stage.is_lost"
                      @change="handleLostChange(index)"
                      class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                    />
                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Lost Stage</span>
                  </label>
                  <span v-if="stage.deal_count" class="text-xs text-gray-500">
                    {{ stage.deal_count }} deals
                  </span>
                </div>
              </div>

              <!-- Remove Button -->
              <button
                type="button"
                @click="removeStage(index)"
                class="flex-shrink-0 p-1 text-gray-400 hover:text-red-600"
                :disabled="form.stages.length <= 2 || stage.deal_count > 0"
                :title="stage.deal_count > 0 ? 'Cannot delete stage with deals' : ''"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            Tip: Stages with deals cannot be deleted. Move deals to another stage first.
          </p>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
          <router-link
            to="/pipelines"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
          >
            Cancel
          </router-link>
          <button
            type="submit"
            :disabled="saving"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span v-if="saving" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            {{ saving ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { usePipelineStore } from '@/stores/pipelines'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const router = useRouter()
const route = useRoute()
const pipelineStore = usePipelineStore()

const loading = ref(true)
const saving = ref(false)
const pipeline = ref(null)

const form = reactive({
  name: '',
  description: '',
  is_active: true,
  stages: []
})

async function loadPipeline() {
  loading.value = true
  try {
    const id = route.params.id
    pipeline.value = await pipelineStore.fetchPipeline(id)

    // Populate form
    form.name = pipeline.value.name
    form.description = pipeline.value.description || ''
    form.is_active = pipeline.value.is_active
    form.stages = (pipeline.value.stages || []).map(s => ({
      id: s.id,
      name: s.name,
      color: s.color || '#6b7280',
      probability: s.probability || 0,
      is_won: s.is_won || false,
      is_lost: s.is_lost || false,
      deal_count: s.deal_count || 0
    }))
  } catch (error) {
    console.error('Failed to load pipeline:', error)
    alert('Failed to load pipeline. Please try again.')
    router.push('/pipelines')
  } finally {
    loading.value = false
  }
}

function addStage() {
  form.stages.push({
    id: null,
    name: '',
    color: '#6b7280',
    probability: 50,
    is_won: false,
    is_lost: false,
    deal_count: 0
  })
}

function removeStage(index) {
  const stage = form.stages[index]
  if (stage.deal_count > 0) {
    alert('Cannot delete a stage that has deals. Move deals to another stage first.')
    return
  }
  if (form.stages.length > 2) {
    form.stages.splice(index, 1)
  }
}

function handleWonChange(index) {
  if (form.stages[index].is_won) {
    form.stages[index].is_lost = false
    form.stages[index].probability = 100
  }
}

function handleLostChange(index) {
  if (form.stages[index].is_lost) {
    form.stages[index].is_won = false
    form.stages[index].probability = 0
  }
}

async function handleSubmit() {
  // Validate
  if (!form.name.trim()) {
    alert('Pipeline name is required')
    return
  }

  const hasWonStage = form.stages.some(s => s.is_won)
  const hasLostStage = form.stages.some(s => s.is_lost)

  if (!hasWonStage || !hasLostStage) {
    alert('Please designate at least one Won stage and one Lost stage')
    return
  }

  saving.value = true

  try {
    const data = {
      name: form.name.trim(),
      description: form.description.trim() || null,
      is_active: form.is_active,
      stages: form.stages.map((s, i) => ({
        id: s.id,
        name: s.name,
        color: s.color,
        probability: s.probability,
        sort_order: i,
        is_won: s.is_won,
        is_lost: s.is_lost
      }))
    }

    await pipelineStore.updatePipeline(route.params.id, data)
    router.push('/pipelines')
  } catch (error) {
    console.error('Failed to update pipeline:', error)
    alert(error.response?.data?.error || 'Failed to update pipeline. Please try again.')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadPipeline()
})
</script>
