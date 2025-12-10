<template>
  <AdminLayout>
    <div class="space-y-6">
      <!-- Page Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Proposals</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Create and manage client proposals with e-signature support
          </p>
        </div>
        <router-link
          to="/proposals/create"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          New Proposal
        </router-link>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Draft</p>
          <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
            {{ proposalStore.draftProposals.length }}
          </p>
        </div>
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Sent</p>
          <p class="mt-1 text-2xl font-bold text-blue-600">
            {{ proposalStore.sentProposals.length }}
          </p>
        </div>
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Accepted</p>
          <p class="mt-1 text-2xl font-bold text-green-600">
            {{ proposalStore.acceptedProposals.length }}
          </p>
        </div>
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Total Value</p>
          <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
            ${{ formatCurrency(proposalStore.totalValue) }}
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
              placeholder="Search proposals..."
              class="w-full px-4 py-2 pl-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
              @input="handleSearch"
            />
            <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>
        <select
          v-model="statusFilter"
          @change="applyFilter"
          class="px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
        >
          <option value="all">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="sent">Sent</option>
          <option value="viewed">Viewed</option>
          <option value="accepted">Accepted</option>
          <option value="rejected">Rejected</option>
          <option value="expired">Expired</option>
        </select>
      </div>

      <!-- Proposals Table -->
      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <!-- Loading State -->
        <div v-if="proposalStore.loading" class="p-8 text-center">
          <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading proposals...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="proposalStore.error" class="p-8 text-center">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/20">
            <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <p class="mt-4 text-sm text-gray-900 dark:text-white font-medium">{{ proposalStore.error }}</p>
          <button @click="loadProposals" class="mt-4 px-4 py-2 text-sm font-medium text-brand-600 hover:text-brand-700">
            Try Again
          </button>
        </div>

        <!-- Empty State -->
        <div v-else-if="displayedProposals.length === 0" class="p-8 text-center">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800">
            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <p class="mt-4 text-sm text-gray-900 dark:text-white font-medium">No proposals found</p>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ searchTerm ? 'Try adjusting your search' : 'Get started by creating your first proposal' }}
          </p>
          <router-link
            v-if="!searchTerm"
            to="/proposals/create"
            class="inline-flex items-center gap-2 mt-4 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
          >
            Create Your First Proposal
          </router-link>
        </div>

        <!-- Table Content -->
        <div v-else class="max-w-full overflow-x-auto custom-scrollbar">
          <table class="min-w-full">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Proposal</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Client</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Status</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Value</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Valid Until</p>
                </th>
                <th class="px-5 py-3 text-right sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Actions</p>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr
                v-for="proposal in displayedProposals"
                :key="proposal.id"
                class="border-t border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50"
              >
                <td class="px-5 py-4 sm:px-6">
                  <router-link
                    :to="`/proposals/${proposal.id}`"
                    class="block font-medium text-gray-800 text-theme-sm dark:text-white/90 hover:text-brand-600 dark:hover:text-brand-400"
                  >
                    {{ proposal.title }}
                  </router-link>
                  <span v-if="proposal.project_name" class="block text-gray-500 text-theme-xs dark:text-gray-400 mt-0.5">
                    {{ proposal.project_name }}
                  </span>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <p class="text-theme-sm text-gray-700 dark:text-gray-300">
                    {{ proposal.client_name || 'No client' }}
                  </p>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full capitalize', getStatusClasses(proposal.status)]">
                    <span class="w-1.5 h-1.5 rounded-full" :class="getStatusDotClasses(proposal.status)"></span>
                    {{ proposal.status }}
                  </span>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <div class="text-theme-sm font-medium text-gray-900 dark:text-white">
                    ${{ formatCurrency(proposal.total) }}
                  </div>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <div :class="['text-theme-sm', isExpired(proposal) ? 'text-red-600 font-medium' : 'text-gray-600 dark:text-gray-400']">
                    {{ formatDate(proposal.valid_until) }}
                  </div>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <div class="flex items-center justify-end gap-2">
                    <router-link
                      :to="`/proposals/${proposal.id}`"
                      class="p-1.5 text-gray-600 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400"
                      title="View"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </router-link>
                    <button
                      v-if="proposal.status === 'draft'"
                      @click="sendProposal(proposal)"
                      class="p-1.5 text-gray-600 hover:text-green-600 dark:text-gray-400 dark:hover:text-green-400"
                      title="Send"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                      </svg>
                    </button>
                    <button
                      v-if="proposal.status === 'accepted'"
                      @click="convertToInvoice(proposal)"
                      class="p-1.5 text-gray-600 hover:text-green-600 dark:text-gray-400 dark:hover:text-green-400"
                      title="Convert to Invoice"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </button>
                    <button
                      @click="duplicateProposal(proposal)"
                      class="p-1.5 text-gray-600 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400"
                      title="Duplicate"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                      </svg>
                    </button>
                    <router-link
                      v-if="proposal.status === 'draft'"
                      :to="`/proposals/${proposal.id}/edit`"
                      class="p-1.5 text-gray-600 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400"
                      title="Edit"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </router-link>
                    <button
                      v-if="proposal.status === 'draft'"
                      @click="confirmDelete(proposal)"
                      class="p-1.5 text-gray-600 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
                      title="Delete"
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
import { useRouter } from 'vue-router'
import { useProposalStore } from '@/stores/proposals'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const router = useRouter()
const proposalStore = useProposalStore()

