<template>
  <!-- Modal Overlay -->
  <div class="fixed inset-0 z-[99999] overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
      <!-- Backdrop -->
      <div
        @click="$emit('close')"
        class="fixed inset-0 bg-gray-900/50 transition-opacity dark:bg-gray-900/80"
      ></div>

      <!-- Modal -->
      <div class="relative w-full max-w-2xl rounded-lg border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-800 dark:bg-gray-900">
        <!-- Header -->
        <div class="mb-6 flex items-start justify-between">
          <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">
              {{ isEditing ? 'Edit Contact' : 'Add New Contact' }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              {{ isEditing ? 'Update contact information' : 'Create a new contact for this client' }}
            </p>
          </div>
          <button
            @click="$emit('close')"
            class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleSubmit" class="space-y-6">
          <!-- Name Fields -->
          <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                First Name <span class="text-red-500">*</span>
              </label>
              <input
                type="text"
                v-model="formData.first_name"
                required
                maxlength="100"
                class="h-11 w-full rounded-lg border px-4 py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10"
                :class="[
                  errors.first_name
                    ? 'border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/20'
                    : 'border-gray-300 bg-transparent dark:border-gray-700 dark:bg-gray-900',
                  'text-gray-800 dark:text-white/90 dark:placeholder:text-white/30'
                ]"
                @input="clearError('first_name')"
              />
              <p v-if="errors.first_name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                {{ errors.first_name }}
              </p>
            </div>

            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                Last Name <span class="text-red-500">*</span>
              </label>
              <input
                type="text"
                v-model="formData.last_name"
                required
                maxlength="100"
                class="h-11 w-full rounded-lg border px-4 py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10"
                :class="[
                  errors.last_name
                    ? 'border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/20'
                    : 'border-gray-300 bg-transparent dark:border-gray-700 dark:bg-gray-900',
                  'text-gray-800 dark:text-white/90 dark:placeholder:text-white/30'
                ]"
                @input="clearError('last_name')"
              />
              <p v-if="errors.last_name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                {{ errors.last_name }}
              </p>
            </div>
          </div>

          <!-- Job Details -->
          <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                Job Title
              </label>
              <input
                type="text"
                v-model="formData.job_title"
                maxlength="100"
                placeholder="e.g., Marketing Manager"
                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
              />
            </div>

            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                Department
              </label>
              <input
                type="text"
                v-model="formData.department"
                maxlength="100"
                placeholder="e.g., Marketing"
                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
              />
            </div>
          </div>

          <!-- Contact Information -->
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
              Email
            </label>
            <input
              type="email"
              v-model="formData.email"
              maxlength="255"
              placeholder="contact@example.com"
              class="h-11 w-full rounded-lg border px-4 py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10"
              :class="[
                errors.email
                  ? 'border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/20'
                  : 'border-gray-300 bg-transparent dark:border-gray-700 dark:bg-gray-900',
                'text-gray-800 dark:text-white/90 dark:placeholder:text-white/30'
              ]"
              @input="clearError('email')"
            />
            <p v-if="errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ errors.email }}
            </p>
          </div>

          <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                Phone
              </label>
              <input
                type="tel"
                v-model="formData.phone"
                maxlength="50"
                placeholder="(123) 456-7890"
                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
              />
            </div>

            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                Mobile
              </label>
              <input
                type="tel"
                v-model="formData.mobile"
                maxlength="50"
                placeholder="(123) 456-7890"
                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
              />
            </div>
          </div>

          <!-- Checkboxes -->
          <div class="space-y-3">
            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-400">
              <input
                type="checkbox"
                v-model="formData.is_primary"
                class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900"
              />
              <span>Set as primary contact</span>
            </label>
            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-400">
              <input
                type="checkbox"
                v-model="formData.is_active"
                class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900"
              />
              <span>Active contact</span>
            </label>
          </div>

          <!-- Error Message -->
          <div v-if="generalError" class="rounded-lg border border-red-300 bg-red-50 p-4 dark:border-red-700 dark:bg-red-900/20">
            <div class="flex items-start gap-3">
              <svg class="h-5 w-5 flex-shrink-0 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
              </svg>
              <div class="flex-1">
                <h3 class="text-sm font-medium text-red-800 dark:text-red-300">
                  Error {{ isEditing ? 'Updating' : 'Creating' }} Contact
                </h3>
                <p class="mt-1 text-sm text-red-700 dark:text-red-400">
                  {{ generalError }}
                </p>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-6 dark:border-gray-800">
            <button
              type="button"
              @click="$emit('close')"
              class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-brand-500 dark:hover:bg-brand-600"
            >
              <span v-if="saving">Saving...</span>
              <span v-else>{{ isEditing ? 'Update Contact' : 'Create Contact' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useContactStore } from '@/stores/contacts'

const props = defineProps({
  clientId: {
    type: String,
    required: true
  },
  contact: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'saved'])

const contactStore = useContactStore()

const saving = ref(false)
const generalError = ref(null)

const isEditing = computed(() => !!props.contact)

const formData = reactive({
  client_id: props.clientId,
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  mobile: '',
  job_title: '',
  department: '',
  is_primary: false,
  is_active: true
})

const errors = reactive({
  first_name: null,
  last_name: null,
  email: null
})

function clearError(field) {
  errors[field] = null
  generalError.value = null
}

function validateForm() {
  let isValid = true

  // Clear previous errors
  errors.first_name = null
  errors.last_name = null
  errors.email = null
  generalError.value = null

  // Validate first name (required)
  if (!formData.first_name || formData.first_name.trim().length === 0) {
    errors.first_name = 'First name is required'
    isValid = false
  } else if (formData.first_name.length > 100) {
    errors.first_name = 'First name cannot exceed 100 characters'
    isValid = false
  }

  // Validate last name (required)
  if (!formData.last_name || formData.last_name.trim().length === 0) {
    errors.last_name = 'Last name is required'
    isValid = false
  } else if (formData.last_name.length > 100) {
    errors.last_name = 'Last name cannot exceed 100 characters'
    isValid = false
  }

  // Validate email (if provided)
  if (formData.email && formData.email.trim().length > 0) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!emailRegex.test(formData.email)) {
      errors.email = 'Please provide a valid email address'
      isValid = false
    }
  }

  return isValid
}

