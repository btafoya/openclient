<template>
  <AdminLayout>
    <div class="space-y-6">
      <!-- Page Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Files & Documents</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Manage uploaded files and documents
          </p>
        </div>
        <button
          @click="triggerUpload"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
          </svg>
          Upload File
        </button>
        <input
          ref="fileInput"
          type="file"
          class="hidden"
          multiple
          @change="handleFileSelect"
        />
      </div>

      <!-- Stats Cards -->
      <div v-if="fileStore.stats" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Total Files</p>
          <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
            {{ fileStore.stats.total_files || 0 }}
          </p>
        </div>
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Storage Used</p>
          <p class="mt-1 text-2xl font-bold text-brand-600">
            {{ fileStore.formatFileSize(fileStore.stats.total_size || 0) }}
          </p>
        </div>
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Images</p>
          <p class="mt-1 text-2xl font-bold text-purple-600">
            {{ fileStore.stats.image_count || 0 }}
          </p>
        </div>
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Documents</p>
          <p class="mt-1 text-2xl font-bold text-green-600">
            {{ fileStore.stats.document_count || 0 }}
          </p>
        </div>
      </div>

      <!-- Search and Filters -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
        <div class="flex-1">
          <div class="relative">
            <input
              v-model="searchTerm"
              type="text"
              placeholder="Search files by name or description..."
              class="w-full px-4 py-2 pl-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
              @input="handleSearch"
            />
            <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>
        <div class="flex gap-3">
          <select
            v-model="typeFilter"
            @change="applyFilter"
            class="px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
          >
            <option value="all">All Types</option>
            <option value="image">Images</option>
            <option value="document">Documents</option>
            <option value="video">Videos</option>
            <option value="archive">Archives</option>
          </select>
        </div>
      </div>

      <!-- Upload Progress -->
      <div v-if="fileStore.uploading" class="p-4 bg-blue-50 rounded-lg border border-blue-200 dark:bg-blue-900/20 dark:border-blue-800">
        <div class="flex items-center gap-3">
          <div class="w-5 h-5 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
          <span class="text-sm font-medium text-blue-700 dark:text-blue-400">Uploading file...</span>
        </div>
      </div>

      <!-- Files Grid -->
      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <!-- Loading State -->
        <div v-if="fileStore.loading" class="p-8 text-center">
          <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading files...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="fileStore.error" class="p-8 text-center">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/20">
            <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <p class="mt-4 text-sm text-gray-900 dark:text-white font-medium">{{ fileStore.error }}</p>
          <button
            @click="loadFiles"
            class="mt-4 px-4 py-2 text-sm font-medium text-brand-600 hover:text-brand-700"
          >
            Try Again
          </button>
        </div>

        <!-- Empty State -->
        <div v-else-if="displayedFiles.length === 0" class="p-8 text-center">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800">
            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
          </div>
          <p class="mt-4 text-sm text-gray-900 dark:text-white font-medium">No files found</p>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ searchTerm ? 'Try adjusting your search' : 'Get started by uploading your first file' }}
          </p>
          <button
            v-if="!searchTerm"
            @click="triggerUpload"
            class="inline-flex items-center gap-2 mt-4 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            Upload Your First File
          </button>
        </div>

        <!-- Files Table -->
        <div v-else class="max-w-full overflow-x-auto custom-scrollbar">
          <table class="min-w-full">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">File</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Type</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Size</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Uploaded</p>
                </th>
                <th class="px-5 py-3 text-right sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Actions</p>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr
                v-for="file in displayedFiles"
                :key="file.id"
                class="border-t border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50"
              >
                <td class="px-5 py-4 sm:px-6">
                  <div class="flex items-center gap-3">
                    <div :class="getFileIconBg(file.mime_type)" class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center">
                      <component :is="getFileIcon(file.mime_type)" class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                      <p class="font-medium text-gray-800 text-theme-sm dark:text-white/90 truncate max-w-xs">
                        {{ file.original_name }}
                      </p>
                      <p v-if="file.description" class="text-gray-500 text-theme-xs dark:text-gray-400 truncate max-w-xs">
                        {{ file.description }}
                      </p>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <span class="text-theme-sm text-gray-600 dark:text-gray-400">
                    {{ getFileTypeName(file.mime_type) }}
                  </span>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <span class="text-theme-sm text-gray-600 dark:text-gray-400">
                    {{ fileStore.formatFileSize(file.file_size) }}
                  </span>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <span class="text-theme-sm text-gray-600 dark:text-gray-400">
                    {{ formatDate(file.created_at) }}
                  </span>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <div class="flex items-center justify-end gap-2">
                    <a
                      :href="fileStore.getDownloadUrl(file.id)"
                      target="_blank"
                      class="p-1.5 text-gray-600 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400"
                      title="Download"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                      </svg>
                    </a>
                    <button
                      @click="confirmDelete(file)"
                      class="p-1.5 text-gray-600 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
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
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, h } from 'vue'
import { useFileStore } from '@/stores/files'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const fileStore = useFileStore()

