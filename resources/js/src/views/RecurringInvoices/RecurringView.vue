<template>
  <div>
    <PageBreadcrumb pageTitle="Recurring Schedule Details" />

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-brand-500"></div>
    </div>

    <!-- Schedule Content -->
    <div v-else-if="schedule" class="space-y-6">
      <!-- Header with Actions -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ schedule.title }}</h2>
          <p class="text-gray-500 dark:text-gray-400 mt-1">
            {{ schedule.client?.company_name || schedule.client?.name }} • {{ frequencyLabel }}
          </p>
        </div>
        <div class="flex items-center gap-3">
          <span :class="[
            'px-3 py-1 rounded-full text-sm font-medium',
            statusClass
          ]">
            {{ statusLabel }}
          </span>
          <div class="flex items-center gap-2">
            <button
              v-if="schedule.status === 'active'"
              @click="generateNow"
              :disabled="generating"
              class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 disabled:opacity-50 flex items-center gap-2"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              Generate Now
            </button>
            <button
              v-if="schedule.status === 'active'"
              @click="pauseSchedule"
              :disabled="updating"
              class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 disabled:opacity-50"
            >
              Pause
            </button>
            <button
              v-if="schedule.status === 'paused'"
              @click="resumeSchedule"
              :disabled="updating"
              class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
            >
              Resume
            </button>
            <router-link
              :to="`/recurring-invoices/${schedule.id}/edit`"
              class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700"
            >
              Edit
            </router-link>
          </div>
        </div>
      </div>

      <!-- Info Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
          <p class="text-sm text-gray-500 dark:text-gray-400">Amount per Invoice</p>
          <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ formatCurrency(schedule.amount) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
          <p class="text-sm text-gray-500 dark:text-gray-400">Invoices Generated</p>
          <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
            {{ schedule.invoices_generated }}
            <span v-if="schedule.max_invoices" class="text-sm font-normal text-gray-500">/ {{ schedule.max_invoices }}</span>
          </p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
          <p class="text-sm text-gray-500 dark:text-gray-400">Next Invoice</p>
          <p class="text-lg font-semibold text-gray-900 dark:text-white mt-1">
            {{ schedule.next_invoice_date ? formatDate(schedule.next_invoice_date) : 'N/A' }}
          </p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
          <p class="text-sm text-gray-500 dark:text-gray-400">Total Billed</p>
          <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
            {{ formatCurrency(schedule.amount * schedule.invoices_generated) }}
          </p>
        </div>
      </div>

      <!-- Schedule Details -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Schedule Details</h3>
          <dl class="space-y-3">
            <div class="flex justify-between">
              <dt class="text-gray-500 dark:text-gray-400">Frequency</dt>
              <dd class="text-gray-900 dark:text-white font-medium">{{ frequencyLabel }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500 dark:text-gray-400">Day of Month</dt>
              <dd class="text-gray-900 dark:text-white font-medium">{{ ordinalDay }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500 dark:text-gray-400">Start Date</dt>
              <dd class="text-gray-900 dark:text-white font-medium">{{ formatDate(schedule.start_date) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500 dark:text-gray-400">End Date</dt>
              <dd class="text-gray-900 dark:text-white font-medium">{{ schedule.end_date ? formatDate(schedule.end_date) : 'No end date' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500 dark:text-gray-400">Payment Terms</dt>
              <dd class="text-gray-900 dark:text-white font-medium">{{ paymentTermsLabel }}</dd>
            </div>
          </dl>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Settings</h3>
          <div class="space-y-3">
            <div class="flex items-center gap-3">
              <span :class="schedule.auto_send ? 'text-green-500' : 'text-gray-400'">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path v-if="schedule.auto_send" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </span>
              <span class="text-gray-700 dark:text-gray-300">Auto-send to client</span>
            </div>
            <div class="flex items-center gap-3">
              <span :class="schedule.auto_payment ? 'text-green-500' : 'text-gray-400'">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path v-if="schedule.auto_payment" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </span>
              <span class="text-gray-700 dark:text-gray-300">Auto-charge payment method</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Line Items -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Invoice Template</h3>
        </div>
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
              <tr v-for="(item, index) in schedule.items" :key="index">
                <td class="px-6 py-4 text-gray-900 dark:text-white">{{ item.description }}</td>
                <td class="px-6 py-4 text-right text-gray-900 dark:text-white">{{ item.quantity }}</td>
                <td class="px-6 py-4 text-right text-gray-900 dark:text-white">{{ formatCurrency(item.rate) }}</td>
                <td class="px-6 py-4 text-right font-medium text-gray-900 dark:text-white">{{ formatCurrency(item.quantity * item.rate) }}</td>
              </tr>
            </tbody>
            <tfoot class="bg-gray-50 dark:bg-gray-900/50">
              <tr v-if="schedule.tax_rate">
                <td colspan="3" class="px-6 py-3 text-right text-gray-600 dark:text-gray-400">Tax ({{ schedule.tax_rate }}%):</td>
                <td class="px-6 py-3 text-right text-gray-900 dark:text-white">{{ formatCurrency(subtotal * schedule.tax_rate / 100) }}</td>
              </tr>
              <tr>
                <td colspan="3" class="px-6 py-4 text-right font-medium text-gray-900 dark:text-white">Total:</td>
                <td class="px-6 py-4 text-right text-xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(schedule.amount) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- Generated Invoices -->
      <div v-if="generatedInvoices.length" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Generated Invoices</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900/50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Invoice #</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="invoice in generatedInvoices" :key="invoice.id">
                <td class="px-6 py-4">
                  <router-link :to="`/invoices/${invoice.id}`" class="text-brand-600 hover:text-brand-700 font-medium">
                    {{ invoice.invoice_number }}
                  </router-link>
                </td>
                <td class="px-6 py-4 text-gray-900 dark:text-white">{{ formatDate(invoice.issue_date) }}</td>
                <td class="px-6 py-4 text-right text-gray-900 dark:text-white">{{ formatCurrency(invoice.total) }}</td>
                <td class="px-6 py-4 text-center">
                  <span :class="[
                    'px-2 py-1 rounded-full text-xs font-medium',
                    invoice.status === 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' :
                    invoice.status === 'overdue' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' :
                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300'
                  ]">
                    {{ invoice.status }}
                  </span>
                </td>
                <td class="px-6 py-4 text-right">
                  <router-link :to="`/invoices/${invoice.id}`" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    View
                  </router-link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Notes -->
      <div v-if="schedule.notes" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Notes</h3>
        <p class="text-gray-600 dark:text-gray-300 whitespace-pre-wrap">{{ schedule.notes }}</p>
      </div>
    </div>

    <!-- Not Found -->
    <div v-else class="text-center py-12">
      <p class="text-gray-500 dark:text-gray-400">Schedule not found.</p>
      <router-link to="/recurring-invoices" class="text-brand-600 hover:text-brand-700 mt-2 inline-block">
        Back to Recurring Invoices
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useRecurringInvoiceStore } from '@/stores/recurringInvoices'
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue'

const route = useRoute()
const recurringStore = useRecurringInvoiceStore()

const loading = ref(true)
const generating = ref(false)
const updating = ref(false)
const schedule = ref(null)
const generatedInvoices = ref([])

const statusClass = computed(() => {
  const classes = {
    active: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    paused: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
    cancelled: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    expired: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
  }
  return classes[schedule.value?.status] || classes.active
})

const statusLabel = computed(() => {
  return schedule.value?.status?.charAt(0).toUpperCase() + schedule.value?.status?.slice(1) || 'Active'
})

const frequencyLabel = computed(() => {
  const labels = {
    'weekly': 'Weekly',
    'bi-weekly': 'Every 2 weeks',
    'monthly': 'Monthly',
    'quarterly': 'Quarterly',
    'semi-annually': 'Every 6 months',
    'annually': 'Annually'
  }
  return labels[schedule.value?.frequency] || schedule.value?.frequency
})

const ordinalDay = computed(() => {
  const day = schedule.value?.day_of_month
  if (!day) return '-'
  const suffix = ['th', 'st', 'nd', 'rd']
  const v = day % 100
  return day + (suffix[(v - 20) % 10] || suffix[v] || suffix[0])
})

const paymentTermsLabel = computed(() => {
  const terms = schedule.value?.payment_terms
  if (!terms) return '-'
  return terms.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
})

const subtotal = computed(() => {
  return schedule.value?.items?.reduce((sum, item) => {
    return sum + (item.quantity * item.rate)
  }, 0) || 0
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

async function generateNow() {
  generating.value = true
  try {
    await recurringStore.generateNow(schedule.value.id)
    // Reload to get updated data
    schedule.value = await recurringStore.fetchSchedule(route.params.id)
    generatedInvoices.value = await recurringStore.fetchGeneratedInvoices(route.params.id)
  } catch (err) {
    console.error('Failed to generate invoice:', err)
  } finally {
    generating.value = false
  }
}

async function pauseSchedule() {
  updating.value = true
  try {
    await recurringStore.pauseSchedule(schedule.value.id)
    schedule.value.status = 'paused'
  } catch (err) {
    console.error('Failed to pause schedule:', err)
  } finally {
    updating.value = false
  }
}

async function resumeSchedule() {
  updating.value = true
  try {
    await recurringStore.resumeSchedule(schedule.value.id)
    schedule.value.status = 'active'
  } catch (err) {
    console.error('Failed to resume schedule:', err)
  } finally {
    updating.value = false
  }
}

onMounted(async () => {
  try {
    schedule.value = await recurringStore.fetchSchedule(route.params.id)
    generatedInvoices.value = await recurringStore.fetchGeneratedInvoices(route.params.id)
  } catch (err) {
    console.error('Failed to load schedule:', err)
  } finally {
    loading.value = false
  }
})
</script>
