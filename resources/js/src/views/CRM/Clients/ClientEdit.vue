<template>
  <AdminLayout>
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="h-12 w-12 animate-spin rounded-full border-4 border-gray-300 border-t-brand-600"></div>
    </div>
    <div v-else class="mx-auto max-w-3xl space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Client</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update client information</p>
      </div>

      <form @submit.prevent="handleSubmit" class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
          <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name *</label><input v-model="form.name" type="text" required class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" /></div>
          <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email *</label><input v-model="form.email" type="email" required class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" /></div>
          <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label><input v-model="form.phone" type="text" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" /></div>
          <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Company</label><input v-model="form.company" type="text" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" /></div>
          <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Address</label><input v-model="form.address" type="text" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" /></div>
          <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">City</label><input v-model="form.city" type="text" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" /></div>
          <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">State</label><input v-model="form.state" type="text" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" /></div>
          <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">ZIP</label><input v-model="form.zip" type="text" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" /></div>
          <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Country</label><input v-model="form.country" type="text" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" /></div>
          <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Website</label><input v-model="form.website" type="url" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" /></div>
          <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label><textarea v-model="form.notes" rows="4" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white"></textarea></div>
        </div>

        <div class="mt-6 flex gap-3">
          <button type="submit" :disabled="saving" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">{{ saving ? 'Updating...' : 'Update Client' }}</button>
          <router-link to="/crm/clients" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">Cancel</router-link>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useClientStore } from '@/stores/clients'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const route = useRoute()
const router = useRouter()
const clientStore = useClientStore()
const loading = ref(false)
const saving = ref(false)
const form = ref({ name: '', email: '', phone: '', company: '', address: '', city: '', state: '', zip: '', country: '', website: '', notes: '' })

async function loadClient() {
  loading.value = true
  try {
    await clientStore.fetchClient(route.params.id)
    const client = clientStore.currentClient
    if (client) {
      form.value = { ...client }
    }
  } finally {
    loading.value = false
  }
}

async function handleSubmit() {
  saving.value = true
  try {
    await clientStore.updateClient(route.params.id, form.value)
    alert('Client updated successfully!')
    router.push(`/crm/clients/${route.params.id}`)
  } catch (error) {
    alert('Failed to update client: ' + (error.response?.data?.message || error.message))
  } finally {
    saving.value = false
  }
}

onMounted(() => loadClient())
</script>
