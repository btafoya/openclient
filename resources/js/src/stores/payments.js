import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'
import { loadStripe } from '@stripe/stripe-js'

export const usePaymentStore = defineStore('payments', () => {
  // State
  const payments = ref([])
  const currentPayment = ref(null)
  const stats = ref({
    total_payments: 0,
    succeeded: 0,
    failed: 0,
    pending: 0,
    refunded: 0,
    total_collected: 0,
    total_refunded: 0,
  })
  const stripeConfig = ref({
    publishable_key: null,
    configured: false,
  })
  const stripePromise = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const processingPayment = ref(false)

  // Getters
  const succeededPayments = computed(() =>
    payments.value.filter(p => p.status === 'succeeded')
  )

  const pendingPayments = computed(() =>
    payments.value.filter(p => p.status === 'pending' || p.status === 'processing')
  )

  const failedPayments = computed(() =>
    payments.value.filter(p => p.status === 'failed')
  )

  const refundedPayments = computed(() =>
    payments.value.filter(p => p.status === 'refunded')
  )

  const isStripeConfigured = computed(() => stripeConfig.value.configured)

  // Actions
  async function fetchPayments(filters = {}) {
    loading.value = true
    error.value = null

    try {
      const params = new URLSearchParams()
      if (filters.status) params.append('status', filters.status)
      if (filters.from_date) params.append('from_date', filters.from_date)
      if (filters.to_date) params.append('to_date', filters.to_date)

      const queryString = params.toString()
      const url = `/api/payments${queryString ? '?' + queryString : ''}`

      const response = await axios.get(url)
      if (response.data.success) {
        payments.value = response.data.data
      }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch payments'
      console.error('Failed to fetch payments:', err)
    } finally {
      loading.value = false
    }
  }

  async function fetchPayment(id) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get(`/api/payments/${id}`)
      if (response.data.success) {
        currentPayment.value = response.data.data
        return response.data.data
      }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch payment'
      console.error('Failed to fetch payment:', err)
    } finally {
      loading.value = false
    }
    return null
  }

  async function fetchPaymentsByInvoice(invoiceId) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get(`/api/invoices/${invoiceId}/payments`)
      if (response.data.success) {
        return response.data.data
      }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch invoice payments'
      console.error('Failed to fetch invoice payments:', err)
    } finally {
      loading.value = false
    }
    return []
  }

  async function fetchStats() {
    try {
      const response = await axios.get('/api/payments/stats')
      if (response.data.success) {
        stats.value = response.data.data
      }
    } catch (err) {
      console.error('Failed to fetch payment stats:', err)
    }
  }

  async function fetchStripeConfig() {
    try {
      const response = await axios.get('/api/payments/config')
      if (response.data.success) {
        stripeConfig.value = response.data.data

        // Initialize Stripe if configured
        if (stripeConfig.value.publishable_key) {
          stripePromise.value = loadStripe(stripeConfig.value.publishable_key)
        }
      }
    } catch (err) {
      console.error('Failed to fetch Stripe config:', err)
      stripeConfig.value = { publishable_key: null, configured: false }
    }
  }

  async function createCheckoutSession(invoiceId) {
    processingPayment.value = true
    error.value = null

    try {
      const response = await axios.post('/api/payments/checkout', {
        invoice_id: invoiceId,
      })

      if (response.data.success) {
        return response.data.data
      }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create checkout session'
      console.error('Failed to create checkout session:', err)
      throw err
    } finally {
      processingPayment.value = false
    }
  }

  async function redirectToCheckout(invoiceId) {
    processingPayment.value = true
    error.value = null

    try {
      // Ensure Stripe is loaded
      if (!stripePromise.value) {
        await fetchStripeConfig()
      }

      // Create checkout session
      const sessionData = await createCheckoutSession(invoiceId)

      if (!sessionData || !sessionData.url) {
        throw new Error('Invalid checkout session response')
      }

      // Redirect to Stripe Checkout
      window.location.href = sessionData.url

      return sessionData
    } catch (err) {
      error.value = err.response?.data?.error || err.message || 'Failed to redirect to checkout'
      processingPayment.value = false
      throw err
    }
  }

  async function verifyPaymentSuccess(sessionId) {
    try {
      const response = await axios.get(`/api/payments/success?session_id=${sessionId}`)
      if (response.data.success) {
        return response.data.data
      }
    } catch (err) {
      console.error('Failed to verify payment:', err)
    }
    return null
  }

  async function handlePaymentCancel(sessionId = null) {
    try {
      const url = sessionId
        ? `/api/payments/cancel?session_id=${sessionId}`
        : '/api/payments/cancel'
      await axios.get(url)
    } catch (err) {
      console.error('Failed to handle payment cancel:', err)
    }
  }

  async function refundPayment(paymentId, amount = null, reason = 'requested_by_customer') {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post(`/api/payments/${paymentId}/refund`, {
        amount,
        reason,
      })

      if (response.data.success) {
        // Refresh payments list
        await fetchPayments()
        return response.data.data
      }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to process refund'
      console.error('Failed to process refund:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  // Get Stripe instance (for advanced use cases)
  async function getStripe() {
    if (!stripePromise.value) {
      await fetchStripeConfig()
    }
    return stripePromise.value
  }

  // Format payment status for display
  function getStatusDisplay(status) {
    const statusMap = {
      pending: { label: 'Pending', color: 'yellow' },
      processing: { label: 'Processing', color: 'blue' },
      succeeded: { label: 'Paid', color: 'green' },
      failed: { label: 'Failed', color: 'red' },
      refunded: { label: 'Refunded', color: 'gray' },
      cancelled: { label: 'Cancelled', color: 'gray' },
    }
    return statusMap[status] || { label: status, color: 'gray' }
  }

  // Format currency for display
  function formatCurrency(amount, currency = 'USD') {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency,
    }).format(amount)
  }

  // Clear store state
  function clearState() {
    payments.value = []
    currentPayment.value = null
    error.value = null
  }

  return {
    // State
    payments,
    currentPayment,
    stats,
    stripeConfig,
    loading,
    error,
    processingPayment,

    // Getters
    succeededPayments,
    pendingPayments,
    failedPayments,
    refundedPayments,
    isStripeConfigured,

    // Actions
    fetchPayments,
    fetchPayment,
    fetchPaymentsByInvoice,
    fetchStats,
    fetchStripeConfig,
    createCheckoutSession,
    redirectToCheckout,
    verifyPaymentSuccess,
    handlePaymentCancel,
    refundPayment,
    getStripe,
    getStatusDisplay,
    formatCurrency,
    clearState,
  }
})
