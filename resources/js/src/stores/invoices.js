import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

/**
 * Invoice Store
 *
 * Manages invoice state with RBAC-aware API calls.
 * Supports status workflows, PDF generation, and email delivery.
 * Automatically filtered by agency via PostgreSQL RLS.
 */
export const useInvoiceStore = defineStore('invoices', () => {
  // State
  const invoices = ref([])
  const currentInvoice = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const searchTerm = ref('')
  const stats = ref(null)

  // Computed
  const draftInvoices = computed(() =>
    invoices.value.filter(inv => inv.status === 'draft')
  )

  const sentInvoices = computed(() =>
    invoices.value.filter(inv => ['sent', 'viewed'].includes(inv.status))
  )

  const paidInvoices = computed(() =>
    invoices.value.filter(inv => inv.status === 'paid')
  )

  const overdueInvoices = computed(() =>
    invoices.value.filter(inv => inv.status === 'overdue')
  )

  const filteredInvoices = computed(() => {
    if (!searchTerm.value) return invoices.value

    const term = searchTerm.value.toLowerCase()
    return invoices.value.filter(inv =>
      inv.invoice_number?.toLowerCase().includes(term) ||
      inv.client_name?.toLowerCase().includes(term) ||
      inv.project_name?.toLowerCase().includes(term)
    )
  })

  const invoiceCount = computed(() => invoices.value.length)

  const totalOutstanding = computed(() =>
    invoices.value
      .filter(inv => ['sent', 'viewed', 'overdue'].includes(inv.status))
      .reduce((sum, inv) => sum + parseFloat(inv.total || 0), 0)
  )

  // Valid status transitions
  const statusTransitions = {
    draft: ['sent', 'cancelled'],
    sent: ['viewed', 'paid', 'overdue', 'cancelled'],
    viewed: ['paid', 'overdue', 'cancelled'],
    paid: [], // Terminal
    overdue: ['paid', 'cancelled'],
    cancelled: [] // Terminal
  }

  // Actions
  async function fetchInvoices(params = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/invoices', { params })
      invoices.value = response.data.data || []
      return invoices.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch invoices'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchInvoice(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/invoices/${id}`)
      currentInvoice.value = response.data.data
      return currentInvoice.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch invoice'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createInvoice(invoiceData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post('/api/invoices', invoiceData)
      const newInvoice = response.data.data
      invoices.value.unshift(newInvoice)
      return newInvoice
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create invoice'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateInvoice(id, invoiceData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.put(`/api/invoices/${id}`, invoiceData)
      const updatedInvoice = response.data.data

      const index = invoices.value.findIndex(inv => inv.id === id)
      if (index !== -1) {
        invoices.value[index] = updatedInvoice
      }

      if (currentInvoice.value?.id === id) {
        currentInvoice.value = updatedInvoice
      }

      return updatedInvoice
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update invoice'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteInvoice(id) {
    loading.value = true
    error.value = null
    try {
      await axios.delete(`/api/invoices/${id}`)
      invoices.value = invoices.value.filter(inv => inv.id !== id)

      if (currentInvoice.value?.id === id) {
        currentInvoice.value = null
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to delete invoice'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateStatus(id, status) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/invoices/${id}/status`, { status })
      const updatedInvoice = response.data.data

      const index = invoices.value.findIndex(inv => inv.id === id)
      if (index !== -1) {
        invoices.value[index] = updatedInvoice
      }

      if (currentInvoice.value?.id === id) {
        currentInvoice.value = updatedInvoice
      }

      return updatedInvoice
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update status'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function sendInvoice(id, emailData = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/invoices/${id}/send`, emailData)
      const updatedInvoice = response.data.data

      const index = invoices.value.findIndex(inv => inv.id === id)
      if (index !== -1) {
        invoices.value[index] = updatedInvoice
      }

      if (currentInvoice.value?.id === id) {
        currentInvoice.value = updatedInvoice
      }

      return response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to send invoice'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function resendInvoice(id, emailData = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/invoices/${id}/resend`, emailData)
      return response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to resend invoice'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function markPaid(id, paymentData = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/invoices/${id}/mark-paid`, paymentData)
      const updatedInvoice = response.data.data

      const index = invoices.value.findIndex(inv => inv.id === id)
      if (index !== -1) {
        invoices.value[index] = updatedInvoice
      }

      if (currentInvoice.value?.id === id) {
        currentInvoice.value = updatedInvoice
      }

      return updatedInvoice
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to mark as paid'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function downloadPdf(id) {
    try {
      const response = await axios.get(`/api/invoices/${id}/pdf`, {
        responseType: 'blob'
      })

      const url = window.URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href = url

      // Extract filename from Content-Disposition if available
      const contentDisposition = response.headers['content-disposition']
      let filename = `invoice-${id}.pdf`
      if (contentDisposition) {
        const match = contentDisposition.match(/filename="?(.+)"?/)
        if (match) filename = match[1]
      }

      link.setAttribute('download', filename)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)

      return true
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to download PDF'
      throw err
    }
  }

  async function previewPdf(id) {
    try {
      const response = await axios.get(`/api/invoices/${id}/preview`, {
        responseType: 'blob'
      })

      const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
      window.open(url, '_blank')

      return url
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to preview PDF'
      throw err
    }
  }

  async function fetchStats() {
    try {
      const response = await axios.get('/api/invoices/stats')
      stats.value = response.data.data
      return stats.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch stats'
      throw err
    }
  }

  async function fetchOverdue() {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/invoices/overdue')
      return response.data.data || []
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch overdue invoices'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createFromProject(projectId, params = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post('/api/invoices/from-project', {
        project_id: projectId,
        ...params
      })
      const newInvoice = response.data.data
      invoices.value.unshift(newInvoice)
      return newInvoice
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create invoice from project'
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

  function setSearchTerm(term) {
    searchTerm.value = term
  }

  function clearError() {
    error.value = null
  }

  function reset() {
    invoices.value = []
    currentInvoice.value = null
    loading.value = false
    error.value = null
    searchTerm.value = ''
    stats.value = null
  }

  return {
    // State
    invoices,
    currentInvoice,
    loading,
    error,
    searchTerm,
    stats,

    // Computed
    draftInvoices,
    sentInvoices,
    paidInvoices,
    overdueInvoices,
    filteredInvoices,
    invoiceCount,
    totalOutstanding,

    // Actions
    fetchInvoices,
    fetchInvoice,
    createInvoice,
    updateInvoice,
    deleteInvoice,
    updateStatus,
    sendInvoice,
    resendInvoice,
    markPaid,
    downloadPdf,
    previewPdf,
    fetchStats,
    fetchOverdue,
    createFromProject,
    canTransitionTo,
    getAvailableStatuses,
    setSearchTerm,
    clearError,
    reset
  }
})
