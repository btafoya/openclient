<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 p-4">
    <div class="w-full max-w-lg rounded-lg bg-white shadow-xl dark:bg-gray-900">
      <!-- Header -->
      <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-800">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
          {{ task ? 'Edit Task' : 'Create Task' }}
        </h2>
        <button
          @click="$emit('close')"
          class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Form -->
      <form @submit.prevent="handleSubmit" class="p-6">
        <!-- Title -->
        <div class="mb-4">
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Title <span class="text-red-500">*</span>
          </label>
          <input
            v-model="formData.title"
            type="text"
            required
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            placeholder="Task title"
          />
          <p v-if="errors.title" class="mt-1 text-xs text-red-600">{{ errors.title }}</p>
        </div>

        <!-- Description -->
        <div class="mb-4">
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Description
          </label>
          <textarea
            v-model="formData.description"
            rows="3"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            placeholder="Task description (optional)"
          ></textarea>
        </div>

        <!-- Status & Priority Row -->
        <div class="mb-4 grid grid-cols-2 gap-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Status
            </label>
            <select
              v-model="formData.status"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            >
              <option value="todo">To Do</option>
              <option value="in_progress">In Progress</option>
              <option value="blocked">Blocked</option>
              <option value="completed">Completed</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Priority
            </label>
            <select
              v-model="formData.priority"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            >
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
        </div>

        <!-- Due Date & Estimated Hours Row -->
        <div class="mb-4 grid grid-cols-2 gap-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Due Date
            </label>
            <input
              v-model="formData.due_date"
              type="date"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Estimated Hours
            </label>
            <input
              v-model="formData.estimated_hours"
              type="number"
              step="0.25"
              min="0"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
              placeholder="0.00"
            />
          </div>
        </div>

        <!-- Error Message -->
        <div v-if="submitError" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
          {{ submitError }}
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-3">
          <button
            type="button"
            @click="$emit('close')"
            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="submitting"
            class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50 dark:bg-brand-500 dark:hover:bg-brand-600"
          >
            <span v-if="submitting" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
            {{ task ? 'Save Changes' : 'Create Task' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useTaskStore } from '@/stores/tasks'

const props = defineProps({
  task: {
    type: Object,
    default: null
  },
  projectId: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['close', 'saved'])

const taskStore = useTaskStore()

// State
const formData = reactive({
  title: '',
  description: '',
  status: 'todo',
  priority: 'medium',
  due_date: '',
  estimated_hours: ''
})
const errors = reactive({})
const submitError = ref(null)
const submitting = ref(false)

// Methods
async function handleSubmit() {
  // Clear previous errors
  Object.keys(errors).forEach(key => delete errors[key])
  submitError.value = null

  // Validate
  if (!formData.title?.trim()) {
    errors.title = 'Title is required'
    return
  }

  submitting.value = true

  try {
    const data = {
      ...formData,
      project_id: props.projectId,
      estimated_hours: formData.estimated_hours ? parseFloat(formData.estimated_hours) : null,
      due_date: formData.due_date || null
    }

    if (props.task) {
      await taskStore.updateTask(props.task.id, data)
    } else {
      await taskStore.createTask(data)
    }

    emit('saved')
  } catch (error) {
    submitError.value = error.response?.data?.error || 'Failed to save task'
    console.error('Failed to save task:', error)
  } finally {
    submitting.value = false
  }
}

// Lifecycle
onMounted(() => {
  if (props.task) {
    formData.title = props.task.title || ''
    formData.description = props.task.description || ''
    formData.status = props.task.status || 'todo'
    formData.priority = props.task.priority || 'medium'
    formData.due_date = props.task.due_date ? props.task.due_date.split('T')[0] : ''
    formData.estimated_hours = props.task.estimated_hours || ''
  }
})
</script>
