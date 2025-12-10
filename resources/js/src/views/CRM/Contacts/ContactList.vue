<template>
  <AdminLayout>
    <div class="space-y-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Contacts</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage your client contacts</p>
        </div>
        <router-link to="/crm/contacts/create" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
          New Contact
        </router-link>
      </div>

      <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
        <div class="flex-1 relative">
          <input v-model="searchTerm" @input="handleSearch" type="text" placeholder="Search contacts..." class="w-full px-4 py-2 pl-10 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white" />
          <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
        </div>
      </div>

      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div v-if="contactStore.loading" class="p-8 text-center">
          <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading...</p>
        </div>
        <div v-else-if="displayedContacts.length === 0" class="p-8 text-center">
          <p class="text-sm text-gray-900 dark:text-white font-medium">No contacts found</p>
        </div>
        <table v-else class="min-w-full">
          <thead><tr class="border-b border-gray-200 dark:border-gray-700">
            <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-xs dark:text-gray-400">Name</p></th>
            <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-xs dark:text-gray-400">Email</p></th>
            <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-xs dark:text-gray-400">Phone</p></th>
            <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-xs dark:text-gray-400">Client</p></th>
            <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-xs dark:text-gray-400">Primary</p></th>
            <th class="px-5 py-3 text-right"><p class="font-medium text-gray-500 text-xs dark:text-gray-400">Actions</p></th>
          </tr></thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="contact in displayedContacts" :key="contact.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
              <td class="px-5 py-4">
                <router-link :to="`/crm/contacts/${contact.id}`" class="font-medium text-gray-800 dark:text-white hover:text-brand-600">
                  {{ contactStore.getFullName(contact) }}
                </router-link>
              </td>
              <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">{{ contact.email || '—' }}</td>
              <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">{{ contact.phone || '—' }}</td>
              <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                <router-link v-if="contact.client_id" :to="`/crm/clients/${contact.client_id}`" class="hover:text-brand-600">
                  {{ contact.client_name }}
                </router-link>
                <span v-else>—</span>
              </td>
              <td class="px-5 py-4"><span v-if="contact.is_primary" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400">Primary</span></td>
              <td class="px-5 py-4"><div class="flex items-center justify-end gap-2">
                <router-link :to="`/crm/contacts/${contact.id}`" class="p-1.5 text-gray-600 hover:text-brand-600 dark:text-gray-400" title="View"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg></router-link>
                <button @click="handleEdit(contact)" class="p-1.5 text-gray-600 hover:text-brand-600 dark:text-gray-400" title="Edit"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg></button>
                <button @click="confirmDelete(contact)" class="p-1.5 text-gray-600 hover:text-red-600 dark:text-gray-400" title="Delete"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
              </div></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useContactStore } from '@/stores/contacts'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const router = useRouter()
const contactStore = useContactStore()
const searchTerm = ref('')
const displayedContacts = computed(() => contactStore.filteredContacts)

async function loadContacts() {
  await contactStore.fetchContacts()
}

function handleSearch() {
  contactStore.setSearchTerm(searchTerm.value)
}

function handleEdit(contact) {
  router.push(`/crm/contacts/${contact.id}/edit`)
}

function handleView(contact) {
  router.push(`/crm/contacts/${contact.id}`)
}

async function confirmDelete(contact) {
  if (confirm(`Delete contact ${contactStore.getFullName(contact)}?`)) {
    await contactStore.deleteContact(contact.id)
  }
}

onMounted(() => loadContacts())
</script>
