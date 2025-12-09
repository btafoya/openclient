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
      <div class="relative w-full max-w-4xl rounded-lg border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-800 dark:bg-gray-900">
        <!-- Header -->
        <div class="mb-6 flex items-start justify-between">
          <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">
              Import Clients from CSV
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              Step {{ currentStep }} of 3: {{ stepTitles[currentStep - 1] }}
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

        <!-- Progress Bar -->
        <div class="mb-6 flex items-center gap-2">
          <div v-for="step in 3" :key="step" class="flex-1">
            <div
              class="h-2 rounded-full transition-colors"
              :class="step <= currentStep ? 'bg-brand-600 dark:bg-brand-500' : 'bg-gray-200 dark:bg-gray-800'"
            ></div>
          </div>
        </div>

        <!-- Step Content -->
        <div class="min-h-[400px]">
          <!-- Step 1: Upload File -->
          <div v-if="currentStep === 1">
            <div class="space-y-6">
              <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Upload CSV File</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                  Select a CSV file containing client data to import.
                </p>
              </div>

              <!-- File Upload -->
              <div
                @dragover.prevent="dragover = true"
                @dragleave.prevent="dragover = false"
                @drop.prevent="handleDrop"
                class="relative"
              >
                <input
                  ref="fileInput"
                  type="file"
                  accept=".csv"
                  @change="handleFileSelect"
                  class="sr-only"
                />
                <div
                  @click="$refs.fileInput.click()"
                  class="cursor-pointer rounded-lg border-2 border-dashed p-12 text-center transition-colors"
                  :class="[
                    dragover
                      ? 'border-brand-500 bg-brand-50 dark:border-brand-400 dark:bg-brand-900/20'
                      : 'border-gray-300 hover:border-gray-400 dark:border-gray-700 dark:hover:border-gray-600'
                  ]"
                >
                  <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                  </svg>
                  <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                    <span class="text-brand-600 dark:text-brand-400">Upload a file</span> or drag and drop
                  </p>
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">
                    CSV files up to 10MB
                  </p>
                </div>
              </div>

              <!-- Selected File -->
              <div v-if="selectedFile" class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-800/50">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <svg class="h-8 w-8 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                    </svg>
                    <div>
                      <p class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedFile.name }}</p>
                      <p class="text-xs text-gray-500 dark:text-gray-500">{{ formatFileSize(selectedFile.size) }}</p>
                    </div>
                  </div>
                  <button
                    @click="selectedFile = null; previewData = null"
                    class="text-red-600 hover:text-red-700 dark:text-red-400"
                  >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Template Download -->
              <div class="rounded-lg border border-brand-200 bg-brand-50 p-4 dark:border-brand-800 dark:bg-brand-900/20">
                <div class="flex items-start gap-3">
                  <svg class="h-5 w-5 flex-shrink-0 text-brand-600 dark:text-brand-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                  </svg>
                  <div class="flex-1">
                    <h4 class="text-sm font-medium text-brand-900 dark:text-brand-300">Need a template?</h4>
                    <p class="mt-1 text-sm text-brand-700 dark:text-brand-400">
                      Download our CSV template with the correct format and column headers.
                    </p>
                    <button
                      @click="downloadTemplate"
                      class="mt-2 text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                    >
                      Download Template →
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Step 2: Map Fields -->
          <div v-else-if="currentStep === 2">
            <div class="space-y-6">
              <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Map CSV Columns</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                  Match your CSV columns to the appropriate client fields.
                </p>
              </div>

              <!-- Preview Table -->
              <div v-if="previewData" class="rounded-lg border border-gray-200 dark:border-gray-800 overflow-hidden">
                <div class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                      <tr>
                        <th v-for="header in previewData.headers" :key="header" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">
                          {{ header }}
                        </th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900">
                      <tr v-for="(row, index) in previewData.rows.slice(0, 3)" :key="index">
                        <td v-for="(cell, cellIndex) in row" :key="cellIndex" class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                          {{ cell }}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div class="bg-gray-50 px-4 py-2 text-xs text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                  Showing 3 of {{ previewData.totalRows }} rows
                </div>
              </div>

              <!-- Field Mapping -->
              <div class="space-y-3">
                <div v-for="field in requiredFields" :key="field.key" class="grid grid-cols-2 gap-4 items-center">
                  <label class="text-sm font-medium text-gray-700 dark:text-gray-400">
                    {{ field.label }}
                    <span v-if="field.required" class="text-red-500">*</span>
                  </label>
                  <select
                    v-model="fieldMapping[field.key]"
                    class="h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                  >
                    <option value="">-- Select Column --</option>
                    <option v-for="header in previewData?.headers" :key="header" :value="header">
                      {{ header }}
                    </option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- Step 3: Review & Import -->
          <div v-else-if="currentStep === 3">
            <div class="space-y-6">
              <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Review & Import</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                  Review your import settings and start the import process.
                </p>
              </div>

              <!-- Summary -->
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-800/50">
                  <p class="text-sm text-gray-600 dark:text-gray-400">Total Rows</p>
                  <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ previewData?.totalRows || 0 }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-800/50">
                  <p class="text-sm text-gray-600 dark:text-gray-400">Mapped Fields</p>
                  <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ Object.keys(fieldMapping).filter(k => fieldMapping[k]).length }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-800/50">
                  <p class="text-sm text-gray-600 dark:text-gray-400">File Size</p>
                  <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ formatFileSize(selectedFile?.size || 0) }}</p>
                </div>
              </div>

              <!-- Import Progress -->
              <div v-if="importing" class="space-y-3">
                <div class="flex items-center justify-between">
                  <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Importing...</span>
                  <span class="text-sm text-gray-600 dark:text-gray-400">{{ importProgress }}%</span>
                </div>
                <div class="h-2 rounded-full bg-gray-200 dark:bg-gray-800">
                  <div
                    class="h-2 rounded-full bg-brand-600 transition-all dark:bg-brand-500"
                    :style="{ width: `${importProgress}%` }"
                  ></div>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                  Imported {{ importedCount }} of {{ previewData?.totalRows || 0 }} rows
                </p>
              </div>

              <!-- Import Result -->
              <div v-if="importComplete" class="rounded-lg border border-green-300 bg-green-50 p-4 dark:border-green-700 dark:bg-green-900/20">
                <div class="flex items-start gap-3">
                  <svg class="h-6 w-6 flex-shrink-0 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                  <div class="flex-1">
                    <h4 class="text-sm font-medium text-green-800 dark:text-green-300">Import Complete!</h4>
                    <p class="mt-1 text-sm text-green-700 dark:text-green-400">
                      Successfully imported {{ importedCount }} clients.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="mt-6 flex items-center justify-between border-t border-gray-200 pt-6 dark:border-gray-800">
          <button
            v-if="currentStep > 1 && !importing"
            @click="currentStep--"
            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
          >
            ← Back
          </button>
          <div v-else></div>

          <div class="flex gap-3">
            <button
              v-if="!importComplete"
              @click="$emit('close')"
              :disabled="importing"
              class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            >
              Cancel
            </button>
            <button
              v-if="currentStep < 3"
              @click="nextStep"
              :disabled="!canProceed"
              class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-brand-500 dark:hover:bg-brand-600"
            >
              Next →
            </button>
            <button
              v-else-if="!importComplete"
              @click="startImport"
              :disabled="importing || !canProceed"
              class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-brand-500 dark:hover:bg-brand-600"
            >
              {{ importing ? 'Importing...' : 'Start Import' }}
            </button>
            <button
              v-else
              @click="$emit('imported'); $emit('close')"
              class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-green-700 dark:bg-green-500 dark:hover:bg-green-600"
            >
              Done
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'

