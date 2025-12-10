<template>
  <div class="space-y-4">
    <div v-if="loading" class="flex items-center justify-center py-8">
      <div class="inline-block w-6 h-6 border-2 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else-if="payments.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
      <svg class="mx-auto w-12 h-12 text-gray-400 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
      </svg>
      <p>No payments found</p>
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="payment in payments"
        :key="payment.id"
        class="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/50"
      >
        <div class="flex items-start justify-between">
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <span class="font-medium text-gray-900 dark:text-white">
                {{ formatCurrency(payment.amount, payment.currency) }}
              </span>
              <PaymentStatus :status="payment.status" />
            </div>
            <p v-if="payment.payment_method_details" class="text-sm text-gray-500 dark:text-gray-400">
              {{ formatPaymentMethod(payment.payment_method_details) }}
            </p>
            <p class="text-xs text-gray-400 dark:text-gray-500">
              {{ formatDateTime(payment.created_at) }}
            </p>
          </div>
          <div v-if="canRefund(payment)" class="ml-4">
            <button
              @click="handleRefund(payment)"
              :disabled="refunding === payment.id"
              class="text-xs text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
            >
              {{ refunding === payment.id ? 'Processing...' : 'Refund' }}
            </button>
          </div>
        </div>

        <!-- Refund info if refunded -->
        <div
          v-if="payment.status === 'refunded' && payment.refund_amount"
          class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700"
        >
          <p class="text-sm text-gray-600 dark:text-gray-400">
            <span class="font-medium">Refunded:</span>
            {{ formatCurrency(payment.refund_amount, payment.currency) }}
            <span v-if="payment.refund_reason" class="text-gray-400">
              — {{ formatRefundReason(payment.refund_reason) }}
            </span>
          </p>
        </div>

        <!-- Failure info if failed -->
        <div
          v-if="payment.status === 'failed' && payment.failure_message"
          class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700"
        >
          <p class="text-sm text-red-600 dark:text-red-400">
            <span class="font-medium">Failed:</span>
            {{ payment.failure_message }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { usePaymentStore } from '@/stores/payments'
import PaymentStatus from './PaymentStatus.vue'

const props = defineProps({
  invoiceId: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['refunded'])

const paymentStore = usePaymentStore()

const loading = ref(true)
const payments = ref([])
const refunding = ref(null)

// Methods
async function loadPayments() {
  loading.value = true
  try {
    payments.value = await paymentStore.fetchPaymentsByInvoice(props.invoiceId)
  } catch (err) {
    console.error('Failed to load payments:', err)
  } finally {
    loading.value = false
  }
}

function canRefund(payment) {
  return payment.status === 'succeeded'
}

async function handleRefund(payment) {
  if (!confirm('Are you sure you want to refund this payment?')) return

  refunding.value = payment.id
  try {
    await paymentStore.refundPayment(payment.id)
    await loadPayments()
    emit('refunded', payment)
  } catch (err) {
    alert('Failed to process refund: ' + (err.message || 'Unknown error'))
  } finally {
    refunding.value = null
  }
}

function formatCurrency(amount, currency = 'USD') {
  return paymentStore.formatCurrency(amount, currency)
}

function formatDateTime(dateString) {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function formatPaymentMethod(details) {
  if (!details) return ''
  if (typeof details === 'string') {
    try {
      details = JSON.parse(details)
    } catch {
      return ''
    }
  }
  if (details.type === 'card' && details.brand && details.last4) {
    return `${details.brand.charAt(0).toUpperCase() + details.brand.slice(1)} •••• ${details.last4}`
  }
  return ''
}

function formatRefundReason(reason) {
  const reasons = {
    requested_by_customer: 'Requested by customer',
    duplicate: 'Duplicate payment',
    fraudulent: 'Fraudulent payment'
  }
  return reasons[reason] || reason
}

// Lifecycle
onMounted(() => {
  loadPayments()
})
</script>
