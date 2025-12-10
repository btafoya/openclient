<template>
  <button
    @click="handlePayment"
    :disabled="isDisabled"
    :class="[
      'inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200',
      isDisabled
        ? 'bg-gray-300 text-gray-500 cursor-not-allowed dark:bg-gray-700 dark:text-gray-500'
        : 'bg-brand-600 text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600',
      buttonClass
    ]"
  >
    <!-- Loading Spinner -->
    <svg
      v-if="processing"
      class="w-4 h-4 animate-spin"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle
        class="opacity-25"
        cx="12"
        cy="12"
        r="10"
        stroke="currentColor"
        stroke-width="4"
      />
      <path
        class="opacity-75"
        fill="currentColor"
        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
      />
    </svg>

    <!-- Credit Card Icon -->
    <svg
      v-else
      class="w-4 h-4"
      fill="none"
      stroke="currentColor"
      viewBox="0 0 24 24"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        stroke-width="2"
        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
      />
    </svg>

    <span>{{ buttonText }}</span>
  </button>
</template>

<script setup>
import { computed } from 'vue'
import { usePaymentStore } from '@/stores/payments'

const props = defineProps({
  invoiceId: {
    type: String,
    required: true
  },
  amount: {
    type: Number,
    default: 0
  },
  currency: {
    type: String,
    default: 'USD'
  },
  label: {
    type: String,
    default: 'Pay Now'
  },
  buttonClass: {
    type: String,
    default: ''
  },
  disabled: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['success', 'error', 'processing'])

const paymentStore = usePaymentStore()

// Computed
const processing = computed(() => paymentStore.processingPayment)

const isDisabled = computed(() => {
  return props.disabled || processing.value || !paymentStore.isStripeConfigured
})

const buttonText = computed(() => {
  if (processing.value) return 'Processing...'
  if (!paymentStore.isStripeConfigured) return 'Payments Unavailable'
  if (props.amount > 0) {
    return `${props.label} $${props.amount.toFixed(2)}`
  }
  return props.label
})

// Methods
async function handlePayment() {
  if (isDisabled.value) return

  emit('processing', true)

  try {
    await paymentStore.redirectToCheckout(props.invoiceId)
    // Note: redirectToCheckout will redirect the browser
    // Success/error handling happens on return pages
    emit('success')
  } catch (err) {
    console.error('Payment initiation failed:', err)
    emit('error', err.message || 'Failed to initiate payment')
    emit('processing', false)
  }
}
</script>
