import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

/**
 * Proposal Store
 *
 * Manages proposal state with RBAC-aware API calls.
 * Supports proposal workflows, e-signature, and conversion to invoice.
 * Automatically filtered by agency via PostgreSQL RLS.
 */
export const useProposalStore = defineStore('proposals', () => {
  // State
  const proposals = ref([])
  const currentProposal = ref(null)
  const templates = ref([])
  const loading = ref(false)
  const error = ref(null)
  const searchTerm = ref('')

  // Computed
  const draftProposals = computed(() =>
    proposals.value.filter(p => p.status === 'draft')
  )

  const sentProposals = computed(() =>
    proposals.value.filter(p => ['sent', 'viewed'].includes(p.status))
  )

  const acceptedProposals = computed(() =>
    proposals.value.filter(p => p.status === 'accepted')
  )

  const rejectedProposals = computed(() =>
    proposals.value.filter(p => p.status === 'rejected')
  )

  const filteredProposals = computed(() => {
    if (!searchTerm.value) return proposals.value

    const term = searchTerm.value.toLowerCase()
    return proposals.value.filter(p =>
      p.title?.toLowerCase().includes(term) ||
      p.client_name?.toLowerCase().includes(term) ||
      p.project_name?.toLowerCase().includes(term)
    )
  })

  const proposalCount = computed(() => proposals.value.length)

  const totalValue = computed(() =>
    proposals.value
      .filter(p => p.status === 'accepted')
      .reduce((sum, p) => sum + parseFloat(p.total || 0), 0)
  )

  // Valid status transitions
  const statusTransitions = {
    draft: ['sent'],
    sent: ['viewed', 'accepted', 'rejected', 'expired'],
    viewed: ['accepted', 'rejected', 'expired'],
    accepted: [], // Terminal (can convert to invoice)
    rejected: ['draft'], // Can revise
    expired: ['draft'] // Can revise
  }

  // Actions
  async function fetchProposals(params = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/proposals', { params })
      proposals.value = response.data.data || []
      return proposals.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch proposals'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchProposal(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/proposals/${id}`)
      currentProposal.value = response.data.data
      return currentProposal.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch proposal'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createProposal(proposalData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post('/api/proposals', proposalData)
      const newProposal = response.data.data
      proposals.value.unshift(newProposal)
      return newProposal
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create proposal'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateProposal(id, proposalData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.put(`/api/proposals/${id}`, proposalData)
      const updatedProposal = response.data.data

      const index = proposals.value.findIndex(p => p.id === id)
      if (index !== -1) {
        proposals.value[index] = updatedProposal
      }

      if (currentProposal.value?.id === id) {
        currentProposal.value = updatedProposal
      }

      return updatedProposal
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update proposal'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteProposal(id) {
    loading.value = true
    error.value = null
    try {
      await axios.delete(`/api/proposals/${id}`)
      proposals.value = proposals.value.filter(p => p.id !== id)

      if (currentProposal.value?.id === id) {
        currentProposal.value = null
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to delete proposal'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function sendProposal(id, emailData = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/proposals/${id}/send`, emailData)
      const updatedProposal = response.data.data

      const index = proposals.value.findIndex(p => p.id === id)
      if (index !== -1) {
        proposals.value[index] = updatedProposal
      }

      if (currentProposal.value?.id === id) {
        currentProposal.value = updatedProposal
      }

      return response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to send proposal'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function convertToInvoice(id, options = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/proposals/${id}/convert-to-invoice`, options)
      return response.data.data // Returns new invoice
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to convert to invoice'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function duplicateProposal(id, newTitle = null) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/proposals/${id}/duplicate`, { title: newTitle })
      const newProposal = response.data.data
      proposals.value.unshift(newProposal)
      return newProposal
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to duplicate proposal'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Template management
  async function fetchTemplates(activeOnly = true) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/proposal-templates', {
        params: { active_only: activeOnly }
      })
      templates.value = response.data.data || []
      return templates.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch templates'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createTemplate(templateData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post('/api/proposal-templates', templateData)
      const newTemplate = response.data.data
      templates.value.unshift(newTemplate)
      return newTemplate
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create template'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateTemplate(id, templateData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.put(`/api/proposal-templates/${id}`, templateData)
      const updatedTemplate = response.data.data

      const index = templates.value.findIndex(t => t.id === id)
      if (index !== -1) {
        templates.value[index] = updatedTemplate
      }

      return updatedTemplate
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update template'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteTemplate(id) {
    loading.value = true
    error.value = null
    try {
      await axios.delete(`/api/proposal-templates/${id}`)
      templates.value = templates.value.filter(t => t.id !== id)
      return true
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to delete template'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function duplicateTemplate(id, newName = null) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/proposal-templates/${id}/duplicate`, { name: newName })
      const newTemplate = response.data.data
      templates.value.unshift(newTemplate)
      return newTemplate
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to duplicate template'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Section management
  async function addSection(proposalId, sectionData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/proposals/${proposalId}/sections`, sectionData)
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to add section'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateSection(proposalId, sectionId, sectionData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.put(`/api/proposals/${proposalId}/sections/${sectionId}`, sectionData)
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update section'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteSection(proposalId, sectionId) {
    loading.value = true
    error.value = null
    try {
      await axios.delete(`/api/proposals/${proposalId}/sections/${sectionId}`)
      return true
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to delete section'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function reorderSections(proposalId, sectionIds) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post(`/api/proposals/${proposalId}/sections/reorder`, {
        section_ids: sectionIds
      })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to reorder sections'
      throw err
    } finally {
      loading.value = false
    }
  }

  function canTransitionTo(currentStatus, newStatus) {
    const allowed = statusTransitions[currentStatus] || []
    return allowed.includes(newStatus)
  }

  function getAvailableStatuses(currentStatus) {
    return statusTransitions[currentStatus] || []
  }

  function setSearchTerm(term) {
    searchTerm.value = term
  }

  function clearError() {
    error.value = null
  }

  function reset() {
    proposals.value = []
    currentProposal.value = null
    templates.value = []
    loading.value = false
    error.value = null
    searchTerm.value = ''
  }

  return {
    // State
    proposals,
    currentProposal,
    templates,
    loading,
    error,
    searchTerm,

    // Computed
    draftProposals,
    sentProposals,
    acceptedProposals,
    rejectedProposals,
    filteredProposals,
    proposalCount,
    totalValue,

    // Actions
    fetchProposals,
    fetchProposal,
    createProposal,
    updateProposal,
    deleteProposal,
    sendProposal,
    convertToInvoice,
    duplicateProposal,
    fetchTemplates,
    createTemplate,
    updateTemplate,
    deleteTemplate,
    duplicateTemplate,
    addSection,
    updateSection,
    deleteSection,
    reorderSections,
    canTransitionTo,
    getAvailableStatuses,
    setSearchTerm,
    clearError,
    reset
  }
})
