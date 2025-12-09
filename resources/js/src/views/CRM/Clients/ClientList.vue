<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Clients</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage your client companies</p>
        </div>
        <router-link to="/crm/clients/create" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          New Client
        </router-link>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Total Clients</p>
          <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ clientStore.clientCount }}</p>
        </div>
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Active Clients</p>
          <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ clientStore.activeClientCount }}</p>
        </div>
      </div>

      <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
        <div class="flex-1 relative">
          <input v-model="searchTerm" @input="handleSearch" type="text" placeholder="Search clients..." class="w-full px-4 py-2 pl-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white" />
          <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
        <select v-model="activeFilter" @change="applyFilter" class="px-4 py-2 text-sm border border-gray-300 rounded-lg dark:bg-gray-800 dark:border-gray-600 dark:text-white">
          <option value="all">All Clients</option>
          <option value="active">Active Only</option>
          <option value="inactive">Inactive Only</option>
        </select>
      </div>

      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div v-if="clientStore.loading" class="p-8 text-center">
          <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading...</p>
        </div>
        <div v-else-if="displayedClients.length === 0" class="p-8 text-center">
          <p class="text-sm text-gray-900 dark:text-white font-medium">No clients found</p>
        </div>
        <table v-else class="min-w-full">
          <thead><tr class="border-b border-gray-200 dark:border-gray-700">
            <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-xs dark:text-gray-400">Name</p></th>
            <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-xs dark:text-gray-400">Email</p></th>
            <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-xs dark:text-gray-400">Phone</p></th>
            <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-xs dark:text-gray-400">Company</p></th>
            <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-xs dark:text-gray-400">Status</p></th>
            <th class="px-5 py-3 text-right"><p class="font-medium text-gray-500 text-xs dark:text-gray-400">Actions</p></th>
          </tr></thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="client in displayedClients" :key="client.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
              <td class="px-5 py-4">
                <router-link :to="`/crm/clients/${client.id}`" class="font-medium text-gray-800 dark:text-white hover:text-brand-600">{{ client.name }}</router-link>
              </td>
              <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">{{ client.email || '—' }}</td>
              <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">{{ client.phone || '—' }}</td>
              <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">{{ client.company || '—' }}</td>
              <td class="px-5 py-4">
                <span :class="client.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full">
                  {{ client.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-5 py-4">
                <div class="flex items-center justify-end gap-2">
                  <router-link :to="`/crm/clients/${client.id}`" class="p-1.5 text-gray-600 hover:text-brand-600 dark:text-gray-400" title="View">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                  </router-link>
                  <router-link :to="`/crm/clients/${client.id}/edit`" class="p-1.5 text-gray-600 hover:text-brand-600 dark:text-gray-400" title="Edit">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                  </router-link>
                  <button @click="toggleClientStatus(client)" class="p-1.5 text-gray-600 hover:text-yellow-600 dark:text-gray-400" title="Toggle">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                  </button>
                  <button @click="confirmDelete(client)" class="p-1.5 text-gray-600 hover:text-red-600 dark:text-gray-400" title="Delete">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useClientStore } from '@/stores/clients'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const clientStore = useClientStore()
const searchTerm = ref('')
const activeFilter = ref('all')

const displayedClients = computed(() => {
  let clients = clientStore.filteredClients
  if (activeFilter.value === 'active') clients = clients.filter(c => c.is_active)
  else if (activeFilter.value === 'inactive') clients = clients.filter(c => !c.is_active)
  return clients
})

async function loadClients() {
  await clientStore.fetchClients(activeFilter.value !== 'inactive')
}

function handleSearch() {
  clientStore.setSearchTerm(searchTerm.value)
}

function applyFilter() {
  loadClients()
}

async function toggleClientStatus(client) {
  await clientStore.toggleActive(client.id)
}

async function confirmDelete(client) {
  if (confirm(`Delete "${client.name}"?`)) {
    await clientStore.deleteClient(client.id)
  }
}

onMounted(() => loadClients())
</script>
