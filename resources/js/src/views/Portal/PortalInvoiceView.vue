<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <!-- Portal Header -->
    <header class="bg-white dark:bg-gray-800 shadow-sm">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
        <div class="flex items-center gap-4">
          <router-link to="/portal/dashboard" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </router-link>
          <h1 class="text-lg font-semibold text-gray-900 dark:text-white">Invoice Details</h1>
        </div>
        <button
          @click="logout"
          class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
          Logout
        </button>
      </div>
    </header>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-brand-500"></div>
    </div>

    <!-- Invoice Content -->
    <main v-else-if="invoice" class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Invoice Header -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
        <div class="p-6">
          <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div>
              <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Invoice #{{ invoice.invoice_number }}</h2>
              <p class="text-gray-500 dark:text-gray-400 mt-1">Issued: {{ formatDate(invoice.issue_date) }}</p>
            </div>
            <div class="text-left md:text-right">
              <p class="text-sm text-gray-500 dark:text-gray-400">Amount Due</p>
              <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(invoice.balance_due) }}</p>
              <span :class="[
                'mt-2 inline-block px-3 py-1 rounded-full text-sm font-medium',
                statusClass
              ]">
                {{ statusLabel }}
              </span>
            </div>
          </div>
        </div>

        <!-- Due Date Warning -->
        <div
          v-if="invoice.status !== 'paid' && isOverdue"
          class="px-6 py-3 bg-red-50 dark:bg-red-900/20 border-t border-red-200 dark:border-red-800"
        >
          <p class="text-sm text-red-600 dark:text-red-400 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            This invoice is overdue. Due date was {{ formatDate(invoice.due_date) }}
          </p>
        </div>
        <div
          v-else-if="invoice.status !== 'paid'"
          class="px-6 py-3 bg-yellow-50 dark:bg-yellow-900/20 border-t border-yellow-200 dark:border-yellow-800"
        >
          <p class="text-sm text-yellow-600 dark:text-yellow-400">
            Payment due by {{ formatDate(invoice.due_date) }}
          </p>
        </div>
      </div>

      <!-- Line Items -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900/50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Qty</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Rate</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="(item, index) in invoice.items" :key="index">
                <td class="px-6 py-4 text-gray-900 dark:text-white">{{ item.description }}</td>
                <td class="px-6 py-4 text-right text-gray-900 dark:text-white">{{ item.quantity }}</td>
                <td class="px-6 py-4 text-right text-gray-900 dark:text-white">{{ formatCurrency(item.rate) }}</td>
                <td class="px-6 py-4 text-right font-medium text-gray-900 dark:text-white">{{ formatCurrency(item.quantity * item.rate) }}</td>
              </tr>
            </tbody>
            <tfoot class="bg-gray-50 dark:bg-gray-900/50">
              <tr>
                <td colspan="3" class="px-6 py-3 text-right text-gray-600 dark:text-gray-400">Subtotal:</td>
                <td class="px-6 py-3 text-right text-gray-900 dark:text-white">{{ formatCurrency(invoice.subtotal) }}</td>
              </tr>
              <tr v-if="invoice.tax_amount">
                <td colspan="3" class="px-6 py-3 text-right text-gray-600 dark:text-gray-400">Tax:</td>
                <td class="px-6 py-3 text-right text-gray-900 dark:text-white">{{ formatCurrency(invoice.tax_amount) }}</td>
              </tr>
              <tr v-if="invoice.amount_paid > 0">
                <td colspan="3" class="px-6 py-3 text-right text-gray-600 dark:text-gray-400">Paid:</td>
                <td class="px-6 py-3 text-right text-green-600 dark:text-green-400">-{{ formatCurrency(invoice.amount_paid) }}</td>
              </tr>
              <tr class="border-t-2 border-gray-300 dark:border-gray-600">
                <td colspan="3" class="px-6 py-4 text-right font-medium text-gray-900 dark:text-white">Balance Due:</td>
                <td class="px-6 py-4 text-right text-xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(invoice.balance_due) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- Notes -->
      <div v-if="invoice.notes" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-2">Notes</h3>
        <p class="text-gray-600 dark:text-gray-300 whitespace-pre-wrap">{{ invoice.notes }}</p>
      </div>

      <!-- Payment Section -->
      <div v-if="invoice.balance_due > 0" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Pay This Invoice</h3>

        <!-- Payment Method Selection -->
        <div v-if="!selectedMethod" class="space-y-3">
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Select a payment method:</p>
          <button
            v-for="method in paymentMethods"
            :key="method.type"
            @click="selectMethod(method)"
            class="w-full flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
          >
            <div class="flex items-center gap-3">
              <span class="text-gray-900 dark:text-white font-medium">{{ method.name }}</span>
              <span v-if="method.note" class="text-xs text-gray-500 dark:text-gray-400">{{ method.note }}</span>
            </div>
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </div>

        <!-- Credit Card Payment -->
        <div v-else-if="selectedMethod.type === 'stripe_card'" class="space-y-4">
          <button @click="selectedMethod = null" class="text-sm text-brand-600 hover:text-brand-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back
          </button>
          <p class="text-gray-600 dark:text-gray-400">Pay with credit or debit card via Stripe secure checkout.</p>
          <button
            @click="payWithStripe"
            :disabled="processing"
            class="w-full px-4 py-3 bg-brand-600 text-white rounded-lg hover:bg-brand-700 disabled:opacity-50 flex items-center justify-center gap-2"
          >
            <svg v-if="processing" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Pay {{ formatCurrency(invoice.balance_due) }}
          </button>
        </div>

        <!-- Manual Payment Instructions -->
        <div v-else class="space-y-4">
          <button @click="selectedMethod = null" class="text-sm text-brand-600 hover:text-brand-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back
          </button>

          <div v-if="paymentInstructions" class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4">
            <h4 class="font-medium text-gray-900 dark:text-white mb-2">{{ selectedMethod.name }} Instructions</h4>
            <div class="prose dark:prose-invert text-sm max-w-none" v-html="paymentInstructions"></div>
          </div>

          <div class="border-t dark:border-gray-700 pt-4">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
              After making your payment, please provide a reference for our records:
            </p>
            <input
              v-model="paymentReference"
              type="text"
              placeholder="Transaction ID or confirmation number"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-gray-900 dark:text-white dark:bg-gray-800 focus:ring-2 focus:ring-brand-500 mb-3"
            />
            <button
              @click="recordManualPayment"
              :disabled="processing || !paymentReference"
              class="w-full px-4 py-3 bg-brand-600 text-white rounded-lg hover:bg-brand-700 disabled:opacity-50"
            >
              Submit Payment Confirmation
            </button>
          </div>
        </div>
      </div>

      <!-- Already Paid -->
      <div v-else class="bg-green-50 dark:bg-green-900/20 rounded-xl border border-green-200 dark:border-green-800 p-6 text-center">
        <svg class="w-12 h-12 mx-auto text-green-500 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h3 class="text-lg font-semibold text-green-800 dark:text-green-200">Invoice Paid</h3>
        <p class="text-green-600 dark:text-green-400 mt-1">Thank you for your payment!</p>
      </div>

      <!-- Download PDF -->
      <div class="mt-6 text-center">
        <button
          @click="downloadPdf"
          :disabled="downloading"
          class="text-brand-600 hover:text-brand-700 font-medium flex items-center gap-2 mx-auto"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          Download PDF
        </button>
      </div>
    </main>

    <!-- Not Found -->
    <div v-else class="text-center py-12">
      <p class="text-gray-500 dark:text-gray-400">Invoice not found.</p>
      <router-link to="/portal/dashboard" class="text-brand-600 hover:text-brand-700 mt-2 inline-block">
        Back to Dashboard
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePortalStore } from '@/stores/portal'

