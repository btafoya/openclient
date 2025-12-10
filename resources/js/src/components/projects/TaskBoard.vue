<template>
  <div class="task-board">
    <!-- Header with Actions -->
    <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-4">
        <!-- Search -->
        <div class="relative">
          <svg
            class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <input
            v-model="searchTerm"
            type="text"
            placeholder="Search tasks..."
            class="w-64 rounded-lg border border-gray-300 bg-white py-2 pl-10 pr-4 text-sm text-gray-900 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
          />
        </div>

        <!-- View Toggle -->
        <div class="flex rounded-lg border border-gray-300 dark:border-gray-700">
          <button
            @click="viewMode = 'kanban'"
            :class="[
              'px-3 py-1.5 text-sm font-medium transition-colors',
              viewMode === 'kanban'
                ? 'bg-brand-600 text-white'
                : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
            ]"
          >
            Board
          </button>
          <button
            @click="viewMode = 'list'"
            :class="[
              'px-3 py-1.5 text-sm font-medium transition-colors',
              viewMode === 'list'
                ? 'bg-brand-600 text-white'
                : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
            ]"
          >
            List
          </button>
        </div>
      </div>

      <button
        @click="showCreateModal = true"
        class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        New Task
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="h-8 w-8 animate-spin rounded-full border-4 border-gray-300 border-t-brand-600 dark:border-gray-700 dark:border-t-brand-500"></div>
    </div>

    <!-- Kanban Board View -->
    <div v-else-if="viewMode === 'kanban'" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <!-- Todo Column -->
      <div class="flex flex-col rounded-lg bg-gray-100 dark:bg-gray-800/50">
        <div class="flex items-center justify-between border-b border-gray-200 p-3 dark:border-gray-700">
          <div class="flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-gray-400"></span>
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">To Do</h3>
            <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400">
              {{ filteredKanban.todo.length }}
            </span>
          </div>
        </div>
        <div
          class="flex-1 space-y-2 p-2 min-h-[200px]"
          @dragover.prevent
          @drop="handleDrop('todo', $event)"
        >
          <TaskCard
            v-for="task in filteredKanban.todo"
            :key="task.id"
            :task="task"
            @edit="editTask"
            @delete="confirmDelete"
            @status-change="updateStatus"
            draggable="true"
            @dragstart="handleDragStart(task, $event)"
          />
          <div v-if="filteredKanban.todo.length === 0" class="py-4 text-center text-sm text-gray-400">
            No tasks
          </div>
        </div>
      </div>

      <!-- In Progress Column -->
      <div class="flex flex-col rounded-lg bg-blue-50 dark:bg-blue-900/10">
        <div class="flex items-center justify-between border-b border-blue-200 p-3 dark:border-blue-800">
          <div class="flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-blue-500"></span>
            <h3 class="text-sm font-semibold text-blue-700 dark:text-blue-300">In Progress</h3>
            <span class="rounded-full bg-blue-200 px-2 py-0.5 text-xs font-medium text-blue-600 dark:bg-blue-800 dark:text-blue-300">
              {{ filteredKanban.in_progress.length }}
            </span>
          </div>
        </div>
        <div
          class="flex-1 space-y-2 p-2 min-h-[200px]"
          @dragover.prevent
          @drop="handleDrop('in_progress', $event)"
        >
          <TaskCard
            v-for="task in filteredKanban.in_progress"
            :key="task.id"
            :task="task"
            @edit="editTask"
            @delete="confirmDelete"
            @status-change="updateStatus"
            draggable="true"
            @dragstart="handleDragStart(task, $event)"
          />
          <div v-if="filteredKanban.in_progress.length === 0" class="py-4 text-center text-sm text-gray-400">
            No tasks
          </div>
        </div>
      </div>

      <!-- Blocked Column -->
      <div class="flex flex-col rounded-lg bg-red-50 dark:bg-red-900/10">
        <div class="flex items-center justify-between border-b border-red-200 p-3 dark:border-red-800">
          <div class="flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-red-500"></span>
            <h3 class="text-sm font-semibold text-red-700 dark:text-red-300">Blocked</h3>
            <span class="rounded-full bg-red-200 px-2 py-0.5 text-xs font-medium text-red-600 dark:bg-red-800 dark:text-red-300">
              {{ filteredKanban.blocked.length }}
            </span>
          </div>
        </div>
        <div
          class="flex-1 space-y-2 p-2 min-h-[200px]"
          @dragover.prevent
          @drop="handleDrop('blocked', $event)"
        >
          <TaskCard
            v-for="task in filteredKanban.blocked"
            :key="task.id"
            :task="task"
            @edit="editTask"
            @delete="confirmDelete"
            @status-change="updateStatus"
            draggable="true"
            @dragstart="handleDragStart(task, $event)"
          />
          <div v-if="filteredKanban.blocked.length === 0" class="py-4 text-center text-sm text-gray-400">
            No tasks
          </div>
        </div>
      </div>

      <!-- Completed Column -->
      <div class="flex flex-col rounded-lg bg-green-50 dark:bg-green-900/10">
        <div class="flex items-center justify-between border-b border-green-200 p-3 dark:border-green-800">
          <div class="flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-green-500"></span>
            <h3 class="text-sm font-semibold text-green-700 dark:text-green-300">Completed</h3>
            <span class="rounded-full bg-green-200 px-2 py-0.5 text-xs font-medium text-green-600 dark:bg-green-800 dark:text-green-300">
              {{ filteredKanban.completed.length }}
            </span>
          </div>
        </div>
        <div
          class="flex-1 space-y-2 p-2 min-h-[200px]"
          @dragover.prevent
          @drop="handleDrop('completed', $event)"
        >
          <TaskCard
            v-for="task in filteredKanban.completed"
            :key="task.id"
            :task="task"
            @edit="editTask"
            @delete="confirmDelete"
            @status-change="updateStatus"
            draggable="true"
            @dragstart="handleDragStart(task, $event)"
          />
          <div v-if="filteredKanban.completed.length === 0" class="py-4 text-center text-sm text-gray-400">
            No tasks
          </div>
        </div>
      </div>
    </div>

    <!-- List View -->
    <div v-else class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
      <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
              Task
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
              Status
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
              Priority
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
              Assignee
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
              Due Date
            </th>
            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          <tr v-for="task in allFilteredTasks" :key="task.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
            <td class="whitespace-nowrap px-4 py-3">
              <div class="text-sm font-medium text-gray-900 dark:text-white">{{ task.title }}</div>
              <div v-if="task.description" class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs">
                {{ task.description }}
              </div>
            </td>
            <td class="whitespace-nowrap px-4 py-3">
              <select
                :value="task.status"
                @change="handleStatusChange(task.id, $event)"
                class="rounded border-gray-300 text-xs dark:border-gray-700 dark:bg-gray-800"
              >
                <option value="todo">To Do</option>
                <option value="in_progress">In Progress</option>
                <option value="blocked">Blocked</option>
                <option value="completed">Completed</option>
              </select>
            </td>
            <td class="whitespace-nowrap px-4 py-3">
              <span :class="getPriorityClasses(task.priority)" class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium">
                {{ task.priority || 'Medium' }}
              </span>
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
              {{ task.assigned_user_name || 'Unassigned' }}
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
              {{ task.due_date ? formatDate(task.due_date) : '—' }}
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
              <button
                @click="editTask(task)"
                class="mr-2 text-brand-600 hover:text-brand-800 dark:text-brand-400"
              >
                Edit
              </button>
              <button
                @click="confirmDelete(task)"
                class="text-red-600 hover:text-red-800 dark:text-red-400"
              >
                Delete
              </button>
            </td>
          </tr>
          <tr v-if="allFilteredTasks.length === 0">
            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
              No tasks found
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create/Edit Modal -->
    <TaskModal
      v-if="showCreateModal || editingTask"
      :task="editingTask"
      :project-id="projectId"
      @close="closeModal"
      @saved="handleTaskSaved"
    />

    <!-- Delete Confirmation Modal -->
    <div v-if="deletingTask" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div class="mx-4 w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-gray-900">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Delete Task</h3>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          Are you sure you want to delete "{{ deletingTask.title }}"? This action cannot be undone.
        </p>
        <div class="mt-4 flex justify-end gap-3">
          <button
            @click="deletingTask = null"
            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
          >
            Cancel
          </button>
          <button
            @click="deleteTask"
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
import { ref, computed, onMounted, watch } from 'vue'
import { useTaskStore } from '@/stores/tasks'
import TaskCard from './TaskCard.vue'
import TaskModal from './TaskModal.vue'

