<template>
  <div class="max-w-md mx-auto">
    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-8 text-center">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-yellow-100 dark:bg-yellow-900/20 mb-4">
        <svg class="w-8 h-8 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
      </div>

      <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
        Payment Cancelled
      </h2>

      <p class="text-gray-600 dark:text-gray-400 mb-6">
        Your payment was cancelled. No charges have been made to your account.
      </p>

      <div class="flex flex-col sm:flex-row gap-3 justify-center">
        <button
          v-if="invoiceId"
          @click="retryPayment"
          :disabled="retrying"
          class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
        >
          <svg v-if="retrying" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          {{ retrying ? 'Redirecting...' : 'Try Again' }}
        </button>
        <router-link
          to="/invoices"
          class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
        >
          Back to Invoices
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { usePaymentStore } from '@/stores/payments'

const route = useRoute()
const paymentStore = usePaymentStore()

const invoiceId = ref(null)
const retrying = ref(false)

async function retryPayment() {
  if (!invoiceId.value) return

  retrying.value = true
  try {
    await paymentStore.redirectToCheckout(invoiceId.value)
  } catch (err) {
    console.error('Retry payment failed:', err)
    alert('Failed to initiate payment. Please try again.')
    retrying.value = false
  }
}

onMounted(async () => {
  const sessionId = route.query.session_id

  if (sessionId) {
    // Notify backend of cancellation
    await paymentStore.handlePaymentCancel(sessionId)
  }

  // Try to extract invoice_id from URL if provided
  invoiceId.value = route.query.invoice_id || null
})
</script>
