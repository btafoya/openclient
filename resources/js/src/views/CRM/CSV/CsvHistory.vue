<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Import History</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">View past CSV import operations</p>
        </div>
        <div class="flex items-center gap-2">
          <router-link to="/crm/csv/import" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            New Import
          </router-link>
          <router-link to="/crm/csv/export" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Export Data
          </router-link>
        </div>
      </div>

      <!-- Statistics -->
      <div v-if="stats" class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="p-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
          <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_imports || 0 }}</p>
          <p class="text-sm text-gray-500 dark:text-gray-400">Total Imports</p>
        </div>
        <div class="p-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
          <p class="text-2xl font-bold text-green-600">{{ stats.successful_imports || 0 }}</p>
          <p class="text-sm text-gray-500 dark:text-gray-400">Successful</p>
        </div>
        <div class="p-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
          <p class="text-2xl font-bold text-red-600">{{ stats.failed_imports || 0 }}</p>
          <p class="text-sm text-gray-500 dark:text-gray-400">Failed</p>
        </div>
        <div class="p-4 rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
          <p class="text-2xl font-bold text-blue-600">{{ stats.total_records_imported || 0 }}</p>
          <p class="text-sm text-gray-500 dark:text-gray-400">Records Imported</p>
        </div>
      </div>

      <!-- History Table -->
      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div v-if="loading" class="p-8 text-center">
          <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading history...</p>
        </div>
        <div v-else-if="error" class="p-8 text-center">
          <p class="text-sm text-red-700 dark:text-red-400">{{ error }}</p>
          <button @click="loadHistory" class="mt-2 text-sm text-brand-600 hover:underline">Try again</button>
        </div>
        <div v-else-if="imports.length === 0" class="p-8 text-center">
          <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <p class="mt-2 text-sm text-gray-900 dark:text-white font-medium">No imports yet</p>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Start by importing a CSV file</p>
        </div>
        <table v-else class="min-w-full">
          <thead>
            <tr class="border-b border-gray-200 dark:border-gray-700">
              <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-xs dark:text-gray-400">File</p></th>
              <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-xs dark:text-gray-400">Type</p></th>
              <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-xs dark:text-gray-400">Status</p></th>
              <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-xs dark:text-gray-400">Records</p></th>
              <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-xs dark:text-gray-400">Date</p></th>
              <th class="px-5 py-3 text-right"><p class="font-medium text-gray-500 text-xs dark:text-gray-400">Actions</p></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="imp in imports" :key="imp.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
              <td class="px-5 py-4">
                <div class="flex items-center gap-2">
                  <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ imp.filename }}</p>
                    <p class="text-xs text-gray-500">{{ formatFileSize(imp.file_size) }}</p>
                  </div>
                </div>
              </td>
              <td class="px-5 py-4">
                <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full capitalize" :class="getTypeClasses(imp.entity_type)">
                  {{ imp.entity_type }}
                </span>
              </td>
              <td class="px-5 py-4">
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 text-xs font-medium rounded-full" :class="getStatusClasses(imp.status)">
                  <span v-if="imp.status === 'processing'" class="w-2 h-2 rounded-full bg-current animate-pulse"></span>
                  {{ imp.status }}
                </span>
              </td>
              <td class="px-5 py-4">
                <div class="text-sm">
                  <span class="text-green-600">{{ imp.rows_imported || 0 }}</span>
                  <span class="text-gray-400"> / </span>
                  <span class="text-gray-600 dark:text-gray-300">{{ imp.rows_processed || 0 }}</span>
                  <span v-if="imp.rows_failed > 0" class="text-red-600 ml-1">({{ imp.rows_failed }} failed)</span>
                </div>
              </td>
              <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                {{ formatDate(imp.created_at) }}
              </td>
              <td class="px-5 py-4">
                <div class="flex items-center justify-end gap-2">
                  <button
                    v-if="imp.status === 'pending' || imp.status === 'processing'"
                    @click="cancelImport(imp)"
                    class="p-1.5 text-gray-600 hover:text-red-600 dark:text-gray-400"
                    title="Cancel"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                  <button
                    @click="deleteImport(imp)"
                    class="p-1.5 text-gray-600 hover:text-red-600 dark:text-gray-400"
                    title="Delete"
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
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const loading = ref(false)
const error = ref(null)
const imports = ref([])
const stats = ref(null)

async function loadHistory() {
  loading.value = true
  error.value = null

  try {
    const [historyRes, statsRes] = await Promise.all([
      axios.get('/api/csv/imports'),
      axios.get('/api/csv/import/statistics')
    ])

    imports.value = historyRes.data || []
    stats.value = statsRes.data || {}
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to load import history'
  } finally {
    loading.value = false
  }
}

async function cancelImport(imp) {
  if (!confirm('Cancel this import?')) return

  try {
    await axios.post(`/api/csv/import/${imp.id}/cancel`)
    await loadHistory()
  } catch (err) {
    alert(err.response?.data?.error || 'Failed to cancel import')
  }
}

async function deleteImport(imp) {
  if (!confirm('Delete this import record?')) return

  try {
    await axios.delete(`/api/csv/import/${imp.id}`)
    await loadHistory()
  } catch (err) {
    alert(err.response?.data?.error || 'Failed to delete import')
  }
}

function formatFileSize(bytes) {
  if (!bytes) return '0 B'
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function formatDate(dateString) {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function getTypeClasses(type) {
  const classes = {
    clients: 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
    contacts: 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400',
    notes: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400'
  }
  return classes[type] || classes.clients
}

function getStatusClasses(status) {
  const classes = {
    pending: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
    processing: 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
    completed: 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400',
    partial: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400',
    failed: 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400',
    cancelled: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
  }
  return classes[status] || classes.pending
}

onMounted(() => loadHistory())
</script>
