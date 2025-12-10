<template>
  <AdminLayout>
    <div class="space-y-6">
      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center py-12">
        <div class="inline-block w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
      </div>

      <!-- Error State -->
      <div v-else-if="loadError" class="p-8 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/20">
          <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </div>
        <p class="mt-4 text-sm text-gray-900 dark:text-white font-medium">{{ loadError }}</p>
        <router-link to="/invoices" class="mt-4 inline-block px-4 py-2 text-sm font-medium text-brand-600 hover:text-brand-700">
          Back to Invoices
        </router-link>
      </div>

      <!-- Not Editable State -->
      <div v-else-if="invoice && invoice.status !== 'draft'" class="p-8 text-center">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-yellow-100 dark:bg-yellow-900/20">
          <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <p class="mt-4 text-sm text-gray-900 dark:text-white font-medium">
          Only draft invoices can be edited
        </p>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          This invoice has status: <span class="font-medium capitalize">{{ invoice.status }}</span>
        </p>
        <router-link :to="`/invoices/${invoice.id}`" class="mt-4 inline-block px-4 py-2 text-sm font-medium text-brand-600 hover:text-brand-700">
          View Invoice
        </router-link>
      </div>

      <!-- Edit Form -->
      <template v-else-if="invoice">
        <!-- Page Header -->
        <div class="flex items-center gap-3">
          <router-link
            :to="`/invoices/${invoice.id}`"
            class="p-1.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </router-link>
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Invoice</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              {{ invoice.invoice_number }}
            </p>
          </div>
        </div>

        <!-- Invoice Form -->
        <form @submit.prevent="updateInvoice" class="space-y-6">
          <!-- Basic Info -->
          <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Invoice Details</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Client -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Client <span class="text-red-500">*</span>
                </label>
                <select
                  v-model="form.client_id"
                  required
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                >
                  <option value="">Select a client...</option>
                  <option v-for="client in clients" :key="client.id" :value="client.id">
                    {{ client.name }}
                  </option>
                </select>
              </div>

              <!-- Project -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Project (Optional)
                </label>
                <select
                  v-model="form.project_id"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                >
                  <option value="">No project</option>
                  <option v-for="project in filteredProjects" :key="project.id" :value="project.id">
                    {{ project.name }}
                  </option>
                </select>
              </div>

              <!-- Invoice Date -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Invoice Date <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="form.invoice_date"
                  type="date"
                  required
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                />
              </div>

              <!-- Due Date -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Due Date <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="form.due_date"
                  type="date"
                  required
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                />
              </div>
            </div>
          </div>

          <!-- Line Items -->
          <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-6">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Line Items</h2>
              <button
                type="button"
                @click="addLineItem"
                class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-brand-600 hover:text-brand-700"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Item
              </button>
            </div>

            <div class="space-y-4">
              <div
                v-for="(item, index) in form.line_items"
                :key="index"
                class="grid grid-cols-12 gap-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg"
              >
                <!-- Description -->
                <div class="col-span-12 sm:col-span-5">
                  <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Description</label>
                  <input
                    v-model="item.description"
                    type="text"
                    required
                    placeholder="Service or product description"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                  />
                </div>

                <!-- Quantity -->
                <div class="col-span-4 sm:col-span-2">
                  <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Qty</label>
                  <input
                    v-model.number="item.quantity"
                    type="number"
                    min="0.01"
                    step="0.01"
                    required
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                  />
                </div>

                <!-- Rate -->
                <div class="col-span-4 sm:col-span-2">
                  <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Rate ($)</label>
                  <input
                    v-model.number="item.rate"
                    type="number"
                    min="0"
                    step="0.01"
                    required
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                  />
                </div>

                <!-- Amount -->
                <div class="col-span-3 sm:col-span-2">
                  <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Amount</label>
                  <p class="px-3 py-2 text-sm font-medium text-gray-900 dark:text-white">
                    ${{ formatCurrency(item.quantity * item.rate) }}
                  </p>
                </div>

                <!-- Remove Button -->
                <div class="col-span-1 flex items-end">
                  <button
                    type="button"
                    @click="removeLineItem(index)"
                    :disabled="form.line_items.length === 1"
                    class="p-2 text-gray-400 hover:text-red-600 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <!-- Totals -->
            <div class="mt-6 flex flex-col items-end space-y-3">
              <div class="flex items-center gap-4 w-72">
                <span class="text-gray-600 dark:text-gray-400">Subtotal</span>
                <span class="ml-auto font-medium text-gray-900 dark:text-white">${{ formatCurrency(subtotal) }}</span>
              </div>

              <div class="flex items-center gap-4 w-72">
                <span class="text-gray-600 dark:text-gray-400">Discount</span>
                <div class="ml-auto flex items-center gap-2">
                  <span class="text-gray-500">$</span>
                  <input
                    v-model.number="form.discount"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-24 px-2 py-1 text-sm text-right border border-gray-300 rounded focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                  />
                </div>
              </div>

              <div class="flex items-center gap-4 w-72">
                <span class="text-gray-600 dark:text-gray-400">Tax Rate</span>
                <div class="ml-auto flex items-center gap-2">
                  <input
                    v-model.number="form.tax_rate"
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    class="w-20 px-2 py-1 text-sm text-right border border-gray-300 rounded focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                  />
                  <span class="text-gray-500">%</span>
                </div>
              </div>

              <div class="flex items-center gap-4 w-72">
                <span class="text-gray-600 dark:text-gray-400">Tax Amount</span>
                <span class="ml-auto font-medium text-gray-900 dark:text-white">${{ formatCurrency(taxAmount) }}</span>
              </div>

              <div class="flex items-center gap-4 w-72 pt-3 border-t border-gray-200 dark:border-gray-700">
                <span class="text-lg font-semibold text-gray-900 dark:text-white">Total</span>
                <span class="ml-auto text-lg font-bold text-gray-900 dark:text-white">${{ formatCurrency(total) }}</span>
              </div>
            </div>
          </div>

          <!-- Notes & Terms -->
          <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Additional Information</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Notes -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Notes (visible to client)
                </label>
                <textarea
                  v-model="form.notes"
                  rows="4"
                  placeholder="Additional notes or instructions..."
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                ></textarea>
              </div>

              <!-- Terms -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Terms & Conditions
                </label>
                <textarea
                  v-model="form.terms"
                  rows="4"
                  placeholder="Payment terms, late fees, etc..."
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                ></textarea>
              </div>
            </div>
          </div>

          <!-- Error Message -->
          <div v-if="error" class="p-4 rounded-lg bg-red-50 dark:bg-red-900/20">
            <p class="text-sm text-red-700 dark:text-red-400">{{ error }}</p>
          </div>

          <!-- Form Actions -->
          <div class="flex items-center justify-end gap-4">
            <router-link
              :to="`/invoices/${invoice.id}`"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600"
            >
              Cancel
            </router-link>
            <button
              type="submit"
              :disabled="saving"
              class="px-6 py-2 text-sm font-medium text-white bg-brand-600 rounded-lg hover:bg-brand-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ saving ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </form>
      </template>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useInvoiceStore } from '@/stores/invoices'