const props = defineProps({
  projectId: {
    type: String,
    required: true
  }
})

const taskStore = useTaskStore()

// State
const searchTerm = ref('')
const viewMode = ref('kanban')
const showCreateModal = ref(false)
const editingTask = ref(null)
const deletingTask = ref(null)
const draggedTask = ref(null)

// Computed
const loading = computed(() => taskStore.loading)

const filteredKanban = computed(() => {
  const term = searchTerm.value.toLowerCase()
  const filterTasks = (tasks) => {
    if (!term) return tasks
    return tasks.filter(task =>
      task.title?.toLowerCase().includes(term) ||
      task.description?.toLowerCase().includes(term)
    )
  }

  return {
    todo: filterTasks(taskStore.kanbanTasks.todo || []),
    in_progress: filterTasks(taskStore.kanbanTasks.in_progress || []),
    blocked: filterTasks(taskStore.kanbanTasks.blocked || []),
    completed: filterTasks(taskStore.kanbanTasks.completed || [])
  }
})

const allFilteredTasks = computed(() => {
  const allTasks = [
    ...taskStore.kanbanTasks.todo,
    ...taskStore.kanbanTasks.in_progress,
    ...taskStore.kanbanTasks.blocked,
    ...taskStore.kanbanTasks.completed
  ]

  if (!searchTerm.value) return allTasks

  const term = searchTerm.value.toLowerCase()
  return allTasks.filter(task =>
    task.title?.toLowerCase().includes(term) ||
    task.description?.toLowerCase().includes(term)
  )
})

