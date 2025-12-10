<template>
  <AdminLayout>
    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Timeline</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">View chronological activity across clients, contacts, notes, and projects</p>
      </div>

      <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
        <select v-model="entityFilter" @change="loadEvents" class="px-4 py-2 text-sm border border-gray-300 rounded-lg dark:bg-gray-800 dark:border-gray-600 dark:text-white">
          <option value="">All Types</option>
          <option value="client">Clients</option>
          <option value="contact">Contacts</option>
          <option value="note">Notes</option>
          <option value="project">Projects</option>
          <option value="task">Tasks</option>
          <option value="invoice">Invoices</option>
        </select>
        <select v-model="eventTypeFilter" @change="loadEvents" class="px-4 py-2 text-sm border border-gray-300 rounded-lg dark:bg-gray-800 dark:border-gray-600 dark:text-white">
          <option value="">All Events</option>
          <option value="created">Created</option>
          <option value="updated">Updated</option>
          <option value="deleted">Deleted</option>
          <option value="status_changed">Status Changed</option>
        </select>
        <div class="flex-1 relative">
          <input
            v-model="searchTerm"
            @input="debouncedSearch"
            type="text"
            placeholder="Search timeline..."
            class="w-full px-4 py-2 pl-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
          />
          <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
        <button
          @click="loadEvents"
          class="px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
        >
          Refresh
        </button>
      </div>

      <div class="space-y-4">
        <div v-if="loading" class="p-8 text-center">
          <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading timeline...</p>
        </div>
        <div v-else-if="error" class="p-8 text-center rounded-lg border border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20">
          <p class="text-sm text-red-700 dark:text-red-400">{{ error }}</p>
          <button @click="loadEvents" class="mt-2 text-sm text-red-600 hover:underline">Try again</button>
        </div>
        <div v-else-if="events.length === 0" class="p-8 text-center rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
          <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p class="mt-2 text-sm text-gray-900 dark:text-white font-medium">No events found</p>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try adjusting your filters or check back later</p>
        </div>
        <div v-else class="space-y-4">
          <div
            v-for="event in events"
            :key="event.id"
            class="flex gap-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 hover:shadow-sm transition-shadow"
          >
            <div :class="getIconClasses(event.entity_type)" class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full">
              <component :is="getIcon(event.entity_type)" class="h-5 w-5" />
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ event.description }}
                  </p>
                  <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <router-link
                      v-if="event.entity_url"
                      :to="getVueRoute(event)"
                      class="text-brand-600 hover:underline"
                    >
                      {{ event.entity_name }}
                    </router-link>
                    <span v-if="event.user_first_name">
                      by {{ event.user_first_name }} {{ event.user_last_name }}
                    </span>
                  </div>
                </div>
                <span class="flex-shrink-0 text-xs text-gray-500 dark:text-gray-400">
                  {{ formatDate(event.created_at) }}
                </span>
              </div>
              <div class="mt-2 flex flex-wrap gap-2">
                <span :class="getTypeClasses(event.entity_type)" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-medium rounded-full capitalize">
                  {{ event.entity_type }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 capitalize">
                  {{ event.event_type.replace('_', ' ') }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, h, onMounted } from 'vue'
import axios from 'axios'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const loading = ref(false)
const error = ref(null)
const entityFilter = ref('')
const eventTypeFilter = ref('')
const searchTerm = ref('')
const events = ref([])

let debounceTimer = null

async function loadEvents() {
  loading.value = true
  error.value = null

  try {
    const params = new URLSearchParams()
    if (entityFilter.value) params.append('entity_type', entityFilter.value)
    if (eventTypeFilter.value) params.append('event_type', eventTypeFilter.value)
    if (searchTerm.value) params.append('search', searchTerm.value)
    params.append('limit', '50')

    const response = await axios.get(`/api/timeline?${params.toString()}`)
    events.value = response.data || []
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to load timeline'
    console.error('Timeline error:', err)
  } finally {
    loading.value = false
  }
}

function debouncedSearch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    loadEvents()
  }, 300)
}

function getVueRoute(event) {
  const routes = {
    client: `/crm/clients/${event.entity_id}`,
    contact: `/crm/contacts/${event.entity_id}`,
    project: `/projects/${event.entity_id}`,
    note: `/crm/notes`,
    task: `/projects`,
    invoice: `/invoices/${event.entity_id}`,
  }
  return routes[event.entity_type] || '#'
}

function getIconClasses(type) {
  const classes = {
    client: 'bg-blue-100 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400',
    contact: 'bg-green-100 text-green-600 dark:bg-green-900/20 dark:text-green-400',
    note: 'bg-yellow-100 text-yellow-600 dark:bg-yellow-900/20 dark:text-yellow-400',
    project: 'bg-purple-100 text-purple-600 dark:bg-purple-900/20 dark:text-purple-400',
    task: 'bg-orange-100 text-orange-600 dark:bg-orange-900/20 dark:text-orange-400',
    invoice: 'bg-pink-100 text-pink-600 dark:bg-pink-900/20 dark:text-pink-400',
  }
  return classes[type] || classes.client
}

function getTypeClasses(type) {
  return getIconClasses(type)
}

function getIcon(type) {
  const icons = {
    client: h('svg', { fill: 'currentColor', viewBox: '0 0 20 20' }, [
      h('path', { d: 'M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 0v12h8V4H6z' })
    ]),
    contact: h('svg', { fill: 'currentColor', viewBox: '0 0 20 20' }, [
      h('path', { 'fill-rule': 'evenodd', d: 'M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z', 'clip-rule': 'evenodd' })
    ]),
    note: h('svg', { fill: 'currentColor', viewBox: '0 0 20 20' }, [
      h('path', { 'fill-rule': 'evenodd', d: 'M18 13V5a2 2 0 00-2-2H4a2 2 0 00-2 2v8a2 2 0 002 2h3l3 3 3-3h3a2 2 0 002-2zM5 7a1 1 0 011-1h8a1 1 0 110 2H6a1 1 0 01-1-1zm1 3a1 1 0 100 2h3a1 1 0 100-2H6z', 'clip-rule': 'evenodd' })
    ]),
    project: h('svg', { fill: 'currentColor', viewBox: '0 0 20 20' }, [
      h('path', { 'fill-rule': 'evenodd', d: 'M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm0 6a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1v-2zm1 5a1 1 0 00-1 1v2a1 1 0 001 1h12a1 1 0 001-1v-2a1 1 0 00-1-1H4z', 'clip-rule': 'evenodd' })
    ]),
    task: h('svg', { fill: 'currentColor', viewBox: '0 0 20 20' }, [
      h('path', { 'fill-rule': 'evenodd', d: 'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z', 'clip-rule': 'evenodd' })
    ]),
    invoice: h('svg', { fill: 'currentColor', viewBox: '0 0 20 20' }, [
      h('path', { 'fill-rule': 'evenodd', d: 'M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z', 'clip-rule': 'evenodd' })
    ]),
  }
  return icons[type] || icons.client
}

function formatDate(dateString) {
  if (!dateString) return ''
  const date = new Date(dateString)
  const now = new Date()
  const diff = now - date
  const diffDays = Math.floor(diff / (1000 * 60 * 60 * 24))

  if (diffDays === 0) {
    return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
  } else if (diffDays === 1) {
    return 'Yesterday'
  } else if (diffDays < 7) {
    return date.toLocaleDateString('en-US', { weekday: 'short' })
  } else {
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  }
}

onMounted(() => loadEvents())
</script>
