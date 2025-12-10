<template>
  <AdminLayout>
    <div class="space-y-6">
      <!-- Page Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Support Tickets</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Manage client support requests and issues
          </p>
        </div>
        <router-link
          to="/tickets/create"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          New Ticket
        </router-link>
      </div>

      <!-- Stats Cards -->
      <div v-if="ticketStore.stats" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Open Tickets</p>
          <p class="mt-1 text-2xl font-bold text-blue-600">
            {{ ticketStore.stats.open_count || 0 }}
          </p>
        </div>
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">In Progress</p>
          <p class="mt-1 text-2xl font-bold text-yellow-600">
            {{ ticketStore.stats.in_progress_count || 0 }}
          </p>
        </div>
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Resolved</p>
          <p class="mt-1 text-2xl font-bold text-green-600">
            {{ ticketStore.stats.resolved_count || 0 }}
          </p>
        </div>
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Urgent</p>
          <p class="mt-1 text-2xl font-bold text-red-600">
            {{ ticketStore.stats.urgent_count || 0 }}
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
              placeholder="Search tickets by number, subject, or description..."
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
            <option value="open">Open</option>
            <option value="in_progress">In Progress</option>
            <option value="waiting">Waiting</option>
            <option value="resolved">Resolved</option>
            <option value="closed">Closed</option>
          </select>
          <select
            v-model="priorityFilter"
            @change="applyFilter"
            class="px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
          >
            <option value="all">All Priorities</option>
            <option value="urgent">Urgent</option>
            <option value="high">High</option>
            <option value="normal">Normal</option>
            <option value="low">Low</option>
          </select>
        </div>
      </div>

      <!-- Tickets Table -->
      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <!-- Loading State -->
        <div v-if="ticketStore.loading" class="p-8 text-center">
          <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading tickets...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="ticketStore.error" class="p-8 text-center">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/20">
            <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <p class="mt-4 text-sm text-gray-900 dark:text-white font-medium">{{ ticketStore.error }}</p>
          <button
            @click="loadTickets"
            class="mt-4 px-4 py-2 text-sm font-medium text-brand-600 hover:text-brand-700"
          >
            Try Again
          </button>
        </div>

        <!-- Empty State -->
        <div v-else-if="displayedTickets.length === 0" class="p-8 text-center">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800">
            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
            </svg>
          </div>
          <p class="mt-4 text-sm text-gray-900 dark:text-white font-medium">No tickets found</p>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ searchTerm ? 'Try adjusting your search' : 'No support tickets yet' }}
          </p>
          <router-link
            v-if="!searchTerm"
            to="/tickets/create"
            class="inline-flex items-center gap-2 mt-4 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Create Your First Ticket
          </router-link>
        </div>

        <!-- Table Content -->
        <div v-else class="max-w-full overflow-x-auto custom-scrollbar">
          <table class="min-w-full">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Ticket</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Status</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Priority</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Client</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Created</p>
                </th>
                <th class="px-5 py-3 text-right sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Actions</p>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr
                v-for="ticket in displayedTickets"
                :key="ticket.id"
                class="border-t border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50"
              >
                <td class="px-5 py-4 sm:px-6">
                  <div>
                    <router-link
                      :to="`/tickets/${ticket.id}`"
                      class="block font-medium text-gray-800 text-theme-sm dark:text-white/90 hover:text-brand-600 dark:hover:text-brand-400"
                    >
                      {{ ticket.ticket_number }}
                    </router-link>
                    <p class="text-gray-500 text-theme-xs dark:text-gray-400 mt-0.5 truncate max-w-xs">
                      {{ ticket.subject }}
                    </p>
                  </div>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <span
                    :class="[
                      'inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full capitalize',
                      ticketStore.getStatusColor(ticket.status)
                    ]"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
                    {{ formatStatus(ticket.status) }}
                  </span>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <span
                    :class="[
                      'inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full capitalize',
                      ticketStore.getPriorityColor(ticket.priority)
                    ]"
                  >
                    {{ ticket.priority }}
                  </span>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <div class="text-theme-sm">
                    <p v-if="ticket.client_name" class="text-gray-700 dark:text-gray-300">
                      {{ ticket.client_name }}
                    </p>
                    <p v-else class="text-gray-400 dark:text-gray-500 italic">
                      No client
                    </p>
                  </div>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <span class="text-theme-sm text-gray-600 dark:text-gray-400">
                    {{ formatDate(ticket.created_at) }}
                  </span>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <div class="flex items-center justify-end gap-2">
                    <router-link
                      :to="`/tickets/${ticket.id}`"
                      class="p-1.5 text-gray-600 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400"
                      title="View Ticket"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </router-link>
                    <button
                      v-if="ticket.status === 'open'"
                      @click="markInProgress(ticket)"
                      class="p-1.5 text-gray-600 hover:text-yellow-600 dark:text-gray-400 dark:hover:text-yellow-400"
                      title="Start Working"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </button>
                    <button
                      v-if="['open', 'in_progress', 'waiting'].includes(ticket.status)"
                      @click="resolveTicket(ticket)"
                      class="p-1.5 text-gray-600 hover:text-green-600 dark:text-gray-400 dark:hover:text-green-400"
                      title="Resolve Ticket"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </button>
                    <button
                      v-if="ticket.status === 'resolved'"
                      @click="closeTicket(ticket)"
                      class="p-1.5 text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                      title="Close Ticket"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                      </svg>
                    </button>
                    <button
                      v-if="ticket.status === 'closed'"
                      @click="reopenTicket(ticket)"
                      class="p-1.5 text-gray-600 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400"
                      title="Reopen Ticket"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                      </svg>
                    </button>
                    <button
                      v-if="ticket.status === 'open'"
                      @click="confirmDelete(ticket)"
                      class="p-1.5 text-gray-600 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
                      title="Delete Ticket"
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
import { useTicketStore } from '@/stores/tickets'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const ticketStore = useTicketStore()

