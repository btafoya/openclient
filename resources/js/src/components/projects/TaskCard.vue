<template>
  <div
    class="group cursor-pointer rounded-lg border bg-white p-3 shadow-sm transition-all hover:shadow-md dark:bg-gray-900"
    :class="getBorderClasses()"
  >
    <!-- Header -->
    <div class="mb-2 flex items-start justify-between">
      <h4 class="text-sm font-medium text-gray-900 dark:text-white line-clamp-2">
        {{ task.title }}
      </h4>
      <div class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
        <button
          @click.stop="$emit('edit', task)"
          class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
          title="Edit"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
        </button>
        <button
          @click.stop="$emit('delete', task)"
          class="rounded p-1 text-gray-400 hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
          title="Delete"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Description -->
    <p v-if="task.description" class="mb-3 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">
      {{ task.description }}
    </p>

    <!-- Footer -->
    <div class="flex flex-wrap items-center gap-2">
      <!-- Priority Badge -->
      <span
        :class="getPriorityClasses()"
        class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium"
      >
        {{ task.priority || 'Medium' }}
      </span>

      <!-- Due Date -->
      <div v-if="task.due_date" class="flex items-center gap-1 text-xs" :class="getDueDateClasses()">
        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        {{ formatDate(task.due_date) }}
      </div>

      <!-- Estimated Hours -->
      <div v-if="task.estimated_hours" class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        {{ task.estimated_hours }}h
      </div>

      <!-- Spacer -->
      <div class="flex-1"></div>

      <!-- Assignee Avatar -->
      <div v-if="task.assigned_user_name" class="flex items-center gap-1">
        <div class="flex h-5 w-5 items-center justify-center rounded-full bg-brand-100 text-xs font-medium text-brand-700 dark:bg-brand-900/30 dark:text-brand-400">
          {{ getInitials(task.assigned_user_name) }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  task: {
    type: Object,
    required: true
  }
})

defineEmits(['edit', 'delete', 'status-change'])

function getBorderClasses() {
  const statusClasses = {
    todo: 'border-gray-200 dark:border-gray-700',
    in_progress: 'border-blue-200 dark:border-blue-800',
    blocked: 'border-red-200 dark:border-red-800',
    completed: 'border-green-200 dark:border-green-800'
  }
  return statusClasses[props.task.status] || statusClasses.todo
}

function getPriorityClasses() {
  const priorityClasses = {
    low: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
    medium: 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
    high: 'bg-orange-100 text-orange-700 dark:bg-orange-900/20 dark:text-orange-400',
    urgent: 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400'
  }
  return priorityClasses[props.task.priority?.toLowerCase()] || priorityClasses.medium
}

function getDueDateClasses() {
  if (!props.task.due_date) return 'text-gray-500 dark:text-gray-400'

  const dueDate = new Date(props.task.due_date)
  const today = new Date()
  today.setHours(0, 0, 0, 0)

  if (dueDate < today && props.task.status !== 'completed') {
    return 'text-red-600 dark:text-red-400'
  }

  const tomorrow = new Date(today)
  tomorrow.setDate(tomorrow.getDate() + 1)

  if (dueDate < tomorrow) {
    return 'text-orange-600 dark:text-orange-400'
  }

  return 'text-gray-500 dark:text-gray-400'
}

function formatDate(dateString) {
  if (!dateString) return ''
  const date = new Date(dateString)
  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric'
  }).format(date)
}

function getInitials(name) {
  if (!name) return '?'
  return name
    .split(' ')
    .map(part => part[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
}
</script>
