<template>
  <AdminLayout>
    <div class="space-y-6">
      <!-- Page Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Recurring Invoices</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Automated billing schedules for recurring clients
          </p>
        </div>
        <router-link
          to="/recurring-invoices/create"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          New Schedule
        </router-link>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Active Schedules</p>
          <p class="mt-1 text-2xl font-bold text-green-600">
            {{ recurringStore.activeSchedules.length }}
          </p>
        </div>
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Paused</p>
          <p class="mt-1 text-2xl font-bold text-yellow-600">
            {{ recurringStore.pausedSchedules.length }}
          </p>
        </div>
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Expired</p>
          <p class="mt-1 text-2xl font-bold text-gray-600 dark:text-gray-400">
            {{ recurringStore.expiredSchedules.length }}
          </p>
        </div>
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Monthly Revenue</p>
          <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
            ${{ formatCurrency(recurringStore.monthlyRevenue) }}
          </p>
        </div>
      </div>

      <!-- Upcoming Invoices Alert -->
      <div v-if="recurringStore.upcomingInvoices.length > 0" class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
        <div class="flex items-start gap-3">
          <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div>
            <p class="text-sm font-medium text-blue-800 dark:text-blue-200">
              {{ recurringStore.upcomingInvoices.length }} invoice(s) will be generated in the next 7 days
            </p>
            <router-link to="/recurring-invoices/upcoming" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
              View upcoming invoices →
            </router-link>
          </div>
        </div>
      </div>

      <!-- Search and Filters -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
        <div class="flex-1">
          <div class="relative">
            <input
              v-model="searchTerm"
              type="text"
              placeholder="Search schedules..."
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
          <option value="active">Active</option>
          <option value="paused">Paused</option>
          <option value="expired">Expired</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      <!-- Schedules Table -->
      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <!-- Loading State -->
        <div v-if="recurringStore.loading" class="p-8 text-center">
          <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading schedules...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="recurringStore.error" class="p-8 text-center">
          <p class="text-sm text-gray-900 dark:text-white font-medium">{{ recurringStore.error }}</p>
          <button @click="loadSchedules" class="mt-4 px-4 py-2 text-sm font-medium text-brand-600 hover:text-brand-700">
            Try Again
          </button>
        </div>

        <!-- Empty State -->
        <div v-else-if="displayedSchedules.length === 0" class="p-8 text-center">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800">
            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <p class="mt-4 text-sm text-gray-900 dark:text-white font-medium">No recurring schedules found</p>
          <router-link
            to="/recurring-invoices/create"
            class="inline-flex items-center gap-2 mt-4 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
          >
            Create Your First Schedule
          </router-link>
        </div>

        <!-- Table Content -->
        <div v-else class="max-w-full overflow-x-auto custom-scrollbar">
          <table class="min-w-full">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Schedule</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Client</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Frequency</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Amount</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Next Invoice</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Status</p>
                </th>
                <th class="px-5 py-3 text-right sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Actions</p>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr
                v-for="schedule in displayedSchedules"
                :key="schedule.id"
                class="border-t border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50"
              >
                <td class="px-5 py-4 sm:px-6">
                  <router-link
                    :to="`/recurring-invoices/${schedule.id}`"
                    class="block font-medium text-gray-800 text-theme-sm dark:text-white/90 hover:text-brand-600 dark:hover:text-brand-400"
                  >
                    {{ schedule.title }}
                  </router-link>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <p class="text-theme-sm text-gray-700 dark:text-gray-300">
                    {{ schedule.client_name || 'No client' }}
                  </p>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <p class="text-theme-sm text-gray-700 dark:text-gray-300 capitalize">
                    {{ recurringStore.getFrequencyLabel(schedule.frequency) }}
                  </p>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <div class="text-theme-sm font-medium text-gray-900 dark:text-white">
                    ${{ formatCurrency(schedule.amount) }}
                  </div>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <p class="text-theme-sm text-gray-600 dark:text-gray-400">
                    {{ formatDate(schedule.next_invoice_date) }}
                  </p>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full capitalize', getStatusClasses(schedule.status)]">
                    <span class="w-1.5 h-1.5 rounded-full" :class="getStatusDotClasses(schedule.status)"></span>
                    {{ schedule.status }}
                  </span>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <div class="flex items-center justify-end gap-2">
                    <router-link
                      :to="`/recurring-invoices/${schedule.id}`"
                      class="p-1.5 text-gray-600 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400"
                      title="View"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </router-link>
                    <button
                      v-if="schedule.status === 'active'"
                      @click="generateNow(schedule)"
                      class="p-1.5 text-gray-600 hover:text-green-600 dark:text-gray-400 dark:hover:text-green-400"
                      title="Generate Invoice Now"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                      </svg>
                    </button>
                    <button
                      v-if="schedule.status === 'active'"
                      @click="pauseSchedule(schedule)"
                      class="p-1.5 text-gray-600 hover:text-yellow-600 dark:text-gray-400 dark:hover:text-yellow-400"
                      title="Pause"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </button>
                    <button
                      v-if="schedule.status === 'paused'"
                      @click="resumeSchedule(schedule)"
                      class="p-1.5 text-gray-600 hover:text-green-600 dark:text-gray-400 dark:hover:text-green-400"
                      title="Resume"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </button>
                    <router-link
                      v-if="schedule.status !== 'cancelled'"
                      :to="`/recurring-invoices/${schedule.id}/edit`"
                      class="p-1.5 text-gray-600 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400"
                      title="Edit"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </router-link>
                    <button
                      v-if="['active', 'paused'].includes(schedule.status)"
                      @click="cancelSchedule(schedule)"
                      class="p-1.5 text-gray-600 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
                      title="Cancel"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
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
import { useRecurringInvoiceStore } from '@/stores/recurringInvoices'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const router = useRouter()
const recurringStore = useRecurringInvoiceStore()

