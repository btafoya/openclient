<template>
  <AdminLayout>
    <div class="mx-auto max-w-3xl space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create Support Ticket</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Submit a new support request
          </p>
        </div>
        <router-link
          to="/tickets"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          Cancel
        </router-link>
      </div>

      <!-- Form -->
      <form @submit.prevent="submitForm" class="space-y-6">
        <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
          <div class="space-y-6">
            <!-- Subject -->
            <div>
              <label for="subject" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Subject <span class="text-red-500">*</span>
              </label>
              <input
                id="subject"
                v-model="form.subject"
                type="text"
                required
                class="mt-1 w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                placeholder="Brief description of the issue"
              />
            </div>

            <!-- Client -->
            <div>
              <label for="client_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Client
              </label>
              <select
                id="client_id"
                v-model="form.client_id"
                class="mt-1 w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
              >
                <option value="">Select a client (optional)</option>
                <option v-for="client in clients" :key="client.id" :value="client.id">
                  {{ client.name }}
                </option>
              </select>
            </div>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
              <!-- Priority -->
              <div>
                <label for="priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                  Priority
                </label>
                <select
                  id="priority"
                  v-model="form.priority"
                  class="mt-1 w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                >
                  <option value="low">Low</option>
                  <option value="normal">Normal</option>
                  <option value="high">High</option>
                  <option value="urgent">Urgent</option>
                </select>
              </div>

              <!-- Category -->
              <div>
                <label for="category" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                  Category
                </label>
                <select
                  id="category"
                  v-model="form.category"
                  class="mt-1 w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                >
                  <option value="general">General</option>
                  <option value="technical">Technical</option>
                  <option value="billing">Billing</option>
                  <option value="feature_request">Feature Request</option>
                  <option value="bug">Bug Report</option>
                </select>
              </div>
            </div>

            <!-- Due Date -->
            <div>
              <label for="due_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Due Date
              </label>
              <input
                id="due_date"
                v-model="form.due_date"
                type="date"
                class="mt-1 w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
              />
            </div>

            <!-- Description -->
            <div>
              <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Description <span class="text-red-500">*</span>
              </label>
              <textarea
                id="description"
                v-model="form.description"
                rows="6"
                required
                class="mt-1 w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                placeholder="Detailed description of the issue or request..."
              ></textarea>
            </div>
          </div>
        </div>

        <!-- Error Message -->
        <div v-if="error" class="p-4 bg-red-50 border border-red-200 rounded-lg dark:bg-red-900/20 dark:border-red-800">
          <p class="text-sm text-red-700 dark:text-red-400">{{ error }}</p>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end gap-3">
          <router-link
            to="/tickets"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
          >
            Cancel
          </router-link>
          <button
            type="submit"
            :disabled="submitting"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <svg v-if="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ submitting ? 'Creating...' : 'Create Ticket' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useTicketStore } from '@/stores/tickets'
import { useClientStore } from '@/stores/clients'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const router = useRouter()
const ticketStore = useTicketStore()
const clientStore = useClientStore()

const submitting = ref(false)
const error = ref(null)
const clients = ref([])

const form = reactive({
  subject: '',
  description: '',
  client_id: '',
  priority: 'normal',
  category: 'general',
  due_date: ''
})

async function loadClients() {
  try {
    await clientStore.fetchClients()
    clients.value = clientStore.clients
  } catch (err) {
    console.error('Failed to load clients:', err)
  }
}

async function submitForm() {
  error.value = null
  submitting.value = true

  try {
    const data = { ...form }
    if (!data.client_id) delete data.client_id
    if (!data.due_date) delete data.due_date

    const ticket = await ticketStore.createTicket(data)
    router.push(`/tickets/${ticket.id}`)
  } catch (err) {
    console.error('Failed to create ticket:', err)
    error.value = err.response?.data?.error || 'Failed to create ticket. Please try again.'
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadClients()
})
</script>
