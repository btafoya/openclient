import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

/**
 * Deal Store
 *
 * Manages deal state with RBAC-aware API calls.
 * Supports Kanban board operations and deal lifecycle management.
 * Automatically filtered by agency via PostgreSQL RLS.
 */
export const useDealStore = defineStore('deals', () => {
  // State
  const deals = ref([])
  const currentDeal = ref(null)
  const kanbanBoard = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const searchTerm = ref('')
  const pipelineFilter = ref(null)
  const statusFilter = ref('all') // all, active, won, lost

  // Computed
  const activeDeals = computed(() =>
    deals.value.filter(deal => deal.is_active)
  )

  const filteredDeals = computed(() => {
    let filtered = deals.value

    // Apply search filter
    if (searchTerm.value) {
      const term = searchTerm.value.toLowerCase()
      filtered = filtered.filter(deal =>
        deal.name?.toLowerCase().includes(term) ||
        deal.description?.toLowerCase().includes(term) ||
        deal.client_name?.toLowerCase().includes(term)
      )
    }

    // Apply pipeline filter
    if (pipelineFilter.value) {
      filtered = filtered.filter(deal => deal.pipeline_id === pipelineFilter.value)
    }

    return filtered
  })

  const dealCount = computed(() => deals.value.length)
  const activeDealCount = computed(() => activeDeals.value.length)

  // Statistics
  const dealStats = computed(() => {
    const stats = {
      total: deals.value.length,
      active: 0,
      won: 0,
      lost: 0,
      total_value: 0,
      weighted_value: 0
    }

    deals.value.forEach(deal => {
      if (deal.is_active) stats.active++
      if (deal.stage?.is_won) stats.won++
      if (deal.stage?.is_lost) stats.lost++
      if (deal.value) stats.total_value += parseFloat(deal.value)
      if (deal.value && deal.probability) {
        stats.weighted_value += parseFloat(deal.value) * (deal.probability / 100)
      }
    })

    return stats
  })

  // Actions
  async function fetchDeals(params = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/deals', { params })
      deals.value = response.data.data || []
      return deals.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch deals'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchDeal(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/deals/${id}`)
      currentDeal.value = response.data.data
      return currentDeal.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch deal'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchKanbanBoard(pipelineId) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/deals/kanban/${pipelineId}`)
      kanbanBoard.value = response.data.data
      return kanbanBoard.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch kanban board'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchDealStats(pipelineId = null) {
    try {
      const params = pipelineId ? { pipeline_id: pipelineId } : {}
      const response = await axios.get('/api/deals/stats', { params })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch statistics'
      throw err
    }
  }

  async function fetchClosingSoon(days = 7, pipelineId = null) {
    try {
      const params = { days }
      if (pipelineId) params.pipeline_id = pipelineId
      const response = await axios.get('/api/deals/closing-soon', { params })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch closing soon deals'
      throw err
    }
  }

  async function fetchOverdue(pipelineId = null) {
    try {
      const params = pipelineId ? { pipeline_id: pipelineId } : {}
      const response = await axios.get('/api/deals/overdue', { params })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch overdue deals'
      throw err
    }
  }

  async function createDeal(dealData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post('/api/deals', dealData)
      const newDeal = response.data.data
      deals.value.push(newDeal)

      // Update kanban board if loaded
      if (kanbanBoard.value && newDeal.pipeline_id === kanbanBoard.value.pipeline?.id) {
        const column = kanbanBoard.value.columns?.find(c => c.stage.id === newDeal.stage_id)
        if (column) {
          column.deals.push(newDeal)
          column.deal_count++
          column.total_value += parseFloat(newDeal.value || 0)
        }
      }

      return newDeal
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create deal'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateDeal(id, dealData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.put(`/api/deals/${id}`, dealData)
      const updatedDeal = response.data.data

      // Update in list
      const index = deals.value.findIndex(d => d.id === id)
      if (index !== -1) {
        deals.value[index] = updatedDeal
      }

      // Update current if viewing
      if (currentDeal.value?.id === id) {
        currentDeal.value = updatedDeal
      }

      return updatedDeal
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update deal'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function moveDeal(id, stageId, sortOrder = null) {
    loading.value = true
    error.value = null
    try {
      const data = { stage_id: stageId }
      if (sortOrder !== null) data.sort_order = sortOrder

      const response = await axios.post(`/api/deals/${id}/move`, data)
      const updatedDeal = response.data.data

      // Update in deals list
      const index = deals.value.findIndex(d => d.id === id)
      if (index !== -1) {
        deals.value[index] = updatedDeal
      }

      // Update current if viewing
      if (currentDeal.value?.id === id) {
        currentDeal.value = updatedDeal
      }

      return updatedDeal
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to move deal'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function markWon(id, reason = null) {
    loading.value = true
    error.value = null
    try {
      const data = reason ? { reason } : {}
      const response = await axios.post(`/api/deals/${id}/won`, data)
      const updatedDeal = response.data.data

      const index = deals.value.findIndex(d => d.id === id)
      if (index !== -1) {
        deals.value[index] = updatedDeal
      }

      if (currentDeal.value?.id === id) {
        currentDeal.value = updatedDeal
      }

      return updatedDeal
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to mark deal as won'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function markLost(id, reason = null) {
    loading.value = true
    error.value = null
    try {
      const data = reason ? { reason } : {}
      const response = await axios.post(`/api/deals/${id}/lost`, data)
      const updatedDeal = response.data.data

      const index = deals.value.findIndex(d => d.id === id)
      if (index !== -1) {
        deals.value[index] = updatedDeal
      }

      if (currentDeal.value?.id === id) {
        currentDeal.value = updatedDeal
      }

      return updatedDeal
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to mark deal as lost'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function convertToProject(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/deals/${id}/convert`)
      const result = response.data.data

      // Update deal in list
      const index = deals.value.findIndex(d => d.id === id)
      if (index !== -1) {
        deals.value[index] = result.deal
      }

      if (currentDeal.value?.id === id) {
        currentDeal.value = result.deal
      }

      return result
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to convert deal to project'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteDeal(id) {
    loading.value = true
    error.value = null
    try {
      await axios.delete(`/api/deals/${id}`)

      // Remove from list
      deals.value = deals.value.filter(d => d.id !== id)

      // Remove from kanban if loaded
      if (kanbanBoard.value) {
        kanbanBoard.value.columns?.forEach(column => {
          const index = column.deals.findIndex(d => d.id === id)
          if (index !== -1) {
            const deal = column.deals[index]
            column.deals.splice(index, 1)
            column.deal_count--
            column.total_value -= parseFloat(deal.value || 0)
          }
        })
      }

      // Clear current if deleted
      if (currentDeal.value?.id === id) {
        currentDeal.value = null
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to delete deal'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Activity management
  async function addActivity(dealId, activityData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/deals/${dealId}/activities`, activityData)
      const newActivity = response.data.data

      // Update current deal activities
      if (currentDeal.value?.id === dealId) {
        if (!currentDeal.value.activities) {
          currentDeal.value.activities = []
        }
        currentDeal.value.activities.unshift(newActivity)
      }

      return newActivity
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to add activity'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchActivities(dealId, limit = 50, offset = 0) {
    try {
      const response = await axios.get(`/api/deals/${dealId}/activities`, {
        params: { limit, offset }
      })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch activities'
      throw err
    }
  }

  async function reorderDeals(stageId, order) {
    loading.value = true
    error.value = null
    try {
      await axios.put('/api/deals/reorder', { stage_id: stageId, order })
      return true
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to reorder deals'
      throw err
    } finally {
      loading.value = false
    }
  }

  function setSearchTerm(term) {
    searchTerm.value = term
  }

  function setPipelineFilter(pipelineId) {
    pipelineFilter.value = pipelineId
  }

  function setStatusFilter(status) {
    statusFilter.value = status
  }

  function clearError() {
    error.value = null
  }

  function reset() {
    deals.value = []
    currentDeal.value = null
    kanbanBoard.value = null
    loading.value = false
    error.value = null
    searchTerm.value = ''
    pipelineFilter.value = null
    statusFilter.value = 'all'
  }

  // Update kanban board locally (for optimistic updates during drag/drop)
  function updateKanbanLocally(fromStageId, toStageId, dealId, newOrder) {
    if (!kanbanBoard.value) return

    const fromColumn = kanbanBoard.value.columns?.find(c => c.stage.id === fromStageId)
    const toColumn = kanbanBoard.value.columns?.find(c => c.stage.id === toStageId)

    if (!fromColumn || !toColumn) return

    // Remove from source column
    const dealIndex = fromColumn.deals.findIndex(d => d.id === dealId)
    if (dealIndex === -1) return

    const [deal] = fromColumn.deals.splice(dealIndex, 1)
    fromColumn.deal_count--
    fromColumn.total_value -= parseFloat(deal.value || 0)

    // Add to destination column
    deal.stage_id = toStageId
    deal.sort_order = newOrder
    toColumn.deals.splice(newOrder, 0, deal)
    toColumn.deal_count++
    toColumn.total_value += parseFloat(deal.value || 0)

    // Sort destination column by sort_order
    toColumn.deals.sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0))
  }

  return {
    // State
    deals,
    currentDeal,
    kanbanBoard,
    loading,
    error,
    searchTerm,
    pipelineFilter,
    statusFilter,

    // Computed
    activeDeals,
    filteredDeals,
    dealCount,
    activeDealCount,
    dealStats,

    // Actions
    fetchDeals,
    fetchDeal,
    fetchKanbanBoard,
    fetchDealStats,
    fetchClosingSoon,
    fetchOverdue,
    createDeal,
    updateDeal,
    moveDeal,
    markWon,
    markLost,
    convertToProject,
    deleteDeal,
    addActivity,
    fetchActivities,
    reorderDeals,
    setSearchTerm,
    setPipelineFilter,
    setStatusFilter,
    clearError,
    reset,
    updateKanbanLocally
  }
})
