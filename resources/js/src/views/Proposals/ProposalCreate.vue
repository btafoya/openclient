<template>
  <div>
    <PageBreadcrumb pageTitle="Create Proposal" />
    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-6">
      <form @submit.prevent="handleSubmit" class="space-y-6">
        <!-- Client Selection -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Client <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.client_id"
              required
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-gray-900 dark:text-white dark:bg-gray-800 focus:ring-2 focus:ring-brand-500"
            >
              <option value="">Select Client</option>
              <option v-for="client in clients" :key="client.id" :value="client.id">
                {{ client.company_name || client.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Title <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.title"
              type="text"
              required
              placeholder="Proposal Title"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-gray-900 dark:text-white dark:bg-gray-800 focus:ring-2 focus:ring-brand-500"
            />
          </div>
        </div>

        <!-- Dates -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Valid Until <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.valid_until"
              type="date"
              required
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-gray-900 dark:text-white dark:bg-gray-800 focus:ring-2 focus:ring-brand-500"
            />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Template
            </label>
            <select
              v-model="form.template_id"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-gray-900 dark:text-white dark:bg-gray-800 focus:ring-2 focus:ring-brand-500"
              @change="loadTemplate"
            >
              <option value="">No Template</option>
              <option v-for="template in templates" :key="template.id" :value="template.id">
                {{ template.name }}
              </option>
            </select>
          </div>
        </div>

        <!-- Content Sections -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Proposal Content
          </label>
          <div class="space-y-4">
            <div
              v-for="(section, index) in form.sections"
              :key="index"
              class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
            >
              <div class="flex items-center justify-between mb-3">
                <input
                  v-model="section.title"
                  type="text"
                  placeholder="Section Title"
                  class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-gray-900 dark:text-white dark:bg-gray-800 focus:ring-2 focus:ring-brand-500"
                />
                <button
                  type="button"
                  @click="removeSection(index)"
                  class="ml-2 p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
              <textarea
                v-model="section.content"
                rows="4"
                placeholder="Section content..."
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-gray-900 dark:text-white dark:bg-gray-800 focus:ring-2 focus:ring-brand-500"
              ></textarea>
            </div>
            <button
              type="button"
              @click="addSection"
              class="flex items-center gap-2 text-brand-600 hover:text-brand-700"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
              </svg>
              Add Section
            </button>
          </div>
        </div>

        <!-- Line Items -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Pricing / Line Items
          </label>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                  <th class="px-4 py-3 text-left">Description</th>
                  <th class="px-4 py-3 text-right w-24">Qty</th>
                  <th class="px-4 py-3 text-right w-32">Rate</th>
                  <th class="px-4 py-3 text-right w-32">Amount</th>
                  <th class="px-4 py-3 w-12"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in form.items" :key="index" class="border-b dark:border-gray-700">
                  <td class="px-4 py-2">
                    <input
                      v-model="item.description"
                      type="text"
                      placeholder="Item description"
                      class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-white px-2 py-1"
                    />
                  </td>
                  <td class="px-4 py-2">
                    <input
                      v-model.number="item.quantity"
                      type="number"
                      min="1"
                      class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-white px-2 py-1 text-right"
                    />
                  </td>
                  <td class="px-4 py-2">
                    <input
                      v-model.number="item.rate"
                      type="number"
                      step="0.01"
                      min="0"
                      class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-white px-2 py-1 text-right"
                    />
                  </td>
                  <td class="px-4 py-2 text-right font-medium text-gray-900 dark:text-white">
                    {{ formatCurrency(item.quantity * item.rate) }}
                  </td>
                  <td class="px-4 py-2">
                    <button
                      type="button"
                      @click="removeItem(index)"
                      class="p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="5" class="px-4 py-2">
                    <button
                      type="button"
                      @click="addItem"
                      class="text-sm text-brand-600 hover:text-brand-700"
                    >
                      + Add Line Item
                    </button>
                  </td>
                </tr>
                <tr class="bg-gray-50 dark:bg-gray-800">
                  <td colspan="3" class="px-4 py-3 text-right font-medium">Total:</td>
                  <td class="px-4 py-3 text-right font-bold text-lg text-gray-900 dark:text-white">
                    {{ formatCurrency(total) }}
                  </td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- Terms & Notes -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Terms & Conditions
            </label>
            <textarea
              v-model="form.terms"
              rows="4"
              placeholder="Terms and conditions..."
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-gray-900 dark:text-white dark:bg-gray-800 focus:ring-2 focus:ring-brand-500"
            ></textarea>
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Notes
            </label>
            <textarea
              v-model="form.notes"
              rows="4"
              placeholder="Additional notes..."
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-gray-900 dark:text-white dark:bg-gray-800 focus:ring-2 focus:ring-brand-500"
            ></textarea>
          </div>
        </div>

        <!-- Signature Settings -->
        <div class="border-t dark:border-gray-700 pt-6">
          <label class="flex items-center gap-3">
            <input
              v-model="form.require_signature"
              type="checkbox"
              class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"
            />
            <span class="text-sm text-gray-700 dark:text-gray-300">Require electronic signature for acceptance</span>
          </label>
        </div>

        <!-- Error Message -->
        <div v-if="error" class="p-4 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400">
          {{ error }}
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-4 pt-4 border-t dark:border-gray-700">
          <router-link
            to="/proposals"
            class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
          >
            Cancel
          </router-link>
          <button
            type="button"
            @click="handleSubmit(true)"
            :disabled="loading"
            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 disabled:opacity-50"
          >
            Save as Draft
          </button>
          <button
            type="submit"
            :disabled="loading"
            class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 disabled:opacity-50 flex items-center gap-2"
          >
            <svg v-if="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Create Proposal
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useProposalStore } from '@/stores/proposals'
import { useClientStore } from '@/stores/clients'
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue'

const router = useRouter()
const proposalStore = useProposalStore()
const clientStore = useClientStore()

const loading = ref(false)
const error = ref('')
const clients = ref([])
const templates = ref([])

const form = ref({
  client_id: '',
  title: '',
  valid_until: '',
  template_id: '',
  sections: [
    { title: 'Executive Summary', content: '' },
    { title: 'Scope of Work', content: '' },
    { title: 'Timeline', content: '' }
  ],
  items: [
    { description: '', quantity: 1, rate: 0 }
  ],
  terms: '',
  notes: '',
  require_signature: true,
  status: 'draft'
})

const total = computed(() => {
  return form.value.items.reduce((sum, item) => {
    return sum + (item.quantity * item.rate)
  }, 0)
})

function formatCurrency(amount) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(amount || 0)
}

