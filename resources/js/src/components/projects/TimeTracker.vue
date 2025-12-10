<template>
  <div class="time-tracker">
    <!-- Timer Widget -->
    <div class="mb-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <div class="flex flex-col items-center gap-6 sm:flex-row sm:justify-between">
        <!-- Timer Display -->
        <div class="flex items-center gap-4">
          <div class="relative">
            <div
              :class="[
                'flex h-24 w-24 items-center justify-center rounded-full border-4 transition-all',
                isTimerRunning
                  ? 'border-green-500 bg-green-50 dark:bg-green-900/20'
                  : 'border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-800'
              ]"
            >
              <span
                :class="[
                  'font-mono text-xl font-bold',
                  isTimerRunning
                    ? 'text-green-600 dark:text-green-400'
                    : 'text-gray-700 dark:text-gray-300'
                ]"
              >
                {{ formattedElapsedTime }}
              </span>
            </div>
            <div v-if="isTimerRunning" class="absolute -right-1 -top-1">
              <span class="relative flex h-4 w-4">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                <span class="relative inline-flex h-4 w-4 rounded-full bg-green-500"></span>
              </span>
            </div>
          </div>

          <div class="text-left">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ isTimerRunning ? 'Timer Running' : 'Timer Stopped' }}
            </p>
            <p v-if="runningTimer?.description" class="text-xs text-gray-500 dark:text-gray-400">
              {{ runningTimer.description }}
            </p>
            <p v-if="runningTimer?.task_title" class="text-xs text-brand-600 dark:text-brand-400">
              Task: {{ runningTimer.task_title }}
            </p>
          </div>
        </div>

        <!-- Timer Controls -->
        <div class="flex flex-col gap-3 sm:flex-row">
          <div v-if="!isTimerRunning" class="flex gap-2">
            <input
              v-model="timerDescription"
              type="text"
              placeholder="What are you working on?"
              class="w-64 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <button
              @click="startTimer"
              :disabled="loading"
              class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
            >
              <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M8 5v14l11-7z" />
              </svg>
              Start
            </button>
          </div>
          <button
            v-else
            @click="stopTimer"
            :disabled="loading"
            class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-6 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
          >
            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
              <path d="M6 6h12v12H6z" />
            </svg>
            Stop Timer
          </button>
        </div>
      </div>
    </div>

    <!-- Quick Stats -->
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
      <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Today's Hours</p>
        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
          {{ todayHours.toFixed(2) }}h
        </p>
      </div>
      <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">This Week</p>
        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
          {{ weekHours.toFixed(2) }}h
        </p>
      </div>
      <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Billable Hours</p>
        <p class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">
          {{ billableHours.toFixed(2) }}h
        </p>
      </div>
    </div>

    <!-- Time Entries List -->
    <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Time Entries</h3>
        <button
          @click="showManualEntry = true"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Add Manual Entry
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="entriesLoading" class="flex items-center justify-center py-8">
        <div class="h-8 w-8 animate-spin rounded-full border-4 border-gray-300 border-t-brand-600 dark:border-gray-700 dark:border-t-brand-500"></div>
      </div>

      <!-- Entries Table -->
      <div v-else-if="timeEntries.length > 0" class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Description
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Task
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Date
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Hours
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Billable
              </th>
              <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="entry in timeEntries" :key="entry.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
              <td class="whitespace-nowrap px-4 py-3">
                <div class="text-sm text-gray-900 dark:text-white">
                  {{ entry.description || 'No description' }}
                </div>
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                {{ entry.task_title || '—' }}
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                {{ formatDate(entry.start_time || entry.created_at) }}
              </td>
              <td class="whitespace-nowrap px-4 py-3">
                <span class="text-sm font-medium text-gray-900 dark:text-white">
                  {{ parseFloat(entry.hours).toFixed(2) }}h
                </span>
              </td>
              <td class="whitespace-nowrap px-4 py-3">
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
              <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                <button
                  @click="editEntry(entry)"
                  class="mr-2 text-brand-600 hover:text-brand-800 dark:text-brand-400"
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
      <div v-else class="py-8 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No time entries yet</p>
        <p class="text-xs text-gray-400 dark:text-gray-500">Start the timer or add a manual entry</p>
      </div>
    </div>

    <!-- Manual Entry Modal -->
    <div v-if="showManualEntry || editingEntry" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-gray-900">
        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
          {{ editingEntry ? 'Edit Time Entry' : 'Add Manual Entry' }}
        </h3>

        <form @submit.prevent="saveManualEntry">
          <div class="mb-4">
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Description
            </label>
            <input
              v-model="entryForm.description"
              type="text"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
              placeholder="What did you work on?"
            />
          </div>

          <div class="mb-4 grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Date
              </label>
              <input
                v-model="entryForm.date"
                type="date"
                required
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
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
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                placeholder="0.00"
              />
            </div>
          </div>

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

          <div class="flex justify-end gap-3">
            <button
              type="button"
              @click="closeManualEntry"
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
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { useTimeTrackingStore } from '@/stores/timeTracking'

