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
          <h1 class="text-lg font-semibold text-gray-900 dark:text-white">Proposal Details</h1>
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

    <!-- Proposal Content -->
    <main v-else-if="proposal" class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Proposal Header -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
        <div class="p-6">
          <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div>
              <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ proposal.title }}</h2>
              <p class="text-gray-500 dark:text-gray-400 mt-1">Proposal #{{ proposal.proposal_number }}</p>
            </div>
            <div class="text-left md:text-right">
              <p class="text-sm text-gray-500 dark:text-gray-400">Total Value</p>
              <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(proposal.total) }}</p>
              <span :class="[
                'mt-2 inline-block px-3 py-1 rounded-full text-sm font-medium',
                statusClass
              ]">
                {{ statusLabel }}
              </span>
            </div>
          </div>
        </div>

        <!-- Valid Until Warning -->
        <div
          v-if="proposal.status === 'sent' && isExpiringSoon"
          class="px-6 py-3 bg-yellow-50 dark:bg-yellow-900/20 border-t border-yellow-200 dark:border-yellow-800"
        >
          <p class="text-sm text-yellow-600 dark:text-yellow-400 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            This proposal expires on {{ formatDate(proposal.valid_until) }}
          </p>
        </div>
      </div>

      <!-- Content Sections -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
          <div
            v-for="(section, index) in proposal.sections"
            :key="index"
            class="p-6"
          >
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">{{ section.title }}</h3>
            <div class="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300 whitespace-pre-wrap">{{ section.content }}</div>
          </div>
        </div>
      </div>

      <!-- Line Items -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
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

      <!-- Terms -->
      <div v-if="proposal.terms" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Terms & Conditions</h3>
        <p class="text-gray-600 dark:text-gray-300 whitespace-pre-wrap text-sm">{{ proposal.terms }}</p>
      </div>

      <!-- Action Section -->
      <div v-if="proposal.status === 'sent' || proposal.status === 'viewed'" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Your Response</h3>

        <!-- Signature Section -->
        <div v-if="proposal.require_signature && !showSignature" class="space-y-4">
          <p class="text-gray-600 dark:text-gray-400">
            This proposal requires your signature to accept. Please review the terms carefully before proceeding.
          </p>
          <div class="flex flex-col sm:flex-row gap-3">
            <button
              @click="showSignature = true"
              class="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium"
            >
              Accept & Sign
            </button>
            <button
              @click="rejectProposal"
              :disabled="processing"
              class="flex-1 px-4 py-3 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 font-medium"
            >
              Decline
            </button>
          </div>
        </div>

        <!-- Signature Input -->
        <div v-else-if="proposal.require_signature && showSignature" class="space-y-4">
          <p class="text-sm text-gray-600 dark:text-gray-400">
            By signing below, you agree to the terms and conditions outlined in this proposal.
          </p>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Your Full Name</label>
            <input
              v-model="signatureName"
              type="text"
              placeholder="Type your full name"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-gray-900 dark:text-white dark:bg-gray-800 focus:ring-2 focus:ring-brand-500"
            />
          </div>
          <div class="flex flex-col sm:flex-row gap-3">
            <button
              @click="acceptProposal"
              :disabled="processing || !signatureName"
              class="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 font-medium flex items-center justify-center gap-2"
            >
              <svg v-if="processing" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Submit Signature
            </button>
            <button
              @click="showSignature = false"
              class="flex-1 px-4 py-3 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700"
            >
              Cancel
            </button>
          </div>
        </div>

        <!-- No Signature Required -->
        <div v-else class="flex flex-col sm:flex-row gap-3">
          <button
            @click="acceptProposal"
            :disabled="processing"
            class="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 font-medium flex items-center justify-center gap-2"
          >
            <svg v-if="processing" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Accept Proposal
          </button>
          <button
            @click="rejectProposal"
            :disabled="processing"
            class="flex-1 px-4 py-3 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 font-medium"
          >
            Decline
          </button>
        </div>
      </div>

      <!-- Accepted Status -->
      <div v-else-if="proposal.status === 'accepted'" class="bg-green-50 dark:bg-green-900/20 rounded-xl border border-green-200 dark:border-green-800 p-6">
        <div class="flex items-center gap-4">
          <div class="flex-shrink-0">
            <svg class="w-12 h-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-green-800 dark:text-green-200">Proposal Accepted</h3>
            <p class="text-green-600 dark:text-green-400">
              You accepted this proposal on {{ formatDate(proposal.accepted_at) }}
              <span v-if="proposal.signature_name"> and signed as "{{ proposal.signature_name }}"</span>
            </p>
          </div>
        </div>
      </div>

      <!-- Rejected Status -->
      <div v-else-if="proposal.status === 'rejected'" class="bg-red-50 dark:bg-red-900/20 rounded-xl border border-red-200 dark:border-red-800 p-6">
        <div class="flex items-center gap-4">
          <div class="flex-shrink-0">
            <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-red-800 dark:text-red-200">Proposal Declined</h3>
            <p class="text-red-600 dark:text-red-400">
              This proposal was declined on {{ formatDate(proposal.rejected_at) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Expired Status -->
      <div v-else-if="proposal.status === 'expired'" class="bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center gap-4">
          <div class="flex-shrink-0">
            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Proposal Expired</h3>
            <p class="text-gray-600 dark:text-gray-400">
              This proposal expired on {{ formatDate(proposal.valid_until) }}. Please contact us for a new proposal.
            </p>
          </div>
        </div>
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
      <p class="text-gray-500 dark:text-gray-400">Proposal not found.</p>
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
const proposal = ref(null)
const showSignature = ref(false)
const signatureName = ref('')

const isExpiringSoon = computed(() => {
  if (!proposal.value?.valid_until) return false
  const validUntil = new Date(proposal.value.valid_until)
  const daysLeft = (validUntil - new Date()) / (1000 * 60 * 60 * 24)
  return daysLeft <= 7 && daysLeft > 0
})

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

async function acceptProposal() {
  processing.value = true
  try {
    await portalStore.acceptProposal(proposal.value.id, signatureName.value || null)
    proposal.value = await portalStore.fetchProposal(route.params.id)
    showSignature.value = false
  } catch (err) {
    console.error('Failed to accept proposal:', err)
  } finally {
    processing.value = false
  }
}

async function rejectProposal() {
  if (!confirm('Are you sure you want to decline this proposal?')) return

  processing.value = true
  try {
    await portalStore.rejectProposal(proposal.value.id)
    proposal.value = await portalStore.fetchProposal(route.params.id)
  } catch (err) {
    console.error('Failed to reject proposal:', err)
  } finally {
    processing.value = false
  }
}

async function downloadPdf() {
  downloading.value = true
  try {
    // Would need to implement this in the portal store
    console.log('Download PDF for proposal:', proposal.value.id)
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
    proposal.value = await portalStore.fetchProposal(route.params.id)
  } catch (err) {
    console.error('Failed to load proposal:', err)
  } finally {
    loading.value = false
  }
})
</script>
