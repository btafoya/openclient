<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Export Data</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Export clients, contacts, or notes to CSV files</p>
        </div>
        <router-link to="/crm/csv/import" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
          </svg>
          Import Data
        </router-link>
      </div>

      <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <div class="space-y-6">
          <!-- Entity Type Selection -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Entity Type</label>
            <select v-model="entityType" @change="loadFields" class="w-full px-4 py-2 border border-gray-300 rounded-lg dark:bg-gray-800 dark:border-gray-600 dark:text-white">
              <option value="">Select type to export...</option>
              <option value="clients">Clients</option>
              <option value="contacts">Contacts</option>
              <option value="notes">Notes</option>
            </select>
          </div>

          <!-- Field Selection -->
          <div v-if="entityType && availableFields.length > 0">
            <div class="flex items-center justify-between mb-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fields to Export</label>
              <button @click="toggleAllFields" type="button" class="text-sm text-brand-600 hover:underline">
                {{ allFieldsSelected ? 'Deselect All' : 'Select All' }}
              </button>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 p-4 border border-gray-200 rounded-lg dark:border-gray-700">
              <label v-for="field in availableFields" :key="field.name" class="flex items-center gap-2">
                <input type="checkbox" v-model="selectedFields" :value="field.name" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ field.label || field.name }}</span>
              </label>
            </div>
          </div>

          <!-- Filters -->
          <div v-if="entityType">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Filters (Optional)</label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 border border-gray-200 rounded-lg dark:border-gray-700">
              <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Search</label>
                <input v-model="filters.search" type="text" placeholder="Search in records..." class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg dark:bg-gray-800 dark:border-gray-600 dark:text-white" />
              </div>
              <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Status</label>
                <label class="flex items-center gap-2 mt-2">
                  <input type="checkbox" v-model="filters.active_only" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                  <span class="text-sm text-gray-700 dark:text-gray-300">Active records only</span>
                </label>
              </div>
              <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Created After</label>
                <input v-model="filters.created_after" type="date" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg dark:bg-gray-800 dark:border-gray-600 dark:text-white" />
              </div>
              <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Created Before</label>
                <input v-model="filters.created_before" type="date" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg dark:bg-gray-800 dark:border-gray-600 dark:text-white" />
              </div>
            </div>
          </div>

          <!-- Error Display -->
          <div v-if="error" class="p-4 rounded-lg bg-red-50 dark:bg-red-900/20">
            <p class="text-sm text-red-700 dark:text-red-400">{{ error }}</p>
          </div>

          <!-- Success Message -->
          <div v-if="success" class="p-4 rounded-lg bg-green-50 dark:bg-green-900/20">
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <p class="text-sm text-green-700 dark:text-green-400">Export started! Your download should begin shortly.</p>
            </div>
          </div>

          <!-- Export Button -->
          <div class="flex justify-end">
            <button
              @click="exportData"
              :disabled="!entityType || selectedFields.length === 0 || exporting"
              :class="[
                'px-6 py-2 text-sm font-medium text-white rounded-lg flex items-center gap-2',
                !entityType || selectedFields.length === 0 || exporting ? 'bg-gray-400 cursor-not-allowed' : 'bg-brand-600 hover:bg-brand-700'
              ]"
            >
              <svg v-if="exporting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
              {{ exporting ? 'Exporting...' : 'Export to CSV' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Quick Export Links -->
      <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Export</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Export all data with default fields</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <button @click="quickExport('clients')" :disabled="exporting" class="flex items-center justify-center gap-2 px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-700">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            Export All Clients
          </button>
          <button @click="quickExport('contacts')" :disabled="exporting" class="flex items-center justify-center gap-2 px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-700">
            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            Export All Contacts
          </button>
          <button @click="quickExport('notes')" :disabled="exporting" class="flex items-center justify-center gap-2 px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-700">
            <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Export All Notes
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const entityType = ref('')
const availableFields = ref([])
const selectedFields = ref([])
const filters = ref({
  search: '',
  active_only: false,
  created_after: '',
  created_before: ''
})
const loading = ref(false)
const exporting = ref(false)
const error = ref(null)
const success = ref(false)

const allFieldsSelected = computed(() => {
  return availableFields.value.length > 0 && selectedFields.value.length === availableFields.value.length
})

function toggleAllFields() {
  if (allFieldsSelected.value) {
    selectedFields.value = []
  } else {
    selectedFields.value = availableFields.value.map(f => f.name)
  }
}

async function loadFields() {
  if (!entityType.value) {
    availableFields.value = []
    selectedFields.value = []
    return
  }

  loading.value = true
  error.value = null

  try {
    const response = await axios.get('/api/csv/export/fields', {
      params: { type: entityType.value }
    })

    const fields = response.data.fields || {}
    availableFields.value = Object.entries(fields).map(([name, label]) => ({ name, label }))
    selectedFields.value = availableFields.value.map(f => f.name)
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to load fields'
    availableFields.value = []
    selectedFields.value = []
  } finally {
    loading.value = false
  }
}

async function exportData() {
  if (!entityType.value || selectedFields.value.length === 0) return

  exporting.value = true
  error.value = null
  success.value = false

  try {
    const response = await axios.post('/api/csv/export', {
      entity_type: entityType.value,
      fields: selectedFields.value,
      ...filters.value
    }, {
      responseType: 'blob'
    })

    // Create download link
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `${entityType.value}_export_${new Date().toISOString().slice(0, 10)}.csv`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)

    success.value = true
    setTimeout(() => { success.value = false }, 5000)
  } catch (err) {
    if (err.response?.data instanceof Blob) {
      const text = await err.response.data.text()
      try {
        const json = JSON.parse(text)
        error.value = json.error || 'Failed to export data'
      } catch {
        error.value = 'Failed to export data'
      }
    } else {
      error.value = err.response?.data?.error || 'Failed to export data'
    }
  } finally {
    exporting.value = false
  }
}

async function quickExport(type) {
  entityType.value = type
  await loadFields()
  selectedFields.value = availableFields.value.map(f => f.name)
  filters.value = {
    search: '',
    active_only: false,
    created_after: '',
    created_before: ''
  }
  await exportData()
}
</script>
