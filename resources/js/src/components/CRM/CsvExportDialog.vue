<template>
  <!-- Modal Overlay -->
  <div class="fixed inset-0 z-[99999] overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
      <!-- Backdrop -->
      <div
        @click="$emit('close')"
        class="fixed inset-0 bg-gray-900/50 transition-opacity dark:bg-gray-900/80"
      ></div>

      <!-- Modal -->
      <div class="relative w-full max-w-2xl rounded-lg border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-800 dark:bg-gray-900">
        <!-- Header -->
        <div class="mb-6 flex items-start justify-between">
          <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">
              Export Clients to CSV
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              Select the fields you want to include in the export
            </p>
          </div>
          <button
            @click="$emit('close')"
            class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Export Complete State -->
        <div v-if="exportComplete" class="space-y-6">
          <div class="rounded-lg border border-green-300 bg-green-50 p-6 text-center dark:border-green-700 dark:bg-green-900/20">
            <svg class="mx-auto h-12 w-12 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="mt-4 text-lg font-semibold text-green-800 dark:text-green-300">
              Export Complete!
            </h3>
            <p class="mt-2 text-sm text-green-700 dark:text-green-400">
              Successfully exported {{ exportedCount }} client{{ exportedCount !== 1 ? 's' : '' }}
            </p>
            <p class="mt-1 text-xs text-green-600 dark:text-green-500">
              Your download should begin automatically
            </p>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end gap-3">
            <button
              @click="resetExport"
              class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            >
              Export Again
            </button>
            <button
              @click="$emit('close')"
              class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600"
            >
              Done
            </button>
          </div>
        </div>

        <!-- Export Form -->
        <div v-else class="space-y-6">
          <!-- Export Options -->
          <div>
            <label class="mb-3 block text-sm font-medium text-gray-700 dark:text-gray-400">
              Export Options
            </label>
            <div class="space-y-2">
              <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input
                  type="radio"
                  v-model="exportOptions.scope"
                  value="all"
                  class="h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900"
                />
                <span>Export all clients ({{ totalClients }} total)</span>
              </label>
              <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input
                  type="radio"
                  v-model="exportOptions.scope"
                  value="active"
                  class="h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900"
                />
                <span>Export active clients only</span>
              </label>
            </div>
          </div>

          <!-- Field Selection -->
          <div>
            <div class="mb-3 flex items-center justify-between">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-400">
                Select Fields to Export
              </label>
              <div class="flex gap-2">
                <button
                  @click="selectAllFields"
                  type="button"
                  class="text-xs text-brand-600 hover:text-brand-700 dark:text-brand-400"
                >
                  Select All
                </button>
                <span class="text-xs text-gray-400">|</span>
                <button
                  @click="deselectAllFields"
                  type="button"
                  class="text-xs text-gray-600 hover:text-gray-700 dark:text-gray-400"
                >
                  Deselect All
                </button>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/50">
              <label
                v-for="field in availableFields"
                :key="field.key"
                class="flex items-center gap-2 text-sm"
                :class="field.required ? 'font-medium text-gray-900 dark:text-white' : 'text-gray-700 dark:text-gray-300'"
              >
                <input
                  type="checkbox"
                  v-model="selectedFields[field.key]"
                  :disabled="field.required"
                  class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900"
                />
                <span>{{ field.label }}</span>
                <span v-if="field.required" class="text-xs text-gray-500 dark:text-gray-500">(required)</span>
              </label>
            </div>

            <p class="mt-2 text-xs text-gray-600 dark:text-gray-500">
              {{ selectedFieldCount }} field{{ selectedFieldCount !== 1 ? 's' : '' }} selected
            </p>
          </div>

          <!-- Export Statistics -->
          <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/50">
            <h4 class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-400">
              Export Summary
            </h4>
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span class="text-gray-600 dark:text-gray-500">Clients to export:</span>
                <span class="ml-2 font-medium text-gray-900 dark:text-white">{{ clientsToExport }}</span>
              </div>
              <div>
                <span class="text-gray-600 dark:text-gray-500">Fields selected:</span>
                <span class="ml-2 font-medium text-gray-900 dark:text-white">{{ selectedFieldCount }}</span>
              </div>
            </div>
          </div>

          <!-- Error Message -->
          <div v-if="error" class="rounded-lg border border-red-300 bg-red-50 p-4 dark:border-red-700 dark:bg-red-900/20">
            <div class="flex items-start gap-3">
              <svg class="h-5 w-5 flex-shrink-0 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
              </svg>
              <div class="flex-1">
                <h3 class="text-sm font-medium text-red-800 dark:text-red-300">
                  Export Failed
                </h3>
                <p class="mt-1 text-sm text-red-700 dark:text-red-400">
                  {{ error }}
                </p>
              </div>
            </div>
          </div>

          <!-- Progress Bar -->
          <div v-if="exporting" class="space-y-2">
            <div class="flex items-center justify-between text-sm">
              <span class="font-medium text-gray-700 dark:text-gray-300">Exporting clients...</span>
              <span class="text-gray-600 dark:text-gray-400">{{ exportProgress }}%</span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
              <div
                class="h-full bg-brand-600 transition-all duration-300 dark:bg-brand-500"
                :style="{ width: exportProgress + '%' }"
              ></div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-6 dark:border-gray-800">
            <button
              type="button"
              @click="$emit('close')"
              :disabled="exporting"
              class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            >
              Cancel
            </button>
            <button
              @click="startExport"
              :disabled="exporting || selectedFieldCount === 0"
              class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-brand-500 dark:hover:bg-brand-600"
            >
              <svg v-if="!exporting" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
              <svg v-else class="h-4 w-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              <span>{{ exporting ? 'Exporting...' : 'Export CSV' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
  totalClients: {
    type: Number,
    default: 0
  },
  activeClients: {
    type: Number,
    default: 0
  }
})

