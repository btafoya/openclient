<template>
  <AdminLayout>
    <div class="space-y-6">
      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center py-12">
        <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="p-8 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/20">
          <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </div>
        <p class="mt-4 text-sm text-gray-900 dark:text-white font-medium">{{ error }}</p>
        <router-link to="/invoices" class="mt-4 inline-block px-4 py-2 text-sm font-medium text-brand-600 hover:text-brand-700">
          Back to Invoices
        </router-link>
      </div>

      <!-- Invoice Content -->
      <template v-else-if="invoice">
        <!-- Page Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <div class="flex items-center gap-3">
              <router-link
                to="/invoices"
                class="p-1.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
              </router-link>
              <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ invoice.invoice_number }}
              </h1>
              <span
                :class="[
                  'inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full capitalize',
                  getStatusClasses(invoice.status)
                ]"
              >
                {{ invoice.status }}
              </span>
            </div>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              Created {{ formatDate(invoice.created_at) }}
            </p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button
              @click="previewPdf"
              class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              Preview
            </button>
            <button
              @click="downloadPdf"
              class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              Download PDF
            </button>
            <button
              v-if="invoice.status === 'draft'"
              @click="sendInvoice"
              class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              Send Invoice
            </button>
            <button
              v-if="['sent', 'viewed', 'overdue'].includes(invoice.status)"
              @click="markAsPaid"
              class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Mark as Paid
            </button>
            <router-link
              v-if="invoice.status === 'draft'"
              :to="`/invoices/${invoice.id}/edit`"
              class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
              Edit
            </router-link>
          </div>
        </div>

        <!-- Invoice Details Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Main Invoice Details -->
          <div class="lg:col-span-2 space-y-6">
            <!-- Client & Project Info -->
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-6">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Invoice Details</h2>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Bill To -->
                <div>
                  <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Bill To</h3>
                  <p class="text-gray-900 dark:text-white font-medium">{{ invoice.client_name || 'No client' }}</p>
                  <p v-if="invoice.client_address" class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-line">
                    {{ invoice.client_address }}
                  </p>
                  <p v-if="invoice.client_email" class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    {{ invoice.client_email }}
                  </p>
                </div>

                <!-- Project -->
                <div v-if="invoice.project_name">
                  <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Project</h3>
                  <router-link
                    :to="`/projects/${invoice.project_id}`"
                    class="text-brand-600 hover:text-brand-700 font-medium"
                  >
                    {{ invoice.project_name }}
                  </router-link>
                </div>

                <!-- Invoice Date -->
                <div>
                  <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Invoice Date</h3>
                  <p class="text-gray-900 dark:text-white">{{ formatDate(invoice.invoice_date) }}</p>
                </div>

                <!-- Due Date -->
                <div>
                  <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Due Date</h3>
                  <p :class="['font-medium', isOverdue ? 'text-red-600' : 'text-gray-900 dark:text-white']">
                    {{ formatDate(invoice.due_date) }}
                    <span v-if="isOverdue" class="text-sm font-normal">(Overdue)</span>
                  </p>
                </div>
              </div>
            </div>

            <!-- Line Items -->
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] overflow-hidden">
              <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Line Items</h2>
              </div>
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Description</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Qty</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Rate</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Amount</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                  <tr v-for="item in invoice.line_items || []" :key="item.id">
                    <td class="px-6 py-4">
                      <p class="text-gray-900 dark:text-white">{{ item.description }}</p>
                      <p v-if="item.notes" class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ item.notes }}</p>
                    </td>
                    <td class="px-6 py-4 text-right text-gray-600 dark:text-gray-400">
                      {{ item.quantity }}
                    </td>
                    <td class="px-6 py-4 text-right text-gray-600 dark:text-gray-400">
                      ${{ formatCurrency(item.rate) }}
                    </td>
                    <td class="px-6 py-4 text-right font-medium text-gray-900 dark:text-white">
                      ${{ formatCurrency(item.amount) }}
                    </td>
                  </tr>
                  <tr v-if="!invoice.line_items?.length">
                    <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                      No line items
                    </td>
                  </tr>
                </tbody>
              </table>

              <!-- Totals -->
              <div class="border-t border-gray-200 dark:border-gray-700 p-6 bg-gray-50 dark:bg-gray-800/50">
                <div class="flex flex-col items-end space-y-2">
                  <div class="flex justify-between w-64">
                    <span class="text-gray-600 dark:text-gray-400">Subtotal</span>
                    <span class="text-gray-900 dark:text-white">${{ formatCurrency(invoice.subtotal) }}</span>
                  </div>
                  <div v-if="invoice.discount > 0" class="flex justify-between w-64">
                    <span class="text-gray-600 dark:text-gray-400">Discount</span>
                    <span class="text-red-600">-${{ formatCurrency(invoice.discount) }}</span>
                  </div>
                  <div v-if="invoice.tax > 0" class="flex justify-between w-64">
                    <span class="text-gray-600 dark:text-gray-400">Tax ({{ invoice.tax_rate || 0 }}%)</span>
                    <span class="text-gray-900 dark:text-white">${{ formatCurrency(invoice.tax) }}</span>
                  </div>
                  <div class="flex justify-between w-64 pt-2 border-t border-gray-200 dark:border-gray-700">
                    <span class="text-lg font-semibold text-gray-900 dark:text-white">Total</span>
                    <span class="text-lg font-bold text-gray-900 dark:text-white">${{ formatCurrency(invoice.total) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Notes -->
            <div v-if="invoice.notes" class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-6">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Notes</h2>
              <p class="text-gray-600 dark:text-gray-400 whitespace-pre-line">{{ invoice.notes }}</p>
            </div>

            <!-- Terms -->
            <div v-if="invoice.terms" class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-6">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Terms & Conditions</h2>
              <p class="text-gray-600 dark:text-gray-400 whitespace-pre-line">{{ invoice.terms }}</p>
            </div>
          </div>

          <!-- Sidebar -->
          <div class="space-y-6">
            <!-- Payment Error Alert -->
            <div
              v-if="paymentError"
              class="p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800"
            >
              <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm text-red-700 dark:text-red-300">{{ paymentError }}</p>
              </div>
            </div>

            <!-- Payment Info -->
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-6">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Payment Information</h2>

              <div class="space-y-4">
                <div>
                  <p class="text-sm text-gray-500 dark:text-gray-400">Amount Due</p>
                  <p class="text-2xl font-bold text-gray-900 dark:text-white">${{ formatCurrency(invoice.total) }}</p>
                </div>

                <div v-if="invoice.paid_at">
                  <p class="text-sm text-gray-500 dark:text-gray-400">Paid On</p>
                  <p class="text-gray-900 dark:text-white">{{ formatDate(invoice.paid_at) }}</p>
                </div>

                <div v-if="invoice.payment_method">
                  <p class="text-sm text-gray-500 dark:text-gray-400">Payment Method</p>
                  <p class="text-gray-900 dark:text-white capitalize">{{ invoice.payment_method }}</p>
                </div>

                <!-- Pay Now Button -->
                <div v-if="canPay" class="pt-4 border-t border-gray-200 dark:border-gray-700">
                  <PaymentButton
                    :invoice-id="invoice.id"
                    :amount="Number(invoice.total)"
                    :currency="invoice.currency || 'USD'"
                    label="Pay Now"
                    button-class="w-full justify-center"
                    @error="handlePaymentError"
                  />
                </div>
              </div>
            </div>

            <!-- Payment History -->
            <div v-if="invoice.status !== 'draft'" class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-6">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Payment History</h2>
              <PaymentHistory
                :invoice-id="invoice.id"
                @refunded="handlePaymentRefunded"
              />
            </div>

            <!-- Status History -->
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-6">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Activity</h2>

              <div class="space-y-4">
                <div class="flex items-start gap-3">
                  <div class="w-2 h-2 mt-2 rounded-full bg-green-500"></div>
                  <div>
                    <p class="text-sm text-gray-900 dark:text-white">Invoice Created</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDateTime(invoice.created_at) }}</p>
                  </div>
                </div>
                <div v-if="invoice.sent_at" class="flex items-start gap-3">
                  <div class="w-2 h-2 mt-2 rounded-full bg-blue-500"></div>
                  <div>
                    <p class="text-sm text-gray-900 dark:text-white">Invoice Sent</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDateTime(invoice.sent_at) }}</p>
                  </div>
                </div>
                <div v-if="invoice.viewed_at" class="flex items-start gap-3">
                  <div class="w-2 h-2 mt-2 rounded-full bg-purple-500"></div>
                  <div>
                    <p class="text-sm text-gray-900 dark:text-white">Invoice Viewed</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDateTime(invoice.viewed_at) }}</p>
                  </div>
                </div>
                <div v-if="invoice.paid_at" class="flex items-start gap-3">
                  <div class="w-2 h-2 mt-2 rounded-full bg-green-500"></div>
                  <div>
                    <p class="text-sm text-gray-900 dark:text-white">Payment Received</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDateTime(invoice.paid_at) }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Quick Actions -->
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-6">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h2>

              <div class="space-y-2">
                <button
                  v-if="['sent', 'viewed'].includes(invoice.status)"
                  @click="resendInvoice"
                  class="w-full flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                  </svg>
                  Resend Invoice
                </button>
                <button
                  v-if="invoiceStore.canTransitionTo(invoice.status, 'cancelled')"
                  @click="cancelInvoice"
                  class="w-full flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-red-700 bg-red-100 rounded-lg hover:bg-red-200 dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/30"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                  Cancel Invoice
                </button>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useInvoiceStore } from '@/stores/invoices'
