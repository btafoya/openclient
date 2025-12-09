import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

/**
 * Task Store
 *
 * Manages task state with Kanban board support.
 * Handles drag-and-drop, status updates, and task ordering.
 */
export const useTaskStore = defineStore('tasks', () => {
  // State
  const tasks = ref([])
  const currentTask = ref(null)
  const kanbanTasks = ref({
    todo: [],
    in_progress: [],
    completed: [],
    blocked: []
  })
  const loading = ref(false)
  const error = ref(null)
  const searchTerm = ref('')
  const statusFilter = ref('all')

  // Computed
  const activeTasks = computed(() =>
    tasks.value.filter(task => task.is_active)
  )

  const filteredTasks = computed(() => {
    let filtered = tasks.value

    // Apply search filter
    if (searchTerm.value) {
      const term = searchTerm.value.toLowerCase()
      filtered = filtered.filter(task =>
        task.title?.toLowerCase().includes(term) ||
        task.description?.toLowerCase().includes(term)
      )
    }

    // Apply status filter
    if (statusFilter.value && statusFilter.value !== 'all') {
      filtered = filtered.filter(task => task.status === statusFilter.value)
    }

    return filtered
  })

  const overdueTasks = computed(() => {
    const now = new Date()
    return tasks.value.filter(task => {
      if (!task.due_date || task.status === 'completed') return false
      const dueDate = new Date(task.due_date)
      return dueDate < now
    })
  })

  const taskCount = computed(() => tasks.value.length)
  const overdueCount = computed(() => overdueTasks.value.length)

  // Actions
  async function fetchTasks(projectId = null, activeOnly = true) {
    loading.value = true
    error.value = null
    try {
      const params = { active: activeOnly }
      if (projectId) params.project_id = projectId

      const response = await axios.get('/api/tasks', { params })
      tasks.value = response.data.data || []
      return tasks.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch tasks'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchKanbanBoard(projectId, activeOnly = true) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/projects/${projectId}/kanban`, {
        params: { active: activeOnly }
      })
      kanbanTasks.value = response.data.data || {
        todo: [],
        in_progress: [],
        completed: [],
        blocked: []
      }
      return kanbanTasks.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch kanban board'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchTask(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/tasks/${id}`)
      currentTask.value = response.data.data
      return currentTask.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch task'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchOverdueTasks(projectId = null) {
    try {
      const params = projectId ? { project_id: projectId } : {}
      const response = await axios.get('/api/tasks/overdue', { params })
      return response.data.data || []
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch overdue tasks'
      throw err
    }
  }

  async function createTask(taskData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post('/api/tasks', taskData)
      const newTask = response.data.data
      tasks.value.push(newTask)

      // Add to kanban if applicable
      if (kanbanTasks.value[newTask.status]) {
        kanbanTasks.value[newTask.status].push(newTask)
      }

      return newTask
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create task'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateTask(id, taskData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.put(`/api/tasks/${id}`, taskData)
      const updatedTask = response.data.data

      // Update in list
      const index = tasks.value.findIndex(t => t.id === id)
      if (index !== -1) {
        tasks.value[index] = updatedTask
      }

      // Update current if viewing
      if (currentTask.value?.id === id) {
        currentTask.value = updatedTask
      }

      // Update in kanban
      updateKanbanTask(updatedTask)

      return updatedTask
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update task'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateTaskStatus(id, status) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.patch(`/api/tasks/${id}/status`, { status })
      const updatedTask = response.data.data

      const index = tasks.value.findIndex(t => t.id === id)
      if (index !== -1) {
        tasks.value[index] = updatedTask
      }

      if (currentTask.value?.id === id) {
        currentTask.value = updatedTask
      }

      updateKanbanTask(updatedTask)

      return updatedTask
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update task status'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function assignTask(id, userId) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.patch(`/api/tasks/${id}/assign`, { user_id: userId })
      const updatedTask = response.data.data

      const index = tasks.value.findIndex(t => t.id === id)
      if (index !== -1) {
        tasks.value[index] = updatedTask
      }

      if (currentTask.value?.id === id) {
        currentTask.value = updatedTask
      }

      updateKanbanTask(updatedTask)

      return updatedTask
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to assign task'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateTaskSortOrder(id, newOrder, newStatus = null) {
    try {
      const payload = { sort_order: newOrder }
      if (newStatus) payload.status = newStatus

      const response = await axios.patch(`/api/tasks/${id}/sort-order`, payload)
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update task order'
      throw err
    }
  }

  async function reorderTasks(projectId, status, taskIds) {
    try {
      await axios.post(`/api/projects/${projectId}/tasks/reorder`, {
        status,
        task_ids: taskIds
      })
      return true
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to reorder tasks'
      throw err
    }
  }

  async function deleteTask(id) {
    loading.value = true
    error.value = null
    try {
      await axios.delete(`/api/tasks/${id}`)

      // Remove from list
      tasks.value = tasks.value.filter(t => t.id !== id)

      // Remove from kanban
      Object.keys(kanbanTasks.value).forEach(status => {
        kanbanTasks.value[status] = kanbanTasks.value[status].filter(t => t.id !== id)
      })

      // Clear current if deleted
      if (currentTask.value?.id === id) {
        currentTask.value = null
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to delete task'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function toggleActive(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/tasks/${id}/toggle-active`)
      const updatedTask = response.data.data

      const index = tasks.value.findIndex(t => t.id === id)
      if (index !== -1) {
        tasks.value[index] = updatedTask
      }

      if (currentTask.value?.id === id) {
        currentTask.value = updatedTask
      }

      return updatedTask
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to toggle task status'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Helper function to update task in kanban board
  function updateKanbanTask(task) {
    // Remove from all columns
    Object.keys(kanbanTasks.value).forEach(status => {
      kanbanTasks.value[status] = kanbanTasks.value[status].filter(t => t.id !== task.id)
    })

    // Add to new column
    if (kanbanTasks.value[task.status]) {
      kanbanTasks.value[task.status].push(task)
    }
  }

  function setSearchTerm(term) {
    searchTerm.value = term
  }

  function setStatusFilter(status) {
    statusFilter.value = status
  }

  function clearError() {
    error.value = null
  }

  function reset() {
    tasks.value = []
    currentTask.value = null
    kanbanTasks.value = {
      todo: [],
      in_progress: [],
      completed: [],
      blocked: []
    }
    loading.value = false
    error.value = null
    searchTerm.value = ''
    statusFilter.value = 'all'
  }

  return {
    // State
    tasks,
    currentTask,
    kanbanTasks,
    loading,
    error,
    searchTerm,
    statusFilter,

    // Computed
    activeTasks,
    filteredTasks,
    overdueTasks,
    taskCount,
    overdueCount,

    // Actions
    fetchTasks,
    fetchKanbanBoard,
    fetchTask,
    fetchOverdueTasks,
    createTask,
    updateTask,
    updateTaskStatus,
    assignTask,
    updateTaskSortOrder,
    reorderTasks,
    deleteTask,
    toggleActive,
    updateKanbanTask,
    setSearchTerm,
    setStatusFilter,
    clearError,
    reset
  }
})