const searchTerm = ref('')
const typeFilter = ref('all')
const fileInput = ref(null)

// Computed
const displayedFiles = computed(() => {
  let files = fileStore.filteredFiles

  if (typeFilter.value !== 'all') {
    files = files.filter(file => {
      const iconType = fileStore.getFileIcon(file.mime_type)
      return iconType === typeFilter.value
    })
  }

  return files
})

// Methods
async function loadFiles() {
  try {
    await Promise.all([
      fileStore.fetchFiles(),
      fileStore.fetchStats()
    ])
  } catch (error) {
    console.error('Failed to load files:', error)
  }
}

function handleSearch() {
  fileStore.setSearchTerm(searchTerm.value)
}

function applyFilter() {
  loadFiles()
}

function triggerUpload() {
  fileInput.value?.click()
}

async function handleFileSelect(event) {
  const files = event.target.files
  if (!files || files.length === 0) return

  for (const file of files) {
    try {
      await fileStore.uploadFile(file)
    } catch (error) {
      console.error('Failed to upload file:', error)
      alert(`Failed to upload ${file.name}. Please try again.`)
    }
  }

  // Clear input
  event.target.value = ''

  // Refresh stats
  await fileStore.fetchStats()
}

async function confirmDelete(file) {
  if (confirm(`Are you sure you want to delete "${file.original_name}"? This action cannot be undone.`)) {
    try {
      await fileStore.deleteFile(file.id)
      await fileStore.fetchStats()
    } catch (error) {
      console.error('Failed to delete file:', error)
      alert('Failed to delete file. Please try again.')
    }
  }
}

function formatDate(dateString) {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

function getFileTypeName(mimeType) {
  if (mimeType?.startsWith('image/')) return 'Image'
  if (mimeType?.includes('pdf')) return 'PDF'
  if (mimeType?.includes('spreadsheet') || mimeType?.includes('excel')) return 'Spreadsheet'
  if (mimeType?.includes('document') || mimeType?.includes('word')) return 'Document'
  if (mimeType?.startsWith('video/')) return 'Video'
  if (mimeType?.startsWith('audio/')) return 'Audio'
  if (mimeType?.includes('zip') || mimeType?.includes('archive')) return 'Archive'
  return 'File'
}

function getFileIconBg(mimeType) {
  if (mimeType?.startsWith('image/')) return 'bg-purple-100 text-purple-600 dark:bg-purple-900/20 dark:text-purple-400'
  if (mimeType?.includes('pdf')) return 'bg-red-100 text-red-600 dark:bg-red-900/20 dark:text-red-400'
  if (mimeType?.includes('spreadsheet') || mimeType?.includes('excel')) return 'bg-green-100 text-green-600 dark:bg-green-900/20 dark:text-green-400'
  if (mimeType?.includes('document') || mimeType?.includes('word')) return 'bg-blue-100 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400'
  if (mimeType?.startsWith('video/')) return 'bg-pink-100 text-pink-600 dark:bg-pink-900/20 dark:text-pink-400'
  if (mimeType?.startsWith('audio/')) return 'bg-yellow-100 text-yellow-600 dark:bg-yellow-900/20 dark:text-yellow-400'
  if (mimeType?.includes('zip') || mimeType?.includes('archive')) return 'bg-orange-100 text-orange-600 dark:bg-orange-900/20 dark:text-orange-400'
  return 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
}

// Simple SVG icons as render functions
const ImageIcon = () => h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z' })
])

const DocumentIcon = () => h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' })
])

const VideoIcon = () => h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z' })
])

const ArchiveIcon = () => h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4' })
])

const FileIcon = () => h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z' })
])

function getFileIcon(mimeType) {
  if (mimeType?.startsWith('image/')) return ImageIcon
  if (mimeType?.includes('pdf') || mimeType?.includes('document') || mimeType?.includes('word') || mimeType?.includes('spreadsheet') || mimeType?.includes('excel')) return DocumentIcon
  if (mimeType?.startsWith('video/')) return VideoIcon
  if (mimeType?.includes('zip') || mimeType?.includes('archive')) return ArchiveIcon
  return FileIcon
}

// Lifecycle
onMounted(() => {
  loadFiles()
})
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  height: 8px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 4px;
}

.dark .custom-scrollbar::-webkit-scrollbar-thumb {
  background: #4b5563;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #9ca3af;
}

.dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #6b7280;
}
</style>
