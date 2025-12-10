<template>
  <AdminLayout>
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="h-12 w-12 animate-spin rounded-full border-4 border-gray-300 border-t-brand-600"></div>
    </div>
    <div v-else-if="ticket" class="mx-auto max-w-5xl space-y-6">
      <!-- Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex-1">
          <div class="flex items-center gap-3 flex-wrap">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ ticket.ticket_number }}</span>
            <span
              :class="[
                'inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-medium rounded-full capitalize',
                ticketStore.getStatusColor(ticket.status)
              ]"
            >
              {{ formatStatus(ticket.status) }}
            </span>
            <span
              :class="[
                'inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full capitalize',
                ticketStore.getPriorityColor(ticket.priority)
              ]"
            >
              {{ ticket.priority }}
            </span>
          </div>
          <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ ticket.subject }}</h1>
          <p v-if="ticket.client_name" class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Client: {{ ticket.client_name }}
          </p>
        </div>
        <div class="flex gap-3">
          <router-link
            to="/tickets"
            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back
          </router-link>
          <button
            v-if="['open', 'in_progress', 'waiting'].includes(ticket.status)"
            @click="resolveTicket"
            class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Resolve
          </button>
          <button
            v-if="ticket.status === 'resolved'"
            @click="closeTicket"
            class="inline-flex items-center gap-2 rounded-lg bg-gray-600 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Close
          </button>
          <button
            v-if="ticket.status === 'closed'"
            @click="reopenTicket"
            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Reopen
          </button>
        </div>
      </div>

      <!-- Ticket Details -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Description -->
          <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Description</h2>
            <div class="prose prose-sm dark:prose-invert max-w-none">
              <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ ticket.description || 'No description provided.' }}</p>
            </div>
          </div>

          <!-- Messages / Conversation -->
          <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="p-4 border-b border-gray-200 dark:border-gray-800">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Conversation</h2>
            </div>
            <div class="p-4 space-y-4 max-h-96 overflow-y-auto">
              <div v-if="!ticket.messages || ticket.messages.length === 0" class="text-center py-8">
                <p class="text-sm text-gray-500 dark:text-gray-400">No messages yet</p>
              </div>
              <div
                v-for="message in ticket.messages"
                :key="message.id"
                :class="[
                  'rounded-lg p-4',
                  message.is_internal
                    ? 'bg-yellow-50 border border-yellow-200 dark:bg-yellow-900/20 dark:border-yellow-800'
                    : message.is_from_client
                      ? 'bg-gray-50 dark:bg-gray-800'
                      : 'bg-brand-50 dark:bg-brand-900/20'
                ]"
              >
                <div class="flex items-start justify-between mb-2">
                  <div class="flex items-center gap-2">
                    <span class="font-medium text-sm text-gray-900 dark:text-white">
                      {{ message.is_from_client ? 'Client' : 'Support' }}
                    </span>
                    <span v-if="message.is_internal" class="text-xs px-2 py-0.5 bg-yellow-200 text-yellow-800 rounded dark:bg-yellow-800 dark:text-yellow-200">
                      Internal Note
                    </span>
                  </div>
                  <span class="text-xs text-gray-500 dark:text-gray-400">
                    {{ formatDateTime(message.created_at) }}
                  </span>
                </div>
                <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ message.message }}</p>
              </div>
            </div>

            <!-- Reply Form -->
            <div v-if="ticket.status !== 'closed'" class="p-4 border-t border-gray-200 dark:border-gray-800">
              <div class="space-y-3">
                <textarea
                  v-model="newMessage"
                  rows="3"
                  placeholder="Type your reply..."
                  class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                ></textarea>
                <div class="flex items-center justify-between">
                  <label class="flex items-center gap-2">
                    <input
                      v-model="isInternalNote"
                      type="checkbox"
                      class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500"
                    />
                    <span class="text-sm text-gray-600 dark:text-gray-400">Internal note (not visible to client)</span>
                  </label>
                  <button
                    @click="sendReply"
                    :disabled="!newMessage.trim() || sending"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <svg v-if="sending" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                    {{ sending ? 'Sending...' : 'Send Reply' }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Ticket Info -->
          <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Details</h2>
            <dl class="space-y-4">
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Category</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white capitalize">{{ ticket.category || '—' }}</dd>
              </div>
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Source</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white capitalize">{{ ticket.source || '—' }}</dd>
              </div>
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Assigned To</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ ticket.assigned_user_name || 'Unassigned' }}</dd>
              </div>
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDateTime(ticket.created_at) }}</dd>
              </div>
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Due Date</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDate(ticket.due_date) || '—' }}</dd>
              </div>
              <div v-if="ticket.resolved_at">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Resolved</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDateTime(ticket.resolved_at) }}</dd>
              </div>
              <div v-if="ticket.closed_at">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Closed</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDateTime(ticket.closed_at) }}</dd>
              </div>
            </dl>
          </div>

          <!-- Quick Actions -->
          <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Quick Actions</h2>
            <div class="space-y-2">
              <button
                v-if="ticket.status === 'open'"
                @click="updateStatus('in_progress')"
                class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg dark:text-gray-300 dark:hover:bg-gray-800"
              >
                Start Working
              </button>
              <button
                v-if="ticket.status === 'in_progress'"
                @click="updateStatus('waiting')"
                class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg dark:text-gray-300 dark:hover:bg-gray-800"
              >
                Mark as Waiting
              </button>
              <button
                @click="changePriority"
                class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg dark:text-gray-300 dark:hover:bg-gray-800"
              >
                Change Priority
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div v-else class="text-center py-12">
      <p class="text-gray-500 dark:text-gray-400">Ticket not found</p>
      <router-link to="/tickets" class="mt-4 inline-block text-brand-600 hover:text-brand-700">
        Back to Tickets
      </router-link>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTicketStore } from '@/stores/tickets'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const route = useRoute()