const props = defineProps({
  projectId: {
    type: String,
    required: true
  }
})

const timeStore = useTimeTrackingStore()

// State
const timerDescription = ref('')
const showManualEntry = ref(false)
const editingEntry = ref(null)
const deletingEntry = ref(null)
const savingEntry = ref(false)
const entriesLoading = ref(false)

const entryForm = reactive({
  description: '',
  date: new Date().toISOString().split('T')[0],
  hours: '',
  is_billable: true
})

// Computed
const loading = computed(() => timeStore.loading)
const isTimerRunning = computed(() => timeStore.isTimerRunning)
const runningTimer = computed(() => timeStore.runningTimer)
const formattedElapsedTime = computed(() => timeStore.formattedElapsedTime)
const timeEntries = computed(() => timeStore.timeEntries)
const billableHours = computed(() => timeStore.billableHours)

const todayHours = computed(() => {
  const today = new Date().toISOString().split('T')[0]
  return timeStore.timeEntries
    .filter(e => (e.start_time || e.created_at)?.startsWith(today))
    .reduce((sum, e) => sum + parseFloat(e.hours || 0), 0)
})

const weekHours = computed(() => {
  const now = new Date()
  const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000)
  return timeStore.timeEntries
    .filter(e => new Date(e.start_time || e.created_at) >= weekAgo)
    .reduce((sum, e) => sum + parseFloat(e.hours || 0), 0)
})

// Methods
async function loadData() {
  entriesLoading.value = true
  try {
    await Promise.all([
      timeStore.fetchTimeEntries({ project_id: props.projectId }),
      timeStore.checkRunningTimer()
    ])
  } catch (error) {
    console.error('Failed to load time tracking data:', error)
  } finally {
    entriesLoading.value = false
  }
}

async function startTimer() {
  try {
    await timeStore.startTimer(props.projectId, null, timerDescription.value || null)
    timerDescription.value = ''
  } catch (error) {
    console.error('Failed to start timer:', error)
  }
}

async function stopTimer() {
  try {
    await timeStore.stopTimer()
  } catch (error) {
    console.error('Failed to stop timer:', error)
  }
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

function closeManualEntry() {
  showManualEntry.value = false
  editingEntry.value = null
  entryForm.description = ''
  entryForm.date = new Date().toISOString().split('T')[0]
  entryForm.hours = ''
  entryForm.is_billable = true
}

async function saveManualEntry() {
  savingEntry.value = true
  try {
    const data = {
      project_id: props.projectId,
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

    closeManualEntry()
  } catch (error) {
    console.error('Failed to save entry:', error)
  } finally {
    savingEntry.value = false
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

// Lifecycle
onMounted(() => {
  loadData()
})

onUnmounted(() => {
  // Don't reset timer state when component unmounts
})
</script>