const searchTerm = ref('')
const statusFilter = ref('all')

const displayedProposals = computed(() => {
  let proposals = proposalStore.filteredProposals
  if (statusFilter.value !== 'all') {
    proposals = proposals.filter(p => p.status === statusFilter.value)
  }
  return proposals
})

async function loadProposals() {
  try {
    await proposalStore.fetchProposals()
  } catch (error) {
    console.error('Failed to load proposals:', error)
  }
}

function handleSearch() {
  proposalStore.setSearchTerm(searchTerm.value)
}

function applyFilter() {
  loadProposals()
}

async function sendProposal(proposal) {
  if (confirm(`Send proposal "${proposal.title}" to ${proposal.client_name || 'client'}?`)) {
    try {
      await proposalStore.sendProposal(proposal.id)
    } catch (error) {
      console.error('Failed to send proposal:', error)
      alert('Failed to send proposal. Please try again.')
    }
  }
}

async function convertToInvoice(proposal) {
  if (confirm(`Convert proposal "${proposal.title}" to an invoice?`)) {
    try {
      const invoice = await proposalStore.convertToInvoice(proposal.id)
      router.push(`/invoices/${invoice.id}`)
    } catch (error) {
      console.error('Failed to convert to invoice:', error)
      alert('Failed to convert to invoice. Please try again.')
    }
  }
}

async function duplicateProposal(proposal) {
  try {
    const newProposal = await proposalStore.duplicateProposal(proposal.id)
    router.push(`/proposals/${newProposal.id}/edit`)
  } catch (error) {
    console.error('Failed to duplicate proposal:', error)
    alert('Failed to duplicate proposal. Please try again.')
  }
}

async function confirmDelete(proposal) {
  if (confirm(`Delete proposal "${proposal.title}"? This cannot be undone.`)) {
    try {
      await proposalStore.deleteProposal(proposal.id)
    } catch (error) {
      console.error('Failed to delete proposal:', error)
      alert('Failed to delete proposal. Please try again.')
    }
  }
}

function formatCurrency(value) {
  return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDate(dateString) {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

function isExpired(proposal) {
  if (['accepted', 'rejected'].includes(proposal.status)) return false
  if (!proposal.valid_until) return false
  return new Date(proposal.valid_until) < new Date()
}

function getStatusClasses(status) {
  const classes = {
    draft: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
    sent: 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
    viewed: 'bg-purple-100 text-purple-700 dark:bg-purple-900/20 dark:text-purple-400',
    accepted: 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400',
    rejected: 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400',
    expired: 'bg-orange-100 text-orange-700 dark:bg-orange-900/20 dark:text-orange-400'
  }
  return classes[status] || classes.draft
}

function getStatusDotClasses(status) {
  const classes = {
    draft: 'bg-gray-600',
    sent: 'bg-blue-600',
    viewed: 'bg-purple-600',
    accepted: 'bg-green-600',
    rejected: 'bg-red-600',
    expired: 'bg-orange-600'
  }
  return classes[status] || classes.draft
}

onMounted(() => {
  loadProposals()
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
</style>