import { usePaymentStore } from '@/stores/payments'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import { PaymentButton, PaymentHistory } from '@/components/payments'

const route = useRoute()
const router = useRouter()
const invoiceStore = useInvoiceStore()
const paymentStore = usePaymentStore()

const loading = ref(true)
const error = ref(null)
const paymentError = ref(null)

// Computed
const invoice = computed(() => invoiceStore.currentInvoice)

const isOverdue = computed(() => {
  if (!invoice.value) return false
  if (invoice.value.status === 'paid' || invoice.value.status === 'cancelled') return false
  if (!invoice.value.due_date) return false
  return new Date(invoice.value.due_date) < new Date()
})

const canPay = computed(() => {
  if (!invoice.value) return false
  return ['sent', 'viewed', 'overdue'].includes(invoice.value.status) && paymentStore.isStripeConfigured
})

// Methods
async function loadInvoice() {
  loading.value = true
  error.value = null
  try {
    await invoiceStore.fetchInvoice(route.params.id)
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to load invoice'
  } finally {
    loading.value = false
  }
}

async function sendInvoice() {
  if (confirm('Send this invoice to the client?')) {
    try {
      await invoiceStore.sendInvoice(invoice.value.id)
    } catch (err) {
      alert('Failed to send invoice. Please try again.')
    }
  }
}