const route = useRoute()
const router = useRouter()
const portalStore = usePortalStore()

const loading = ref(true)
const processing = ref(false)
const downloading = ref(false)
const invoice = ref(null)
const paymentMethods = ref([])
const selectedMethod = ref(null)
const paymentInstructions = ref('')
const paymentReference = ref('')

const isOverdue = computed(() => {
  if (!invoice.value?.due_date) return false
  return new Date(invoice.value.due_date) < new Date()
})

const statusClass = computed(() => {
  const classes = {
    draft: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    sent: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    viewed: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
    paid: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    partial: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
    overdue: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
  }
  const status = isOverdue.value && invoice.value?.status !== 'paid' ? 'overdue' : invoice.value?.status
  return classes[status] || classes.draft
})

const statusLabel = computed(() => {
  if (isOverdue.value && invoice.value?.status !== 'paid') return 'Overdue'
  return invoice.value?.status?.charAt(0).toUpperCase() + invoice.value?.status?.slice(1) || 'Draft'
})

function formatCurrency(amount) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(amount || 0)
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

async function selectMethod(method) {
  selectedMethod.value = method

  if (!['stripe_card', 'paypal'].includes(method.type)) {
    try {
      const instructions = await portalStore.getPaymentInstructions(invoice.value.id, method.type)
      paymentInstructions.value = instructions
    } catch (err) {
      console.error('Failed to get payment instructions:', err)
    }
  }
}

async function payWithStripe() {
  processing.value = true
  try {
    const intent = await portalStore.createStripeIntent(invoice.value.id)
    // Redirect to Stripe checkout
    window.location.href = intent.url
  } catch (err) {
    console.error('Failed to initiate payment:', err)
  } finally {
    processing.value = false
  }
}

async function recordManualPayment() {
  if (!paymentReference.value) return

  processing.value = true
  try {
    await portalStore.recordManualPayment(
      invoice.value.id,
      selectedMethod.value.type,
      paymentReference.value
    )
    // Reload invoice to show pending status
    invoice.value = await portalStore.fetchInvoice(route.params.id)
    selectedMethod.value = null
    paymentReference.value = ''
  } catch (err) {
    console.error('Failed to record payment:', err)
  } finally {
    processing.value = false
  }
}

async function downloadPdf() {
  downloading.value = true
  try {
    await portalStore.downloadInvoicePdf(invoice.value.id)
  } catch (err) {
    console.error('Failed to download PDF:', err)
  } finally {
    downloading.value = false
  }
}

function logout() {
  portalStore.logout()
  router.push('/portal')
}

onMounted(async () => {
  try {
    invoice.value = await portalStore.fetchInvoice(route.params.id)
    paymentMethods.value = await portalStore.fetchPaymentMethods()
  } catch (err) {
    console.error('Failed to load invoice:', err)
  } finally {
    loading.value = false
  }
})
</script>
