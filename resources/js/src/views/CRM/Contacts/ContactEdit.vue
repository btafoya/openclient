<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex items-center gap-4">
        <router-link
          :to="`/crm/contacts/${route.params.id}`"
          class="p-2 text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
        </router-link>
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Contact</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ contactStore.currentContact ? `${contactStore.currentContact.first_name} ${contactStore.currentContact.last_name}` : 'Loading...' }}
          </p>
        </div>
      </div>

      <div v-if="error" class="rounded-lg bg-red-50 p-4 dark:bg-red-900/20">
        <p class="text-sm text-red-700 dark:text-red-400">{{ error }}</p>
      </div>

      <div v-if="contactStore.loading && !contactStore.currentContact" class="p-8 text-center">
        <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading contact...</p>
      </div>

      <ContactForm
        v-else-if="contactStore.currentContact"
        :initial-data="contactStore.currentContact"
        submit-text="Update Contact"
        @submit="handleSubmit"
        @cancel="handleCancel"
      />
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useContactStore } from '@/stores/contacts'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import ContactForm from './ContactForm.vue'

const router = useRouter()
const route = useRoute()
const contactStore = useContactStore()

const error = ref(null)

async function loadContact() {
  try {
    await contactStore.fetchContact(route.params.id)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load contact'
  }
}

async function handleSubmit(formData) {
  error.value = null
  try {
    await contactStore.updateContact(route.params.id, formData)
    router.push(`/crm/contacts/${route.params.id}`)
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to update contact'
  }
}

function handleCancel() {
  router.push(`/crm/contacts/${route.params.id}`)
}

onMounted(() => loadContact())
</script>
