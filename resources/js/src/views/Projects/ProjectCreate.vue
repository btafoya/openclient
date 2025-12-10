<template>
  <AdminLayout>
    <div class="mx-auto max-w-3xl">
      <!-- Header -->
      <div class="mb-6">
        <div class="flex items-center gap-3">
          <router-link
            to="/projects"
            class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
          </router-link>
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create New Project</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              Fill in the details below to create a new project
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
          <div class="flex justify-end gap-3 border-t border-gray-200 pt-6 dark:border-gray-800">
            <router-link
              to="/projects"
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
              {{ submitting ? 'Creating...' : 'Create Project' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useProjectStore } from '@/stores/projects'
import { useClientStore } from '@/stores/clients'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const router = useRouter()
const projectStore = useProjectStore()
const clientStore = useClientStore()

// State
const clients = ref([])
const submitting = ref(false)
const submitError = ref(null)
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
async function loadClients() {
  try {
    await clientStore.fetchClients(true)
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

    const newProject = await projectStore.createProject(data)
    router.push(`/projects/${newProject.id}`)
  } catch (error) {
    submitError.value = error.response?.data?.error || 'Failed to create project'
    console.error('Failed to create project:', error)
  } finally {
    submitting.value = false
  }
}

// Lifecycle
onMounted(() => {
  loadClients()
})
</script>
