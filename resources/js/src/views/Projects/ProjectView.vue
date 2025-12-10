<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl">
      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center py-12">
        <div class="h-12 w-12 animate-spin rounded-full border-4 border-gray-300 border-t-brand-600 dark:border-gray-700 dark:border-t-brand-500"></div>
      </div>

      <!-- Error State -->
      <div v-else-if="loadError" class="rounded-lg border border-red-300 bg-red-50 p-6 dark:border-red-700 dark:bg-red-900/20">
        <div class="flex items-start gap-3">
          <svg class="h-6 w-6 flex-shrink-0 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
          </svg>
          <div class="flex-1">
            <h3 class="text-lg font-medium text-red-800 dark:text-red-300">
              Failed to Load Project
            </h3>
            <p class="mt-1 text-sm text-red-700 dark:text-red-400">
              {{ loadError }}
            </p>
            <div class="mt-4 flex gap-3">
              <button
                @click="loadProject"
                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600"
              >
                Try Again
              </button>
              <router-link
                to="/projects"
                class="rounded-lg border border-red-600 bg-white px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-500 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-900/20"
              >
                Back to Projects
              </router-link>
            </div>
          </div>
        </div>
      </div>

      <!-- Project Details -->
      <div v-else-if="project">
        <!-- Header with Actions -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div class="flex-1">
            <div class="flex items-center gap-3">
              <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                {{ project.name }}
              </h1>
              <span
                :class="[
                  'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                  getStatusClasses(project.status)
                ]"
              >
                {{ getStatusLabel(project.status) }}
              </span>
            </div>
            <p v-if="project.description" class="mt-2 text-gray-600 dark:text-gray-400">
              {{ project.description }}
            </p>
            <p v-if="project.client_name" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              Client: <span class="font-medium text-gray-700 dark:text-gray-300">{{ project.client_name }}</span>
            </p>
          </div>

          <div class="flex gap-3">
            <router-link
              to="/projects"
              class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
              Back
            </router-link>
            <router-link
              :to="`/projects/${project.id}/edit`"
              class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
              Edit
            </router-link>
          </div>
        </div>

        <!-- Stats Cards -->
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Budget</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                  ${{ project.budget ? Number(project.budget).toLocaleString() : '0' }}
                </p>
              </div>
              <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900/30">
                <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
            </div>
          </div>

          <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Hours</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                  {{ project.total_hours || '0' }}h
                </p>
              </div>
              <div class="rounded-full bg-green-100 p-3 dark:bg-green-900/30">
                <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
            </div>
          </div>

          <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tasks</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                  {{ project.task_count || '0' }}
                </p>
              </div>
              <div class="rounded-full bg-purple-100 p-3 dark:bg-purple-900/30">
                <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
              </div>
            </div>
          </div>

          <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Progress</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                  {{ project.progress || '0' }}%
                </p>
              </div>
              <div class="rounded-full bg-yellow-100 p-3 dark:bg-yellow-900/30">
                <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab Navigation -->
        <div class="mb-6 border-b border-gray-200 dark:border-gray-800">
          <nav class="-mb-px flex gap-8">
            <button
              @click="activeTab = 'details'"
              :class="[
                'border-b-2 py-4 text-sm font-medium transition-colors',
                activeTab === 'details'
                  ? 'border-brand-600 text-brand-600 dark:border-brand-500 dark:text-brand-400'
                  : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900 dark:text-gray-400 dark:hover:border-gray-700 dark:hover:text-gray-300'
              ]"
            >
              Details
            </button>
            <button
              @click="activeTab = 'tasks'"
              :class="[
                'border-b-2 py-4 text-sm font-medium transition-colors',
                activeTab === 'tasks'
                  ? 'border-brand-600 text-brand-600 dark:border-brand-500 dark:text-brand-400'
                  : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900 dark:text-gray-400 dark:hover:border-gray-700 dark:hover:text-gray-300'
              ]"
            >
              Tasks ({{ project.task_count || 0 }})
            </button>
            <button
              @click="activeTab = 'time'"
              :class="[
                'border-b-2 py-4 text-sm font-medium transition-colors',
                activeTab === 'time'
                  ? 'border-brand-600 text-brand-600 dark:border-brand-500 dark:text-brand-400'
                  : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900 dark:text-gray-400 dark:hover:border-gray-700 dark:hover:text-gray-300'
              ]"
            >
              Time Tracking
            </button>
            <button
              @click="activeTab = 'timeline'"
              :class="[
                'border-b-2 py-4 text-sm font-medium transition-colors',
                activeTab === 'timeline'
                  ? 'border-brand-600 text-brand-600 dark:border-brand-500 dark:text-brand-400'
                  : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900 dark:text-gray-400 dark:hover:border-gray-700 dark:hover:text-gray-300'
              ]"
            >
              Timeline
            </button>
          </nav>
        </div>

        <!-- Tab Content -->
        <div>
          <!-- Details Tab -->
          <div v-if="activeTab === 'details'" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Main Details Card -->
            <div class="lg:col-span-2">
              <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                  Project Information
                </h2>
                <dl class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                  <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                    <dd class="mt-1">
                      <span
                        :class="[
                          'inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full',
                          getStatusClasses(project.status)
                        ]"
                      >
                        <span class="w-1.5 h-1.5 rounded-full" :class="getStatusDotClasses(project.status)"></span>
                        {{ getStatusLabel(project.status) }}
                      </span>
                    </dd>
                  </div>
                  <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Priority</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                      {{ project.priority || 'Not set' }}
                    </dd>
                  </div>
                  <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Start Date</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                      {{ project.start_date ? formatDate(project.start_date) : '—' }}
                    </dd>
                  </div>
                  <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">End Date</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                      {{ project.end_date ? formatDate(project.end_date) : '—' }}
                    </dd>
                  </div>
                  <div class="sm:col-span-2" v-if="project.description">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white whitespace-pre-wrap">
                      {{ project.description }}
                    </dd>
                  </div>
                </dl>
              </div>
            </div>

            <!-- Metadata Card -->
            <div class="lg:col-span-1">
              <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                  Metadata
                </h2>
                <dl class="space-y-4">
                  <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                      {{ formatDate(project.created_at) }}
                    </dd>
                  </div>
                  <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Updated</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                      {{ formatDate(project.updated_at) }}
                    </dd>
                  </div>
                  <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Status</dt>
                    <dd class="mt-1">
                      <span
                        :class="[
                          'inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full',
                          project.is_active
                            ? 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400'
                            : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
                        ]"
                      >
                        <span class="w-1.5 h-1.5 rounded-full" :class="project.is_active ? 'bg-green-600' : 'bg-gray-400'"></span>
                        {{ project.is_active ? 'Active' : 'Inactive' }}
                      </span>
                    </dd>
                  </div>
                </dl>
              </div>
            </div>
          </div>

          <!-- Tasks Tab -->
          <div v-else-if="activeTab === 'tasks'">
            <TaskBoard :project-id="project.id" />
          </div>

          <!-- Time Tracking Tab -->
          <div v-else-if="activeTab === 'time'">
            <TimeTracker :project-id="project.id" />
          </div>

          <!-- Timeline Tab -->
          <div v-else-if="activeTab === 'timeline'">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
              <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                Project Timeline
              </h2>
              <p class="text-sm text-gray-600 dark:text-gray-400">
                Timeline view showing project history and activity log.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useProjectStore } from '@/stores/projects'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import TaskBoard from '@/components/projects/TaskBoard.vue'
