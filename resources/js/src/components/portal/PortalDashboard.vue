<template>
  <div class="space-y-6">
    <!-- Welcome Section -->
    <div class="rounded-lg bg-gradient-to-r from-brand-500 to-brand-600 px-6 py-8 text-white">
      <h1 class="mb-2 text-2xl font-bold">
        Welcome back, {{ userName }}!
      </h1>
      <p class="text-brand-100">
        Here's an overview of your account and recent activity.
      </p>
    </div>

    <!-- Quick Stats -->
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Projects</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
              {{ stats.activeProjects }}
            </p>
          </div>
          <div class="rounded-lg bg-blue-100 p-3 dark:bg-blue-900/30">
            <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Invoices</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
              {{ stats.pendingInvoices }}
            </p>
          </div>
          <div class="rounded-lg bg-yellow-100 p-3 dark:bg-yellow-900/30">
            <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Outstanding Balance</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
              {{ formatCurrency(stats.outstandingBalance) }}
            </p>
          </div>
          <div class="rounded-lg bg-red-100 p-3 dark:bg-red-900/30">
            <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Proposals</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
              {{ stats.pendingProposals }}
            </p>
          </div>
          <div class="rounded-lg bg-purple-100 p-3 dark:bg-purple-900/30">
            <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Recent Invoices -->
      <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Invoices</h2>
          <router-link to="/portal/invoices" class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400">
            View all
          </router-link>
        </div>
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
          <div
            v-for="invoice in recentInvoices"
            :key="invoice.id"
            class="flex items-center justify-between px-6 py-4"
          >
            <div>
              <p class="font-medium text-gray-900 dark:text-white">{{ invoice.invoice_number }}</p>
              <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ formatDate(invoice.issue_date) }}
              </p>
            </div>
            <div class="text-right">
              <p class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(invoice.total) }}</p>
              <span
                :class="getStatusClasses(invoice.status)"
                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
              >
                {{ invoice.status }}
              </span>
            </div>
          </div>
          <div v-if="recentInvoices.length === 0" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
            No invoices yet
          </div>
        </div>
      </div>

      <!-- Active Projects -->
      <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Active Projects</h2>
          <router-link to="/portal/projects" class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400">
            View all
          </router-link>
        </div>
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
          <div
            v-for="project in activeProjects"
            :key="project.id"
            class="px-6 py-4"
          >
            <div class="mb-2 flex items-center justify-between">
              <p class="font-medium text-gray-900 dark:text-white">{{ project.name }}</p>
              <span
                :class="getProjectStatusClasses(project.status)"
                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
              >
                {{ project.status }}
              </span>
            </div>
            <div class="flex items-center gap-2">
              <div class="flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div
                  class="h-2 rounded-full bg-brand-500"
                  :style="{ width: `${project.progress || 0}%` }"
                ></div>
              </div>
              <span class="text-sm text-gray-500 dark:text-gray-400">{{ project.progress || 0 }}%</span>
            </div>
          </div>
          <div v-if="activeProjects.length === 0" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
            No active projects
          </div>
        </div>
      </div>
    </div>

    <!-- Pending Proposals -->
    <div v-if="pendingProposals.length > 0" class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
      <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Proposals Awaiting Response</h2>
      </div>
      <div class="divide-y divide-gray-200 dark:divide-gray-700">
        <div
          v-for="proposal in pendingProposals"
          :key="proposal.id"
          class="flex items-center justify-between px-6 py-4"
        >
          <div>
            <p class="font-medium text-gray-900 dark:text-white">{{ proposal.title }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              Valid until {{ formatDate(proposal.valid_until) }}
            </p>
          </div>
          <div class="flex items-center gap-3">
            <p class="font-semibold text-gray-900 dark:text-white">{{ formatCurrency(proposal.total) }}</p>
            <router-link
              :to="`/portal/proposals/${proposal.id}`"
              class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600"
            >
              Review
            </router-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  userName: {
    type: String,
    default: 'Guest'
  },
  stats: {
    type: Object,
    default: () => ({
      activeProjects: 0,
      pendingInvoices: 0,
      outstandingBalance: 0,
      pendingProposals: 0
    })
  },
  recentInvoices: {
    type: Array,
    default: () => []
  },
  activeProjects: {
    type: Array,
    default: () => []
  },
  pendingProposals: {
    type: Array,
    default: () => []
  }
})

function formatCurrency(value, currency = 'USD') {
  if (!value) return '$0'
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency,
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value)
}

function formatDate(dateString) {
  if (!dateString) return ''
  const date = new Date(dateString)
  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  }).format(date)
}

function getStatusClasses(status) {
  const classes = {
    draft: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
    sent: 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
    viewed: 'bg-purple-100 text-purple-700 dark:bg-purple-900/20 dark:text-purple-400',
    paid: 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400',
    overdue: 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400',
    cancelled: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
  }
  return classes[status] || classes.draft
}

function getProjectStatusClasses(status) {
  const classes = {
    active: 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400',
    on_hold: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400',
    completed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
    cancelled: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
  }
  return classes[status] || classes.active
}
</script>