import { useClientStore } from '@/stores/clients'
import { useProjectStore } from '@/stores/projects'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const route = useRoute()
const router = useRouter()
const invoiceStore = useInvoiceStore()
const clientStore = useClientStore()
const projectStore = useProjectStore()

const loading = ref(true)
const loadError = ref(null)
const saving = ref(false)
const error = ref(null)

// Form data
const form = ref({
  client_id: '',
  project_id: '',
  invoice_date: '',
  due_date: '',
  discount: 0,
  tax_rate: 0,
  notes: '',
  terms: '',
  line_items: []
})

// Computed
const invoice = computed(() => invoiceStore.currentInvoice)
const clients = computed(() => clientStore.activeClients)
const filteredProjects = computed(() => {
  if (!form.value.client_id) return projectStore.activeProjects
  return projectStore.projects.filter(p => p.client_id === form.value.client_id)
})

const subtotal = computed(() => {
  return form.value.line_items.reduce((sum, item) => {
    return sum + (item.quantity * item.rate)
  }, 0)
})

const taxAmount = computed(() => {
  const taxableAmount = subtotal.value - (form.value.discount || 0)
  return taxableAmount * ((form.value.tax_rate || 0) / 100)
})

const total = computed(() => {
  return subtotal.value - (form.value.discount || 0) + taxAmount.value
})

// Methods
function addLineItem() {
  form.value.line_items.push({ description: '', quantity: 1, rate: 0 })
}

function removeLineItem(index) {
  if (form.value.line_items.length > 1) {
    form.value.line_items.splice(index, 1)
  }
}

async function loadInvoice() {
  loading.value = true
  loadError.value = null
  try {
    await Promise.all([
      invoiceStore.fetchInvoice(route.params.id),
      clientStore.fetchClients(),
      projectStore.fetchProjects()
    ])

    // Populate form with invoice data
    if (invoiceStore.currentInvoice) {
      const inv = invoiceStore.currentInvoice
      form.value = {
        client_id: inv.client_id || '',
        project_id: inv.project_id || '',
        invoice_date: inv.invoice_date?.split('T')[0] || '',
        due_date: inv.due_date?.split('T')[0] || '',
        discount: inv.discount || 0,
        tax_rate: inv.tax_rate || 0,
        notes: inv.notes || '',
        terms: inv.terms || '',
        line_items: inv.line_items?.length > 0
          ? inv.line_items.map(item => ({
              id: item.id,
              description: item.description,
              quantity: item.quantity,
              rate: item.rate
            }))
          : [{ description: '', quantity: 1, rate: 0 }]
      }
    }
  } catch (err) {
    loadError.value = err.response?.data?.error || 'Failed to load invoice'
  } finally {
    loading.value = false
  }
}

async function updateInvoice() {
  saving.value = true
  error.value = null

  try {
    // Prepare line items with calculated amounts
    const lineItems = form.value.line_items.map(item => ({
      ...item,
      amount: item.quantity * item.rate
    }))

    const invoiceData = {
      ...form.value,
      line_items: lineItems,
      subtotal: subtotal.value,
      tax: taxAmount.value,
      total: total.value
    }

    await invoiceStore.updateInvoice(route.params.id, invoiceData)
    router.push(`/invoices/${route.params.id}`)
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to update invoice'
  } finally {
    saving.value = false
  }
}

function formatCurrency(value) {
  return Number(value || 0).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

// Lifecycle
onMounted(() => {
  loadInvoice()
})
</script>
