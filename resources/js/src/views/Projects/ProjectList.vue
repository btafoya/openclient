<template>
  <AdminLayout>
    <div class="space-y-6">
      <!-- Page Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Projects</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Manage project tracking, tasks, and time entries
          </p>
        </div>
        <div class="flex gap-3">
          <button
            @click="exportToCSV"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Export CSV
          </button>
          <router-link
            to="/projects/create"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            New Project
          </router-link>
        </div>
      </div>

      <!-- Search and Filters -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
        <div class="flex-1">
          <div class="relative">
            <input
              v-model="searchTerm"
              type="text"
              placeholder="Search projects by name, description, or client..."
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
            <option value="planning">Planning</option>
            <option value="in_progress">In Progress</option>
            <option value="on_hold">On Hold</option>
            <option value="completed">Completed</option>
          </select>
          <select
            v-model="activeFilter"
            @change="applyFilter"
            class="px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
          >
            <option value="all">All Projects</option>
            <option value="active">Active Only</option>
            <option value="inactive">Inactive Only</option>
          </select>
        </div>
      </div>

      <!-- Projects Table -->
      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <!-- Loading State -->
        <div v-if="projectStore.loading" class="p-8 text-center">
          <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading projects...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="projectStore.error" class="p-8 text-center">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/20">
            <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <p class="mt-4 text-sm text-gray-900 dark:text-white font-medium">{{ projectStore.error }}</p>
          <button
            @click="loadProjects"
            class="mt-4 px-4 py-2 text-sm font-medium text-brand-600 hover:text-brand-700"
          >
            Try Again
          </button>
        </div>

        <!-- Empty State -->
        <div v-else-if="displayedProjects.length === 0" class="p-8 text-center">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800">
            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
          </div>
          <p class="mt-4 text-sm text-gray-900 dark:text-white font-medium">No projects found</p>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ searchTerm ? 'Try adjusting your search' : 'Get started by creating your first project' }}
          </p>
          <router-link
            v-if="!searchTerm"
            to="/projects/create"
            class="inline-flex items-center gap-2 mt-4 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Create Your First Project
          </router-link>
        </div>

        <!-- Table Content -->
        <div v-else class="max-w-full overflow-x-auto custom-scrollbar">
          <table class="min-w-full">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Project</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Client</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Status</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Budget</p>
                </th>
                <th class="px-5 py-3 text-left sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Progress</p>
                </th>
                <th class="px-5 py-3 text-right sm:px-6">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Actions</p>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr
                v-for="project in displayedProjects"
                :key="project.id"
                class="border-t border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50"
              >
                <td class="px-5 py-4 sm:px-6">
                  <div>
                    <router-link
                      :to="`/projects/${project.id}`"
                      class="block font-medium text-gray-800 text-theme-sm dark:text-white/90 hover:text-brand-600 dark:hover:text-brand-400"
                    >
                      {{ project.name }}
                    </router-link>
                    <span v-if="project.description" class="block text-gray-500 text-theme-xs dark:text-gray-400 mt-0.5 line-clamp-1">
                      {{ project.description }}
                    </span>
                  </div>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <div class="text-theme-sm">
                    <p v-if="project.client_name" class="text-gray-700 dark:text-gray-300">
                      {{ project.client_name }}
                    </p>
                    <p v-else class="text-gray-400 dark:text-gray-500 italic">
                      No client
                    </p>
                  </div>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <span
                    :class="[
                      'inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full',
                      getStatusClasses(project.status)
                    ]"
                  >
                    <span class="w-1.5 h-1.5 rounded-full" :class="getStatusDotClasses(project.status)"></span>
                    {{ getStatusLabel(project.status) }}
                  </span>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <div v-if="project.budget" class="text-theme-sm text-gray-700 dark:text-gray-300">
                    ${{ Number(project.budget).toLocaleString() }}
                  </div>
                  <div v-else class="text-gray-400 dark:text-gray-500 text-theme-sm italic">
                    No budget
                  </div>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <div class="flex items-center gap-2">
                    <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                      <div
                        class="bg-brand-600 h-2 rounded-full"
                        :style="{ width: `${project.progress || 0}%` }"
                      ></div>
                    </div>
                    <span class="text-theme-xs text-gray-600 dark:text-gray-400 w-10 text-right">
                      {{ project.progress || 0 }}%
                    </span>
                  </div>
                </td>
                <td class="px-5 py-4 sm:px-6">
                  <div class="flex items-center justify-end gap-2">
                    <router-link
                      :to="`/projects/${project.id}`"
                      class="p-1.5 text-gray-600 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400"
                      title="View Project"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </router-link>
                    <router-link
                      :to="`/projects/${project.id}/edit`"
                      class="p-1.5 text-gray-600 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400"
                      title="Edit Project"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </router-link>
                    <button
                      @click="toggleProjectStatus(project)"
                      class="p-1.5 text-gray-600 hover:text-yellow-600 dark:text-gray-400 dark:hover:text-yellow-400"
                      :title="project.is_active ? 'Deactivate' : 'Activate'"
                    >
                      <svg v-if="project.is_active" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                      </svg>
                      <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </button>
                    <button
                      @click="confirmDelete(project)"
                      class="p-1.5 text-gray-600 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
                      title="Delete Project"
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

      <!-- Statistics Summary -->
      <div v-if="displayedProjects.length > 0" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Total Projects</p>
          <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ projectStore.projectCount }}</p>
        </div>
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Active Projects</p>
          <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ projectStore.activeProjectCount }}</p>
        </div>
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">In Progress</p>
          <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ projectStore.projectStats.in_progress }}</p>
        </div>
        <div class="p-4 bg-white rounded-lg border border-gray-200 dark:bg-white/[0.03] dark:border-gray-800">
          <p class="text-sm text-gray-500 dark:text-gray-400">Completed</p>
          <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ projectStore.projectStats.completed }}</p>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useProjectStore } from '@/stores/projects'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const router = useRouter()
const projectStore = useProjectStore()

const searchTerm = ref('')
const statusFilter = ref('all')
const activeFilter = ref('all')

// Computed
const displayedProjects = computed(() => {
  let projects = projectStore.filteredProjects

  // Apply active filter
  if (activeFilter.value === 'active') {
    projects = projects.filter(p => p.is_active)
  } else if (activeFilter.value === 'inactive') {
    projects = projects.filter(p => !p.is_active)
  }

  return projects
})

// Methods
async function loadProjects() {
  try {
    await projectStore.fetchProjects(activeFilter.value !== 'inactive')
  } catch (error) {
    console.error('Failed to load projects:', error)
  }
}

function handleSearch() {
  projectStore.setSearchTerm(searchTerm.value)
}

function applyFilter() {
  projectStore.setStatusFilter(statusFilter.value)
  loadProjects()
}

async function toggleProjectStatus(project) {
  try {
    await projectStore.toggleActive(project.id)
  } catch (error) {
    console.error('Failed to toggle project status:', error)
    alert('Failed to update project status. Please try again.')
  }
}

async function confirmDelete(project) {
  if (confirm(`Are you sure you want to delete "${project.name}"? This action cannot be undone.`)) {
    try {
      await projectStore.deleteProject(project.id)
    } catch (error) {
      console.error('Failed to delete project:', error)
      if (error.response?.data?.reasons) {
        alert(`Cannot delete project:\n${error.response.data.reasons.join('\n')}`)
      } else {
        alert('Failed to delete project. Please try again.')
      }
    }
  }
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

function exportToCSV() {
  alert('CSV export functionality will be implemented in the CSV Import/Export component')
}

// Lifecycle
onMounted(() => {
  loadProjects()
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

.line-clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