const emit = defineEmits(['close', 'exported'])

const exporting = ref(false)
const exportProgress = ref(0)
const exportComplete = ref(false)
const exportedCount = ref(0)
const error = ref(null)

const exportOptions = reactive({
  scope: 'all'
})

const availableFields = [
  { key: 'name', label: 'Name', required: true },
  { key: 'email', label: 'Email', required: false },
  { key: 'phone', label: 'Phone', required: false },
  { key: 'company', label: 'Company', required: false },
  { key: 'address', label: 'Address', required: false },
  { key: 'city', label: 'City', required: false },
  { key: 'state', label: 'State/Province', required: false },
  { key: 'postal_code', label: 'Postal Code', required: false },
  { key: 'country', label: 'Country', required: false },
  { key: 'website', label: 'Website', required: false },
  { key: 'industry', label: 'Industry', required: false },
  { key: 'status', label: 'Status', required: false },
  { key: 'created_at', label: 'Created Date', required: false },
  { key: 'updated_at', label: 'Updated Date', required: false }
]

const selectedFields = reactive(
  availableFields.reduce((acc, field) => {
    acc[field.key] = field.required || ['email', 'phone', 'company'].includes(field.key)
    return acc
  }, {})
)

const selectedFieldCount = computed(() => {
  return Object.values(selectedFields).filter(Boolean).length
})

const clientsToExport = computed(() => {
  return exportOptions.scope === 'all' ? props.totalClients : props.activeClients
})

function selectAllFields() {
  availableFields.forEach(field => {
    selectedFields[field.key] = true
  })
}

function deselectAllFields() {
  availableFields.forEach(field => {
    if (!field.required) {
      selectedFields[field.key] = false
    }
  })
}

async function startExport() {
  if (selectedFieldCount.value === 0) {
    return
  }

  exporting.value = true
  exportProgress.value = 0
  error.value = null

  try {
    // Prepare export parameters
    const fields = Object.keys(selectedFields).filter(key => selectedFields[key])

    const response = await axios.post('/api/clients/export', {
      scope: exportOptions.scope,
      fields: fields
    }, {
      responseType: 'blob',
      onDownloadProgress: (progressEvent) => {
        if (progressEvent.total) {
          exportProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total)
        }
      }
    })

    // Create download link
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `clients_export_${new Date().toISOString().split('T')[0]}.csv`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)

    // Set export complete
    exportedCount.value = clientsToExport.value
    exportComplete.value = true

    // Emit success event
    emit('exported', {
      count: exportedCount.value,
      scope: exportOptions.scope,
      fields: fields
    })
  } catch (err) {
    console.error('Export failed:', err)
    error.value = err.response?.data?.message || 'Failed to export clients. Please try again.'
    exportProgress.value = 0
  } finally {
    exporting.value = false
  }
}

function resetExport() {
  exportComplete.value = false
  exportedCount.value = 0
  exportProgress.value = 0
  error.value = null
}
</script>
