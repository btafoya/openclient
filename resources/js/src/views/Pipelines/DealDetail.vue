<template>
  <AdminLayout>
    <div class="space-y-6">
      <!-- Loading State -->
      <div v-if="loading && !deal" class="p-8 text-center">
        <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading deal...</p>
      </div>

      <template v-else-if="deal">
        <!-- Page Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div class="flex items-start gap-4">
            <router-link
              to="/deals"
              class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
            </router-link>
            <div>
              <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ deal.name }}</h1>
                <span
                  class="px-2 py-1 text-xs font-medium rounded-full"
                  :style="{ backgroundColor: deal.stage?.color + '20', color: deal.stage?.color }"
                >
                  {{ deal.stage?.name }}
                </span>
              </div>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ deal.client_name || 'No client assigned' }}
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <button
              @click="showEditModal = true"
              class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
              Edit
            </button>
            <button
              v-if="deal.is_active && !deal.stage?.is_won && !deal.stage?.is_lost"
              @click="handleMarkWon"
              class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Mark Won
            </button>
            <button
              v-if="deal.is_active && !deal.stage?.is_won && !deal.stage?.is_lost"
              @click="handleMarkLost"
              class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Mark Lost
            </button>
            <button
              v-if="deal.stage?.is_won && !deal.project_id"
              @click="handleConvertToProject"
              class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
              Convert to Project
            </button>
          </div>
        </div>

        <!-- Deal Info Grid -->
        <div class="grid gap-6 lg:grid-cols-3">
          <!-- Main Info -->
          <div class="lg:col-span-2 space-y-6">
            <!-- Details Card -->
            <div class="bg-white dark:bg-white/[0.03] rounded-xl border border-gray-200 dark:border-gray-800 p-6">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Deal Details</h2>

              <dl class="grid grid-cols-2 gap-4">
                <div>
                  <dt class="text-sm text-gray-500 dark:text-gray-400">Value</dt>
                  <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                    ${{ formatCurrency(deal.value || 0) }}
                  </dd>
                </div>
                <div>
                  <dt class="text-sm text-gray-500 dark:text-gray-400">Probability</dt>
                  <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                    {{ deal.probability || 0 }}%
                  </dd>
                </div>
                <div>
                  <dt class="text-sm text-gray-500 dark:text-gray-400">Expected Close</dt>
                  <dd class="mt-1 text-gray-900 dark:text-white">
                    {{ deal.expected_close_date ? formatDate(deal.expected_close_date) : 'Not set' }}
                  </dd>
                </div>
                <div>
                  <dt class="text-sm text-gray-500 dark:text-gray-400">Priority</dt>
                  <dd class="mt-1">
                    <span
                      class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full"
                      :class="priorityClass(deal.priority)"
                    >
                      {{ deal.priority || 'Normal' }}
                    </span>
                  </dd>
                </div>
                <div>
                  <dt class="text-sm text-gray-500 dark:text-gray-400">Pipeline</dt>
                  <dd class="mt-1 text-gray-900 dark:text-white">
                    {{ deal.pipeline_name || 'Unknown' }}
                  </dd>
                </div>
                <div>
                  <dt class="text-sm text-gray-500 dark:text-gray-400">Owner</dt>
                  <dd class="mt-1 text-gray-900 dark:text-white">
                    {{ deal.owner_name || 'Unassigned' }}
                  </dd>
                </div>
              </dl>

              <div v-if="deal.description" class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-800">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-2">Description</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-wrap">{{ deal.description }}</p>
              </div>
            </div>

            <!-- Activities -->
            <div class="bg-white dark:bg-white/[0.03] rounded-xl border border-gray-200 dark:border-gray-800 p-6">
              <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Activity</h2>
                <button
                  @click="showActivityModal = true"
                  class="inline-flex items-center gap-1 text-sm text-brand-600 hover:text-brand-700"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                  Add Activity
                </button>
              </div>

              <div v-if="!activities.length" class="py-8 text-center">
                <svg class="mx-auto w-12 h-12 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No activity recorded yet</p>
              </div>

              <div v-else class="space-y-4">
                <div
                  v-for="activity in activities"
                  :key="activity.id"
                  class="flex gap-3"
                >
                  <div class="flex-shrink-0">
                    <div
                      class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-medium"
                      :class="activityTypeClass(activity.type)"
                    >
                      {{ activityTypeIcon(activity.type) }}
                    </div>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-900 dark:text-white">
                      <span class="font-medium">{{ activity.user_name || 'System' }}</span>
                      {{ activity.description }}
                    </p>
                    <p v-if="activity.notes" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                      {{ activity.notes }}
                    </p>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                      {{ formatDateTime(activity.created_at) }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Sidebar -->
          <div class="space-y-6">
            <!-- Stage Progress -->
            <div class="bg-white dark:bg-white/[0.03] rounded-xl border border-gray-200 dark:border-gray-800 p-6">
              <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Pipeline Progress</h3>
              <div class="space-y-2">
                <div
                  v-for="stage in pipelineStages"
                  :key="stage.id"
                  class="flex items-center gap-2"
                >
                  <div
                    class="w-3 h-3 rounded-full"
                    :style="{ backgroundColor: stage.id === deal.stage_id ? stage.color : '#e5e7eb' }"
                  ></div>
                  <span
                    class="text-sm"
                    :class="stage.id === deal.stage_id ? 'text-gray-900 dark:text-white font-medium' : 'text-gray-500 dark:text-gray-400'"
                  >
                    {{ stage.name }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Quick Stats -->
            <div class="bg-white dark:bg-white/[0.03] rounded-xl border border-gray-200 dark:border-gray-800 p-6">
              <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Weighted Value</h3>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">
                ${{ formatCurrency((deal.value || 0) * ((deal.probability || 0) / 100)) }}
              </p>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ deal.probability || 0 }}% of ${{ formatCurrency(deal.value || 0) }}
              </p>
            </div>

            <!-- Client Info -->
            <div v-if="deal.client_id" class="bg-white dark:bg-white/[0.03] rounded-xl border border-gray-200 dark:border-gray-800 p-6">
              <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Client</h3>
              <router-link
                :to="`/crm/clients/${deal.client_id}`"
                class="flex items-center gap-3 p-3 -m-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-900/50"
              >
                <div class="w-10 h-10 rounded-full bg-brand-100 dark:bg-brand-900/30 flex items-center justify-center">
                  <span class="text-brand-600 font-medium">{{ getInitials(deal.client_name) }}</span>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-900 dark:text-white">{{ deal.client_name }}</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">View client profile</p>
                </div>
              </router-link>
            </div>

            <!-- Project Link -->
            <div v-if="deal.project_id" class="bg-white dark:bg-white/[0.03] rounded-xl border border-gray-200 dark:border-gray-800 p-6">
              <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Linked Project</h3>
              <router-link
                :to="`/projects/${deal.project_id}`"
                class="flex items-center gap-3 p-3 -m-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-900/50"
              >
                <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                  <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                  </svg>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-900 dark:text-white">View Project</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">Converted from this deal</p>
                </div>
              </router-link>
            </div>
          </div>
        </div>
      </template>

      <!-- Edit Modal -->
      <div
        v-if="showEditModal"
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title"
        role="dialog"
        aria-modal="true"
      >
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
          <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showEditModal = false"></div>

          <div class="inline-block align-bottom bg-white dark:bg-gray-900 rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form @submit.prevent="handleUpdate">
              <div class="px-6 pt-6 pb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Edit Deal</h3>

                <div class="space-y-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name *</label>
                    <input
                      v-model="editForm.name"
                      type="text"
                      required
                      class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                    />
                  </div>

                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Value ($)</label>
                      <input
                        v-model.number="editForm.value"
                        type="number"
                        min="0"
                        step="0.01"
                        class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Expected Close</label>
                      <input
                        v-model="editForm.expected_close_date"
                        type="date"
                        class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                      />
                    </div>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priority</label>
                    <select
                      v-model="editForm.priority"
                      class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                    >
                      <option value="low">Low</option>
                      <option value="normal">Normal</option>
                      <option value="high">High</option>
                      <option value="urgent">Urgent</option>
                    </select>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                    <textarea
                      v-model="editForm.description"
                      rows="3"
                      class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                    ></textarea>
                  </div>
                </div>
              </div>

              <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 flex justify-end gap-3">
                <button
                  type="button"
                  @click="showEditModal = false"
                  class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  :disabled="saving"
                  class="px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 disabled:opacity-50"
                >
                  {{ saving ? 'Saving...' : 'Save Changes' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Activity Modal -->
      <div
        v-if="showActivityModal"
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title"
        role="dialog"
        aria-modal="true"
      >
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
          <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showActivityModal = false"></div>

          <div class="inline-block align-bottom bg-white dark:bg-gray-900 rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form @submit.prevent="handleAddActivity">
              <div class="px-6 pt-6 pb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Add Activity</h3>

                <div class="space-y-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type *</label>
                    <select
                      v-model="activityForm.type"
                      required
                      class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                    >
                      <option value="call">Call</option>
                      <option value="email">Email</option>
                      <option value="meeting">Meeting</option>
                      <option value="note">Note</option>
                      <option value="task">Task</option>
                    </select>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description *</label>
                    <input
                      v-model="activityForm.description"
                      type="text"
                      required
                      class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                      placeholder="e.g., Follow-up call scheduled"
                    />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                    <textarea
                      v-model="activityForm.notes"
                      rows="3"
                      class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                      placeholder="Additional details..."
                    ></textarea>
                  </div>
                </div>
              </div>

              <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 flex justify-end gap-3">
                <button
                  type="button"
                  @click="showActivityModal = false"
                  class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  :disabled="savingActivity"
                  class="px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 disabled:opacity-50"
                >
                  {{ savingActivity ? 'Adding...' : 'Add Activity' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useDealStore } from '@/stores/deals'
import { usePipelineStore } from '@/stores/pipelines'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const router = useRouter()
const route = useRoute()
const dealStore = useDealStore()
const pipelineStore = usePipelineStore()

const loading = ref(true)
const saving = ref(false)
const savingActivity = ref(false)
const deal = ref(null)
const activities = ref([])
const pipelineStages = ref([])

const showEditModal = ref(false)
const showActivityModal = ref(false)

const editForm = reactive({
  name: '',
  value: 0,
  expected_close_date: '',
  priority: 'normal',
  description: ''
})

const activityForm = reactive({
  type: 'note',
  description: '',
  notes: ''
})

async function loadDeal() {
  loading.value = true
  try {
    const id = route.params.id
    deal.value = await dealStore.fetchDeal(id)

    // Populate edit form
    editForm.name = deal.value.name
    editForm.value = deal.value.value || 0
    editForm.expected_close_date = deal.value.expected_close_date || ''
    editForm.priority = deal.value.priority || 'normal'
    editForm.description = deal.value.description || ''

    // Load pipeline stages
    if (deal.value.pipeline_id) {
      const pipeline = await pipelineStore.fetchPipeline(deal.value.pipeline_id)
      pipelineStages.value = pipeline.stages || []
    }

    // Load activities
    activities.value = await dealStore.fetchActivities(id)
  } catch (error) {
    console.error('Failed to load deal:', error)
    alert('Failed to load deal. Please try again.')
    router.push('/deals')
  } finally {
    loading.value = false
  }
}

async function handleUpdate() {
  saving.value = true
  try {
    await dealStore.updateDeal(deal.value.id, editForm)
    deal.value = { ...deal.value, ...editForm }
    showEditModal.value = false
  } catch (error) {
    console.error('Failed to update deal:', error)
    alert(error.response?.data?.error || 'Failed to update deal. Please try again.')
  } finally {
    saving.value = false
  }
}

async function handleMarkWon() {
  if (!confirm('Mark this deal as won?')) return

  try {
    deal.value = await dealStore.markWon(deal.value.id)
    await loadDeal()
  } catch (error) {
    console.error('Failed to mark deal as won:', error)
    alert(error.response?.data?.error || 'Failed to update deal. Please try again.')
  }
}

async function handleMarkLost() {
  const reason = prompt('Reason for losing this deal (optional):')

  try {
    deal.value = await dealStore.markLost(deal.value.id, reason)
    await loadDeal()
  } catch (error) {
    console.error('Failed to mark deal as lost:', error)
    alert(error.response?.data?.error || 'Failed to update deal. Please try again.')
  }
}

async function handleConvertToProject() {
  if (!confirm('Convert this deal to a project? This action cannot be undone.')) return

  try {
    const result = await dealStore.convertToProject(deal.value.id)
    alert('Deal converted to project successfully!')
    router.push(`/projects/${result.project.id}`)
  } catch (error) {
    console.error('Failed to convert deal:', error)
    alert(error.response?.data?.error || 'Failed to convert deal. Please try again.')
  }
}

async function handleAddActivity() {
  savingActivity.value = true
  try {
    const newActivity = await dealStore.addActivity(deal.value.id, activityForm)
    activities.value.unshift(newActivity)
    showActivityModal.value = false
    activityForm.type = 'note'
    activityForm.description = ''
    activityForm.notes = ''
  } catch (error) {
    console.error('Failed to add activity:', error)
    alert(error.response?.data?.error || 'Failed to add activity. Please try again.')
  } finally {
    savingActivity.value = false
  }
}

function formatCurrency(value) {
  return Number(value).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

function formatDate(dateString) {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

function formatDateTime(dateString) {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  })
}

function getInitials(name) {
  if (!name) return '?'
  return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()
}

function priorityClass(priority) {
  const classes = {
    low: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    normal: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    high: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    urgent: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
  }
  return classes[priority] || classes.normal
}

function activityTypeClass(type) {
  const classes = {
    call: 'bg-blue-500',
    email: 'bg-purple-500',
    meeting: 'bg-green-500',
    note: 'bg-gray-500',
    task: 'bg-orange-500',
    stage_change: 'bg-brand-500'
  }
  return classes[type] || 'bg-gray-500'
}

function activityTypeIcon(type) {
  const icons = {
    call: '📞',
    email: '📧',
    meeting: '📅',
    note: '📝',
    task: '✓',
    stage_change: '→'
  }
  return icons[type] || '•'
}

onMounted(() => {
  loadDeal()
})
</script>