const searchTerm = ref('')
const statusFilter = ref('all')

const displayedSchedules = computed(() => {
  let schedules = recurringStore.filteredSchedules
  if (statusFilter.value !== 'all') {
    schedules = schedules.filter(s => s.status === statusFilter.value)
  }
  return schedules
})

async function loadSchedules() {
  try {
    await Promise.all([
      recurringStore.fetchSchedules(),
      recurringStore.fetchUpcoming(7)
    ])
  } catch (error) {
    console.error('Failed to load schedules:', error)
  }
}

function handleSearch() {
  recurringStore.setSearchTerm(searchTerm.value)
}

function applyFilter() {
  loadSchedules()
}

async function generateNow(schedule) {
  if (confirm(`Generate invoice now for "${schedule.title}"?`)) {
    try {
      const invoice = await recurringStore.generateNow(schedule.id)
      router.push(`/invoices/${invoice.id}`)
    } catch (error) {
      console.error('Failed to generate invoice:', error)
      alert('Failed to generate invoice. Please try again.')
    }
  }
}

async function pauseSchedule(schedule) {
  if (confirm(`Pause schedule "${schedule.title}"?`)) {
    try {
      await recurringStore.pauseSchedule(schedule.id)
    } catch (error) {
      console.error('Failed to pause schedule:', error)
      alert('Failed to pause schedule. Please try again.')
    }
  }
}

async function resumeSchedule(schedule) {
  try {
    await recurringStore.resumeSchedule(schedule.id)
  } catch (error) {
    console.error('Failed to resume schedule:', error)
    alert('Failed to resume schedule. Please try again.')
  }
}

async function cancelSchedule(schedule) {
  if (confirm(`Cancel schedule "${schedule.title}"? This cannot be undone.`)) {
    try {
      await recurringStore.cancelSchedule(schedule.id)
    } catch (error) {
      console.error('Failed to cancel schedule:', error)
      alert('Failed to cancel schedule. Please try again.')
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

function getStatusClasses(status) {
  const classes = {
    active: 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400',
    paused: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400',
    expired: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400'
  }
  return classes[status] || classes.active
}

function getStatusDotClasses(status) {
  const classes = {
    active: 'bg-green-600',
    paused: 'bg-yellow-600',
    expired: 'bg-gray-600',
    cancelled: 'bg-red-600'
  }
  return classes[status] || classes.active
}

onMounted(() => {
  loadSchedules()
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