// Methods
async function loadTasks() {
  await taskStore.fetchKanbanBoard(props.projectId)
}

function handleDragStart(task, event) {
  draggedTask.value = task
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', task.id)
}

async function handleDrop(newStatus, event) {
  event.preventDefault()
  if (!draggedTask.value || draggedTask.value.status === newStatus) {
    draggedTask.value = null
    return
  }

  try {
    await taskStore.updateTaskStatus(draggedTask.value.id, newStatus)
    await loadTasks()
  } catch (error) {
    console.error('Failed to update task status:', error)
  } finally {
    draggedTask.value = null
  }
}

async function updateStatus(taskId, newStatus) {
  try {
    await taskStore.updateTaskStatus(taskId, newStatus)
    await loadTasks()
  } catch (error) {
    console.error('Failed to update task status:', error)
  }
}

function handleStatusChange(taskId, event) {
  const newStatus = event.target.value
  updateStatus(taskId, newStatus)
}

function editTask(task) {
  editingTask.value = task
}

function confirmDelete(task) {
  deletingTask.value = task
}

async function deleteTask() {
  if (!deletingTask.value) return

  try {
    await taskStore.deleteTask(deletingTask.value.id)
    deletingTask.value = null
  } catch (error) {
    console.error('Failed to delete task:', error)
  }
}

function closeModal() {
  showCreateModal.value = false
  editingTask.value = null
}

async function handleTaskSaved() {
  closeModal()
  await loadTasks()
}

function formatDate(dateString) {
  if (!dateString) return '—'
  const date = new Date(dateString)
  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric'
  }).format(date)
}

function getPriorityClasses(priority) {
  const classes = {
    low: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
    medium: 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
    high: 'bg-orange-100 text-orange-700 dark:bg-orange-900/20 dark:text-orange-400',
    urgent: 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400'
  }
  return classes[priority?.toLowerCase()] || classes.medium
}

// Lifecycle
onMounted(() => {
  loadTasks()
})

watch(() => props.projectId, () => {
  loadTasks()
})
</script>