async function handleSubmit() {
  // Validate form
  if (!validateForm()) {
    return
  }

  saving.value = true
  generalError.value = null

  try {
    const contactData = {
      ...formData,
      // Trim string values
      first_name: formData.first_name.trim(),
      last_name: formData.last_name.trim(),
      email: formData.email?.trim() || null,
      phone: formData.phone?.trim() || null,
      mobile: formData.mobile?.trim() || null,
      job_title: formData.job_title?.trim() || null,
      department: formData.department?.trim() || null
    }

    if (isEditing.value) {
      await contactStore.updateContact(props.contact.id, contactData)
    } else {
      await contactStore.createContact(contactData)
    }

    emit('saved')
  } catch (error) {
    console.error('Failed to save contact:', error)

    // Handle validation errors from backend
    if (error.response?.data?.messages) {
      const backendErrors = error.response.data.messages
      if (backendErrors.first_name) {
        errors.first_name = Array.isArray(backendErrors.first_name)
          ? backendErrors.first_name[0]
          : backendErrors.first_name
      }
      if (backendErrors.last_name) {
        errors.last_name = Array.isArray(backendErrors.last_name)
          ? backendErrors.last_name[0]
          : backendErrors.last_name
      }
      if (backendErrors.email) {
        errors.email = Array.isArray(backendErrors.email)
          ? backendErrors.email[0]
          : backendErrors.email
      }
      generalError.value = 'Please correct the errors below'
    } else {
      generalError.value = error.response?.data?.message || `Failed to ${isEditing.value ? 'update' : 'create'} contact. Please try again.`
    }
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  if (props.contact) {
    // Populate form with contact data
    formData.first_name = props.contact.first_name || ''
    formData.last_name = props.contact.last_name || ''
    formData.email = props.contact.email || ''
    formData.phone = props.contact.phone || ''
    formData.mobile = props.contact.mobile || ''
    formData.job_title = props.contact.job_title || ''
    formData.department = props.contact.department || ''
    formData.is_primary = props.contact.is_primary || false
    formData.is_active = props.contact.is_active !== false
  }
})
</script>
