<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Import Data</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Import clients, contacts, or notes from CSV files</p>
        </div>
        <router-link to="/crm/csv/history" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Import History
        </router-link>
      </div>

      <!-- Step Indicator -->
      <div class="flex items-center justify-center gap-4">
        <div v-for="(step, idx) in steps" :key="step.name" class="flex items-center">
          <div :class="[
            'flex items-center justify-center w-8 h-8 rounded-full text-sm font-medium',
            currentStep >= idx ? 'bg-brand-600 text-white' : 'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
          ]">
            {{ idx + 1 }}
          </div>
          <span :class="[
            'ml-2 text-sm font-medium',
            currentStep >= idx ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400'
          ]">{{ step.name }}</span>
          <svg v-if="idx < steps.length - 1" class="w-5 h-5 mx-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </div>
      </div>

      <!-- Step 1: Upload -->
      <div v-if="currentStep === 0" class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Step 1: Upload CSV File</h2>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Entity Type</label>
            <select v-model="entityType" class="w-full px-4 py-2 border border-gray-300 rounded-lg dark:bg-gray-800 dark:border-gray-600 dark:text-white">
              <option value="">Select type...</option>
              <option value="clients">Clients</option>
              <option value="contacts">Contacts</option>
              <option value="notes">Notes</option>
            </select>
          </div>

          <div v-if="entityType" class="flex items-center gap-4">
            <a :href="`/api/csv/import/template/${entityType}`" class="text-sm text-brand-600 hover:underline flex items-center gap-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
              Download Template
            </a>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">CSV File</label>
            <div
              @dragover.prevent="dragOver = true"
              @dragleave="dragOver = false"
              @drop.prevent="handleDrop"
              :class="[
                'border-2 border-dashed rounded-lg p-8 text-center transition-colors',
                dragOver ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/10' : 'border-gray-300 dark:border-gray-600'
              ]"
            >
              <input type="file" ref="fileInput" @change="handleFileSelect" accept=".csv" class="hidden" />
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
              <p v-if="!selectedFile" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                <button type="button" @click="$refs.fileInput.click()" class="text-brand-600 hover:underline">Upload a file</button>
                or drag and drop
              </p>
              <p v-else class="mt-2 text-sm text-gray-900 dark:text-white font-medium">
                {{ selectedFile.name }} ({{ formatFileSize(selectedFile.size) }})
                <button type="button" @click="selectedFile = null" class="ml-2 text-red-600 hover:underline">Remove</button>
              </p>
              <p class="mt-1 text-xs text-gray-500">CSV files up to 10MB</p>
            </div>
          </div>

          <div class="flex items-center gap-6">
            <label class="flex items-center gap-2">
              <input type="checkbox" v-model="options.skip_duplicates" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
              <span class="text-sm text-gray-700 dark:text-gray-300">Skip duplicate records</span>
            </label>
            <label class="flex items-center gap-2">
              <input type="checkbox" v-model="options.update_existing" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
              <span class="text-sm text-gray-700 dark:text-gray-300">Update existing records</span>
            </label>
          </div>

          <div v-if="error" class="p-4 rounded-lg bg-red-50 dark:bg-red-900/20">
            <p class="text-sm text-red-700 dark:text-red-400">{{ error }}</p>
          </div>

          <div class="flex justify-end">
            <button
              @click="uploadFile"
              :disabled="!entityType || !selectedFile || uploading"
              :class="[
                'px-6 py-2 text-sm font-medium text-white rounded-lg',
                !entityType || !selectedFile || uploading ? 'bg-gray-400 cursor-not-allowed' : 'bg-brand-600 hover:bg-brand-700'
              ]"
            >
              <span v-if="uploading" class="flex items-center gap-2">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                Uploading...
              </span>
              <span v-else>Continue to Mapping</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Step 2: Map Fields -->
      <div v-if="currentStep === 1" class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Step 2: Map CSV Columns</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Match your CSV columns to the corresponding fields</p>

        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4 font-medium text-sm text-gray-700 dark:text-gray-300 pb-2 border-b border-gray-200 dark:border-gray-700">
            <div>CSV Column</div>
            <div>Maps To Field</div>
          </div>

          <div v-for="header in csvHeaders" :key="header" class="grid grid-cols-2 gap-4 items-center">
            <div class="text-sm text-gray-900 dark:text-white">{{ header }}</div>
            <select v-model="fieldMapping[header]" class="px-3 py-2 text-sm border border-gray-300 rounded-lg dark:bg-gray-800 dark:border-gray-600 dark:text-white">
              <option value="">-- Skip this column --</option>
              <optgroup label="Required Fields">
                <option v-for="field in requiredFields" :key="field" :value="field">{{ field }} *</option>
              </optgroup>
              <optgroup label="Optional Fields">
                <option v-for="field in optionalFields" :key="field" :value="field">{{ field }}</option>
              </optgroup>
            </select>
          </div>

          <div v-if="missingRequired.length > 0" class="p-4 rounded-lg bg-yellow-50 dark:bg-yellow-900/20">
            <p class="text-sm text-yellow-700 dark:text-yellow-400">
              <strong>Missing required fields:</strong> {{ missingRequired.join(', ') }}
            </p>
          </div>

          <div v-if="error" class="p-4 rounded-lg bg-red-50 dark:bg-red-900/20">
            <p class="text-sm text-red-700 dark:text-red-400">{{ error }}</p>
          </div>

          <div class="flex justify-between">
            <button @click="currentStep = 0" class="px-6 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700">
              Back
            </button>
            <button
              @click="startImport"
              :disabled="missingRequired.length > 0 || processing"
              :class="[
                'px-6 py-2 text-sm font-medium text-white rounded-lg',
                missingRequired.length > 0 || processing ? 'bg-gray-400 cursor-not-allowed' : 'bg-brand-600 hover:bg-brand-700'
              ]"
            >
              <span v-if="processing" class="flex items-center gap-2">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                Processing...
              </span>
              <span v-else>Start Import</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Step 3: Results -->
      <div v-if="currentStep === 2" class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Step 3: Import Results</h2>

        <div v-if="importResult" class="space-y-4">
          <div :class="[
            'p-4 rounded-lg',
            importResult.status === 'completed' ? 'bg-green-50 dark:bg-green-900/20' :
            importResult.status === 'partial' ? 'bg-yellow-50 dark:bg-yellow-900/20' :
            'bg-red-50 dark:bg-red-900/20'
          ]">
            <div class="flex items-center gap-2">
              <svg v-if="importResult.status === 'completed'" class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <svg v-else-if="importResult.status === 'partial'" class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
              </svg>
              <svg v-else class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
              </svg>
              <span :class="[
                'text-sm font-medium',
                importResult.status === 'completed' ? 'text-green-700 dark:text-green-400' :
                importResult.status === 'partial' ? 'text-yellow-700 dark:text-yellow-400' :
                'text-red-700 dark:text-red-400'
              ]">
                {{ importResult.status === 'completed' ? 'Import completed successfully!' :
                   importResult.status === 'partial' ? 'Import completed with some errors' :
                   'Import failed' }}
              </span>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-4">
            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ importResult.rows_processed || 0 }}</p>
              <p class="text-sm text-gray-500 dark:text-gray-400">Rows Processed</p>
            </div>
            <div class="p-4 rounded-lg bg-green-50 dark:bg-green-900/20">
              <p class="text-2xl font-bold text-green-700 dark:text-green-400">{{ importResult.rows_imported || 0 }}</p>
              <p class="text-sm text-gray-500 dark:text-gray-400">Successfully Imported</p>
            </div>
            <div class="p-4 rounded-lg bg-red-50 dark:bg-red-900/20">
              <p class="text-2xl font-bold text-red-700 dark:text-red-400">{{ importResult.rows_failed || 0 }}</p>
              <p class="text-sm text-gray-500 dark:text-gray-400">Failed</p>
            </div>
          </div>

          <div v-if="importResult.errors && importResult.errors.length > 0" class="mt-4">
            <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-2">Errors:</h3>
            <ul class="text-sm text-red-700 dark:text-red-400 list-disc list-inside space-y-1">
              <li v-for="(err, idx) in importResult.errors.slice(0, 10)" :key="idx">{{ err }}</li>
              <li v-if="importResult.errors.length > 10">... and {{ importResult.errors.length - 10 }} more errors</li>
            </ul>
          </div>

          <div class="flex justify-end gap-4">
            <button @click="resetImport" class="px-6 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700">
              Import Another File
            </button>
            <router-link :to="`/crm/${entityType}`" class="px-6 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700">
              View {{ entityType }}
            </router-link>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const steps = [
  { name: 'Upload' },
  { name: 'Map Fields' },
  { name: 'Results' }
]