async function resendInvoice() {
  if (confirm('Resend this invoice to the client?')) {
    try {
      await invoiceStore.resendInvoice(invoice.value.id)
      alert('Invoice resent successfully')
    } catch (err) {
      alert('Failed to resend invoice. Please try again.')
    }
  }
}

async function markAsPaid() {
  if (confirm('Mark this invoice as paid?')) {
    try {
      await invoiceStore.markPaid(invoice.value.id)
    } catch (err) {
      alert('Failed to mark as paid. Please try again.')
    }
  }
}

async function cancelInvoice() {
  if (confirm('Are you sure you want to cancel this invoice? This action cannot be undone.')) {
    try {
      await invoiceStore.updateStatus(invoice.value.id, 'cancelled')
    } catch (err) {
      alert('Failed to cancel invoice. Please try again.')
    }
  }
}

async function previewPdf() {
  try {
    await invoiceStore.previewPdf(invoice.value.id)
  } catch (err) {
    alert('Failed to preview PDF. Please try again.')
  }
}

async function downloadPdf() {
  try {
    await invoiceStore.downloadPdf(invoice.value.id)
  } catch (err) {
    alert('Failed to download PDF. Please try again.')
  }
}

function formatCurrency(value) {
  return Number(value || 0).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

function formatDate(dateString) {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
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

function getStatusClasses(status) {
  const statusClasses = {
    draft: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
    sent: 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
    viewed: 'bg-purple-100 text-purple-700 dark:bg-purple-900/20 dark:text-purple-400',
    paid: 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400',
    overdue: 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400',
    cancelled: 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-500'
  }
  return statusClasses[status] || statusClasses.draft
}

function handlePaymentError(errorMessage) {
  paymentError.value = errorMessage
  setTimeout(() => {
    paymentError.value = null
  }, 5000)
}

function handlePaymentRefunded() {
  // Reload invoice to get updated status
  loadInvoice()
}

// Lifecycle
onMounted(async () => {
  // Load Stripe config and invoice in parallel
  await Promise.all([
    paymentStore.fetchStripeConfig(),
    loadInvoice()
  ])
})
</script>
