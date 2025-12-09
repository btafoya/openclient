<template>
  <AdminLayout>
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="h-12 w-12 animate-spin rounded-full border-4 border-gray-300 border-t-brand-600"></div>
    </div>
    <div v-else-if="client" class="mx-auto max-w-7xl space-y-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex-1">
          <div class="flex items-center gap-3">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">{{ client.name }}</h1>
            <span :class="client.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium">
              {{ client.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
          <p v-if="client.company" class="mt-2 text-gray-600 dark:text-gray-400">{{ client.company }}</p>
        </div>
        <div class="flex gap-3">
          <router-link to="/crm/clients" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            Back
          </router-link>
          <router-link :to="`/crm/clients/${client.id}/edit`" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
            Edit
          </router-link>
        </div>
      </div>

      <div class="border-b border-gray-200 dark:border-gray-800">
        <nav class="-mb-px flex gap-8">
          <button @click="activeTab = 'details'" :class="activeTab === 'details' ? 'border-brand-600 text-brand-600 dark:border-brand-500 dark:text-brand-400' : 'border-transparent text-gray-600 hover:border-gray-300 dark:text-gray-400'" class="border-b-2 py-4 text-sm font-medium">Details</button>
          <button @click="activeTab = 'contacts'" :class="activeTab === 'contacts' ? 'border-brand-600 text-brand-600 dark:border-brand-500 dark:text-brand-400' : 'border-transparent text-gray-600 hover:border-gray-300 dark:text-gray-400'" class="border-b-2 py-4 text-sm font-medium">Contacts</button>
          <button @click="activeTab = 'notes'" :class="activeTab === 'notes' ? 'border-brand-600 text-brand-600 dark:border-brand-500 dark:text-brand-400' : 'border-transparent text-gray-600 hover:border-gray-300 dark:text-gray-400'" class="border-b-2 py-4 text-sm font-medium">Notes</button>
          <button @click="activeTab = 'projects'" :class="activeTab === 'projects' ? 'border-brand-600 text-brand-600 dark:border-brand-500 dark:text-brand-400' : 'border-transparent text-gray-600 hover:border-gray-300 dark:text-gray-400'" class="border-b-2 py-4 text-sm font-medium">Projects</button>
        </nav>
      </div>

      <div v-if="activeTab === 'details'" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
          <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Contact Information</h2>
          <dl class="space-y-4">
            <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt><dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ client.email || '—' }}</dd></div>
            <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Phone</dt><dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ client.phone || '—' }}</dd></div>
            <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Website</dt><dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ client.website || '—' }}</dd></div>
          </dl>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
          <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Address</h2>
          <dl class="space-y-4">
            <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Street</dt><dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ client.address || '—' }}</dd></div>
            <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">City, State ZIP</dt><dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ [client.city, client.state, client.zip].filter(Boolean).join(', ') || '—' }}</dd></div>
            <div><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Country</dt><dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ client.country || '—' }}</dd></div>
          </dl>
        </div>
      </div>
      <div v-else-if="activeTab === 'contacts'" class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <p class="text-sm text-gray-600 dark:text-gray-400">Related contacts will be displayed here.</p>
      </div>
      <div v-else-if="activeTab === 'notes'" class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <p class="text-sm text-gray-600 dark:text-gray-400">Client notes will be displayed here.</p>
      </div>
      <div v-else-if="activeTab === 'projects'" class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <p class="text-sm text-gray-600 dark:text-gray-400">Client projects will be displayed here.</p>
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
const loading = ref(false)
const activeTab = ref('details')
const client = computed(() => clientStore.currentClient)

async function loadClient() {
  loading.value = true
  try {
    await clientStore.fetchClient(route.params.id)
  } catch (error) {
    console.error('Failed to load client:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => loadClient())
</script>
