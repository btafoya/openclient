import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

/**
 * Pipeline Store
 *
 * Manages pipeline state with RBAC-aware API calls.
 * Automatically filtered by agency via PostgreSQL RLS.
 */
export const usePipelineStore = defineStore('pipelines', () => {
  // State
  const pipelines = ref([])
  const currentPipeline = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const searchTerm = ref('')

  // Computed
  const activePipelines = computed(() =>
    pipelines.value.filter(pipeline => pipeline.is_active)
  )

  const filteredPipelines = computed(() => {
    let filtered = pipelines.value

    // Apply search filter
    if (searchTerm.value) {
      const term = searchTerm.value.toLowerCase()
      filtered = filtered.filter(pipeline =>
        pipeline.name?.toLowerCase().includes(term) ||
        pipeline.description?.toLowerCase().includes(term)
      )
    }

    return filtered
  })

  const pipelineCount = computed(() => pipelines.value.length)
  const activePipelineCount = computed(() => activePipelines.value.length)

  // Actions
  async function fetchPipelines(activeOnly = true) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/pipelines', {
        params: { active: activeOnly }
      })
      pipelines.value = response.data.data || []
      return pipelines.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch pipelines'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchPipeline(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/pipelines/${id}`)
      currentPipeline.value = response.data.data
      return currentPipeline.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch pipeline'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchPipelineStats(id) {
    try {
      const response = await axios.get(`/api/pipelines/${id}/stats`)
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch statistics'
      throw err
    }
  }

  async function createPipeline(pipelineData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post('/api/pipelines', pipelineData)
      const newPipeline = response.data.data
      pipelines.value.push(newPipeline)
      return newPipeline
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create pipeline'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updatePipeline(id, pipelineData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.put(`/api/pipelines/${id}`, pipelineData)
      const updatedPipeline = response.data.data

      // Update in list
      const index = pipelines.value.findIndex(p => p.id === id)
      if (index !== -1) {
        pipelines.value[index] = updatedPipeline
      }

      // Update current if viewing
      if (currentPipeline.value?.id === id) {
        currentPipeline.value = updatedPipeline
      }

      return updatedPipeline
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update pipeline'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deletePipeline(id) {
    loading.value = true
    error.value = null
    try {
      await axios.delete(`/api/pipelines/${id}`)

      // Remove from list
      pipelines.value = pipelines.value.filter(p => p.id !== id)

      // Clear current if deleted
      if (currentPipeline.value?.id === id) {
        currentPipeline.value = null
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to delete pipeline'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Stage management
  async function addStage(pipelineId, stageData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/pipelines/${pipelineId}/stages`, stageData)
      const newStage = response.data.data

      // Update current pipeline stages
      if (currentPipeline.value?.id === pipelineId) {
        if (!currentPipeline.value.stages) {
          currentPipeline.value.stages = []
        }
        currentPipeline.value.stages.push(newStage)
      }

      return newStage
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to add stage'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateStage(pipelineId, stageId, stageData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.put(`/api/pipelines/${pipelineId}/stages/${stageId}`, stageData)
      const updatedStage = response.data.data

      // Update in current pipeline
      if (currentPipeline.value?.id === pipelineId && currentPipeline.value.stages) {
        const index = currentPipeline.value.stages.findIndex(s => s.id === stageId)
        if (index !== -1) {
          currentPipeline.value.stages[index] = updatedStage
        }
      }

      return updatedStage
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update stage'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteStage(pipelineId, stageId) {
    loading.value = true
    error.value = null
    try {
      await axios.delete(`/api/pipelines/${pipelineId}/stages/${stageId}`)

      // Remove from current pipeline
      if (currentPipeline.value?.id === pipelineId && currentPipeline.value.stages) {
        currentPipeline.value.stages = currentPipeline.value.stages.filter(s => s.id !== stageId)
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to delete stage'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function reorderStages(pipelineId, order) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.put(`/api/pipelines/${pipelineId}/stages/reorder`, { order })
      const stages = response.data.data

      // Update in current pipeline
      if (currentPipeline.value?.id === pipelineId) {
        currentPipeline.value.stages = stages
      }

      return stages
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to reorder stages'
      throw err
    } finally {
      loading.value = false
    }
  }

  function setSearchTerm(term) {
    searchTerm.value = term
  }

  function clearError() {
    error.value = null
  }

  function reset() {
    pipelines.value = []
    currentPipeline.value = null
    loading.value = false
    error.value = null
    searchTerm.value = ''
  }

  return {
    // State
    pipelines,
    currentPipeline,
    loading,
    error,
    searchTerm,

    // Computed
    activePipelines,
    filteredPipelines,
    pipelineCount,
    activePipelineCount,

    // Actions
    fetchPipelines,
    fetchPipeline,
    fetchPipelineStats,
    createPipeline,
    updatePipeline,
    deletePipeline,
    addStage,
    updateStage,
    deleteStage,
    reorderStages,
    setSearchTerm,
    clearError,
    reset
  }
})
