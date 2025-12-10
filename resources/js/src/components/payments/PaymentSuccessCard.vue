<template>
  <div class="max-w-md mx-auto">
    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-8 text-center">
      <!-- Loading State -->
      <div v-if="loading" class="py-8">
        <div class="inline-block w-10 h-10 border-4 border-brand-500 border-t-transparent rounded-full animate-spin mb-4"></div>
        <p class="text-gray-600 dark:text-gray-400">Verifying payment...</p>
      </div>

      <!-- Success State -->
      <template v-else-if="paymentData">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/20 mb-4">
          <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>

        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
          Payment Successful!
        </h2>

        <p class="text-gray-600 dark:text-gray-400 mb-6">
          Thank you for your payment. A receipt has been sent to your email.
        </p>

        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4 mb-6">
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div class="text-left">
              <p class="text-gray-500 dark:text-gray-400">Payment ID</p>
              <p class="font-medium text-gray-900 dark:text-white">
                {{ paymentData.payment_id?.slice(0, 8) }}...
              </p>
            </div>
            <div class="text-right">
              <p class="text-gray-500 dark:text-gray-400">Status</p>
              <p class="font-medium text-green-600 dark:text-green-400 capitalize">
                {{ paymentData.status }}
              </p>
            </div>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 justify-center">
          <router-link
            :to="`/invoices/${paymentData.invoice_id}`"
            class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            View Invoice
          </router-link>
          <router-link
            to="/invoices"
            class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
          >
            Back to Invoices
          </router-link>
        </div>
      </template>

      <!-- Error State -->
      <template v-else-if="error">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-100 dark:bg-red-900/20 mb-4">
          <svg class="w-8 h-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </div>

        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
          Payment Verification Failed
        </h2>

        <p class="text-gray-600 dark:text-gray-400 mb-6">
          {{ error }}
        </p>

        <router-link
          to="/invoices"
          class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
        >
          Back to Invoices
        </router-link>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { usePaymentStore } from '@/stores/payments'

const route = useRoute()
const paymentStore = usePaymentStore()

const loading = ref(true)
const paymentData = ref(null)
const error = ref(null)

async function verifyPayment() {
  const sessionId = route.query.session_id

  if (!sessionId) {
    error.value = 'No session ID provided'
    loading.value = false
    return
  }

  try {
    const result = await paymentStore.verifyPaymentSuccess(sessionId)
    if (result) {
      paymentData.value = result
    } else {
      error.value = 'Could not verify payment status'
    }
  } catch (err) {
    error.value = err.message || 'Failed to verify payment'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  verifyPayment()
})
</script>