function addSection() {
  form.value.sections.push({ title: '', content: '' })
}

function removeSection(index) {
  form.value.sections.splice(index, 1)
}

function addItem() {
  form.value.items.push({ description: '', quantity: 1, rate: 0 })
}

function removeItem(index) {
  form.value.items.splice(index, 1)
}

async function loadTemplate() {
  if (!form.value.template_id) return

  const template = templates.value.find(t => t.id === form.value.template_id)
  if (template) {
    form.value.sections = JSON.parse(JSON.stringify(template.sections || []))
    form.value.items = JSON.parse(JSON.stringify(template.items || [{ description: '', quantity: 1, rate: 0 }]))
    form.value.terms = template.terms || ''
  }
}

async function handleSubmit(asDraft = false) {
  loading.value = true
  error.value = ''

  try {
    const data = {
      ...form.value,
      status: asDraft ? 'draft' : 'sent',
      total: total.value
    }

    const result = await proposalStore.createProposal(data)
    router.push(`/proposals/${result.id}`)
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to create proposal'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await clientStore.fetchClients()
  clients.value = clientStore.clients

  await proposalStore.fetchTemplates()
  templates.value = proposalStore.templates

  // Set default valid_until to 30 days from now
  const date = new Date()
  date.setDate(date.getDate() + 30)
  form.value.valid_until = date.toISOString().split('T')[0]
})
</script>
