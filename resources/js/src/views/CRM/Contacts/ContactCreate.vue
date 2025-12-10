<template>
  <AdminLayout>
    <div class="space-y-6">
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
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create Contact</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Add a new contact to your CRM</p>
        </div>
      </div>

      <div v-if="error" class="rounded-lg bg-red-50 p-4 dark:bg-red-900/20">
        <p class="text-sm text-red-700 dark:text-red-400">{{ error }}</p>
      </div>

      <ContactForm
        :initial-data="initialData"
        submit-text="Create Contact"
        @submit="handleSubmit"
        @cancel="handleCancel"
      />
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useContactStore } from '@/stores/contacts'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import ContactForm from './ContactForm.vue'

const router = useRouter()
const route = useRoute()
const contactStore = useContactStore()

const error = ref(null)

// Pre-populate client_id if passed via query param
const initialData = computed(() => ({
  client_id: route.query.client_id || ''
}))

async function handleSubmit(formData) {
  error.value = null
  try {
    const newContact = await contactStore.createContact(formData)
    router.push(`/crm/contacts/${newContact.id}`)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to create contact'
  }
}

function handleCancel() {
  router.push('/crm/contacts')
}
</script>
