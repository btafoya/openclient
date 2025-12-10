<template>
  <AdminLayout>
    <div class="space-y-6">
      <!-- Page Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Invoices</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Manage client invoices, payments, and billing
          </p>
        </div>
        <router-link
          to="/invoices/create"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          New Invoice
        </router-link>
      </div>

      <!-- Stats Cards -->
      <div v-if="invoiceStore.stats" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Total Outstanding</p>
          <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
            ${{ formatCurrency(invoiceStore.stats.total_outstanding || 0) }}
          </p>
        </div>
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Paid This Month</p>
          <p class="mt-1 text-2xl font-bold text-green-600">
            ${{ formatCurrency(invoiceStore.stats.paid_this_month || 0) }}
          </p>
        </div>
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Overdue</p>
          <p class="mt-1 text-2xl font-bold text-red-600">
            ${{ formatCurrency(invoiceStore.stats.total_overdue || 0) }}
          </p>
        </div>
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Draft Invoices</p>
          <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
            {{ invoiceStore.stats.draft_count || 0 }}
          </p>
        </div>
      </div>

      <!-- Search and Filters -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
        <div class="flex-1">
          <div class="relative">
            <input
              v-model="searchTerm"
              type="text"
              placeholder="Search invoices by number, client, or project..."
              class="w-full px-4 py-2 pl-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
              @input="handleSearch"
            />
            <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>
        <div class="flex gap-3">
          <select
            v-model="statusFilter"
            @change="applyFilter"
            class="px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
          >
            <option value="all">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="sent">Sent</option>
            <option value="viewed">Viewed</option>
            <option value="paid">Paid</option>
            <option value="overdue">Overdue</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
      </div>

      <!-- Invoices Table -->
      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <!-- Loading State -->
        <div v-if="invoiceStore.loading" class="p-8 text-center">
          <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading invoices...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="invoiceStore.error" class="p-8 text-center">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/20">
            <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <p class="mt-4 text-sm text-gray-900 dark:text-white font-medium">{{ invoiceStore.error }}</p>
          <button
            @click="loadInvoices"
            class="mt-4 px-4 py-2 text-sm font-medium text-brand-600 hover:text-brand-700"
          >
            Try Again
          </button>
        </div>

        <!-- Empty State -->
        <div v-else-if="displayedInvoices.length === 0" class="p-8 text-center">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800">
            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <p class="mt-4 text-sm text-gray-900 dark:text-white font-medium">No invoices found</p>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ searchTerm ? 'Try adjusting your search' : 'Get started by creating your first invoice' }}
          </p>
          <router-link
            v-if="!searchTerm"
            to="/invoices/create"
            class="inline-flex items-center gap-2 mt-4 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Create Your First Invoice
          </router-link>
        </div>

        <!-- Table Content -->
        <div v-else class="max-w-full overflow-x-auto custom-scrollbar">
          <table class="min-w-full">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Invoice</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Client</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Status</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Amount</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Due Date</p>
                </th>
                <th class="px-5 py-3 text-right sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Actions</p>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr
                v-for="invoice in displayedInvoices"
                :key="invoice.id"
                class="border-t border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50"
              >
                <td class="px-5 py-4 sm:px-6">
                  <div>
                    <router-link
                      :to="`/invoices/${invoice.id}`"
                      class="block font-medium text-gray-800 text-theme-sm dark:text-white/90 hover:text-brand-600 dark:hover:text-brand-400"
                    >
                      {{ invoice.invoice_number }}
                    </router-link>
                    <span v-if="invoice.project_name" class="block text-gray-500 text-theme-xs dark:text-gray-400 mt-0.5">
                      {{ invoice.project_name }}
                    </span>
                  </div>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <div class="text-theme-sm">
                    <p v-if="invoice.client_name" class="text-gray-700 dark:text-gray-300">
                      {{ invoice.client_name }}
                    </p>
                    <p v-else class="text-gray-400 dark:text-gray-500 italic">
                      No client
                    </p>
                  </div>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <span
                    :class="[
                      'inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full capitalize',
                      getStatusClasses(invoice.status)
                    ]"
                  >
                    <span class="w-1.5 h-1.5 rounded-full" :class="getStatusDotClasses(invoice.status)"></span>
                    {{ invoice.status }}
                  </span>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <div class="text-theme-sm font-medium text-gray-900 dark:text-white">
                    ${{ formatCurrency(invoice.total) }}
                  </div>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <div :class="['text-theme-sm', isOverdue(invoice) ? 'text-red-600 font-medium' : 'text-gray-600 dark:text-gray-400']">
                    {{ formatDate(invoice.due_date) }}
                    <span v-if="isOverdue(invoice)" class="text-xs">(Overdue)</span>
                  </div>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <div class="flex items-center justify-end gap-2">
                    <router-link
                      :to="`/invoices/${invoice.id}`"
                      class="p-1.5 text-gray-600 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400"
                      title="View Invoice"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </router-link>
                    <button
                      v-if="invoice.status === 'draft'"
                      @click="sendInvoice(invoice)"
                      class="p-1.5 text-gray-600 hover:text-green-600 dark:text-gray-400 dark:hover:text-green-400"
                      title="Send Invoice"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                      </svg>
                    </button>
                    <button
                      v-if="['sent', 'viewed', 'overdue'].includes(invoice.status)"
                      @click="markAsPaid(invoice)"
                      class="p-1.5 text-gray-600 hover:text-green-600 dark:text-gray-400 dark:hover:text-green-400"
                      title="Mark as Paid"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </button>
                    <button
                      @click="downloadPdf(invoice)"
                      class="p-1.5 text-gray-600 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400"
                      title="Download PDF"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                    </button>
                    <router-link
                      v-if="invoice.status === 'draft'"
                      :to="`/invoices/${invoice.id}/edit`"
                      class="p-1.5 text-gray-600 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400"
                      title="Edit Invoice"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </router-link>
                    <button
                      v-if="invoice.status === 'draft'"
                      @click="confirmDelete(invoice)"
                      class="p-1.5 text-gray-600 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
                      title="Delete Invoice"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useInvoiceStore } from '@/stores/invoices'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const invoiceStore = useInvoiceStore()

