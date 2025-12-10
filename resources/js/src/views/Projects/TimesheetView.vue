<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl">
      <!-- Header -->
      <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Timesheet</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            View and manage all time entries across projects
          </p>
        </div>
        <div class="flex gap-3">
          <button
            @click="showFilters = !showFilters"
            :class="[
              'inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition-colors',
              showFilters || hasActiveFilters
                ? 'border-brand-600 bg-brand-50 text-brand-700 dark:border-brand-500 dark:bg-brand-900/20 dark:text-brand-400'
                : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
            ]"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
            </svg>
            Filters
            <span v-if="hasActiveFilters" class="ml-1 rounded-full bg-brand-600 px-1.5 py-0.5 text-xs text-white">
              {{ activeFilterCount }}
            </span>
          </button>
          <button
            @click="exportTimesheet"
            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Export
          </button>
          <button
            @click="showManualEntry = true"
            class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Entry
          </button>
        </div>
      </div>

      <!-- Filters Panel -->
      <div v-if="showFilters" class="mb-6 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <!-- Date Range -->
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Date Range
            </label>
            <select
              v-model="filters.dateRange"
              @change="handleDateRangeChange"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            >
              <option value="today">Today</option>
              <option value="this_week">This Week</option>
              <option value="last_week">Last Week</option>
              <option value="this_month">This Month</option>
              <option value="last_month">Last Month</option>
              <option value="custom">Custom Range</option>
            </select>
          </div>

          <!-- Custom Date Range -->
          <div v-if="filters.dateRange === 'custom'" class="flex gap-2">
            <div class="flex-1">
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                From
              </label>
              <input
                v-model="filters.startDate"
                type="date"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
              />
            </div>
            <div class="flex-1">
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                To
              </label>
              <input
                v-model="filters.endDate"
                type="date"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
              />
            </div>
          </div>

          <!-- Project Filter -->
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Project
            </label>
            <select
              v-model="filters.projectId"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            >
              <option value="">All Projects</option>
              <option v-for="project in projects" :key="project.id" :value="project.id">
                {{ project.name }}
              </option>
            </select>
          </div>

          <!-- Billable Filter -->
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Billable Status
            </label>
            <select
              v-model="filters.billable"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            >
              <option value="">All Entries</option>
              <option value="true">Billable Only</option>
              <option value="false">Non-Billable Only</option>
            </select>
          </div>
        </div>

        <div class="mt-4 flex justify-end gap-3">
          <button
            @click="clearFilters"
            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
          >
            Clear Filters
          </button>
          <button
            @click="applyFilters"
            class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600"
          >
            Apply Filters
          </button>
        </div>
      </div>

      <!-- Summary Cards -->
      <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Hours</p>
          <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">
            {{ totalHours.toFixed(2) }}h
          </p>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            {{ filteredEntries.length }} entries
          </p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Billable Hours</p>
          <p class="mt-1 text-3xl font-bold text-green-600 dark:text-green-400">
            {{ billableHours.toFixed(2) }}h
          </p>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            {{ billablePercentage }}% of total
          </p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Non-Billable Hours</p>
          <p class="mt-1 text-3xl font-bold text-gray-600 dark:text-gray-400">
            {{ nonBillableHours.toFixed(2) }}h
          </p>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            {{ 100 - billablePercentage }}% of total
          </p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Projects Worked</p>
          <p class="mt-1 text-3xl font-bold text-brand-600 dark:text-brand-400">
            {{ uniqueProjects }}
          </p>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            in selected period
          </p>
        </div>
      </div>

      <!-- Time Entries Table -->
      <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <!-- Table Header -->
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Time Entries</h2>
            <div class="flex items-center gap-3">
              <label class="flex items-center gap-2 text-sm">
                <span class="text-gray-600 dark:text-gray-400">Group by:</span>
                <select
                  v-model="groupBy"
                  class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                >
                  <option value="none">None</option>
                  <option value="date">Date</option>
                  <option value="project">Project</option>
                </select>
              </label>
            </div>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
          <div class="h-8 w-8 animate-spin rounded-full border-4 border-gray-300 border-t-brand-600 dark:border-gray-700 dark:border-t-brand-500"></div>
        </div>

        <!-- Grouped View -->
        <div v-else-if="groupBy !== 'none' && groupedEntries.length > 0">
          <div v-for="group in groupedEntries" :key="group.key" class="border-b border-gray-200 last:border-b-0 dark:border-gray-800">
            <!-- Group Header -->
            <div class="flex items-center justify-between bg-gray-50 px-6 py-3 dark:bg-gray-800/50">
              <div class="flex items-center gap-3">
                <h3 class="font-medium text-gray-900 dark:text-white">
                  {{ group.label }}
                </h3>
                <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                  {{ group.entries.length }} entries
                </span>
              </div>
              <span class="font-semibold text-gray-900 dark:text-white">
                {{ group.totalHours.toFixed(2) }}h
              </span>
            </div>
            <!-- Group Entries -->
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="entry in group.entries" :key="entry.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                  <td class="whitespace-nowrap px-6 py-4">
                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                      {{ entry.description || 'No description' }}
                    </div>
                    <div v-if="groupBy !== 'project'" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                      {{ entry.project_name || 'Unknown Project' }}
                    </div>
                  </td>
                  <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                    {{ entry.task_title || '—' }}
                  </td>
                  <td v-if="groupBy !== 'date'" class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                    {{ formatDate(entry.start_time || entry.created_at) }}
                  </td>
                  <td class="whitespace-nowrap px-6 py-4">
                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                      {{ parseFloat(entry.hours).toFixed(2) }}h
                    </span>
                  </td>
                  <td class="whitespace-nowrap px-6 py-4">
                    <button
                      @click="toggleBillable(entry)"
                      :class="[
                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium transition-colors',
                        entry.is_billable
                          ? 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/20 dark:text-green-400'
                          : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400'
                      ]"
                    >
                      {{ entry.is_billable ? 'Billable' : 'Non-billable' }}
                    </button>
                  </td>
                  <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                    <button
                      @click="editEntry(entry)"
                      class="mr-3 text-brand-600 hover:text-brand-800 dark:text-brand-400"
                    >
                      Edit
                    </button>
                    <button
                      @click="confirmDeleteEntry(entry)"
                      class="text-red-600 hover:text-red-800 dark:text-red-400"
                    >
                      Delete
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Flat View -->
        <div v-else-if="filteredEntries.length > 0" class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Description
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Project
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Task
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Date
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Hours
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Billable
                </th>
                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="entry in filteredEntries" :key="entry.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                <td class="whitespace-nowrap px-6 py-4">
                  <div class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ entry.description || 'No description' }}
                  </div>
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                  {{ entry.project_name || 'Unknown Project' }}
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                  {{ entry.task_title || '—' }}
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                  {{ formatDate(entry.start_time || entry.created_at) }}
                </td>
                <td class="whitespace-nowrap px-6 py-4">
                  <span class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ parseFloat(entry.hours).toFixed(2) }}h
                  </span>
                </td>
                <td class="whitespace-nowrap px-6 py-4">
                  <button
                    @click="toggleBillable(entry)"
                    :class="[
                      'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium transition-colors',
                      entry.is_billable
                        ? 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/20 dark:text-green-400'
                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400'
                    ]"
                  >
                    {{ entry.is_billable ? 'Billable' : 'Non-billable' }}
                  </button>
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                  <button
                    @click="editEntry(entry)"
                    class="mr-3 text-brand-600 hover:text-brand-800 dark:text-brand-400"
                  >
                    Edit
                  </button>
                  <button
                    @click="confirmDeleteEntry(entry)"
                    class="text-red-600 hover:text-red-800 dark:text-red-400"
                  >
                    Delete
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty State -->
        <div v-else class="py-12 text-center">
          <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No time entries found</p>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ hasActiveFilters ? 'Try adjusting your filters' : 'Start tracking time to see entries here' }}
          </p>
          <button
            v-if="hasActiveFilters"
            @click="clearFilters"
            class="mt-4 inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
          >
            Clear Filters
          </button>
        </div>
      </div>

      <!-- Manual Entry Modal -->
      <div v-if="showManualEntry || editingEntry" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-gray-900">
          <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
            {{ editingEntry ? 'Edit Time Entry' : 'Add Time Entry' }}
          </h3>

          <form @submit.prevent="saveEntry">
            <!-- Project -->
            <div class="mb-4">
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Project <span class="text-red-500">*</span>
              </label>
              <select
                v-model="entryForm.project_id"
                required
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
              >
                <option value="">Select a project</option>
                <option v-for="project in projects" :key="project.id" :value="project.id">
                  {{ project.name }}
                </option>
              </select>
            </div>

            <!-- Description -->
            <div class="mb-4">
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Description
              </label>
              <input
                v-model="entryForm.description"
                type="text"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                placeholder="What did you work on?"
              />
            </div>

            <!-- Date & Hours -->
            <div class="mb-4 grid grid-cols-2 gap-4">
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                  Date <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="entryForm.date"
                  type="date"
                  required
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                  Hours <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="entryForm.hours"
                  type="number"
                  step="0.25"
                  min="0.25"
                  required
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                  placeholder="0.00"
                />
              </div>
            </div>

            <!-- Billable -->
            <div class="mb-4">
              <label class="flex items-center gap-2">
                <input
                  v-model="entryForm.is_billable"
                  type="checkbox"
                  class="rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-700"
                />
                <span class="text-sm text-gray-700 dark:text-gray-300">Billable</span>
              </label>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3">
              <button
                type="button"
                @click="closeEntryModal"
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="savingEntry"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
              >
                {{ savingEntry ? 'Saving...' : 'Save Entry' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Delete Confirmation -->
      <div v-if="deletingEntry" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="mx-4 w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-gray-900">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Delete Entry</h3>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Are you sure you want to delete this time entry? This action cannot be undone.
          </p>
          <div class="mt-4 flex justify-end gap-3">
            <button
              @click="deletingEntry = null"
              class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
            >
              Cancel
            </button>
            <button
              @click="deleteEntry"
              class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
            >
              Delete
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useTimeTrackingStore } from '@/stores/timeTracking'
import { useProjectStore } from '@/stores/projects'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const timeStore = useTimeTrackingStore()
const projectStore = useProjectStore()

// State
const loading = ref(false)
const showFilters = ref(false)
const showManualEntry = ref(false)
const editingEntry = ref(null)
const deletingEntry = ref(null)
const savingEntry = ref(false)
const groupBy = ref('none')

const filters = reactive({
  dateRange: 'this_week',
  startDate: '',
  endDate: '',
  projectId: '',
  billable: ''
})

const entryForm = reactive({
  project_id: '',
  description: '',
  date: new Date().toISOString().split('T')[0],
  hours: '',
  is_billable: true
})

// Computed
const projects = computed(() => projectStore.projects)

const filteredEntries = computed(() => {
  let entries = [...timeStore.timeEntries]

  // Apply date filter
  const { start, end } = getDateRange()
  if (start && end) {
    entries = entries.filter(e => {
      const entryDate = new Date(e.start_time || e.created_at)
      return entryDate >= start && entryDate <= end
    })
  }

  // Apply project filter
  if (filters.projectId) {
    entries = entries.filter(e => e.project_id === filters.projectId)
  }

  // Apply billable filter
  if (filters.billable !== '') {
    const isBillable = filters.billable === 'true'
    entries = entries.filter(e => e.is_billable === isBillable)
  }

  // Sort by date descending
  entries.sort((a, b) => new Date(b.start_time || b.created_at) - new Date(a.start_time || a.created_at))

  return entries
})

const groupedEntries = computed(() => {
  if (groupBy.value === 'none') return []

  const groups = {}

  filteredEntries.value.forEach(entry => {
    let key, label

    if (groupBy.value === 'date') {
      const date = new Date(entry.start_time || entry.created_at)
      key = date.toISOString().split('T')[0]
      label = formatDateLabel(date)
    } else if (groupBy.value === 'project') {
      key = entry.project_id || 'unknown'
      label = entry.project_name || 'Unknown Project'
    }

    if (!groups[key]) {
      groups[key] = {
        key,
        label,
        entries: [],
        totalHours: 0
      }
    }

    groups[key].entries.push(entry)
    groups[key].totalHours += parseFloat(entry.hours || 0)
  })

  // Sort groups
  return Object.values(groups).sort((a, b) => {
    if (groupBy.value === 'date') {
      return b.key.localeCompare(a.key)
    }
    return a.label.localeCompare(b.label)
  })
})

const totalHours = computed(() => {
  return filteredEntries.value.reduce((sum, e) => sum + parseFloat(e.hours || 0), 0)
})

const billableHours = computed(() => {
  return filteredEntries.value
    .filter(e => e.is_billable)
    .reduce((sum, e) => sum + parseFloat(e.hours || 0), 0)
})

const nonBillableHours = computed(() => {
  return totalHours.value - billableHours.value
})

const billablePercentage = computed(() => {
  if (totalHours.value === 0) return 0
  return Math.round((billableHours.value / totalHours.value) * 100)
})

const uniqueProjects = computed(() => {
  const projectIds = new Set(filteredEntries.value.map(e => e.project_id))
  return projectIds.size
})

const hasActiveFilters = computed(() => {
  return filters.projectId || filters.billable !== '' || filters.dateRange === 'custom'
})

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.projectId) count++
  if (filters.billable !== '') count++
  if (filters.dateRange === 'custom') count++
  return count
})

