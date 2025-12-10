<template>
  <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
    <form @submit.prevent="handleSubmit">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Note Content *</label>
          <textarea
            v-model="formData.content"
            rows="4"
            required
            placeholder="Enter your note..."
            class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
          ></textarea>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Client</label>
            <select
              v-model="formData.client_id"
              @change="onClientChange"
              class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
            >
              <option value="">None</option>
              <option v-for="client in clients" :key="client.id" :value="client.id">
                {{ client.name }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contact</label>
            <select
              v-model="formData.contact_id"
              :disabled="!formData.client_id && contacts.length === 0"
              class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
            >
              <option value="">None</option>
              <option v-for="contact in filteredContacts" :key="contact.id" :value="contact.id">
                {{ contact.first_name }} {{ contact.last_name }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Project</label>
            <select
              v-model="formData.project_id"
              class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
            >
              <option value="">None</option>
              <option v-for="project in projects" :key="project.id" :value="project.id">
                {{ project.name }}
              </option>
            </select>
          </div>
        </div>

        <div class="flex items-center">
          <label class="flex items-center">
            <input
              v-model="formData.is_pinned"
              type="checkbox"
              class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"
            />
            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Pin this note</span>
          </label>
        </div>
      </div>

      <div class="mt-6 flex gap-3">
        <button
          type="submit"
          :disabled="loading || !formData.content"
          class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
        >
          {{ loading ? 'Saving...' : submitText }}
        </button>
        <button
          type="button"
          @click="$emit('cancel')"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
        >
          Cancel
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useClientStore } from '@/stores/clients'
import { useContactStore } from '@/stores/contacts'
import { useProjectStore } from '@/stores/projects'

const props = defineProps({
  initialData: {
    type: Object,
    default: () => ({})
  },
  submitText: {
    type: String,
    default: 'Save Note'
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['submit', 'cancel'])

const clientStore = useClientStore()
const contactStore = useContactStore()
const projectStore = useProjectStore()

const clients = ref([])
const contacts = ref([])
const projects = ref([])

const formData = ref({
  content: '',
  client_id: '',
  contact_id: '',
  project_id: '',
  is_pinned: false,
  ...props.initialData
})

const filteredContacts = computed(() => {
  if (!formData.value.client_id) return contacts.value
  return contacts.value.filter(c => c.client_id === formData.value.client_id)
})

async function loadData() {
  try {
    await Promise.all([
      clientStore.fetchClients(),
      contactStore.fetchContacts(),
      projectStore.fetchProjects()
    ])
    clients.value = clientStore.clients
    contacts.value = contactStore.contacts
    projects.value = projectStore.projects || []
  } catch (err) {
    console.error('Failed to load form data:', err)
  }
}

function onClientChange() {
  // Reset contact if it doesn't belong to the selected client
  if (formData.value.client_id && formData.value.contact_id) {
    const contact = contacts.value.find(c => c.id === formData.value.contact_id)
    if (contact && contact.client_id !== formData.value.client_id) {
      formData.value.contact_id = ''
    }
  }
}

function handleSubmit() {
  emit('submit', {
    ...formData.value,
    client_id: formData.value.client_id || null,
    contact_id: formData.value.contact_id || null,
    project_id: formData.value.project_id || null
  })
}

watch(() => props.initialData, (newData) => {
  formData.value = { ...formData.value, ...newData }
}, { deep: true })

onMounted(() => loadData())
</script>
