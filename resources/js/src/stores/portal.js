import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

/**
 * Client Portal Store
 *
 * Manages client portal state for self-service access.
 * Handles magic link authentication, invoice viewing, and payments.
 * Portal-specific API endpoints with token-based authentication.
 */
export const usePortalStore = defineStore('portal', () => {
  // State
  const isAuthenticated = ref(false)
  const client = ref(null)
  const portalToken = ref(null)
  const invoices = ref([])
  const proposals = ref([])
  const payments = ref([])
  const currentInvoice = ref(null)
  const currentProposal = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const paymentMethods = ref([])

  // Computed
  const unpaidInvoices = computed(() =>
    invoices.value.filter(inv => ['sent', 'viewed', 'overdue'].includes(inv.status))
  )

  const paidInvoices = computed(() =>
    invoices.value.filter(inv => inv.status === 'paid')
  )

  const pendingProposals = computed(() =>
    proposals.value.filter(p => ['sent', 'viewed'].includes(p.status))
  )

  const totalDue = computed(() =>
    unpaidInvoices.value.reduce((sum, inv) => sum + parseFloat(inv.total || 0), 0)
  )

  const clientName = computed(() => client.value?.name || 'Guest')

  // Actions

  /**
   * Request magic link for portal access
   */
  async function requestMagicLink(email) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post('/api/portal/request-access', { email })
      return response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to send access link'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Authenticate with magic link token
   */
  async function authenticateWithToken(token) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post('/api/portal/authenticate', { token })
      const data = response.data.data

      portalToken.value = data.portal_token
      client.value = data.client
      isAuthenticated.value = true

      // Store token for subsequent requests
      axios.defaults.headers.common['X-Portal-Token'] = data.portal_token
      localStorage.setItem('portalToken', data.portal_token)

      return data
    } catch (err) {
      error.value = err.response?.data?.error || 'Invalid or expired access link'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Restore session from stored token
   */
  async function restoreSession() {
    const storedToken = localStorage.getItem('portalToken')
    if (!storedToken) return false

    loading.value = true
    error.value = null
    try {
      axios.defaults.headers.common['X-Portal-Token'] = storedToken
      const response = await axios.get('/api/portal/session')
      const data = response.data.data

      portalToken.value = storedToken
      client.value = data.client
      isAuthenticated.value = true

      return true
    } catch (err) {
      // Token invalid or expired
      localStorage.removeItem('portalToken')
      delete axios.defaults.headers.common['X-Portal-Token']
      return false
    } finally {
      loading.value = false
    }
  }

  /**
   * Logout from portal
   */
  function logout() {
    isAuthenticated.value = false
    client.value = null
    portalToken.value = null
    invoices.value = []
    proposals.value = []
    payments.value = []
    currentInvoice.value = null
    currentProposal.value = null
    paymentMethods.value = []

    localStorage.removeItem('portalToken')
    delete axios.defaults.headers.common['X-Portal-Token']
  }

  /**
   * Fetch client invoices
   */
  async function fetchInvoices() {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/portal/invoices')
      invoices.value = response.data.data || []
      return invoices.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch invoices'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch single invoice details
   */
  async function fetchInvoice(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/portal/invoices/${id}`)
      currentInvoice.value = response.data.data
      return currentInvoice.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch invoice'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Download invoice PDF
   */
  async function downloadInvoicePdf(id) {
    try {
      const response = await axios.get(`/api/portal/invoices/${id}/pdf`, {
        responseType: 'blob'
      })

      const url = window.URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href = url

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

  /**
   * Fetch client proposals
   */
  async function fetchProposals() {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/portal/proposals')
      proposals.value = response.data.data || []
      return proposals.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch proposals'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch single proposal details
   */
  async function fetchProposal(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/portal/proposals/${id}`)
      currentProposal.value = response.data.data
      return currentProposal.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch proposal'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Accept proposal with e-signature
   */
  async function acceptProposal(id, signatureData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/portal/proposals/${id}/accept`, signatureData)
      const updatedProposal = response.data.data

      const index = proposals.value.findIndex(p => p.id === id)
      if (index !== -1) {
        proposals.value[index] = updatedProposal
      }

      if (currentProposal.value?.id === id) {
        currentProposal.value = updatedProposal
      }

      return updatedProposal
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to accept proposal'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Reject proposal
   */
  async function rejectProposal(id, reason = '') {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/portal/proposals/${id}/reject`, { reason })
      const updatedProposal = response.data.data

      const index = proposals.value.findIndex(p => p.id === id)
      if (index !== -1) {
        proposals.value[index] = updatedProposal
      }

      if (currentProposal.value?.id === id) {
        currentProposal.value = updatedProposal
      }

      return updatedProposal
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to reject proposal'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch available payment methods for invoice
   */
  async function fetchPaymentMethods(invoiceId) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/portal/invoices/${invoiceId}/payment-methods`)
      paymentMethods.value = response.data.data || []
      return paymentMethods.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch payment methods'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Create Stripe payment intent
   */
  async function createStripeIntent(invoiceId) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/portal/invoices/${invoiceId}/pay/stripe`)
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create payment'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Create PayPal order
   */
  async function createPayPalOrder(invoiceId) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/portal/invoices/${invoiceId}/pay/paypal`)
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create PayPal order'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Capture PayPal payment
   */
  async function capturePayPalPayment(orderId) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post('/api/portal/payments/paypal/capture', {
        order_id: orderId
      })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to capture payment'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Record manual payment (Zelle, check, etc.)
   */
  async function recordManualPayment(invoiceId, paymentMethod, reference = '') {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/portal/invoices/${invoiceId}/pay/manual`, {
        payment_method: paymentMethod,
        reference
      })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to record payment'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Get payment instructions for manual payment method
   */
  async function getPaymentInstructions(invoiceId, method) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/portal/invoices/${invoiceId}/payment-instructions/${method}`)
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to get payment instructions'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch payment history
   */
  async function fetchPayments() {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/portal/payments')
      payments.value = response.data.data || []
      return payments.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch payments'
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Update client profile
   */
  async function updateProfile(profileData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.put('/api/portal/profile', profileData)
      client.value = response.data.data
      return client.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update profile'
      throw err
    } finally {
      loading.value = false
    }
  }

  function clearError() {
    error.value = null
  }

  function reset() {
    isAuthenticated.value = false
    client.value = null
    portalToken.value = null
    invoices.value = []
    proposals.value = []
    payments.value = []
    currentInvoice.value = null
    currentProposal.value = null
    loading.value = false
    error.value = null
    paymentMethods.value = []
  }

  return {
    // State
    isAuthenticated,
    client,
    portalToken,
    invoices,
    proposals,
    payments,
    currentInvoice,
    currentProposal,
    loading,
    error,
    paymentMethods,

    // Computed
    unpaidInvoices,
    paidInvoices,
    pendingProposals,
    totalDue,
    clientName,

    // Actions
    requestMagicLink,
    authenticateWithToken,
    restoreSession,
    logout,
    fetchInvoices,
    fetchInvoice,
    downloadInvoicePdf,
    fetchProposals,
    fetchProposal,
    acceptProposal,
    rejectProposal,
    fetchPaymentMethods,
    createStripeIntent,
    createPayPalOrder,
    capturePayPalPayment,
    recordManualPayment,
    getPaymentInstructions,
    fetchPayments,
    updateProfile,
    clearError,
    reset
  }
})
