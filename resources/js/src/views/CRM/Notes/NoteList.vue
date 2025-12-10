<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Notes</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage notes for clients, contacts, and projects</p>
        </div>
        <button
          @click="showCreateForm = true"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          New Note
        </button>
      </div>

      <!-- Create/Edit Form Modal -->
      <div v-if="showCreateForm || editingNote" class="mb-6">
        <NoteForm
          :initial-data="editingNote || {}"
          :submit-text="editingNote ? 'Update Note' : 'Create Note'"
          :loading="noteStore.loading"
          @submit="handleFormSubmit"
          @cancel="closeForm"
        />
      </div>

      <!-- Filters -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
        <div class="flex-1 relative">
          <input
            v-model="searchTerm"
            type="text"
            placeholder="Search notes..."
            class="w-full px-4 py-2 pl-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
          />
          <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
        <select
          v-model="pinnedFilter"
          class="px-4 py-2 text-sm border border-gray-300 rounded-lg dark:bg-gray-800 dark:border-gray-600 dark:text-white"
        >
          <option value="all">All Notes</option>
          <option value="pinned">Pinned Only</option>
          <option value="unpinned">Unpinned Only</option>
        </select>
      </div>

      <!-- Pinned Notes Section -->
      <div v-if="pinnedNotes.length > 0 && pinnedFilter !== 'unpinned'" class="space-y-3">
        <h2 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pinned</h2>
        <div class="space-y-3">
          <NoteCard
            v-for="note in pinnedNotes"
            :key="note.id"
            :note="note"
            @edit="handleEdit"
            @delete="handleDelete"
            @toggle-pin="handleTogglePin"
          />
        </div>
      </div>

      <!-- Regular Notes Section -->
      <div class="space-y-3">
        <h2 v-if="pinnedNotes.length > 0 && pinnedFilter !== 'pinned'" class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
          All Notes
        </h2>
        <div v-if="noteStore.loading" class="p-8 text-center">
          <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading...</p>
        </div>
        <div v-else-if="filteredNotes.length === 0" class="p-8 text-center rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
          <p class="text-sm text-gray-900 dark:text-white font-medium">No notes found</p>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create your first note to get started</p>
        </div>
        <div v-else class="space-y-3">
          <NoteCard
            v-for="note in regularNotes"
            :key="note.id"
            :note="note"
            @edit="handleEdit"
            @delete="handleDelete"
            @toggle-pin="handleTogglePin"
          />
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useNoteStore } from '@/stores/notes'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import NoteCard from './NoteCard.vue'
import NoteForm from './NoteForm.vue'

const noteStore = useNoteStore()
const searchTerm = ref('')
const pinnedFilter = ref('all')
const showCreateForm = ref(false)
const editingNote = ref(null)

const filteredNotes = computed(() => {
  let notes = noteStore.notes

  // Search filter
  if (searchTerm.value) {
    const term = searchTerm.value.toLowerCase()
    notes = notes.filter(note =>
      note.content?.toLowerCase().includes(term) ||
      note.client_name?.toLowerCase().includes(term) ||
      note.contact_name?.toLowerCase().includes(term) ||
      note.project_name?.toLowerCase().includes(term)
    )
  }

  // Pinned filter
  if (pinnedFilter.value === 'pinned') {
    notes = notes.filter(note => note.is_pinned)
  } else if (pinnedFilter.value === 'unpinned') {
    notes = notes.filter(note => !note.is_pinned)
  }

  return notes
})

const pinnedNotes = computed(() => filteredNotes.value.filter(note => note.is_pinned))
const regularNotes = computed(() => filteredNotes.value.filter(note => !note.is_pinned))

async function loadNotes() {
  await noteStore.fetchNotes()
}

function handleEdit(note) {
  editingNote.value = { ...note }
  showCreateForm.value = false
}

async function handleDelete(note) {
  try {
    await noteStore.deleteNote(note.id)
  } catch (err) {
    console.error('Failed to delete note:', err)
  }
}

async function handleTogglePin(note) {
  try {
    await noteStore.togglePin(note.id)
  } catch (err) {
    console.error('Failed to toggle pin:', err)
  }
}

async function handleFormSubmit(formData) {
  try {
    if (editingNote.value) {
      await noteStore.updateNote(editingNote.value.id, formData)
    } else {
      await noteStore.createNote(formData)
    }
    closeForm()
  } catch (err) {
    console.error('Failed to save note:', err)
  }
}

function closeForm() {
  showCreateForm.value = false
  editingNote.value = null
}

onMounted(() => loadNotes())
</script>
