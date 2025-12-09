import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

/**
 * Notes Store
 *
 * Manages notes state with RBAC-aware API calls.
 * Notes can be attached to clients, contacts, or projects.
 */
export const useNoteStore = defineStore('notes', () => {
  // State
  const notes = ref([])
  const currentNote = ref(null)
  const loading = ref(false)
  const error = ref(null)

  // Computed
  const pinnedNotes = computed(() =>
    notes.value.filter(note => note.is_pinned)
  )

  const noteCount = computed(() => notes.value.length)

  const notesByEntity = computed(() => (entityType, entityId) => {
    if (!entityType || !entityId) return []

    const typeField = `${entityType}_id`
    return notes.value.filter(note => note[typeField] === entityId)
  })

  // Actions
  async function fetchNotes(filters = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/notes', { params: filters })
      notes.value = response.data.data || []
      return notes.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch notes'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchNote(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/notes/${id}`)
      currentNote.value = response.data.data
      return currentNote.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch note'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchNotesByClient(clientId) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/clients/${clientId}/notes`)
      return response.data.data || []
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch client notes'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchNotesByContact(contactId) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/contacts/${contactId}/notes`)
      return response.data.data || []
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch contact notes'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchNotesByProject(projectId) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/projects/${projectId}/notes`)
      return response.data.data || []
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch project notes'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createNote(noteData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post('/api/notes', noteData)
      const newNote = response.data.data
      notes.value.unshift(newNote) // Add to beginning
      return newNote
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create note'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateNote(id, noteData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.put(`/api/notes/${id}`, noteData)
      const updatedNote = response.data.data

      const index = notes.value.findIndex(n => n.id === id)
      if (index !== -1) {
        notes.value[index] = updatedNote
      }

      if (currentNote.value?.id === id) {
        currentNote.value = updatedNote
      }

      return updatedNote
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update note'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteNote(id, purge = false) {
    loading.value = true
    error.value = null
    try {
      await axios.delete(`/api/notes/${id}`, {
        params: { purge }
      })

      notes.value = notes.value.filter(n => n.id !== id)

      if (currentNote.value?.id === id) {
        currentNote.value = null
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete note'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function togglePin(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/notes/${id}/toggle-pin`)
      const updatedNote = response.data.data

      const index = notes.value.findIndex(n => n.id === id)
      if (index !== -1) {
        notes.value[index] = updatedNote
      }

      if (currentNote.value?.id === id) {
        currentNote.value = updatedNote
      }

      return updatedNote
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to toggle pin status'
      throw err
    } finally {
      loading.value = false
    }
  }

  function clearError() {
    error.value = null
  }

  function reset() {
    notes.value = []
    currentNote.value = null
    loading.value = false
    error.value = null
  }

  return {
    // State
    notes,
    currentNote,
    loading,
    error,

    // Computed
    pinnedNotes,
    noteCount,
    notesByEntity,

    // Actions
    fetchNotes,
    fetchNote,
    fetchNotesByClient,
    fetchNotesByContact,
    fetchNotesByProject,
    createNote,
    updateNote,
    deleteNote,
    togglePin,
    clearError,
    reset
  }
})
