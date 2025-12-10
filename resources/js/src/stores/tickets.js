import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

/**
 * Tickets Store
 *
 * Manages support ticket state with RBAC-aware API calls.
 * Supports ticket management, messaging, and workflows.
 */
export const useTicketStore = defineStore('tickets', () => {
  // State
  const tickets = ref([])
  const currentTicket = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const searchTerm = ref('')
  const stats = ref(null)
  const statusFilter = ref('all')
  const priorityFilter = ref('all')

  // Computed
  const filteredTickets = computed(() => {
    let filtered = tickets.value

    if (statusFilter.value !== 'all') {
      filtered = filtered.filter(t => t.status === statusFilter.value)
    }

    if (priorityFilter.value !== 'all') {
      filtered = filtered.filter(t => t.priority === priorityFilter.value)
    }

    if (searchTerm.value) {
      const term = searchTerm.value.toLowerCase()
      filtered = filtered.filter(t =>
        t.ticket_number?.toLowerCase().includes(term) ||
        t.subject?.toLowerCase().includes(term) ||
        t.description?.toLowerCase().includes(term)
      )
    }

    return filtered
  })

  const ticketCount = computed(() => tickets.value.length)

  const openTickets = computed(() =>
    tickets.value.filter(t => ['open', 'in_progress', 'waiting'].includes(t.status))
  )

  const resolvedTickets = computed(() =>
    tickets.value.filter(t => t.status === 'resolved')
  )

  const closedTickets = computed(() =>
    tickets.value.filter(t => t.status === 'closed')
  )

  const urgentTickets = computed(() =>
    tickets.value.filter(t => t.priority === 'urgent' && t.status !== 'closed')
  )

  // Priority order
  const priorityOrder = { urgent: 0, high: 1, normal: 2, low: 3 }

  const sortedByPriority = computed(() =>
    [...tickets.value].sort((a, b) =>
      (priorityOrder[a.priority] || 2) - (priorityOrder[b.priority] || 2)
    )
  )

  // Actions
  async function fetchTickets(params = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/tickets', { params })
      tickets.value = response.data.data || []
      return tickets.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch tickets'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchTicket(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/tickets/${id}`)
      currentTicket.value = response.data.data
      return currentTicket.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch ticket'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createTicket(data) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post('/api/tickets', data)
      const newTicket = response.data.data
      tickets.value.unshift(newTicket)
      return newTicket
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create ticket'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateTicket(id, data) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.patch(`/api/tickets/${id}`, data)
      const updatedTicket = response.data.data

      const index = tickets.value.findIndex(t => t.id === id)
      if (index !== -1) {
        tickets.value[index] = updatedTicket
      }

      if (currentTicket.value?.id === id) {
        currentTicket.value = updatedTicket
      }

      return updatedTicket
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update ticket'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteTicket(id) {
    loading.value = true
    error.value = null
    try {
      await axios.delete(`/api/tickets/${id}`)
      tickets.value = tickets.value.filter(t => t.id !== id)

      if (currentTicket.value?.id === id) {
        currentTicket.value = null
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to delete ticket'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function assignTicket(id, userId) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/tickets/${id}/assign`, { user_id: userId })
      const updatedTicket = response.data.data

      const index = tickets.value.findIndex(t => t.id === id)
      if (index !== -1) {
        tickets.value[index] = updatedTicket
      }

      if (currentTicket.value?.id === id) {
        currentTicket.value = updatedTicket
      }

      return updatedTicket
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to assign ticket'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function resolveTicket(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/tickets/${id}/resolve`)
      const updatedTicket = response.data.data

      const index = tickets.value.findIndex(t => t.id === id)
      if (index !== -1) {
        tickets.value[index] = updatedTicket
      }

      if (currentTicket.value?.id === id) {
        currentTicket.value = updatedTicket
      }

      return updatedTicket
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to resolve ticket'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function closeTicket(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/tickets/${id}/close`)
      const updatedTicket = response.data.data

      const index = tickets.value.findIndex(t => t.id === id)
      if (index !== -1) {
        tickets.value[index] = updatedTicket
      }

      if (currentTicket.value?.id === id) {
        currentTicket.value = updatedTicket
      }

      return updatedTicket
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to close ticket'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function reopenTicket(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/tickets/${id}/reopen`)
      const updatedTicket = response.data.data

      const index = tickets.value.findIndex(t => t.id === id)
      if (index !== -1) {
        tickets.value[index] = updatedTicket
      }

      if (currentTicket.value?.id === id) {
        currentTicket.value = updatedTicket
      }

      return updatedTicket
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to reopen ticket'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function addMessage(ticketId, message, isInternal = false) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/tickets/${ticketId}/messages`, {
        message,
        is_internal: isInternal
      })

      // Refresh current ticket if viewing
      if (currentTicket.value?.id === ticketId) {
        await fetchTicket(ticketId)
      }

      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to add message'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchStats() {
    try {
      const response = await axios.get('/api/tickets/stats')
      stats.value = response.data.data
      return stats.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch ticket stats'
      throw err
    }
  }

  async function fetchOverdue() {
    try {
      const response = await axios.get('/api/tickets/overdue')
      return response.data.data || []
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch overdue tickets'
      throw err
    }
  }

  function setSearchTerm(term) {
    searchTerm.value = term
  }

  function setStatusFilter(status) {
    statusFilter.value = status
  }

  function setPriorityFilter(priority) {
    priorityFilter.value = priority
  }

  function clearError() {
    error.value = null
  }

  function clearCurrentTicket() {
    currentTicket.value = null
  }

  function getStatusColor(status) {
    const colors = {
      open: 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
      in_progress: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400',
      waiting: 'bg-purple-100 text-purple-700 dark:bg-purple-900/20 dark:text-purple-400',
      resolved: 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400',
      closed: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
    }
    return colors[status] || colors.open
  }

  function getPriorityColor(priority) {
    const colors = {
      low: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
      normal: 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
      high: 'bg-orange-100 text-orange-700 dark:bg-orange-900/20 dark:text-orange-400',
      urgent: 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400'
    }
    return colors[priority] || colors.normal
  }

  return {
    // State
    tickets,
    currentTicket,
    loading,
    error,
    searchTerm,
    stats,
    statusFilter,
    priorityFilter,

    // Computed
    filteredTickets,
    ticketCount,
    openTickets,
    resolvedTickets,
    closedTickets,
    urgentTickets,
    sortedByPriority,

    // Actions
    fetchTickets,
    fetchTicket,
    createTicket,
    updateTicket,
    deleteTicket,
    assignTicket,
    resolveTicket,
    closeTicket,
    reopenTicket,
    addMessage,
    fetchStats,
    fetchOverdue,
    setSearchTerm,
    setStatusFilter,
    setPriorityFilter,
    clearError,
    clearCurrentTicket,
    getStatusColor,
    getPriorityColor
  }
})
