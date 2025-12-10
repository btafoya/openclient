import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

/**
 * Recurring Invoice Store
 *
 * Manages recurring invoice schedules with RBAC-aware API calls.
 * Supports automated billing, pause/resume, and schedule management.
 * Automatically filtered by agency via PostgreSQL RLS.
 */
export const useRecurringInvoiceStore = defineStore('recurringInvoices', () => {
  // State
  const schedules = ref([])
  const currentSchedule = ref(null)
  const upcomingInvoices = ref([])
  const loading = ref(false)
  const error = ref(null)
  const searchTerm = ref('')

  // Computed
  const activeSchedules = computed(() =>
    schedules.value.filter(s => s.status === 'active')
  )

  const pausedSchedules = computed(() =>
    schedules.value.filter(s => s.status === 'paused')
  )

  const expiredSchedules = computed(() =>
    schedules.value.filter(s => s.status === 'expired')
  )

  const filteredSchedules = computed(() => {
    if (!searchTerm.value) return schedules.value

    const term = searchTerm.value.toLowerCase()
    return schedules.value.filter(s =>
      s.title?.toLowerCase().includes(term) ||
      s.client_name?.toLowerCase().includes(term) ||
      s.description?.toLowerCase().includes(term)
    )
  })

  const scheduleCount = computed(() => schedules.value.length)

  const monthlyRevenue = computed(() =>
    activeSchedules.value.reduce((sum, s) => {
      const amount = parseFloat(s.amount || 0)
      // Convert to monthly equivalent
      switch (s.frequency) {
        case 'weekly': return sum + (amount * 4.33)
        case 'biweekly': return sum + (amount * 2.17)
        case 'monthly': return sum + amount
        case 'quarterly': return sum + (amount / 3)
        case 'semiannually': return sum + (amount / 6)
        case 'annually': return sum + (amount / 12)
        default: return sum + amount
      }
    }, 0)
  )

  // Valid status transitions
  const statusTransitions = {
    active: ['paused', 'cancelled'],
    paused: ['active', 'cancelled'],
    cancelled: [], // Terminal
    expired: [] // Terminal
  }

  // Frequency options
  const frequencies = [
    { value: 'weekly', label: 'Weekly', days: 7 },
    { value: 'biweekly', label: 'Bi-weekly', days: 14 },
    { value: 'monthly', label: 'Monthly', days: 30 },
    { value: 'quarterly', label: 'Quarterly', days: 90 },
    { value: 'semiannually', label: 'Semi-annually', days: 182 },
    { value: 'annually', label: 'Annually', days: 365 }
  ]

  // Actions
  async function fetchSchedules(params = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/recurring-invoices', { params })
      schedules.value = response.data.data || []
      return schedules.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch recurring invoices'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchSchedule(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/recurring-invoices/${id}`)
      currentSchedule.value = response.data.data
      return currentSchedule.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch recurring invoice'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createSchedule(scheduleData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post('/api/recurring-invoices', scheduleData)
      const newSchedule = response.data.data
      schedules.value.unshift(newSchedule)
      return newSchedule
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create recurring invoice'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateSchedule(id, scheduleData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.put(`/api/recurring-invoices/${id}`, scheduleData)
      const updatedSchedule = response.data.data

      const index = schedules.value.findIndex(s => s.id === id)
      if (index !== -1) {
        schedules.value[index] = updatedSchedule
      }

      if (currentSchedule.value?.id === id) {
        currentSchedule.value = updatedSchedule
      }

      return updatedSchedule
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update recurring invoice'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteSchedule(id) {
    loading.value = true
    error.value = null
    try {
      await axios.delete(`/api/recurring-invoices/${id}`)
      schedules.value = schedules.value.filter(s => s.id !== id)

      if (currentSchedule.value?.id === id) {
        currentSchedule.value = null
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to delete recurring invoice'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function pauseSchedule(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/recurring-invoices/${id}/pause`)
      const updatedSchedule = response.data.data

      const index = schedules.value.findIndex(s => s.id === id)
      if (index !== -1) {
        schedules.value[index] = updatedSchedule
      }

      if (currentSchedule.value?.id === id) {
        currentSchedule.value = updatedSchedule
      }

      return updatedSchedule
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to pause schedule'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function resumeSchedule(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/recurring-invoices/${id}/resume`)
      const updatedSchedule = response.data.data

      const index = schedules.value.findIndex(s => s.id === id)
      if (index !== -1) {
        schedules.value[index] = updatedSchedule
      }

      if (currentSchedule.value?.id === id) {
        currentSchedule.value = updatedSchedule
      }

      return updatedSchedule
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to resume schedule'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function cancelSchedule(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/recurring-invoices/${id}/cancel`)
      const updatedSchedule = response.data.data

      const index = schedules.value.findIndex(s => s.id === id)
      if (index !== -1) {
        schedules.value[index] = updatedSchedule
      }

      if (currentSchedule.value?.id === id) {
        currentSchedule.value = updatedSchedule
      }

      return updatedSchedule
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to cancel schedule'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function generateNow(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/recurring-invoices/${id}/generate`)
      return response.data.data // Returns generated invoice
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to generate invoice'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchUpcoming(days = 30) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/recurring-invoices/upcoming', {
        params: { days }
      })
      upcomingInvoices.value = response.data.data || []
      return upcomingInvoices.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch upcoming invoices'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchGeneratedInvoices(scheduleId) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/recurring-invoices/${scheduleId}/invoices`)
      return response.data.data || []
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch generated invoices'
      throw err
    } finally {
      loading.value = false
    }
  }

  function canTransitionTo(currentStatus, newStatus) {
    const allowed = statusTransitions[currentStatus] || []
    return allowed.includes(newStatus)
  }

  function getAvailableStatuses(currentStatus) {
    return statusTransitions[currentStatus] || []
  }

  function getFrequencyLabel(frequency) {
    const freq = frequencies.find(f => f.value === frequency)
    return freq?.label || frequency
  }

  function calculateNextDate(lastDate, frequency) {
    const date = new Date(lastDate)
    const freq = frequencies.find(f => f.value === frequency)
    if (freq) {
      date.setDate(date.getDate() + freq.days)
    }
    return date
  }

  function setSearchTerm(term) {
    searchTerm.value = term
  }

  function clearError() {
    error.value = null
  }

  function reset() {
    schedules.value = []
    currentSchedule.value = null
    upcomingInvoices.value = []
    loading.value = false
    error.value = null
    searchTerm.value = ''
  }

  return {
    // State
    schedules,
    currentSchedule,
    upcomingInvoices,
    loading,
    error,
    searchTerm,

    // Computed
    activeSchedules,
    pausedSchedules,
    expiredSchedules,
    filteredSchedules,
    scheduleCount,
    monthlyRevenue,

    // Constants
    frequencies,

    // Actions
    fetchSchedules,
    fetchSchedule,
    createSchedule,
    updateSchedule,
    deleteSchedule,
    pauseSchedule,
    resumeSchedule,
    cancelSchedule,
    generateNow,
    fetchUpcoming,
    fetchGeneratedInvoices,
    canTransitionTo,
    getAvailableStatuses,
    getFrequencyLabel,
    calculateNextDate,
    setSearchTerm,
    clearError,
    reset
  }
})
