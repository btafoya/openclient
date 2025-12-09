import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

/**
 * Project Store
 *
 * Manages project state with RBAC-aware API calls.
 * Automatically filtered by agency via PostgreSQL RLS.
 */
export const useProjectStore = defineStore('projects', () => {
  // State
  const projects = ref([])
  const currentProject = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const searchTerm = ref('')
  const statusFilter = ref('all')

  // Computed
  const activeProjects = computed(() =>
    projects.value.filter(project => project.is_active)
  )

  const filteredProjects = computed(() => {
    let filtered = projects.value

    // Apply search filter
    if (searchTerm.value) {
      const term = searchTerm.value.toLowerCase()
      filtered = filtered.filter(project =>
        project.name?.toLowerCase().includes(term) ||
        project.description?.toLowerCase().includes(term) ||
        project.client_name?.toLowerCase().includes(term)
      )
    }

    // Apply status filter
    if (statusFilter.value && statusFilter.value !== 'all') {
      filtered = filtered.filter(project => project.status === statusFilter.value)
    }

    return filtered
  })

  const projectCount = computed(() => projects.value.length)
  const activeProjectCount = computed(() => activeProjects.value.length)

  // Statistics
  const projectStats = computed(() => {
    const stats = {
      total: projects.value.length,
      active: 0,
      planning: 0,
      in_progress: 0,
      on_hold: 0,
      completed: 0,
      total_budget: 0,
      total_hours: 0
    }

    projects.value.forEach(project => {
      if (project.is_active) stats.active++

      switch (project.status) {
        case 'planning':
          stats.planning++
          break
        case 'in_progress':
          stats.in_progress++
          break
        case 'on_hold':
          stats.on_hold++
          break
        case 'completed':
          stats.completed++
          break
      }

      if (project.budget) stats.total_budget += parseFloat(project.budget)
      if (project.total_hours) stats.total_hours += parseFloat(project.total_hours)
    })

    return stats
  })

  // Actions
  async function fetchProjects(activeOnly = true) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/projects', {
        params: { active: activeOnly }
      })
      projects.value = response.data.data || []
      return projects.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch projects'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchProject(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/projects/${id}`)
      currentProject.value = response.data.data
      return currentProject.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch project'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchProjectStats() {
    try {
      const response = await axios.get('/api/projects/stats')
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch statistics'
      throw err
    }
  }

  async function createProject(projectData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post('/api/projects', projectData)
      const newProject = response.data.data
      projects.value.push(newProject)
      return newProject
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create project'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateProject(id, projectData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.put(`/api/projects/${id}`, projectData)
      const updatedProject = response.data.data

      // Update in list
      const index = projects.value.findIndex(p => p.id === id)
      if (index !== -1) {
        projects.value[index] = updatedProject
      }

      // Update current if viewing
      if (currentProject.value?.id === id) {
        currentProject.value = updatedProject
      }

      return updatedProject
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update project'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateProjectStatus(id, status) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.patch(`/api/projects/${id}/status`, { status })
      const updatedProject = response.data.data

      const index = projects.value.findIndex(p => p.id === id)
      if (index !== -1) {
        projects.value[index] = updatedProject
      }

      if (currentProject.value?.id === id) {
        currentProject.value = updatedProject
      }

      return updatedProject
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update project status'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteProject(id) {
    loading.value = true
    error.value = null
    try {
      await axios.delete(`/api/projects/${id}`)

      // Remove from list
      projects.value = projects.value.filter(p => p.id !== id)

      // Clear current if deleted
      if (currentProject.value?.id === id) {
        currentProject.value = null
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to delete project'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function toggleActive(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/projects/${id}/toggle-active`)
      const updatedProject = response.data.data

      const index = projects.value.findIndex(p => p.id === id)
      if (index !== -1) {
        projects.value[index] = updatedProject
      }

      if (currentProject.value?.id === id) {
        currentProject.value = updatedProject
      }

      return updatedProject
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to toggle project status'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function searchProjects(term, activeOnly = true) {
    if (!term) {
      return fetchProjects(activeOnly)
    }

    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/projects', {
        params: { search: term, active: activeOnly }
      })
      projects.value = response.data.data || []
      return projects.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to search projects'
      throw err
    } finally {
      loading.value = false
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
    projects.value = []
    currentProject.value = null
    loading.value = false
    error.value = null
    searchTerm.value = ''
    statusFilter.value = 'all'
  }

  return {
    // State
    projects,
    currentProject,
    loading,
    error,
    searchTerm,
    statusFilter,

    // Computed
    activeProjects,
    filteredProjects,
    projectCount,
    activeProjectCount,
    projectStats,

    // Actions
    fetchProjects,
    fetchProject,
    fetchProjectStats,
    createProject,
    updateProject,
    updateProjectStatus,
    deleteProject,
    toggleActive,
    searchProjects,
    setSearchTerm,
    setStatusFilter,
    clearError,
    reset
  }
})
