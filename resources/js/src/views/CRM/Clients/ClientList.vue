<template>
  <AdminLayout>
    <div class="space-y-6">
      <!-- Page Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Clients</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Manage your client companies and organizations
          </p>
        </div>
        <div class="flex gap-3">
          <button
            @click="exportToCSV"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Export CSV
          </button>
          <router-link
            to="/crm/clients/create"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Client
          </router-link>
        </div>
      </div>

      <!-- Search and Filters -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
        <div class="flex-1">
          <div class="relative">
            <input
              v-model="searchTerm"
              type="text"
              placeholder="Search clients by name, email, or company..."
              class="w-full px-4 py-2 pl-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
              @input="handleSearch"
            />
            <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>
        <div class="flex gap-3">
          <select
            v-model="activeFilter"
            @change="applyFilter"
            class="px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
          >
            <option value="all">All Clients</option>
            <option value="active">Active Only</option>
            <option value="inactive">Inactive Only</option>
          </select>
        </div>
      </div>

      <!-- Clients Table -->
      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <!-- Loading State -->
        <div v-if="clientStore.loading" class="p-8 text-center">
          <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading clients...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="clientStore.error" class="p-8 text-center">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/20">
            <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <p class="mt-4 text-sm text-gray-900 dark:text-white font-medium">{{ clientStore.error }}</p>
          <button
            @click="loadClients"
            class="mt-4 px-4 py-2 text-sm font-medium text-brand-600 hover:text-brand-700"
          >
            Try Again
          </button>
        </div>

        <!-- Empty State -->
        <div v-else-if="displayedClients.length === 0" class="p-8 text-center">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800">
            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
          <p class="mt-4 text-sm text-gray-900 dark:text-white font-medium">No clients found</p>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ searchTerm ? 'Try adjusting your search' : 'Get started by adding your first client' }}
          </p>
          <router-link
            v-if="!searchTerm"
            to="/crm/clients/create"
            class="inline-flex items-center gap-2 mt-4 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Your First Client
          </router-link>
        </div>

        <!-- Table Content -->
        <div v-else class="max-w-full overflow-x-auto custom-scrollbar">
          <table class="min-w-full">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Client</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Contact</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Projects</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Status</p>
                </th>
                <th class="px-5 py-3 text-right sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Actions</p>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr
                v-for="client in displayedClients"
                :key="client.id"
                class="border-t border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50"
              >
                <td class="px-5 py-4 sm:px-6">
                  <div>
                    <router-link
                      :to="`/crm/clients/${client.id}`"
                      class="block font-medium text-gray-800 text-theme-sm dark:text-white/90 hover:text-brand-600 dark:hover:text-brand-400"
                    >
                      {{ client.name }}
                    </router-link>
                    <span v-if="client.company" class="block text-gray-500 text-theme-xs dark:text-gray-400 mt-0.5">
                      {{ client.company }}
                    </span>
                  </div>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <div class="text-theme-sm">
                    <p v-if="client.email" class="text-gray-700 dark:text-gray-300">
                      {{ client.email }}
                    </p>
                    <p v-if="client.phone" class="text-gray-500 dark:text-gray-400 text-theme-xs mt-0.5">
                      {{ client.phone }}
                    </p>
                  </div>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    {{ client.project_count || 0 }} projects
                  </span>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <span
                    :class="[
                      'inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full',
                      client.is_active
                        ? 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400'
                        : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
                    ]"
                  >
                    <span class="w-1.5 h-1.5 rounded-full" :class="client.is_active ? 'bg-green-600' : 'bg-gray-400'"></span>
                    {{ client.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <div class="flex items-center justify-end gap-2">
                    <router-link
                      :to="`/crm/clients/${client.id}`"
                      class="p-1.5 text-gray-600 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400"
                      title="View Details"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </router-link>
                    <router-link
                      :to="`/crm/clients/${client.id}/edit`"
                      class="p-1.5 text-gray-600 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400"
                      title="Edit Client"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </router-link>
                    <button
                      @click="toggleClientStatus(client)"
                      class="p-1.5 text-gray-600 hover:text-yellow-600 dark:text-gray-400 dark:hover:text-yellow-400"
                      :title="client.is_active ? 'Deactivate' : 'Activate'"
                    >
                      <svg v-if="client.is_active" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                      </svg>
                      <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </button>
                    <button
                      @click="confirmDelete(client)"
                      class="p-1.5 text-gray-600 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
                      title="Delete Client"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination (placeholder for future implementation) -->
      <div v-if="displayedClients.length > 0" class="flex items-center justify-between">
        <p class="text-sm text-gray-700 dark:text-gray-300">
          Showing <span class="font-medium">{{ displayedClients.length }}</span> of <span class="font-medium">{{ clientStore.clientCount }}</span> clients
        </p>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useClientStore } from '@/stores/clients'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const router = useRouter()
const clientStore = useClientStore()

const searchTerm = ref('')
const activeFilter = ref('all')

// Computed
const displayedClients = computed(() => {
  let clients = clientStore.filteredClients

  // Apply active filter
  if (activeFilter.value === 'active') {
    clients = clients.filter(c => c.is_active)
  } else if (activeFilter.value === 'inactive') {
    clients = clients.filter(c => !c.is_active)
  }

  return clients
})

// Methods
async function loadClients() {
  try {
    await clientStore.fetchClients(activeFilter.value !== 'inactive')
  } catch (error) {
    console.error('Failed to load clients:', error)
  }
}

function handleSearch() {
  clientStore.setSearchTerm(searchTerm.value)
}

function applyFilter() {
  loadClients()
}

async function toggleClientStatus(client) {
  try {
    await clientStore.toggleActive(client.id)
  } catch (error) {
    console.error('Failed to toggle client status:', error)
    alert('Failed to update client status. Please try again.')
  }
}

async function confirmDelete(client) {
  // Check if client can be deleted
  try {
    const validation = await clientStore.validateDelete(client.id)

    if (!validation.can_delete) {
      alert(`Cannot delete client:\n${validation.blockers.join('\n')}`)
      return
    }

    if (confirm(`Are you sure you want to delete "${client.name}"? This action cannot be undone.`)) {
      await clientStore.deleteClient(client.id)
    }
  } catch (error) {
    console.error('Failed to delete client:', error)
    alert('Failed to delete client. Please try again.')
  }
}

function exportToCSV() {
  // Placeholder for CSV export functionality
  alert('CSV export functionality will be implemented in the CSV Import/Export component')
}

// Lifecycle
onMounted(() => {
  loadClients()
})
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  height: 8px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 4px;
}

.dark .custom-scrollbar::-webkit-scrollbar-thumb {
  background: #4b5563;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #9ca3af;
}

.dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #6b7280;
}
</style>