const router = useRouter()
const ticketStore = useTicketStore()

const loading = ref(false)
const sending = ref(false)
const newMessage = ref('')
const isInternalNote = ref(false)

const ticket = computed(() => ticketStore.currentTicket)

async function loadTicket() {
  loading.value = true
  try {
    await ticketStore.fetchTicket(route.params.id)
  } catch (error) {
    console.error('Failed to load ticket:', error)
  } finally {
    loading.value = false
  }
}

async function sendReply() {
  if (!newMessage.value.trim()) return

  sending.value = true
  try {
    await ticketStore.addMessage(ticket.value.id, newMessage.value, isInternalNote.value)
    newMessage.value = ''
    isInternalNote.value = false
  } catch (error) {
    console.error('Failed to send reply:', error)
    alert('Failed to send reply. Please try again.')
  } finally {
    sending.value = false
  }
}

async function updateStatus(status) {
  try {
    await ticketStore.updateTicket(ticket.value.id, { status })
  } catch (error) {
    console.error('Failed to update status:', error)
    alert('Failed to update ticket status.')
  }
}

async function resolveTicket() {
  if (confirm('Mark this ticket as resolved?')) {
    try {
      await ticketStore.resolveTicket(ticket.value.id)
    } catch (error) {
      console.error('Failed to resolve ticket:', error)
      alert('Failed to resolve ticket.')
    }
  }
}

async function closeTicket() {
  if (confirm('Close this ticket?')) {
    try {
      await ticketStore.closeTicket(ticket.value.id)
    } catch (error) {
      console.error('Failed to close ticket:', error)
      alert('Failed to close ticket.')
    }
  }
}

async function reopenTicket() {
  if (confirm('Reopen this ticket?')) {
    try {
      await ticketStore.reopenTicket(ticket.value.id)
    } catch (error) {
      console.error('Failed to reopen ticket:', error)
      alert('Failed to reopen ticket.')
    }
  }
}

function changePriority() {
  const priorities = ['low', 'normal', 'high', 'urgent']
  const current = priorities.indexOf(ticket.value.priority)
  const newPriority = prompt('Enter new priority (low, normal, high, urgent):', ticket.value.priority)

  if (newPriority && priorities.includes(newPriority.toLowerCase())) {
    ticketStore.updateTicket(ticket.value.id, { priority: newPriority.toLowerCase() })
  }
}

function formatDate(dateString) {
  if (!dateString) return null
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

function formatDateTime(dateString) {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  })
}

function formatStatus(status) {
  return status?.replace('_', ' ') || status
}

onMounted(() => loadTicket())
</script>
