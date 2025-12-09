<template>
  <AdminLayout>
    <div class="mx-auto max-w-3xl space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create Client</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Add a new client to your CRM</p>
      </div>

      <form @submit.prevent="handleSubmit" class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name *</label>
            <input v-model="form.name" type="text" required class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email *</label>
            <input v-model="form.email" type="email" required class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
            <input v-model="form.phone" type="text" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Company</label>
            <input v-model="form.company" type="text" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Address</label>
            <input v-model="form.address" type="text" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
          </div>
          <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">City</label><input v-model="form.city" type="text" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" /></div>
          <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">State</label><input v-model="form.state" type="text" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" /></div>
          <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">ZIP</label><input v-model="form.zip" type="text" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" /></div>
          <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Country</label><input v-model="form.country" type="text" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" /></div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Website</label>
            <input v-model="form.website" type="url" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
            <textarea v-model="form.notes" rows="4" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white"></textarea>
          </div>
        </div>

        <div class="mt-6 flex gap-3">
          <button type="submit" :disabled="loading" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
            <svg v-if="loading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            {{ loading ? 'Creating...' : 'Create Client' }}
          </button>
          <router-link to="/crm/clients" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">Cancel</router-link>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useClientStore } from '@/stores/clients'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const router = useRouter()
const clientStore = useClientStore()
const loading = ref(false)
const form = ref({ name: '', email: '', phone: '', company: '', address: '', city: '', state: '', zip: '', country: '', website: '', notes: '' })

async function handleSubmit() {
  loading.value = true
  try {
    const client = await clientStore.createClient(form.value)
    alert('Client created successfully!')
    router.push(`/crm/clients/${client.id}`)
  } catch (error) {
    alert('Failed to create client: ' + (error.response?.data?.message || error.message))
  } finally {
    loading.value = false
  }
}
</script>