// Methods
function getDateRange() {
  const now = new Date()
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  let start, end

  switch (filters.dateRange) {
    case 'today':
      start = today
      end = new Date(today.getTime() + 24 * 60 * 60 * 1000 - 1)
      break
    case 'this_week':
      const dayOfWeek = today.getDay()
      start = new Date(today.getTime() - dayOfWeek * 24 * 60 * 60 * 1000)
      end = new Date(start.getTime() + 7 * 24 * 60 * 60 * 1000 - 1)
      break
    case 'last_week':
      const lastWeekDay = today.getDay()
      start = new Date(today.getTime() - (lastWeekDay + 7) * 24 * 60 * 60 * 1000)
      end = new Date(start.getTime() + 7 * 24 * 60 * 60 * 1000 - 1)
      break
    case 'this_month':
      start = new Date(now.getFullYear(), now.getMonth(), 1)
      end = new Date(now.getFullYear(), now.getMonth() + 1, 0, 23, 59, 59)
      break
    case 'last_month':
      start = new Date(now.getFullYear(), now.getMonth() - 1, 1)
      end = new Date(now.getFullYear(), now.getMonth(), 0, 23, 59, 59)
      break
    case 'custom':
      if (filters.startDate) start = new Date(filters.startDate)
      if (filters.endDate) end = new Date(filters.endDate + 'T23:59:59')
      break
    default:
      start = null
      end = null
  }

  return { start, end }
}