const searchTerm = ref('')
const statusFilter = ref('all')
const priorityFilter = ref('all')

// Computed
const displayedTickets = computed(() => {
  let tickets = ticketStore.filteredTickets

  if (statusFilter.value !== 'all') {
    tickets = tickets.filter(t => t.status === statusFilter.value)
  }

  if (priorityFilter.value !== 'all') {
    tickets = tickets.filter(t => t.priority === priorityFilter.value)
  }

  return tickets
})

// Methods
async function loadTickets() {
  try {
    await Promise.all([
      ticketStore.fetchTickets(),
      ticketStore.fetchStats()
    ])
  } catch (error) {
    console.error('Failed to load tickets:', error)
  }
}

function handleSearch() {
  ticketStore.setSearchTerm(searchTerm.value)
}

function applyFilter() {
  ticketStore.setStatusFilter(statusFilter.value)
  ticketStore.setPriorityFilter(priorityFilter.value)
}

async function markInProgress(ticket) {
  try {
    await ticketStore.updateTicket(ticket.id, { status: 'in_progress' })
    await loadTickets()
  } catch (error) {
    console.error('Failed to update ticket:', error)
    alert('Failed to update ticket status.')
  }
}

async function resolveTicket(ticket) {
  if (confirm(`Mark ticket ${ticket.ticket_number} as resolved?`)) {
    try {
      await ticketStore.resolveTicket(ticket.id)
      await loadTickets()
    } catch (error) {
      console.error('Failed to resolve ticket:', error)
      alert('Failed to resolve ticket.')
    }
  }
}

async function closeTicket(ticket) {
  if (confirm(`Close ticket ${ticket.ticket_number}?`)) {
    try {
      await ticketStore.closeTicket(ticket.id)
      await loadTickets()
    } catch (error) {
      console.error('Failed to close ticket:', error)
      alert('Failed to close ticket.')
    }
  }
}

async function reopenTicket(ticket) {
  if (confirm(`Reopen ticket ${ticket.ticket_number}?`)) {
    try {
      await ticketStore.reopenTicket(ticket.id)
      await loadTickets()
    } catch (error) {
      console.error('Failed to reopen ticket:', error)
      alert('Failed to reopen ticket.')
    }
  }
}

async function confirmDelete(ticket) {
  if (confirm(`Are you sure you want to delete ticket ${ticket.ticket_number}? This action cannot be undone.`)) {
    try {
      await ticketStore.deleteTicket(ticket.id)
    } catch (error) {
      console.error('Failed to delete ticket:', error)
      alert('Failed to delete ticket.')
    }
  }
}

function formatDate(dateString) {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

function formatStatus(status) {
  return status?.replace('_', ' ') || status
}

// Lifecycle
onMounted(() => {
  loadTickets()
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
