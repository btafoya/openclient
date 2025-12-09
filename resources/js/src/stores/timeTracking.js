import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

/**
 * Time Tracking Store
 *
 * Manages time entries and timer functionality.
 * Supports start/stop timer, manual entries, and billable tracking.
 */
export const useTimeTrackingStore = defineStore('timeTracking', () => {
  // State
  const timeEntries = ref([])
  const currentEntry = ref(null)
  const runningTimer = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const timerInterval = ref(null)
  const elapsedSeconds = ref(0)

  // Computed
  const isTimerRunning = computed(() => runningTimer.value !== null)

  const billableEntries = computed(() =>
    timeEntries.value.filter(entry => entry.is_billable)
  )

  const totalHours = computed(() => {
    return timeEntries.value.reduce((sum, entry) => {
      return sum + (parseFloat(entry.hours) || 0)
    }, 0)
  })

  const billableHours = computed(() => {
    return billableEntries.value.reduce((sum, entry) => {
      return sum + (parseFloat(entry.hours) || 0)
    }, 0)
  })

  const formattedElapsedTime = computed(() => {
    const hours = Math.floor(elapsedSeconds.value / 3600)
    const minutes = Math.floor((elapsedSeconds.value % 3600) / 60)
    const seconds = elapsedSeconds.value % 60
    return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
  })

  // Actions
  async function fetchTimeEntries(filters = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/time-entries', { params: filters })
      timeEntries.value = response.data.data || []
      return timeEntries.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch time entries'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchTimeEntry(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/time-entries/${id}`)
      currentEntry.value = response.data.data
      return currentEntry.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch time entry'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function checkRunningTimer() {
    try {
      const response = await axios.get('/api/time-entries/timer/running')
      if (response.data.data) {
        runningTimer.value = response.data.data
        startElapsedTimer()
      }
      return runningTimer.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to check running timer'
      throw err
    }
  }

  async function startTimer(projectId, taskId = null, description = null) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post('/api/time-entries/timer/start', {
        project_id: projectId,
        task_id: taskId,
        description
      })
      runningTimer.value = response.data.data
      elapsedSeconds.value = 0
      startElapsedTimer()
      return runningTimer.value
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to start timer'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function stopTimer(description = null) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post('/api/time-entries/timer/stop', {
        description
      })
      const stoppedEntry = response.data.data

      stopElapsedTimer()
      runningTimer.value = null
      elapsedSeconds.value = 0

      // Add to entries list
      timeEntries.value.unshift(stoppedEntry)

      return stoppedEntry
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to stop timer'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createTimeEntry(entryData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post('/api/time-entries', entryData)
      const newEntry = response.data.data
      timeEntries.value.unshift(newEntry)
      return newEntry
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create time entry'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateTimeEntry(id, entryData) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.put(`/api/time-entries/${id}`, entryData)
      const updatedEntry = response.data.data

      // Update in list
      const index = timeEntries.value.findIndex(e => e.id === id)
      if (index !== -1) {
        timeEntries.value[index] = updatedEntry
      }

      // Update current if viewing
      if (currentEntry.value?.id === id) {
        currentEntry.value = updatedEntry
      }

      return updatedEntry
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update time entry'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function toggleBillable(id) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.patch(`/api/time-entries/${id}/toggle-billable`)
      const updatedEntry = response.data.data

      const index = timeEntries.value.findIndex(e => e.id === id)
      if (index !== -1) {
        timeEntries.value[index] = updatedEntry
      }

      if (currentEntry.value?.id === id) {
        currentEntry.value = updatedEntry
      }

      return updatedEntry
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to toggle billable status'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteTimeEntry(id) {
    loading.value = true
    error.value = null
    try {
      await axios.delete(`/api/time-entries/${id}`)

      // Remove from list
      timeEntries.value = timeEntries.value.filter(e => e.id !== id)

      // Clear current if deleted
      if (currentEntry.value?.id === id) {
        currentEntry.value = null
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to delete time entry'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function getBillableSummary(projectId) {
    try {
      const response = await axios.get(`/api/projects/${projectId}/time-entries/summary`)
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch billable summary'
      throw err
    }
  }

  async function getUserStats(userId, startDate = null, endDate = null) {
    try {
      const params = {}
      if (startDate) params.start_date = startDate
      if (endDate) params.end_date = endDate

      const response = await axios.get(`/api/users/${userId}/time-entries/stats`, { params })
      return response.data.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch user statistics'
      throw err
    }
  }

  // Timer management helpers
  function startElapsedTimer() {
    if (runningTimer.value && runningTimer.value.start_time) {
      const startTime = new Date(runningTimer.value.start_time)

      // Calculate initial elapsed seconds
      elapsedSeconds.value = Math.floor((Date.now() - startTime.getTime()) / 1000)

      // Update every second
      timerInterval.value = setInterval(() => {
        elapsedSeconds.value = Math.floor((Date.now() - startTime.getTime()) / 1000)
      }, 1000)
    }
  }

  function stopElapsedTimer() {
    if (timerInterval.value) {
      clearInterval(timerInterval.value)
      timerInterval.value = null
    }
  }

  function clearError() {
    error.value = null
  }

  function reset() {
    timeEntries.value = []
    currentEntry.value = null
    runningTimer.value = null
    loading.value = false
    error.value = null
    stopElapsedTimer()
    elapsedSeconds.value = 0
  }

  return {
    // State
    timeEntries,
    currentEntry,
    runningTimer,
    loading,
    error,
    elapsedSeconds,

    // Computed
    isTimerRunning,
    billableEntries,
    totalHours,
    billableHours,
    formattedElapsedTime,

    // Actions
    fetchTimeEntries,
    fetchTimeEntry,
    checkRunningTimer,
    startTimer,
    stopTimer,
    createTimeEntry,
    updateTimeEntry,
    toggleBillable,
    deleteTimeEntry,
    getBillableSummary,
    getUserStats,
    clearError,
    reset
  }
})