import TimeTracker from '@/components/projects/TimeTracker.vue'

const route = useRoute()
const router = useRouter()
const projectStore = useProjectStore()

const loading = ref(false)
const loadError = ref(null)
const activeTab = ref('details')

const project = computed(() => projectStore.currentProject)

// Methods
async function loadProject() {
  loading.value = true
  loadError.value = null
  try {
    await projectStore.fetchProject(route.params.id)
  } catch (error) {
    console.error('Failed to load project:', error)
    loadError.value = error.response?.data?.error || 'Failed to load project details'
  } finally {
    loading.value = false
  }
}

function formatDate(dateString) {
  if (!dateString) return '—'
  const date = new Date(dateString)
  return new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  }).format(date)
}

function getStatusClasses(status) {
  const statusClasses = {
    planning: 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
    in_progress: 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400',
    on_hold: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400',
    completed: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
  }
  return statusClasses[status] || statusClasses.planning
}

function getStatusDotClasses(status) {
  const dotClasses = {
    planning: 'bg-blue-600',
    in_progress: 'bg-green-600',
    on_hold: 'bg-yellow-600',
    completed: 'bg-gray-600'
  }
  return dotClasses[status] || dotClasses.planning
}

function getStatusLabel(status) {
  const labels = {
    planning: 'Planning',
    in_progress: 'In Progress',
    on_hold: 'On Hold',
    completed: 'Completed'
  }
  return labels[status] || status
}

// Lifecycle
onMounted(() => {
  loadProject()
})
</script>
