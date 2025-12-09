<template>
  <AdminLayout>
    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Timeline</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">View chronological activity across clients, contacts, notes, and projects</p>
      </div>

      <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
        <select v-model="entityFilter" @change="applyFilters" class="px-4 py-2 text-sm border border-gray-300 rounded-lg dark:bg-gray-800 dark:border-gray-600 dark:text-white">
          <option value="all">All Types</option>
          <option value="client">Clients</option>
          <option value="contact">Contacts</option>
          <option value="note">Notes</option>
          <option value="project">Projects</option>
        </select>
        <div class="flex gap-2">
          <input v-model="dateFrom" type="date" class="px-4 py-2 text-sm border border-gray-300 rounded-lg dark:bg-gray-800 dark:border-gray-600 dark:text-white" />
          <input v-model="dateTo" type="date" class="px-4 py-2 text-sm border border-gray-300 rounded-lg dark:bg-gray-800 dark:border-gray-600 dark:text-white" />
          <button @click="applyFilters" class="px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700">Filter</button>
        </div>
      </div>

      <div class="space-y-4">
        <div v-if="loading" class="p-8 text-center">
          <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading timeline...</p>
        </div>
        <div v-else-if="events.length === 0" class="p-8 text-center rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
          <p class="text-sm text-gray-900 dark:text-white font-medium">No events found</p>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try adjusting your filters</p>
        </div>
        <div v-else class="space-y-4">
          <div v-for="event in events" :key="event.id" class="flex gap-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div :class="getIconClasses(event.type)" class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full">
              <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" /></svg>
            </div>
            <div class="flex-1">
              <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ event.title }}</p>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(event.timestamp) }}</span>
              </div>
              <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ event.description }}</p>
              <span :class="getTypeClasses(event.type)" class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-medium rounded-full">{{ event.type }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const loading = ref(false)
const entityFilter = ref('all')
const dateFrom = ref('')
const dateTo = ref('')
const events = ref([])

function applyFilters() {
  loadEvents()
}

async function loadEvents() {
  loading.value = true
  setTimeout(() => {
    events.value = [
      { id: 1, type: 'client', title: 'New Client Created', description: 'Acme Corporation added to CRM', timestamp: new Date().toISOString() },
      { id: 2, type: 'contact', title: 'Contact Updated', description: 'John Doe contact information updated', timestamp: new Date(Date.now() - 86400000).toISOString() },
      { id: 3, type: 'note', title: 'Note Added', description: 'Meeting notes added to client record', timestamp: new Date(Date.now() - 172800000).toISOString() },
    ]
    loading.value = false
  }, 500)
}

function getIconClasses(type) {
  const classes = { client: 'bg-blue-100 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400', contact: 'bg-green-100 text-green-600 dark:bg-green-900/20 dark:text-green-400', note: 'bg-yellow-100 text-yellow-600 dark:bg-yellow-900/20 dark:text-yellow-400', project: 'bg-purple-100 text-purple-600 dark:bg-purple-900/20 dark:text-purple-400' }
  return classes[type] || classes.client
}

function getTypeClasses(type) {
  return getIconClasses(type)
}

function formatDate(dateString) {
  return new Date(dateString).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

onMounted(() => loadEvents())
</script>
