<script setup>
import { ref, computed, onMounted } from 'vue'
import { useUserStore } from '@/stores/user'
import { usePermissions } from '@/composables/usePermissions'
import StatsCard from './StatsCard.vue'
import axios from 'axios'

const userStore = useUserStore()
const { can } = usePermissions()

// State
const stats = ref({
  clients: { total: 0, trend: null },
  projects: { total: 0, active: 0, trend: null },
  invoices: { pending: 0, total: 0, trend: null },
  revenue: { current: 0, trend: null }
})

const recentActivity = ref([])
const loading = ref(true)
const error = ref(null)

// Computed
const canViewFinancials = computed(() => userStore.canViewFinancials)

// Dashboard stats configuration
const dashboardStats = computed(() => {
  const baseStats = [
    {
      title: 'Total Clients',
      value: stats.value.clients.total,
      icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
      color: 'primary',
      trend: stats.value.clients.trend
    },
    {
      title: 'Active Projects',
      value: stats.value.projects.active,
      icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
      color: 'success',
      trend: stats.value.projects.trend
    }
  ]

  // Add financial stats only if user has permission
  if (canViewFinancials.value) {
    baseStats.push(
      {
        title: 'Pending Invoices',
        value: stats.value.invoices.pending,
        icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        color: 'warning',
        trend: stats.value.invoices.trend
      },
      {
        title: 'Revenue (MTD)',
        value: formatCurrency(stats.value.revenue.current),
        icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        color: 'info',
        trend: stats.value.revenue.trend
      }
    )
  }

  return baseStats
})

// Methods
const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value)
}

const formatDate = (dateString) => {
  const date = new Date(dateString)
  const now = new Date()
  const diffTime = Math.abs(now - date)
  const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24))

  if (diffDays === 0) {
    const diffHours = Math.floor(diffTime / (1000 * 60 * 60))
    if (diffHours === 0) {
      const diffMinutes = Math.floor(diffTime / (1000 * 60))
      return `${diffMinutes} minutes ago`
    }
    return `${diffHours} hours ago`
  } else if (diffDays === 1) {
    return 'Yesterday'
  } else if (diffDays < 7) {
    return `${diffDays} days ago`
  } else {
    return date.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
    })
  }
}

const getActivityIcon = (type) => {
  const icons = {
    client: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
    project: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    invoice: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    payment: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    user: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'
  }
  return icons[type] || icons.project
}

const getActivityColor = (type) => {
  const colors = {
    client: 'bg-primary-100 text-primary-600',
    project: 'bg-green-100 text-green-600',
    invoice: 'bg-yellow-100 text-yellow-600',
    payment: 'bg-blue-100 text-blue-600',
    user: 'bg-purple-100 text-purple-600'
  }
  return colors[type] || colors.project
}

const fetchDashboardData = async () => {
  try {
    loading.value = true
    error.value = null

    const response = await axios.get('/api/dashboard')

    stats.value = response.data.stats
    recentActivity.value = response.data.recentActivity

    loading.value = false
  } catch (err) {
    console.error('Failed to fetch dashboard data:', err)
    error.value = 'Failed to load dashboard data. Please try again.'
    loading.value = false
  }
}

// Lifecycle
onMounted(() => {
  fetchDashboardData()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Page Header -->
    <div>
      <h1 class="text-2xl font-semibold text-gray-900">Dashboard</h1>
      <p class="mt-1 text-sm text-gray-500">
        Welcome back, {{ userStore.email }}
      </p>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="card card-body bg-red-50 border-red-200">
      <div class="flex items-center">
        <svg
          class="h-5 w-5 text-red-600 mr-2"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
        <p class="text-sm text-red-800">{{ error }}</p>
      </div>
    </div>

    <!-- Dashboard Content -->
    <template v-else>
      <!-- Stats Grid -->
      <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <StatsCard
          v-for="stat in dashboardStats"
          :key="stat.title"
          :title="stat.title"
          :value="stat.value"
          :icon="stat.icon"
          :color="stat.color"
          :trend="stat.trend"
        />
      </div>

      <!-- Recent Activity -->
      <div class="card">
        <div class="card-header">
          <h2 class="text-lg font-medium text-gray-900">Recent Activity</h2>
        </div>
        <div class="card-body">
          <div v-if="recentActivity.length === 0" class="text-center py-6">
            <p class="text-sm text-gray-500">No recent activity</p>
          </div>

          <div v-else class="flow-root">
            <ul role="list" class="-mb-8">
              <li
                v-for="(activity, activityIdx) in recentActivity"
                :key="activity.id"
              >
                <div class="relative pb-8">
                  <span
                    v-if="activityIdx !== recentActivity.length - 1"
                    class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"
                    aria-hidden="true"
                  />
                  <div class="relative flex space-x-3">
                    <div>
                      <span
                        :class="[
                          'h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white',
                          getActivityColor(activity.type)
                        ]"
                      >
                        <svg
                          class="h-4 w-4"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            :d="getActivityIcon(activity.type)"
                          />
                        </svg>
                      </span>
                    </div>
                    <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                      <div>
                        <p class="text-sm text-gray-900">
                          {{ activity.description }}
                        </p>
                        <p v-if="activity.user" class="mt-0.5 text-xs text-gray-500">
                          by {{ activity.user }}
                        </p>
                      </div>
                      <div class="whitespace-nowrap text-right text-sm text-gray-500">
                        <time :datetime="activity.created_at">
                          {{ formatDate(activity.created_at) }}
                        </time>
                      </div>
                    </div>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
