<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
        Activity Timeline
      </h3>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Chronological history of all activities and changes
      </p>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="h-8 w-8 animate-spin rounded-full border-4 border-gray-300 border-t-brand-600 dark:border-gray-700 dark:border-t-brand-500"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="rounded-lg border border-red-300 bg-red-50 p-4 dark:border-red-700 dark:bg-red-900/20">
      <div class="flex items-start gap-3">
        <svg class="h-5 w-5 flex-shrink-0 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <div class="flex-1">
          <h3 class="text-sm font-medium text-red-800 dark:text-red-300">Error Loading Timeline</h3>
          <p class="mt-1 text-sm text-red-700 dark:text-red-400">{{ error }}</p>
          <button
            @click="loadTimeline"
            class="mt-3 text-sm font-medium text-red-600 hover:text-red-700 dark:text-red-400"
          >
            Try Again
          </button>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="events.length === 0" class="rounded-lg border-2 border-dashed border-gray-300 p-12 text-center dark:border-gray-700">
      <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No activity yet</h3>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Timeline events will appear here as activities occur.</p>
    </div>

    <!-- Timeline -->
    <div v-else class="relative">
      <!-- Vertical Line -->
      <div class="absolute left-5 top-0 bottom-0 w-0.5 bg-gray-200 dark:bg-gray-800"></div>

      <!-- Events -->
      <div class="space-y-8">
        <div v-for="(event, index) in events" :key="event.id" class="relative flex gap-4">
          <!-- Icon -->
          <div class="relative z-10 flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full border-4 border-white dark:border-gray-900"
            :class="getEventIconClass(event.event_type)"
          >
            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
              <path :d="getEventIconPath(event.event_type)" />
            </svg>
          </div>

          <!-- Content -->
          <div class="flex-1 pb-8">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
              <!-- Header -->
              <div class="flex items-start justify-between gap-3">
                <div class="flex-1">
                  <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                    {{ getEventTitle(event) }}
                  </h4>
                  <div class="mt-1 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-500">
                    <span v-if="event.user_name">{{ event.user_name }}</span>
                    <span>•</span>
                    <span>{{ formatDate(event.created_at) }}</span>
                  </div>
                </div>
                <span
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="getEventBadgeClass(event.event_type)"
                >
                  {{ getEventType(event.event_type) }}
                </span>
              </div>

              <!-- Description -->
              <p v-if="event.description" class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                {{ event.description }}
              </p>

              <!-- Changes -->
              <div v-if="event.changes && Object.keys(event.changes).length > 0" class="mt-3 space-y-1">
                <div
                  v-for="(change, key) in event.changes"
                  :key="key"
                  class="text-sm"
                >
                  <span class="font-medium text-gray-700 dark:text-gray-300">{{ formatFieldName(key) }}:</span>
                  <span class="text-gray-600 dark:text-gray-400">
                    <template v-if="change.old !== undefined">
                      <span class="line-through">{{ formatValue(change.old) }}</span>
                      →
                    </template>
                    <span class="font-medium">{{ formatValue(change.new) }}</span>
                  </span>
                </div>
              </div>

              <!-- Metadata -->
              <div v-if="event.metadata" class="mt-3 rounded-lg bg-gray-50 p-3 dark:bg-gray-800/50">
                <div class="grid grid-cols-2 gap-2 text-xs">
                  <div v-for="(value, key) in event.metadata" :key="key">
                    <span class="font-medium text-gray-600 dark:text-gray-400">{{ formatFieldName(key) }}:</span>
                    <span class="ml-1 text-gray-700 dark:text-gray-300">{{ value }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Load More -->
      <div v-if="hasMore" class="mt-6 text-center">
        <button
          @click="loadMore"
          :disabled="loadingMore"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
        >
          <svg v-if="loadingMore" class="h-4 w-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          <span>{{ loadingMore ? 'Loading...' : 'Load More' }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  entityType: {
    type: String,
    required: true,
    validator: (value) => ['client', 'contact', 'project'].includes(value)
  },
  entityId: {
    type: String,
    required: true
  }
})

const loading = ref(false)
const loadingMore = ref(false)
const error = ref(null)
const events = ref([])
const currentPage = ref(1)
const hasMore = ref(false)

async function loadTimeline(page = 1) {
  if (page === 1) {
    loading.value = true
  } else {
    loadingMore.value = true
  }
  error.value = null

  try {
    const response = await axios.get(`/api/${props.entityType}s/${props.entityId}/timeline`, {
      params: { page, limit: 20 }
    })

    if (page === 1) {
      events.value = response.data.data || []
    } else {
      events.value.push(...(response.data.data || []))
    }

    hasMore.value = response.data.has_more || false
    currentPage.value = page
  } catch (err) {
    console.error('Failed to load timeline:', err)
    error.value = err.response?.data?.message || 'Failed to load timeline. Please try again.'
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

function loadMore() {
  loadTimeline(currentPage.value + 1)
}

function getEventTitle(event) {
  const titles = {
    created: `${props.entityType.charAt(0).toUpperCase() + props.entityType.slice(1)} Created`,
    updated: `${props.entityType.charAt(0).toUpperCase() + props.entityType.slice(1)} Updated`,
    deleted: `${props.entityType.charAt(0).toUpperCase() + props.entityType.slice(1)} Deleted`,
    status_changed: 'Status Changed',
    note_added: 'Note Added',
    contact_added: 'Contact Added',
    contact_updated: 'Contact Updated',
    project_created: 'Project Created',
    invoice_sent: 'Invoice Sent',
    payment_received: 'Payment Received'
  }
  return titles[event.event_type] || event.event_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
}

function getEventType(type) {
  return type.replace(/_/g, ' ').toUpperCase()
}

function getEventIconClass(type) {
  const classes = {
    created: 'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400',
    updated: 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
    deleted: 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400',
    status_changed: 'bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400',
    note_added: 'bg-yellow-100 text-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-400'
  }
  return classes[type] || 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
}

function getEventBadgeClass(type) {
  const classes = {
    created: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    updated: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    deleted: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    status_changed: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
    note_added: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'
  }
  return classes[type] || 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400'
}

function getEventIconPath(type) {
  const paths = {
    created: 'M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z',
    updated: 'M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z',
    deleted: 'M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z',
    status_changed: 'M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z',
    note_added: 'M9 2a1 1 0 000 2h2a1 1 0 100-2H9z M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z'
  }
  return paths[type] || 'M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z'
}

function formatDate(dateString) {
  if (!dateString) return ''
  const date = new Date(dateString)
  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date)
}

function formatFieldName(key) {
  return key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
}

function formatValue(value) {
  if (value === null || value === undefined || value === '') return '(empty)'
  if (typeof value === 'boolean') return value ? 'Yes' : 'No'
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}

onMounted(() => {
  loadTimeline()
})
</script>