function handleDateRangeChange() {
  if (filters.dateRange !== 'custom') {
    filters.startDate = ''
    filters.endDate = ''
  }
}

function clearFilters() {
  filters.dateRange = 'this_week'
  filters.startDate = ''
  filters.endDate = ''
  filters.projectId = ''
  filters.billable = ''
}

function applyFilters() {
  showFilters.value = false
  loadData()
}

async function loadData() {
  loading.value = true
  try {
    await Promise.all([
      timeStore.fetchTimeEntries(),
      projectStore.fetchProjects()
    ])
  } catch (error) {
    console.error('Failed to load data:', error)
  } finally {
    loading.value = false
  }
}

function formatDate(dateString) {
  if (!dateString) return '—'
  const date = new Date(dateString)
  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  }).format(date)
}

function formatDateLabel(date) {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const yesterday = new Date(today.getTime() - 24 * 60 * 60 * 1000)

  const dateOnly = new Date(date)
  dateOnly.setHours(0, 0, 0, 0)

  if (dateOnly.getTime() === today.getTime()) {
    return 'Today'
  } else if (dateOnly.getTime() === yesterday.getTime()) {
    return 'Yesterday'
  }

  return new Intl.DateTimeFormat('en-US', {
    weekday: 'long',
    month: 'short',
    day: 'numeric'
  }).format(date)
}

