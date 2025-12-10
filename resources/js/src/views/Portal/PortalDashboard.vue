<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <!-- Header -->
    <header class="bg-white dark:bg-gray-800 shadow">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Client Portal</h1>
        <div class="flex items-center gap-4">
          <span class="text-sm text-gray-600 dark:text-gray-400">
            Welcome, {{ portalStore.clientName }}
          </span>
          <button
            @click="logout"
            class="text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
          >
            Sign Out
          </button>
        </div>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <div class="flex items-center">
            <div class="flex-shrink-0 bg-red-100 dark:bg-red-900/20 rounded-md p-3">
              <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Amount Due</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">${{ formatCurrency(portalStore.totalDue) }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <div class="flex items-center">
            <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900/20 rounded-md p-3">
              <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Unpaid Invoices</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ portalStore.unpaidInvoices.length }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <div class="flex items-center">
            <div class="flex-shrink-0 bg-yellow-100 dark:bg-yellow-900/20 rounded-md p-3">
              <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Proposals</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ portalStore.pendingProposals.length }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="-mb-px flex space-x-8">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            :class="[
              activeTab === tab.id
                ? 'border-brand-500 text-brand-600 dark:text-brand-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200',
              'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
            ]"
          >
            {{ tab.name }}
          </button>
        </nav>
      </div>

      <!-- Invoices Tab -->
      <div v-if="activeTab === 'invoices'" class="space-y-4">
        <div v-if="portalStore.loading" class="text-center py-8">
          <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
        </div>
        <div v-else-if="portalStore.invoices.length === 0" class="text-center py-8">
          <p class="text-gray-500 dark:text-gray-400">No invoices found</p>
        </div>
        <div v-else class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Invoice</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Due Date</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="invoice in portalStore.invoices" :key="invoice.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                  {{ invoice.invoice_number }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="['px-2 py-1 text-xs font-medium rounded-full capitalize', getStatusClasses(invoice.status)]">
                    {{ invoice.status }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                  ${{ formatCurrency(invoice.total) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ formatDate(invoice.due_date) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                  <button
                    @click="downloadInvoice(invoice)"
                    class="text-brand-600 hover:text-brand-900 dark:text-brand-400 dark:hover:text-brand-300"
                  >
                    Download
                  </button>
                  <button
                    v-if="['sent', 'viewed', 'overdue'].includes(invoice.status)"
                    @click="payInvoice(invoice)"
                    class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                  >
                    Pay Now
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Proposals Tab -->
      <div v-if="activeTab === 'proposals'" class="space-y-4">
        <div v-if="portalStore.loading" class="text-center py-8">
          <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
        </div>
        <div v-else-if="portalStore.proposals.length === 0" class="text-center py-8">
          <p class="text-gray-500 dark:text-gray-400">No proposals found</p>
        </div>
        <div v-else class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Proposal</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Value</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Valid Until</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="proposal in portalStore.proposals" :key="proposal.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                  {{ proposal.title }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="['px-2 py-1 text-xs font-medium rounded-full capitalize', getProposalStatusClasses(proposal.status)]">
                    {{ proposal.status }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                  ${{ formatCurrency(proposal.total) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ formatDate(proposal.valid_until) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                  <router-link
                    :to="`/portal/proposals/${proposal.id}`"
                    class="text-brand-600 hover:text-brand-900 dark:text-brand-400 dark:hover:text-brand-300"
                  >
                    View
                  </router-link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Payments Tab -->
      <div v-if="activeTab === 'payments'" class="space-y-4">
        <div v-if="portalStore.loading" class="text-center py-8">
          <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
        </div>
        <div v-else-if="portalStore.payments.length === 0" class="text-center py-8">
          <p class="text-gray-500 dark:text-gray-400">No payment history found</p>
        </div>
        <div v-else class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Invoice</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Method</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="payment in portalStore.payments" :key="payment.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ formatDate(payment.created_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                  {{ payment.invoice_number }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 capitalize">
                  {{ payment.payment_method?.replace('_', ' ') || 'N/A' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                  ${{ formatCurrency(payment.amount) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="['px-2 py-1 text-xs font-medium rounded-full capitalize', getPaymentStatusClasses(payment.status)]">
                    {{ payment.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePortalStore } from '@/stores/portal'

const router = useRouter()
const portalStore = usePortalStore()

const activeTab = ref('invoices')
const tabs = [
  { id: 'invoices', name: 'Invoices' },
  { id: 'proposals', name: 'Proposals' },
  { id: 'payments', name: 'Payment History' }
]

async function loadData() {
  try {
    await Promise.all([
      portalStore.fetchInvoices(),
      portalStore.fetchProposals(),
      portalStore.fetchPayments()
    ])
  } catch (error) {
    console.error('Failed to load portal data:', error)
  }
}

function logout() {
  portalStore.logout()
  router.push('/portal')
}

async function downloadInvoice(invoice) {
  try {
    await portalStore.downloadInvoicePdf(invoice.id)
  } catch (error) {
    console.error('Failed to download invoice:', error)
    alert('Failed to download invoice. Please try again.')
  }
}

function payInvoice(invoice) {
  router.push(`/portal/invoices/${invoice.id}/pay`)
}

function formatCurrency(value) {
  return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDate(dateString) {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

function getStatusClasses(status) {
  const classes = {
    draft: 'bg-gray-100 text-gray-700',
    sent: 'bg-blue-100 text-blue-700',
    viewed: 'bg-purple-100 text-purple-700',
    paid: 'bg-green-100 text-green-700',
    overdue: 'bg-red-100 text-red-700',
    cancelled: 'bg-gray-100 text-gray-500'
  }
  return classes[status] || classes.draft
}

function getProposalStatusClasses(status) {
  const classes = {
    sent: 'bg-blue-100 text-blue-700',
    viewed: 'bg-purple-100 text-purple-700',
    accepted: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    expired: 'bg-orange-100 text-orange-700'
  }
  return classes[status] || 'bg-gray-100 text-gray-700'
}

function getPaymentStatusClasses(status) {
  const classes = {
    succeeded: 'bg-green-100 text-green-700',
    pending: 'bg-yellow-100 text-yellow-700',
    processing: 'bg-blue-100 text-blue-700',
    failed: 'bg-red-100 text-red-700',
    refunded: 'bg-gray-100 text-gray-500'
  }
  return classes[status] || classes.pending
}

onMounted(async () => {
  if (!portalStore.isAuthenticated) {
    const restored = await portalStore.restoreSession()
    if (!restored) {
      router.push('/portal')
      return
    }
  }
  loadData()
})
</script>
