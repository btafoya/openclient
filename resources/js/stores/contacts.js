import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

/**
 * Contact Store
 *
 * Manages contact state with RBAC-aware API calls.
 * Contacts belong to clients and are filtered by agency via RLS.
 */
export const useContactStore = defineStore('contacts', () => {
  // State
  const contacts = ref([])
  const currentContact = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const searchTerm = ref('')

  // Computed
  const activeContacts = computed(() =>
    contacts.value.filter(contact => contact.is_active)
  )

  const primaryContacts = computed(() =>
    contacts.value.filter(contact => contact.is_primary && contact.is_active)
  )

  const filteredContacts = computed(() => {
    if (!searchTerm.value) return contacts.value

    const term = searchTerm.value.toLowerCase()
    return contacts.value.filter(contact =>
      contact.first_name?.toLowerCase().includes(term) ||
      contact.last_name?.toLowerCase().includes(term) ||
      contact.email?.toLowerCase().includes(term) ||
      contact.job_title?.toLowerCase().includes(term)
    )
  })

  const contactCount = computed(() => contacts.value.length)
  const activeContactCount = computed(() => activeContacts.value.length)

  // Actions
  async function fetchContacts(activeOnly = true) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/contacts', {
        params: { active_only: activeOnly }
      })
      contacts.value = response.data.data || []
      return contacts.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch contacts'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchContact(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/contacts/${id}`)
      currentContact.value = response.data.data
      return currentContact.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch contact'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchContactsByClient(clientId, activeOnly = true) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/clients/${clientId}/contacts`, {
        params: { active_only: activeOnly }
      })
      return response.data.data || []
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch client contacts'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchPrimaryContact(clientId) {
    try {
      const response = await axios.get(`/api/clients/${clientId}/contacts/primary`)
      return response.data.data
    } catch (err) {
      return null
    }
  }

  async function createContact(contactData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post('/api/contacts', contactData)
      const newContact = response.data.data
      contacts.value.push(newContact)
      return newContact
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create contact'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateContact(id, contactData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.put(`/api/contacts/${id}`, contactData)
      const updatedContact = response.data.data

      const index = contacts.value.findIndex(c => c.id === id)
      if (index !== -1) {
        contacts.value[index] = updatedContact
      }

      if (currentContact.value?.id === id) {
        currentContact.value = updatedContact
      }

      return updatedContact
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update contact'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteContact(id, purge = false) {
    loading.value = true
    error.value = null
    try {
      await axios.delete(`/api/contacts/${id}`, {
        params: { purge }
      })

      contacts.value = contacts.value.filter(c => c.id !== id)

      if (currentContact.value?.id === id) {
        currentContact.value = null
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete contact'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function toggleActive(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/contacts/${id}/toggle-active`)
      const updatedContact = response.data.data

      const index = contacts.value.findIndex(c => c.id === id)
      if (index !== -1) {
        contacts.value[index] = updatedContact
      }

      if (currentContact.value?.id === id) {
        currentContact.value = updatedContact
      }

      return updatedContact
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to toggle contact status'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function searchContacts(term, activeOnly = true) {
    if (!term) {
      return fetchContacts(activeOnly)
    }

    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/contacts/search', {
        params: { term, active_only: activeOnly }
      })
      contacts.value = response.data.data || []
      return contacts.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to search contacts'
      throw err
    } finally {
      loading.value = false
    }
  }

  function getFullName(contact) {
    if (!contact) return ''
    return `${contact.first_name || ''} ${contact.last_name || ''}`.trim()
  }

  function setSearchTerm(term) {
    searchTerm.value = term
  }

  function clearError() {
    error.value = null
  }

  function reset() {
    contacts.value = []
    currentContact.value = null
    loading.value = false
    error.value = null
    searchTerm.value = ''
  }

  return {
    // State
    contacts,
    currentContact,
    loading,
    error,
    searchTerm,

    // Computed
    activeContacts,
    primaryContacts,
    filteredContacts,
    contactCount,
    activeContactCount,

    // Actions
    fetchContacts,
    fetchContact,
    fetchContactsByClient,
    fetchPrimaryContact,
    createContact,
    updateContact,
    deleteContact,
    toggleActive,
    searchContacts,
    getFullName,
    setSearchTerm,
    clearError,
    reset
  }
})