async function toggleBillable(entry) {
  try {
    await timeStore.toggleBillable(entry.id)
  } catch (error) {
    console.error('Failed to toggle billable:', error)
  }
}

function editEntry(entry) {
  editingEntry.value = entry
  entryForm.project_id = entry.project_id || ''
  entryForm.description = entry.description || ''
  entryForm.date = (entry.start_time || entry.created_at)?.split('T')[0] || new Date().toISOString().split('T')[0]
  entryForm.hours = entry.hours
  entryForm.is_billable = entry.is_billable
}

function confirmDeleteEntry(entry) {
  deletingEntry.value = entry
}

async function deleteEntry() {
  if (!deletingEntry.value) return

  try {
    await timeStore.deleteTimeEntry(deletingEntry.value.id)
    deletingEntry.value = null
  } catch (error) {
    console.error('Failed to delete entry:', error)
  }
}

function closeEntryModal() {
  showManualEntry.value = false
  editingEntry.value = null
  entryForm.project_id = ''
  entryForm.description = ''
  entryForm.date = new Date().toISOString().split('T')[0]
  entryForm.hours = ''
  entryForm.is_billable = true
}

async function saveEntry() {
  savingEntry.value = true
  try {
    const data = {
      project_id: entryForm.project_id,
      description: entryForm.description || null,
      hours: parseFloat(entryForm.hours),
      start_time: entryForm.date + 'T09:00:00',
      is_billable: entryForm.is_billable
    }

    if (editingEntry.value) {
      await timeStore.updateTimeEntry(editingEntry.value.id, data)
    } else {
      await timeStore.createTimeEntry(data)
    }

    closeEntryModal()
  } catch (error) {
    console.error('Failed to save entry:', error)
  } finally {
    savingEntry.value = false
  }
}

function exportTimesheet() {
  const headers = ['Date', 'Project', 'Task', 'Description', 'Hours', 'Billable']
  const rows = filteredEntries.value.map(e => [
    (e.start_time || e.created_at)?.split('T')[0] || '',
    e.project_name || '',
    e.task_title || '',
    e.description || '',
    parseFloat(e.hours).toFixed(2),
    e.is_billable ? 'Yes' : 'No'
  ])

  const csv = [headers, ...rows].map(row => row.map(cell => `"${cell}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `timesheet-${new Date().toISOString().split('T')[0]}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

// Lifecycle
onMounted(() => {
  loadData()
})
</script>
