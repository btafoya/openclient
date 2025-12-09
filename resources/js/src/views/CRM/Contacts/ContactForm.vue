<template>
  <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
    <form @submit.prevent="handleSubmit">
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">First Name *</label><input v-model="formData.first_name" type="text" required class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" /></div>
        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name *</label><input v-model="formData.last_name" type="text" required class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" /></div>
        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email *</label><input v-model="formData.email" type="email" required class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" /></div>
        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label><input v-model="formData.phone" type="text" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" /></div>
        <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Client *</label><select v-model="formData.client_id" required class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white"><option value="">Select a client</option><option v-for="client in clients" :key="client.id" :value="client.id">{{ client.name }}</option></select></div>
        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Title</label><input v-model="formData.title" type="text" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white" /></div>
        <div class="flex items-center"><label class="flex items-center"><input v-model="formData.is_primary" type="checkbox" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" /><span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Primary Contact</span></label></div>
        <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label><textarea v-model="formData.notes" rows="4" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white"></textarea></div>
      </div>

      <div class="mt-6 flex gap-3">
        <button type="submit" :disabled="loading" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">{{ loading ? 'Saving...' : submitText }}</button>
        <button type="button" @click="$emit('cancel')" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">Cancel</button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useClientStore } from '@/stores/clients'

const props = defineProps({ initialData: { type: Object, default: () => ({}) }, submitText: { type: String, default: 'Save' } })
const emit = defineEmits(['submit', 'cancel'])

const clientStore = useClientStore()
const loading = ref(false)
const clients = ref([])
const formData = ref({ first_name: '', last_name: '', email: '', phone: '', client_id: '', title: '', is_primary: false, notes: '', ...props.initialData })

async function loadClients() {
  await clientStore.fetchClients()
  clients.value = clientStore.clients
}

function handleSubmit() {
  emit('submit', formData.value)
}

onMounted(() => loadClients())
</script>
