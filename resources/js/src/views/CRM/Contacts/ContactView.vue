<template>
  <AdminLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
          <router-link
            to="/crm/contacts"
            class="p-2 text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
          </router-link>
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ contact ? `${contact.first_name} ${contact.last_name}` : 'Contact Details' }}
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              {{ contact?.job_title || 'Contact' }}
              <span v-if="contact?.client_name"> at {{ contact.client_name }}</span>
            </p>
          </div>
        </div>
        <div v-if="contact" class="flex items-center gap-2">
          <router-link
            :to="`/crm/contacts/${contact.id}/edit`"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Edit
          </router-link>
          <button
            @click="handleDelete"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-red-700 bg-white border border-red-300 rounded-lg hover:bg-red-50 dark:bg-gray-800 dark:text-red-400 dark:border-red-700"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
            Delete
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="contactStore.loading && !contact" class="p-8 text-center">
        <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading contact...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="rounded-lg bg-red-50 p-4 dark:bg-red-900/20">
        <p class="text-sm text-red-700 dark:text-red-400">{{ error }}</p>
      </div>

      <!-- Contact Details -->
      <div v-else-if="contact" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Main Info Card -->
        <div class="lg:col-span-2 space-y-6">
          <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Contact Information</h2>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">First Name</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ contact.first_name }}</dd>
              </div>
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Name</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ contact.last_name }}</dd>
              </div>
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                  <a v-if="contact.email" :href="`mailto:${contact.email}`" class="text-brand-600 hover:underline">
                    {{ contact.email }}
                  </a>
                  <span v-else class="text-gray-400">—</span>
                </dd>
              </div>
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Phone</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                  <a v-if="contact.phone" :href="`tel:${contact.phone}`" class="text-brand-600 hover:underline">
                    {{ contact.phone }}
                  </a>
                  <span v-else class="text-gray-400">—</span>
                </dd>
              </div>
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Job Title</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ contact.job_title || '—' }}</dd>
              </div>
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Client</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                  <router-link
                    v-if="contact.client_id"
                    :to="`/crm/clients/${contact.client_id}`"
                    class="text-brand-600 hover:underline"
                  >
                    {{ contact.client_name }}
                  </router-link>
                  <span v-else class="text-gray-400">—</span>
                </dd>
              </div>
            </dl>
          </div>

          <!-- Notes Section -->
          <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Notes</h2>
              <button
                @click="showNoteForm = true"
                class="text-sm text-brand-600 hover:text-brand-700"
              >
                + Add Note
              </button>
            </div>

            <div v-if="showNoteForm" class="mb-4">
              <NoteForm
                :initial-data="{ contact_id: contact.id, client_id: contact.client_id }"
                @submit="handleCreateNote"
                @cancel="showNoteForm = false"
              />
            </div>

            <div v-if="notes.length === 0 && !showNoteForm" class="text-center py-8 text-gray-500 dark:text-gray-400">
              <p>No notes yet</p>
            </div>
            <div v-else class="space-y-3">
              <NoteCard
                v-for="note in notes"
                :key="note.id"
                :note="note"
                @edit="handleEditNote"
                @delete="handleDeleteNote"
                @toggle-pin="handleTogglePin"
              />
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Status Card -->
          <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Status</h2>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Primary Contact</span>
                <span
                  :class="contact.is_primary ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'"
                  class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full"
                >
                  {{ contact.is_primary ? 'Yes' : 'No' }}
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">Status</span>
                <span
                  :class="contact.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'"
                  class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full"
                >
                  {{ contact.is_active ? 'Active' : 'Inactive' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Timestamps Card -->
          <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Activity</h2>
            <dl class="space-y-3">
              <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Created</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDate(contact.created_at) }}</dd>
              </div>
              <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Last Updated</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDate(contact.updated_at) }}</dd>
              </div>
            </dl>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useContactStore } from '@/stores/contacts'
import { useNoteStore } from '@/stores/notes'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import NoteCard from '../Notes/NoteCard.vue'
import NoteForm from '../Notes/NoteForm.vue'

const router = useRouter()
const route = useRoute()
const contactStore = useContactStore()
const noteStore = useNoteStore()

const error = ref(null)
const showNoteForm = ref(false)
const notes = ref([])

const contact = computed(() => contactStore.currentContact)

async function loadContact() {
  try {
    await contactStore.fetchContact(route.params.id)
    await loadNotes()
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load contact'
  }
}

async function loadNotes() {
  try {
    notes.value = await noteStore.fetchNotesByContact(route.params.id)
  } catch (err) {
    console.error('Failed to load notes:', err)
  }
}

async function handleDelete() {
  if (confirm(`Delete ${contact.value.first_name} ${contact.value.last_name}?`)) {
    try {
      await contactStore.deleteContact(route.params.id)
      router.push('/crm/contacts')
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete contact'
    }
  }
}

async function handleCreateNote(formData) {
  try {
    await noteStore.createNote(formData)
    showNoteForm.value = false
    await loadNotes()
  } catch (err) {
    console.error('Failed to create note:', err)
  }
}

async function handleEditNote(note) {
  // For simplicity, redirect to edit or implement inline editing
  alert('Edit note: ' + note.id)
}

async function handleDeleteNote(note) {
  try {
    await noteStore.deleteNote(note.id)
    await loadNotes()
  } catch (err) {
    console.error('Failed to delete note:', err)
  }
}

async function handleTogglePin(note) {
  try {
    await noteStore.togglePin(note.id)
    await loadNotes()
  } catch (err) {
    console.error('Failed to toggle pin:', err)
  }
}

function formatDate(dateString) {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

onMounted(() => loadContact())
</script>