const emit = defineEmits(['close', 'imported'])

const currentStep = ref(1)
const stepTitles = ['Upload File', 'Map Fields', 'Import']
const dragover = ref(false)
const selectedFile = ref(null)
const previewData = ref(null)
const fieldMapping = ref({})
const importing = ref(false)
const importProgress = ref(0)
const importedCount = ref(0)
const importComplete = ref(false)

const requiredFields = [
  { key: 'name', label: 'Client Name', required: true },
  { key: 'email', label: 'Email', required: false },
  { key: 'phone', label: 'Phone', required: false },
  { key: 'company', label: 'Company', required: false },
  { key: 'address', label: 'Address', required: false },
  { key: 'city', label: 'City', required: false },
  { key: 'state', label: 'State', required: false },
  { key: 'postal_code', label: 'Postal Code', required: false },
  { key: 'country', label: 'Country', required: false }
]

const canProceed = computed(() => {
  if (currentStep.value === 1) return selectedFile.value !== null
  if (currentStep.value === 2) return fieldMapping.value.name !== '' && fieldMapping.value.name !== undefined
  if (currentStep.value === 3) return true
  return false
})

function handleDrop(e) {
  dragover.value = false
  const files = e.dataTransfer.files
  if (files.length > 0 && files[0].type === 'text/csv') {
    selectedFile.value = files[0]
    parseCSV(files[0])
  }
}

function handleFileSelect(e) {
  const files = e.target.files
  if (files.length > 0) {
    selectedFile.value = files[0]
    parseCSV(files[0])
  }
}

function parseCSV(file) {
  const reader = new FileReader()
  reader.onload = (e) => {
    const text = e.target.result
    const lines = text.split('\n').filter(line => line.trim())
    const headers = lines[0].split(',').map(h => h.trim())
    const rows = lines.slice(1).map(line => line.split(',').map(cell => cell.trim()))

    previewData.value = {
      headers,
      rows,
      totalRows: rows.length
    }
  }
  reader.readAsText(file)
}

function downloadTemplate() {
  const template = 'name,email,phone,company,address,city,state,postal_code,country\nJohn Doe,john@example.com,555-0100,Acme Corp,123 Main St,Springfield,IL,62701,USA'
  const blob = new Blob([template], { type: 'text/csv' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'client-import-template.csv'
  a.click()
  URL.revokeObjectURL(url)
}

function nextStep() {
  if (canProceed.value) {
    currentStep.value++
  }
}

async function startImport() {
  importing.value = true
  importProgress.value = 0
  importedCount.value = 0

  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value)
    formData.append('mapping', JSON.stringify(fieldMapping.value))

    const response = await axios.post('/api/clients/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (progressEvent) => {
        importProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total)
      }
    })

    importedCount.value = response.data.imported || previewData.value.totalRows
    importProgress.value = 100
    importComplete.value = true
  } catch (error) {
    console.error('Import failed:', error)
    alert('Failed to import clients. Please try again.')
  } finally {
    importing.value = false
  }
}

function formatFileSize(bytes) {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}
</script>
