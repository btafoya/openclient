<template>
  <AdminLayout>
    <div class="mx-auto max-w-4xl">
      <!-- Header -->
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            Create New Client
          </h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Add a new client to your CRM
          </p>
        </div>
        <router-link
          to="/crm/clients"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          Back to Clients
        </router-link>
      </div>

      <!-- Form Card -->
      <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <form @submit.prevent="handleSubmit" class="space-y-6">
          <!-- Basic Information Section -->
          <div>
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
              Basic Information
            </h2>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
              <!-- Client Name (Required) -->
              <div class="md:col-span-2">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                  Client Name <span class="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  v-model="formData.name"
                  placeholder="Enter client name"
                  required
                  minlength="2"
                  maxlength="255"
                  class="h-11 w-full rounded-lg border px-4 py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10"
                  :class="[
                    errors.name
                      ? 'border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/20'
                      : 'border-gray-300 bg-transparent dark:border-gray-700 dark:bg-gray-900',
                    'text-gray-800 dark:text-white/90 dark:placeholder:text-white/30'
                  ]"
                  @input="clearError('name')"
                />
                <p v-if="errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                  {{ errors.name }}
                </p>
              </div>

              <!-- Company -->
              <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                  Company
                </label>
                <input
                  type="text"
                  v-model="formData.company"
                  placeholder="Enter company name"
                  maxlength="255"
                  class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                />
              </div>

              <!-- Email -->
              <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                  Email
                </label>
                <input
                  type="email"
                  v-model="formData.email"
                  placeholder="client@example.com"
                  maxlength="255"
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

              <!-- Phone -->
              <div class="md:col-span-2">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                  Phone
                </label>
                <input
                  type="tel"
                  v-model="formData.phone"
                  placeholder="(123) 456-7890"
                  maxlength="50"
                  class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                />
              </div>
            </div>
          </div>

          <!-- Address Section -->
          <div>
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
              Address
            </h2>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
              <!-- Street Address -->
              <div class="md:col-span-2">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                  Street Address
                </label>
                <input
                  type="text"
                  v-model="formData.address"
                  placeholder="123 Main Street"
                  class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                />
              </div>

              <!-- City -->
              <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                  City
                </label>
                <input
                  type="text"
                  v-model="formData.city"
                  placeholder="Enter city"
                  maxlength="100"
                  class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                />
              </div>

              <!-- State -->
              <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                  State/Province
                </label>
                <input
                  type="text"
                  v-model="formData.state"
                  placeholder="Enter state"
                  maxlength="50"
                  class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                />
              </div>

              <!-- Postal Code -->
              <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                  Postal Code
                </label>
                <input
                  type="text"
                  v-model="formData.postal_code"
                  placeholder="12345"
                  maxlength="20"
                  class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                />
              </div>

              <!-- Country -->
              <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                  Country
                </label>
                <input
                  type="text"
                  v-model="formData.country"
                  placeholder="United States"
                  maxlength="100"
                  class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                />
              </div>
            </div>
          </div>

          <!-- Notes Section -->
          <div>
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
              Additional Information
            </h2>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                Notes
              </label>
              <textarea
                v-model="formData.notes"
                rows="4"
                placeholder="Add any additional notes about this client..."
                class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
              ></textarea>
            </div>
          </div>

          <!-- Error Message -->
          <div v-if="generalError" class="rounded-lg border border-red-300 bg-red-50 p-4 dark:border-red-700 dark:bg-red-900/20">
            <div class="flex items-start gap-3">
              <svg class="h-5 w-5 flex-shrink-0 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
              </svg>
              <div class="flex-1">
                <h3 class="text-sm font-medium text-red-800 dark:text-red-300">
                  Error Creating Client
                </h3>
                <p class="mt-1 text-sm text-red-700 dark:text-red-400">
                  {{ generalError }}
                </p>
              </div>
            </div>
          </div>

          <!-- Form Actions -->
          <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-6 dark:border-gray-800">
            <router-link
              to="/crm/clients"
              class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            >
              Cancel
            </router-link>
            <button
              type="button"
              @click="handleSubmit('continue')"
              :disabled="saving"
              class="inline-flex items-center gap-2 rounded-lg border border-brand-600 bg-white px-4 py-2.5 text-sm font-medium text-brand-600 hover:bg-brand-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-brand-500 dark:bg-gray-800 dark:text-brand-400 dark:hover:bg-brand-900/20"
            >
              <span v-if="saving">Saving...</span>
              <span v-else>Save & Continue</span>
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-brand-500 dark:hover:bg-brand-600"
            >
              <span v-if="saving">Saving...</span>
              <span v-else>Save & Close</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useClientStore } from '@/stores/clients'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const router = useRouter()
const clientStore = useClientStore()

const saving = ref(false)
const generalError = ref(null)

const formData = reactive({
  name: '',
  company: '',
  email: '',
  phone: '',
  address: '',
  city: '',
  state: '',
  postal_code: '',
  country: '',
  notes: '',
  is_active: true
})

const errors = reactive({
  name: null,
  email: null
})

function clearError(field) {
  errors[field] = null
  generalError.value = null
}

function validateForm() {
  let isValid = true

  // Clear previous errors
  errors.name = null
  errors.email = null
  generalError.value = null

  // Validate name (required, 2-255 chars)
  if (!formData.name || formData.name.trim().length === 0) {
    errors.name = 'Client name is required'
    isValid = false
  } else if (formData.name.trim().length < 2) {
    errors.name = 'Client name must be at least 2 characters'
    isValid = false
  } else if (formData.name.length > 255) {
    errors.name = 'Client name cannot exceed 255 characters'
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

async function handleSubmit(action = 'close') {
  // Validate form
  if (!validateForm()) {
    return
  }

  saving.value = true
  generalError.value = null

  try {
    // Create client via store
    const newClient = await clientStore.createClient({
      ...formData,
      // Trim string values
      name: formData.name.trim(),
      company: formData.company?.trim() || null,
      email: formData.email?.trim() || null,
      phone: formData.phone?.trim() || null,
      address: formData.address?.trim() || null,
      city: formData.city?.trim() || null,
      state: formData.state?.trim() || null,
      postal_code: formData.postal_code?.trim() || null,
      country: formData.country?.trim() || null,
      notes: formData.notes?.trim() || null
    })

    // Show success message
    alert(`Client "${newClient.name}" created successfully!`)

    // Handle navigation based on action
    if (action === 'continue') {
      // Reset form for next client
      Object.keys(formData).forEach(key => {
        if (key === 'is_active') {
          formData[key] = true
        } else {
          formData[key] = ''
        }
      })
      // Scroll to top
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } else {
      // Navigate to client view
      router.push(`/crm/clients/${newClient.id}`)
    }
  } catch (error) {
    console.error('Failed to create client:', error)

    // Handle validation errors from backend
    if (error.response?.data?.messages) {
      const backendErrors = error.response.data.messages
      if (backendErrors.name) {
        errors.name = Array.isArray(backendErrors.name)
          ? backendErrors.name[0]
          : backendErrors.name
      }
      if (backendErrors.email) {
        errors.email = Array.isArray(backendErrors.email)
          ? backendErrors.email[0]
          : backendErrors.email
      }
      generalError.value = 'Please correct the errors below'
    } else {
      generalError.value = error.response?.data?.message || 'Failed to create client. Please try again.'
    }
  } finally {
    saving.value = false
  }
}
</script>
