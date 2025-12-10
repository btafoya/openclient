<template>
  <AdminLayout>
    <div class="mx-auto max-w-3xl">
      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center py-12">
        <div class="h-12 w-12 animate-spin rounded-full border-4 border-gray-300 border-t-brand-600 dark:border-gray-700 dark:border-t-brand-500"></div>
      </div>

      <!-- Error State -->
      <div v-else-if="loadError" class="rounded-lg border border-red-300 bg-red-50 p-6 dark:border-red-700 dark:bg-red-900/20">
        <div class="flex items-start gap-3">
          <svg class="h-6 w-6 flex-shrink-0 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
          </svg>
          <div class="flex-1">
            <h3 class="text-lg font-medium text-red-800 dark:text-red-300">
              Failed to Load Project
            </h3>
            <p class="mt-1 text-sm text-red-700 dark:text-red-400">
              {{ loadError }}
            </p>
            <div class="mt-4 flex gap-3">
              <button
                @click="loadProject"
                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600"
              >
                Try Again
              </button>
              <router-link
                to="/projects"
                class="rounded-lg border border-red-600 bg-white px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-500 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-900/20"
              >
                Back to Projects
              </router-link>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Form -->
      <template v-else>
        <!-- Header -->
        <div class="mb-6">
          <div class="flex items-center gap-3">
            <router-link
              :to="`/projects/${route.params.id}`"
              class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
            </router-link>
            <div>
              <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Project</h1>
              <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Update project details and settings
              </p>
            </div>
          </div>
        </div>

        <!-- Form -->
        <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <form @submit.prevent="handleSubmit" class="p-6">
            <!-- Basic Information -->
            <div class="mb-6">
              <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Basic Information</h2>

              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <!-- Project Name -->
                <div class="sm:col-span-2">
                  <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Project Name <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="formData.name"
                    type="text"
                    required
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    placeholder="Enter project name"
                  />
                  <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
                </div>

                <!-- Client -->
                <div>
                  <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Client
                  </label>
                  <select
                    v-model="formData.client_id"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                  >
                    <option value="">No Client</option>
                    <option v-for="client in clients" :key="client.id" :value="client.id">
                      {{ client.name }}
                    </option>
                  </select>
                </div>

                <!-- Status -->
                <div>
                  <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Status
                  </label>
                  <select
                    v-model="formData.status"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                  >
                    <option value="planning">Planning</option>
                    <option value="in_progress">In Progress</option>
                    <option value="on_hold">On Hold</option>
                    <option value="completed">Completed</option>
                  </select>
                </div>

                <!-- Priority -->
                <div>
                  <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Priority
                  </label>
                  <select
                    v-model="formData.priority"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                  >
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                  </select>
                </div>

                <!-- Budget -->
                <div>
                  <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Budget
                  </label>
                  <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">$</span>
                    <input
                      v-model="formData.budget"
                      type="number"
                      step="0.01"
                      min="0"
                      class="w-full rounded-lg border border-gray-300 pl-7 pr-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                      placeholder="0.00"
                    />
                  </div>
                </div>

                <!-- Description -->
                <div class="sm:col-span-2">
                  <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Description
                  </label>
                  <textarea
                    v-model="formData.description"
                    rows="3"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    placeholder="Describe the project..."
                  ></textarea>
                </div>
              </div>
            </div>

            <!-- Dates -->
            <div class="mb-6">
              <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Schedule</h2>

              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <!-- Start Date -->
                <div>
                  <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Start Date
                  </label>
                  <input
                    v-model="formData.start_date"
                    type="date"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                  />
                </div>

                <!-- End Date -->
                <div>
                  <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    End Date
                  </label>
                  <input
                    v-model="formData.end_date"
                    type="date"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                  />
                </div>
              </div>
            </div>

            <!-- Settings -->
            <div class="mb-6">
              <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Settings</h2>

              <label class="flex items-center gap-3">
                <input
                  v-model="formData.is_active"
                  type="checkbox"
                  class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-700"
                />
                <div>
                  <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Active Project</span>
                  <p class="text-xs text-gray-500 dark:text-gray-400">
                    Active projects appear in the main project list and reports
                  </p>
                </div>
              </label>
            </div>

            <!-- Error Message -->
            <div v-if="submitError" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
              {{ submitError }}
            </div>

            <!-- Actions -->
            <div class="flex justify-between border-t border-gray-200 pt-6 dark:border-gray-800">
              <button
                type="button"
                @click="confirmDelete"
                class="inline-flex items-center gap-2 rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/20"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Delete Project
              </button>
              <div class="flex gap-3">
                <router-link
                  :to="`/projects/${route.params.id}`"
                  class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                  Cancel
                </router-link>
                <button
                  type="submit"
                  :disabled="submitting"
                  class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50 dark:bg-brand-500 dark:hover:bg-brand-600"
                >
                  <span v-if="submitting" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                  {{ submitting ? 'Saving...' : 'Save Changes' }}
                </button>
              </div>
            </div>
          </form>
        </div>
      </template>

      <!-- Delete Confirmation Modal -->
      <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="mx-4 w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-gray-900">
          <div class="mb-4 flex items-center gap-3 text-red-600 dark:text-red-400">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <h3 class="text-lg font-semibold">Delete Project</h3>
          </div>
          <p class="mb-2 text-sm text-gray-600 dark:text-gray-400">
            Are you sure you want to delete this project? This will also delete:
          </p>
          <ul class="mb-4 ml-4 list-disc text-sm text-gray-600 dark:text-gray-400">
            <li>All tasks associated with this project</li>
            <li>All time entries for this project</li>
          </ul>
          <p class="mb-4 text-sm font-medium text-red-600 dark:text-red-400">
            This action cannot be undone.
          </p>
          <div class="flex justify-end gap-3">
            <button
              @click="showDeleteConfirm = false"
              class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
            >
              Cancel
            </button>
            <button
              @click="deleteProject"
              :disabled="deleting"
              class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
            >
              <span v-if="deleting" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
              {{ deleting ? 'Deleting...' : 'Delete Project' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useProjectStore } from '@/stores/projects'
import { useClientStore } from '@/stores/clients'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const route = useRoute()
const router = useRouter()
const projectStore = useProjectStore()
const clientStore = useClientStore()

// State
const loading = ref(false)
const loadError = ref(null)
const clients = ref([])
const submitting = ref(false)
const submitError = ref(null)
const showDeleteConfirm = ref(false)
const deleting = ref(false)
const errors = reactive({})

const formData = reactive({
  name: '',
  description: '',
  client_id: '',
  status: 'planning',
  priority: 'medium',
  budget: '',
  start_date: '',
  end_date: '',
  is_active: true
})

// Methods
async function loadProject() {
  loading.value = true
  loadError.value = null
  try {
    const project = await projectStore.fetchProject(route.params.id)

    // Populate form
    formData.name = project.name || ''
    formData.description = project.description || ''
    formData.client_id = project.client_id || ''
    formData.status = project.status || 'planning'
    formData.priority = project.priority || 'medium'
    formData.budget = project.budget || ''
    formData.start_date = project.start_date ? project.start_date.split('T')[0] : ''
    formData.end_date = project.end_date ? project.end_date.split('T')[0] : ''
    formData.is_active = project.is_active !== false
  } catch (error) {
    loadError.value = error.response?.data?.error || 'Failed to load project'
    console.error('Failed to load project:', error)
  } finally {
    loading.value = false
  }
}

async function loadClients() {
  try {
    await clientStore.fetchClients(false) // Include inactive clients
    clients.value = clientStore.clients
  } catch (error) {
    console.error('Failed to load clients:', error)
  }
}

async function handleSubmit() {
  // Clear previous errors
  Object.keys(errors).forEach(key => delete errors[key])
  submitError.value = null

  // Validate
  if (!formData.name?.trim()) {
    errors.name = 'Project name is required'
    return
  }

  submitting.value = true

  try {
    const data = {
      name: formData.name.trim(),
      description: formData.description?.trim() || null,
      client_id: formData.client_id || null,
      status: formData.status,
      priority: formData.priority,
      budget: formData.budget ? parseFloat(formData.budget) : null,
      start_date: formData.start_date || null,
      end_date: formData.end_date || null,
      is_active: formData.is_active
    }

    await projectStore.updateProject(route.params.id, data)
    router.push(`/projects/${route.params.id}`)
  } catch (error) {
    submitError.value = error.response?.data?.error || 'Failed to update project'
    console.error('Failed to update project:', error)
  } finally {
    submitting.value = false
  }
}

function confirmDelete() {
  showDeleteConfirm.value = true
}

async function deleteProject() {
  deleting.value = true
  try {
    await projectStore.deleteProject(route.params.id)
    router.push('/projects')
  } catch (error) {
    submitError.value = error.response?.data?.error || 'Failed to delete project'
    console.error('Failed to delete project:', error)
    showDeleteConfirm.value = false
  } finally {
    deleting.value = false
  }
}

// Lifecycle
onMounted(() => {
  loadProject()
  loadClients()
})
</script>