const searchTerm = ref('')
const statusFilter = ref('all')

// Computed
const displayedInvoices = computed(() => {
  let invoices = invoiceStore.filteredInvoices

  if (statusFilter.value !== 'all') {
    invoices = invoices.filter(inv => inv.status === statusFilter.value)
  }

  return invoices
})

// Methods
async function loadInvoices() {
  try {
    await Promise.all([
      invoiceStore.fetchInvoices(),
      invoiceStore.fetchStats()
    ])
  } catch (error) {
    console.error('Failed to load invoices:', error)
  }
}

function handleSearch() {
  invoiceStore.setSearchTerm(searchTerm.value)
}

function applyFilter() {
  loadInvoices()
}

async function sendInvoice(invoice) {
  if (confirm(`Send invoice ${invoice.invoice_number} to ${invoice.client_name || 'client'}?`)) {
    try {
      await invoiceStore.sendInvoice(invoice.id)
      await loadInvoices()
    } catch (error) {
      console.error('Failed to send invoice:', error)
      alert('Failed to send invoice. Please try again.')
    }
  }
}

async function markAsPaid(invoice) {
  if (confirm(`Mark invoice ${invoice.invoice_number} as paid?`)) {
    try {
      await invoiceStore.markPaid(invoice.id)
      await loadInvoices()
    } catch (error) {
      console.error('Failed to mark as paid:', error)
      alert('Failed to mark invoice as paid. Please try again.')
    }
  }
}

async function downloadPdf(invoice) {
  try {
    await invoiceStore.downloadPdf(invoice.id)
  } catch (error) {
    console.error('Failed to download PDF:', error)
    alert('Failed to download PDF. Please try again.')
  }
}

async function confirmDelete(invoice) {
  if (confirm(`Are you sure you want to delete invoice ${invoice.invoice_number}? This action cannot be undone.`)) {
    try {
      await invoiceStore.deleteInvoice(invoice.id)
    } catch (error) {
      console.error('Failed to delete invoice:', error)
      alert('Failed to delete invoice. Please try again.')
    }
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

function isOverdue(invoice) {
  if (invoice.status === 'paid' || invoice.status === 'cancelled') return false
  if (!invoice.due_date) return false
  return new Date(invoice.due_date) < new Date()
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

function getStatusDotClasses(status) {
  const dotClasses = {
    draft: 'bg-gray-600',
    sent: 'bg-blue-600',
    viewed: 'bg-purple-600',
    paid: 'bg-green-600',
    overdue: 'bg-red-600',
    cancelled: 'bg-gray-500'
  }
  return dotClasses[status] || dotClasses.draft
}

// Lifecycle
onMounted(() => {
  loadInvoices()
})
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  height: 8px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 4px;
}

.dark .custom-scrollbar::-webkit-scrollbar-thumb {
  background: #4b5563;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #9ca3af;
}

.dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #6b7280;
}
</style>
