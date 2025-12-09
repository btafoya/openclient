<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl">
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
              Failed to Load Client
            </h3>
            <p class="mt-1 text-sm text-red-700 dark:text-red-400">
              {{ loadError }}
            </p>
            <div class="mt-4 flex gap-3">
              <button
                @click="loadClient"
                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600"
              >
                Try Again
              </button>
              <router-link
                to="/crm/clients"
                class="rounded-lg border border-red-600 bg-white px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-500 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-900/20"
              >
                Back to Clients
              </router-link>
            </div>
          </div>
        </div>
      </div>

      <!-- Client Details -->
      <div v-else-if="client">
        <!-- Header with Actions -->
        <div class="mb-6 flex items-start justify-between">
          <div class="flex-1">
            <div class="flex items-center gap-3">
              <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                {{ client.name }}
              </h1>
              <span
                v-if="client.is_active"
                class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400"
              >
                Active
              </span>
              <span
                v-else
                class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-800 dark:text-gray-400"
              >
                Inactive
              </span>
            </div>
            <p v-if="client.company" class="mt-1 text-lg text-gray-600 dark:text-gray-400">
              {{ client.company }}
            </p>
          </div>

          <div class="flex gap-3">
            <router-link
              to="/crm/clients"
              class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
              Back
            </router-link>
            <router-link
              :to="`/crm/clients/${client.id}/edit`"
              class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
              Edit
            </router-link>
          </div>
        </div>

        <!-- Tab Navigation -->
        <div class="mb-6 border-b border-gray-200 dark:border-gray-800">
          <nav class="-mb-px flex gap-8">
            <button
              @click="activeTab = 'details'"
              :class="[
                'border-b-2 py-4 text-sm font-medium transition-colors',
                activeTab === 'details'
                  ? 'border-brand-600 text-brand-600 dark:border-brand-500 dark:text-brand-400'
                  : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900 dark:text-gray-400 dark:hover:border-gray-700 dark:hover:text-gray-300'
              ]"
            >
              Details
            </button>
            <button
              @click="activeTab = 'contacts'"
              :class="[
                'border-b-2 py-4 text-sm font-medium transition-colors',
                activeTab === 'contacts'
                  ? 'border-brand-600 text-brand-600 dark:border-brand-500 dark:text-brand-400'
                  : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900 dark:text-gray-400 dark:hover:border-gray-700 dark:hover:text-gray-300'
              ]"
            >
              Contacts
            </button>
            <button
              @click="activeTab = 'notes'"
              :class="[
                'border-b-2 py-4 text-sm font-medium transition-colors',
                activeTab === 'notes'
                  ? 'border-brand-600 text-brand-600 dark:border-brand-500 dark:text-brand-400'
                  : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900 dark:text-gray-400 dark:hover:border-gray-700 dark:hover:text-gray-300'
              ]"
            >
              Notes
            </button>
            <button
              @click="activeTab = 'timeline'"
              :class="[
                'border-b-2 py-4 text-sm font-medium transition-colors',
                activeTab === 'timeline'
                  ? 'border-brand-600 text-brand-600 dark:border-brand-500 dark:text-brand-400'
                  : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900 dark:text-gray-400 dark:hover:border-gray-700 dark:hover:text-gray-300'
              ]"
            >
              Timeline
            </button>
          </nav>
        </div>

        <!-- Tab Content -->
        <div>
          <!-- Details Tab -->
          <div v-if="activeTab === 'details'" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Main Details Card -->
            <div class="lg:col-span-2">
              <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                  Client Information
                </h2>
                <dl class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                  <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                      <a v-if="client.email" :href="`mailto:${client.email}`" class="text-brand-600 hover:text-brand-700 dark:text-brand-400">
                        {{ client.email }}
                      </a>
                      <span v-else class="text-gray-400">—</span>
                    </dd>
                  </div>
                  <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Phone</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                      <a v-if="client.phone" :href="`tel:${client.phone}`" class="text-brand-600 hover:text-brand-700 dark:text-brand-400">
                        {{ client.phone }}
                      </a>
                      <span v-else class="text-gray-400">—</span>
                    </dd>
                  </div>
                  <div class="sm:col-span-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Address</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                      <template v-if="hasAddress">
                        <div>{{ client.address }}</div>
                        <div>{{ [client.city, client.state, client.postal_code].filter(Boolean).join(', ') }}</div>
                        <div v-if="client.country">{{ client.country }}</div>
                      </template>
                      <span v-else class="text-gray-400">—</span>
                    </dd>
                  </div>
                  <div class="sm:col-span-2" v-if="client.notes">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Notes</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white whitespace-pre-wrap">
                      {{ client.notes }}
                    </dd>
                  </div>
                </dl>
              </div>
            </div>

            <!-- Metadata Card -->
            <div class="lg:col-span-1">
              <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                  Metadata
                </h2>
                <dl class="space-y-4">
                  <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                      {{ formatDate(client.created_at) }}
                    </dd>
                  </div>
                  <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Updated</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                      {{ formatDate(client.updated_at) }}
                    </dd>
                  </div>
                  <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Client ID</dt>
                    <dd class="mt-1 font-mono text-xs text-gray-600 dark:text-gray-400">
                      {{ client.id }}
                    </dd>
                  </div>
                </dl>
              </div>
            </div>
          </div>

          <!-- Contacts Tab -->
          <div v-else-if="activeTab === 'contacts'" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Contacts
              </h2>
              <button class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Contact
              </button>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">
              Contact management will be implemented in the next phase.
            </p>
          </div>

          <!-- Notes Tab -->
          <div v-else-if="activeTab === 'notes'" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Notes
              </h2>
              <button class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Note
              </button>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">
              Note management will be implemented in the next phase.
            </p>
          </div>

          <!-- Timeline Tab -->
          <div v-else-if="activeTab === 'timeline'" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
              Activity Timeline
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">
              Timeline view will be implemented in the next phase.
            </p>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useClientStore } from '@/stores/clients'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const route = useRoute()
const clientStore = useClientStore()

const clientId = route.params.id
const loading = ref(true)
const loadError = ref(null)
const activeTab = ref('details')

const client = computed(() => clientStore.currentClient)

const hasAddress = computed(() => {
  if (!client.value) return false
  return !!(
    client.value.address ||
    client.value.city ||
    client.value.state ||
    client.value.postal_code ||
    client.value.country
  )
})

async function loadClient() {
  loading.value = true
  loadError.value = null

  try {
    await clientStore.fetchClient(clientId)
  } catch (error) {
    console.error('Failed to load client:', error)
    loadError.value = error.response?.data?.message || 'Failed to load client data. Please try again.'
  } finally {
    loading.value = false
  }
}

function formatDate(dateString) {
  if (!dateString) return '—'
  const date = new Date(dateString)
  return new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date)
}

onMounted(() => {
  loadClient()
})
</script>