const currentStep = ref(0)
const entityType = ref('')
const selectedFile = ref(null)
const options = ref({
  skip_duplicates: false,
  update_existing: false
})
const dragOver = ref(false)
const uploading = ref(false)
const processing = ref(false)
const error = ref(null)

const importId = ref(null)
const csvHeaders = ref([])
const requiredFields = ref([])
const optionalFields = ref([])
const fieldMapping = ref({})
const importResult = ref(null)

const fileInput = ref(null)

const missingRequired = computed(() => {
  const mappedFields = Object.values(fieldMapping.value).filter(Boolean)
  return requiredFields.value.filter(f => !mappedFields.includes(f))
})

function handleFileSelect(event) {
  const file = event.target.files[0]
  if (file) {
    selectedFile.value = file
  }
}

function handleDrop(event) {
  dragOver.value = false
  const file = event.dataTransfer.files[0]
  if (file && file.name.endsWith('.csv')) {
    selectedFile.value = file
  }
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

async function uploadFile() {
  if (!entityType.value || !selectedFile.value) return

  uploading.value = true
  error.value = null

  try {
    const formData = new FormData()
    formData.append('entity_type', entityType.value)
    formData.append('csv_file', selectedFile.value)
    formData.append('skip_duplicates', options.value.skip_duplicates ? '1' : '0')
    formData.append('update_existing', options.value.update_existing ? '1' : '0')

    const response = await axios.post('/api/csv/import/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    importId.value = response.data.import_id
    csvHeaders.value = response.data.csv_headers || []
    requiredFields.value = response.data.required_fields || []
    optionalFields.value = response.data.optional_fields || []

    // Auto-map fields by name match
    csvHeaders.value.forEach(header => {
      const lowerHeader = header.toLowerCase().replace(/[^a-z0-9]/g, '_')
      const allFields = [...requiredFields.value, ...optionalFields.value]
      const match = allFields.find(f => f.toLowerCase() === lowerHeader)
      if (match) {
        fieldMapping.value[header] = match
      }
    })

    currentStep.value = 1
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to upload file'
  } finally {
    uploading.value = false
  }
}

async function startImport() {
  if (missingRequired.value.length > 0) return

  processing.value = true
  error.value = null

  try {
    const response = await axios.post(`/api/csv/import/${importId.value}/mapping`, {
      mapping: fieldMapping.value
    })

    importResult.value = response.data
    currentStep.value = 2
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to process import'
  } finally {
    processing.value = false
  }
}

function resetImport() {
  currentStep.value = 0
  entityType.value = ''
  selectedFile.value = null
  options.value = { skip_duplicates: false, update_existing: false }
  importId.value = null
  csvHeaders.value = []
  requiredFields.value = []
  optionalFields.value = []
  fieldMapping.value = {}
  importResult.value = null
  error.value = null
}
</script>
