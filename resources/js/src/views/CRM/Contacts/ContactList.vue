<template>
  <div>
    <!-- Header with Add Button -->
    <div class="mb-4 flex items-center justify-between">
      <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
          Contacts
        </h3>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
          {{ contacts.length }} contact{{ contacts.length !== 1 ? 's' : '' }}
        </p>
      </div>
      <button
        @click="showForm = true"
        class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Add Contact
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="h-8 w-8 animate-spin rounded-full border-4 border-gray-300 border-t-brand-600 dark:border-gray-700 dark:border-t-brand-500"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="rounded-lg border border-red-300 bg-red-50 p-4 dark:border-red-700 dark:bg-red-900/20">
      <div class="flex items-start gap-3">
        <svg class="h-5 w-5 flex-shrink-0 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <div class="flex-1">
          <h3 class="text-sm font-medium text-red-800 dark:text-red-300">Error Loading Contacts</h3>
          <p class="mt-1 text-sm text-red-700 dark:text-red-400">{{ error }}</p>
          <button
            @click="loadContacts"
            class="mt-3 text-sm font-medium text-red-600 hover:text-red-700 dark:text-red-400"
          >
            Try Again
          </button>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="contacts.length === 0" class="rounded-lg border-2 border-dashed border-gray-300 p-12 text-center dark:border-gray-700">
      <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
      </svg>
      <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No contacts</h3>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by adding a contact for this client.</p>
      <button
        @click="showForm = true"
        class="mt-4 inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Add First Contact
      </button>
    </div>

    <!-- Contacts Grid -->
    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="contact in contacts"
        :key="contact.id"
        class="relative rounded-lg border border-gray-200 bg-white p-5 shadow-sm hover:shadow-md transition-shadow dark:border-gray-800 dark:bg-gray-900"
      >
        <!-- Primary Badge -->
        <div v-if="contact.is_primary" class="absolute top-3 right-3">
          <span class="inline-flex items-center rounded-full bg-brand-100 px-2 py-0.5 text-xs font-medium text-brand-800 dark:bg-brand-900/30 dark:text-brand-400">
            Primary
          </span>
        </div>

        <!-- Contact Info -->
        <div class="flex items-start gap-3">
          <!-- Avatar -->
          <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-600 dark:bg-brand-900/30 dark:text-brand-400">
            <span class="text-lg font-semibold">
              {{ getInitials(contact) }}
            </span>
          </div>

          <!-- Details -->
          <div class="flex-1 min-w-0">
            <h4 class="text-sm font-semibold text-gray-900 dark:text-white truncate">
              {{ getFullName(contact) }}
            </h4>
            <p v-if="contact.job_title" class="mt-0.5 text-xs text-gray-600 dark:text-gray-400 truncate">
              {{ contact.job_title }}
            </p>
            <p v-if="contact.department" class="text-xs text-gray-500 dark:text-gray-500 truncate">
              {{ contact.department }}
            </p>
          </div>
        </div>

        <!-- Contact Methods -->
        <div class="mt-4 space-y-2">
          <div v-if="contact.email" class="flex items-center gap-2 text-sm">
            <svg class="h-4 w-4 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            <a :href="`mailto:${contact.email}`" class="text-brand-600 hover:text-brand-700 dark:text-brand-400 truncate">
              {{ contact.email }}
            </a>
          </div>
          <div v-if="contact.phone" class="flex items-center gap-2 text-sm">
            <svg class="h-4 w-4 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
            </svg>
            <a :href="`tel:${contact.phone}`" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white truncate">
              {{ contact.phone }}
            </a>
          </div>
          <div v-if="contact.mobile" class="flex items-center gap-2 text-sm">
            <svg class="h-4 w-4 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
            <a :href="`tel:${contact.mobile}`" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white truncate">
              {{ contact.mobile }}
            </a>
          </div>
        </div>

        <!-- Actions -->
        <div class="mt-4 flex items-center justify-end gap-2 border-t border-gray-100 pt-3 dark:border-gray-800">
          <button
            @click="editContact(contact)"
            class="text-sm text-brand-600 hover:text-brand-700 dark:text-brand-400"
          >
            Edit
          </button>
          <button
            v-if="!contact.is_primary"
            @click="setPrimaryContact(contact)"
            class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
          >
            Set Primary
          </button>
          <button
            @click="deleteContact(contact)"
            class="text-sm text-red-600 hover:text-red-700 dark:text-red-400"
          >
            Delete
          </button>
        </div>
      </div>
    </div>

    <!-- Contact Form Modal -->
    <ContactForm
      v-if="showForm"
      :client-id="clientId"
      :contact="editingContact"
      @close="closeForm"
      @saved="handleSaved"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useContactStore } from '@/stores/contacts'
import ContactForm from './ContactForm.vue'

const props = defineProps({
  clientId: {
    type: String,
    required: true
  }
})

const contactStore = useContactStore()

const loading = ref(false)
const error = ref(null)
const contacts = ref([])
const showForm = ref(false)
const editingContact = ref(null)

async function loadContacts() {
  loading.value = true
  error.value = null

  try {
    contacts.value = await contactStore.fetchContactsByClient(props.clientId)
  } catch (err) {
    console.error('Failed to load contacts:', err)
    error.value = err.response?.data?.message || 'Failed to load contacts. Please try again.'
  } finally {
    loading.value = false
  }
}

function getFullName(contact) {
  return `${contact.first_name} ${contact.last_name}`.trim()
}

function getInitials(contact) {
  const firstInitial = contact.first_name?.[0] || ''
  const lastInitial = contact.last_name?.[0] || ''
  return (firstInitial + lastInitial).toUpperCase()
}

function editContact(contact) {
  editingContact.value = contact
  showForm.value = true
}

function closeForm() {
  showForm.value = false
  editingContact.value = null
}

async function handleSaved() {
  closeForm()
  await loadContacts()
}

async function setPrimaryContact(contact) {
  if (!confirm(`Set ${getFullName(contact)} as the primary contact?`)) {
    return
  }

  try {
    // Update contact to be primary
    await contactStore.updateContact(contact.id, {
      ...contact,
      is_primary: true
    })
    await loadContacts()
  } catch (err) {
    console.error('Failed to set primary contact:', err)
    alert('Failed to set primary contact. Please try again.')
  }
}

async function deleteContact(contact) {
  if (contact.is_primary) {
    alert('Cannot delete the primary contact. Please set another contact as primary first.')
    return
  }

  if (!confirm(`Delete ${getFullName(contact)}? This action cannot be undone.`)) {
    return
  }

  try {
    await contactStore.deleteContact(contact.id)
    await loadContacts()
  } catch (err) {
    console.error('Failed to delete contact:', err)
    alert('Failed to delete contact. Please try again.')
  }
}

onMounted(() => {
  loadContacts()
})
</script>
