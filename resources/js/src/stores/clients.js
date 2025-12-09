import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

/**
 * Client Store
 *
 * Manages client (customer company) state with RBAC-aware API calls.
 * Automatically filtered by agency via PostgreSQL RLS.
 */
export const useClientStore = defineStore('clients', () => {
  // State
  const clients = ref([])
  const currentClient = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const searchTerm = ref('')

  // Computed
  const activeClients = computed(() =>
    clients.value.filter(client => client.is_active)
  )

  const filteredClients = computed(() => {
    if (!searchTerm.value) return clients.value

    const term = searchTerm.value.toLowerCase()
    return clients.value.filter(client =>
      client.name?.toLowerCase().includes(term) ||
      client.email?.toLowerCase().includes(term) ||
      client.company?.toLowerCase().includes(term)
    )
  })

  const clientCount = computed(() => clients.value.length)
  const activeClientCount = computed(() => activeClients.value.length)

  // Actions
  async function fetchClients(activeOnly = true) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/clients', {
        params: { active_only: activeOnly }
      })
      clients.value = response.data.data || []
      return clients.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch clients'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchClient(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/clients/${id}`)
      currentClient.value = response.data.data
      return currentClient.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch client'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createClient(clientData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post('/api/clients', clientData)
      const newClient = response.data.data
      clients.value.push(newClient)
      return newClient
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create client'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateClient(id, clientData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.put(`/api/clients/${id}`, clientData)
      const updatedClient = response.data.data

      // Update in list
      const index = clients.value.findIndex(c => c.id === id)
      if (index !== -1) {
        clients.value[index] = updatedClient
      }

      // Update current if viewing
      if (currentClient.value?.id === id) {
        currentClient.value = updatedClient
      }

      return updatedClient
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update client'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteClient(id, purge = false) {
    loading.value = true
    error.value = null
    try {
      await axios.delete(`/api/clients/${id}`, {
        params: { purge }
      })

      // Remove from list
      clients.value = clients.value.filter(c => c.id !== id)

      // Clear current if deleted
      if (currentClient.value?.id === id) {
        currentClient.value = null
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete client'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function toggleActive(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/clients/${id}/toggle-active`)
      const updatedClient = response.data.data

      const index = clients.value.findIndex(c => c.id === id)
      if (index !== -1) {
        clients.value[index] = updatedClient
      }

      if (currentClient.value?.id === id) {
        currentClient.value = updatedClient
      }

      return updatedClient
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to toggle client status'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function searchClients(term, activeOnly = true) {
    if (!term) {
      return fetchClients(activeOnly)
    }

    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/clients/search', {
        params: { term, active_only: activeOnly }
      })
      clients.value = response.data.data || []
      return clients.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to search clients'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function validateDelete(id) {
    try {
      const response = await axios.get(`/api/clients/${id}/validate-delete`)
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to validate deletion'
      throw err
    }
  }

  function setSearchTerm(term) {
    searchTerm.value = term
  }

  function clearError() {
    error.value = null
  }

  function reset() {
    clients.value = []
    currentClient.value = null
    loading.value = false
    error.value = null
    searchTerm.value = ''
  }

  return {
    // State
    clients,
    currentClient,
    loading,
    error,
    searchTerm,

    // Computed
    activeClients,
    filteredClients,
    clientCount,
    activeClientCount,

    // Actions
    fetchClients,
    fetchClient,
    createClient,
    updateClient,
    deleteClient,
    toggleActive,
    searchClients,
    validateDelete,
    setSearchTerm,
    clearError,
    reset
  }
})
