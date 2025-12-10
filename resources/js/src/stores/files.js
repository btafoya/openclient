import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

/**
 * Files Store
 *
 * Manages file/document state with RBAC-aware API calls.
 * Supports upload, download, and file management.
 */
export const useFileStore = defineStore('files', () => {
  // State
  const files = ref([])
  const currentFile = ref(null)
  const loading = ref(false)
  const uploading = ref(false)
  const error = ref(null)
  const searchTerm = ref('')
  const stats = ref(null)

  // Computed
  const filteredFiles = computed(() => {
    if (!searchTerm.value) return files.value

    const term = searchTerm.value.toLowerCase()
    return files.value.filter(file =>
      file.original_name?.toLowerCase().includes(term) ||
      file.description?.toLowerCase().includes(term)
    )
  })

  const fileCount = computed(() => files.value.length)

  const totalStorageUsed = computed(() =>
    files.value.reduce((sum, file) => sum + (file.file_size || 0), 0)
  )

  const imageFiles = computed(() =>
    files.value.filter(file => file.mime_type?.startsWith('image/'))
  )

  const documentFiles = computed(() =>
    files.value.filter(file =>
      file.mime_type?.includes('pdf') ||
      file.mime_type?.includes('document') ||
      file.mime_type?.includes('spreadsheet')
    )
  )

  // Actions
  async function fetchFiles(params = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/files', { params })
      files.value = response.data.data || []
      return files.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch files'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchFile(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/files/${id}`)
      currentFile.value = response.data.data
      return currentFile.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch file'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function uploadFile(file, options = {}) {
    uploading.value = true
    error.value = null
    try {
      const formData = new FormData()
      formData.append('file', file)

      if (options.entity_type) formData.append('entity_type', options.entity_type)
      if (options.entity_id) formData.append('entity_id', options.entity_id)
      if (options.folder) formData.append('folder', options.folder)
      if (options.description) formData.append('description', options.description)
      if (options.is_public) formData.append('is_public', options.is_public)

      const response = await axios.post('/api/files', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })

      const newFile = response.data.data
      files.value.unshift(newFile)
      return newFile
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to upload file'
      throw err
    } finally {
      uploading.value = false
    }
  }

  async function updateFile(id, data) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.patch(`/api/files/${id}`, data)
      const updatedFile = response.data.data

      const index = files.value.findIndex(f => f.id === id)
      if (index !== -1) {
        files.value[index] = updatedFile
      }

      if (currentFile.value?.id === id) {
        currentFile.value = updatedFile
      }

      return updatedFile
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update file'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteFile(id) {
    loading.value = true
    error.value = null
    try {
      await axios.delete(`/api/files/${id}`)
      files.value = files.value.filter(f => f.id !== id)

      if (currentFile.value?.id === id) {
        currentFile.value = null
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to delete file'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchStats() {
    try {
      const response = await axios.get('/api/files/stats')
      stats.value = response.data.data
      return stats.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch file stats'
      throw err
    }
  }

  async function fetchRecent(limit = 10) {
    try {
      const response = await axios.get('/api/files/recent', { params: { limit } })
      return response.data.data || []
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch recent files'
      throw err
    }
  }

  async function searchFiles(term) {
    try {
      const response = await axios.get('/api/files/search', { params: { q: term } })
      return response.data.data || []
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to search files'
      throw err
    }
  }

  function getDownloadUrl(id) {
    return `/api/files/${id}/download`
  }

  function setSearchTerm(term) {
    searchTerm.value = term
  }

  function clearError() {
    error.value = null
  }

  function clearCurrentFile() {
    currentFile.value = null
  }

  function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes'
    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
  }

  function getFileIcon(mimeType) {
    if (mimeType?.startsWith('image/')) return 'image'
    if (mimeType?.includes('pdf')) return 'pdf'
    if (mimeType?.includes('spreadsheet') || mimeType?.includes('excel')) return 'spreadsheet'
    if (mimeType?.includes('document') || mimeType?.includes('word')) return 'document'
    if (mimeType?.startsWith('video/')) return 'video'
    if (mimeType?.startsWith('audio/')) return 'audio'
    if (mimeType?.includes('zip') || mimeType?.includes('archive')) return 'archive'
    return 'file'
  }

  return {
    // State
    files,
    currentFile,
    loading,
    uploading,
    error,
    searchTerm,
    stats,

    // Computed
    filteredFiles,
    fileCount,
    totalStorageUsed,
    imageFiles,
    documentFiles,

    // Actions
    fetchFiles,
    fetchFile,
    uploadFile,
    updateFile,
    deleteFile,
    fetchStats,
    fetchRecent,
    searchFiles,
    getDownloadUrl,
    setSearchTerm,
    clearError,
    clearCurrentFile,
    formatFileSize,
    getFileIcon
  }
})
