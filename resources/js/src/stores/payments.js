import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'
import { loadStripe } from '@stripe/stripe-js'

export const usePaymentStore = defineStore('payments', () => {
  // State
  const payments = ref([])
  const currentPayment = ref(null)
  const pendingManual = ref([])
  const availableMethods = ref([])
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

  // Payment method configurations
  const paymentMethodConfig = {
    stripe_card: {
      name: 'Credit/Debit Card',
      icon: 'credit-card',
      instant: true
    },
    stripe_ach: {
      name: 'Bank Transfer (ACH)',
      icon: 'bank',
      instant: false,
      note: 'Takes 4-5 business days'
    },
    paypal: {
      name: 'PayPal',
      icon: 'paypal',
      instant: true
    },
    zelle: {
      name: 'Zelle',
      icon: 'zelle',
      instant: false,
      note: 'Pending verification'
    },
    check: {
      name: 'Check',
      icon: 'check',
      instant: false,
      note: 'Pending verification'
    },
    wire: {
      name: 'Wire Transfer',
      icon: 'wire',
      instant: false,
      note: 'Pending verification'
    }
  }

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

  const pendingManualCount = computed(() => pendingManual.value.length)

  const totalCollected = computed(() =>
    succeededPayments.value.reduce((sum, p) => sum + parseFloat(p.amount || 0), 0)
  )

  // Actions

  async function fetchAvailableMethods(invoiceId) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/invoices/${invoiceId}/payment-methods`)
      if (response.data.success) {
        availableMethods.value = response.data.data || []
      }
      return availableMethods.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch payment methods'
      throw err
    } finally {
      loading.value = false
    }
  }
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

  // PayPal Payment Methods
  async function createPayPalOrder(invoiceId) {
    processingPayment.value = true
    error.value = null
    try {
      const response = await axios.post('/api/payments/paypal/create-order', {
        invoice_id: invoiceId
      })
      if (response.data.success) {
        return response.data.data
      }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create PayPal order'
      throw err
    } finally {
      processingPayment.value = false
    }
  }

  async function capturePayPalOrder(orderId) {
    processingPayment.value = true
    error.value = null
    try {
      const response = await axios.post('/api/payments/paypal/capture', {
        order_id: orderId
      })
      if (response.data.success) {
        // Refresh payments list
        await fetchPayments()
        return response.data.data
      }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to capture PayPal payment'
      throw err
    } finally {
      processingPayment.value = false
    }
  }

  // Stripe ACH Payment Methods
  async function createACHIntent(invoiceId) {
    processingPayment.value = true
    error.value = null
    try {
      const response = await axios.post('/api/payments/ach/create-intent', {
        invoice_id: invoiceId
      })
      if (response.data.success) {
        return response.data.data
      }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create ACH payment intent'
      throw err
    } finally {
      processingPayment.value = false
    }
  }

  async function verifyACHMicrodeposits(paymentIntentId, amounts) {
    processingPayment.value = true
    error.value = null
    try {
      const response = await axios.post('/api/payments/ach/verify-microdeposits', {
        payment_intent_id: paymentIntentId,
        amounts
      })
      if (response.data.success) {
        return response.data.data
      }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to verify microdeposits'
      throw err
    } finally {
      processingPayment.value = false
    }
  }

  // Manual Payments (Zelle, Check, Wire)
  async function recordManualPayment(invoiceId, paymentMethod, amount = null, reference = '') {
    processingPayment.value = true
    error.value = null
    try {
      const response = await axios.post('/api/payments/manual/record', {
        invoice_id: invoiceId,
        payment_method: paymentMethod,
        amount,
        reference
      })
      if (response.data.success) {
        await fetchPayments()
        return response.data.data
      }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to record payment'
      throw err
    } finally {
      processingPayment.value = false
    }
  }

  async function fetchPendingManualPayments() {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/payments/manual/pending')
      if (response.data.success) {
        pendingManual.value = response.data.data || []
      }
      return pendingManual.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch pending payments'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function verifyManualPayment(paymentId, reference = '', notes = '') {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/payments/manual/${paymentId}/verify`, {
        reference,
        notes
      })
      if (response.data.success) {
        await fetchPayments()
        pendingManual.value = pendingManual.value.filter(p => p.id !== paymentId)
        return response.data.data
      }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to verify payment'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function rejectManualPayment(paymentId, reason) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/payments/manual/${paymentId}/reject`, {
        reason
      })
      if (response.data.success) {
        await fetchPayments()
        pendingManual.value = pendingManual.value.filter(p => p.id !== paymentId)
        return response.data.data
      }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to reject payment'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function getPaymentInstructions(invoiceId, method) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/invoices/${invoiceId}/payment-instructions/${method}`)
      if (response.data.success) {
        return response.data.data
      }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to get payment instructions'
      throw err
    } finally {
      loading.value = false
    }
  }

  function getMethodConfig(method) {
    return paymentMethodConfig[method] || { name: method, icon: 'credit-card' }
  }

  // Clear store state
  function clearState() {
    payments.value = []
    currentPayment.value = null
    pendingManual.value = []
    availableMethods.value = []
    error.value = null
  }

  return {
    // State
    payments,
    currentPayment,
    pendingManual,
    availableMethods,
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
    pendingManualCount,
    totalCollected,

    // Config
    paymentMethodConfig,

    // Actions - Stripe Card
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

    // Actions - Payment Methods
    fetchAvailableMethods,
    getPaymentInstructions,
    getMethodConfig,

    // Actions - PayPal
    createPayPalOrder,
    capturePayPalOrder,

    // Actions - Stripe ACH
    createACHIntent,
    verifyACHMicrodeposits,

    // Actions - Manual Payments
    recordManualPayment,
    fetchPendingManualPayments,
    verifyManualPayment,
    rejectManualPayment,

    // Utilities
    getStatusDisplay,
    formatCurrency,
    clearState,
  }
})
