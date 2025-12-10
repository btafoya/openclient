<template>
  <div>
    <PageBreadcrumb pageTitle="Proposal Details" />

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-brand-500"></div>
    </div>

    <!-- Proposal Content -->
    <div v-else-if="proposal" class="space-y-6">
      <!-- Header with Actions -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ proposal.title }}</h2>
          <p class="text-gray-500 dark:text-gray-400 mt-1">
            {{ proposal.client?.company_name || proposal.client?.name }} • Proposal #{{ proposal.proposal_number }}
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
              v-if="proposal.status === 'draft'"
              @click="sendProposal"
              :disabled="sending"
              class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 disabled:opacity-50 flex items-center gap-2"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              Send to Client
            </button>
            <button
              v-if="proposal.status === 'accepted'"
              @click="convertToInvoice"
              :disabled="converting"
              class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 flex items-center gap-2"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              Convert to Invoice
            </button>
            <router-link
              :to="`/proposals/${proposal.id}/edit`"
              class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700"
            >
              Edit
            </router-link>
            <button
              @click="downloadPdf"
              :disabled="downloading"
              class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              PDF
            </button>
          </div>
        </div>
      </div>

      <!-- Info Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
          <p class="text-sm text-gray-500 dark:text-gray-400">Total Value</p>
          <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ formatCurrency(proposal.total) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
          <p class="text-sm text-gray-500 dark:text-gray-400">Created</p>
          <p class="text-lg font-semibold text-gray-900 dark:text-white mt-1">{{ formatDate(proposal.created_at) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
          <p class="text-sm text-gray-500 dark:text-gray-400">Valid Until</p>
          <p class="text-lg font-semibold text-gray-900 dark:text-white mt-1">{{ formatDate(proposal.valid_until) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
          <p class="text-sm text-gray-500 dark:text-gray-400">E-Signature</p>
          <p class="text-lg font-semibold text-gray-900 dark:text-white mt-1">
            {{ proposal.require_signature ? 'Required' : 'Not Required' }}
          </p>
        </div>
      </div>

      <!-- Signature Info (if signed) -->
      <div v-if="proposal.signed_at" class="bg-green-50 dark:bg-green-900/20 rounded-xl border border-green-200 dark:border-green-800 p-4">
        <div class="flex items-center gap-3">
          <div class="flex-shrink-0">
            <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="flex-1">
            <p class="text-sm font-medium text-green-800 dark:text-green-200">
              Signed by {{ proposal.signature_name }} on {{ formatDate(proposal.signed_at) }}
            </p>
            <p class="text-xs text-green-600 dark:text-green-400 mt-1">
              IP: {{ proposal.signature_ip }}
            </p>
          </div>
        </div>
      </div>

      <!-- Content Sections -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Proposal Content</h3>
        </div>
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
          <div
            v-for="(section, index) in proposal.sections"
            :key="index"
            class="p-6"
          >
            <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">{{ section.title }}</h4>
            <div class="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300 whitespace-pre-wrap">{{ section.content }}</div>
          </div>
        </div>
      </div>

      <!-- Line Items -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Pricing</h3>
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
              <tr v-for="(item, index) in proposal.items" :key="index">
                <td class="px-6 py-4 text-gray-900 dark:text-white">{{ item.description }}</td>
                <td class="px-6 py-4 text-right text-gray-900 dark:text-white">{{ item.quantity }}</td>
                <td class="px-6 py-4 text-right text-gray-900 dark:text-white">{{ formatCurrency(item.rate) }}</td>
                <td class="px-6 py-4 text-right font-medium text-gray-900 dark:text-white">{{ formatCurrency(item.quantity * item.rate) }}</td>
              </tr>
            </tbody>
            <tfoot class="bg-gray-50 dark:bg-gray-900/50">
              <tr>
                <td colspan="3" class="px-6 py-4 text-right font-medium text-gray-900 dark:text-white">Total:</td>
                <td class="px-6 py-4 text-right text-xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(proposal.total) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- Terms & Notes -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div v-if="proposal.terms" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Terms & Conditions</h3>
          <p class="text-gray-600 dark:text-gray-300 whitespace-pre-wrap">{{ proposal.terms }}</p>
        </div>
        <div v-if="proposal.notes" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Notes</h3>
          <p class="text-gray-600 dark:text-gray-300 whitespace-pre-wrap">{{ proposal.notes }}</p>
        </div>
      </div>

      <!-- Activity Log -->
      <div v-if="proposal.activity?.length" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Activity</h3>
        <div class="space-y-4">
          <div v-for="(activity, index) in proposal.activity" :key="index" class="flex items-start gap-3">
            <div class="flex-shrink-0 w-2 h-2 mt-2 rounded-full bg-gray-300 dark:bg-gray-600"></div>
            <div>
              <p class="text-sm text-gray-900 dark:text-white">{{ activity.description }}</p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ formatDateTime(activity.created_at) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Not Found -->
    <div v-else class="text-center py-12">
      <p class="text-gray-500 dark:text-gray-400">Proposal not found.</p>
      <router-link to="/proposals" class="text-brand-600 hover:text-brand-700 mt-2 inline-block">
        Back to Proposals
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useProposalStore } from '@/stores/proposals'
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue'

const route = useRoute()
const router = useRouter()
const proposalStore = useProposalStore()

const loading = ref(true)
const sending = ref(false)
const converting = ref(false)
const downloading = ref(false)
const proposal = ref(null)

const statusClass = computed(() => {
  const classes = {
    draft: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    sent: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    viewed: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
    accepted: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
    expired: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
  }
  return classes[proposal.value?.status] || classes.draft
})

const statusLabel = computed(() => {
  return proposal.value?.status?.charAt(0).toUpperCase() + proposal.value?.status?.slice(1) || 'Draft'
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

function formatDateTime(date) {
  if (!date) return '-'
  return new Date(date).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

async function sendProposal() {
  sending.value = true
  try {
    await proposalStore.sendProposal(proposal.value.id)
    proposal.value.status = 'sent'
    proposal.value.sent_at = new Date().toISOString()
  } catch (err) {
    console.error('Failed to send proposal:', err)
  } finally {
    sending.value = false
  }
}

async function convertToInvoice() {
  converting.value = true
  try {
    const invoice = await proposalStore.convertToInvoice(proposal.value.id)
    router.push(`/invoices/${invoice.id}`)
  } catch (err) {
    console.error('Failed to convert to invoice:', err)
  } finally {
    converting.value = false
  }
}

async function downloadPdf() {
  downloading.value = true
  try {
    await proposalStore.downloadPdf(proposal.value.id)
  } catch (err) {
    console.error('Failed to download PDF:', err)
  } finally {
    downloading.value = false
  }
}

onMounted(async () => {
  try {
    proposal.value = await proposalStore.fetchProposal(route.params.id)
  } catch (err) {
    console.error('Failed to load proposal:', err)
  } finally {
    loading.value = false
  }
})
</script>
