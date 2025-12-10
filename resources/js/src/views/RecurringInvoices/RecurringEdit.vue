<template>
  <div>
    <PageBreadcrumb pageTitle="Edit Recurring Schedule" />

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-brand-500"></div>
    </div>

    <div v-else class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-6">
      <form @submit.prevent="handleSubmit" class="space-y-6">
        <!-- Basic Info -->
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
              Title/Description <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.title"
              type="text"
              required
              placeholder="Monthly Hosting Fee"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-gray-900 dark:text-white dark:bg-gray-800 focus:ring-2 focus:ring-brand-500"
            />
          </div>
        </div>

        <!-- Frequency -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Frequency <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.frequency"
              required
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-gray-900 dark:text-white dark:bg-gray-800 focus:ring-2 focus:ring-brand-500"
            >
              <option value="weekly">Weekly</option>
              <option value="bi-weekly">Bi-weekly</option>
              <option value="monthly">Monthly</option>
              <option value="quarterly">Quarterly</option>
              <option value="semi-annually">Semi-Annually</option>
              <option value="annually">Annually</option>
            </select>
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Day of Month
            </label>
            <select
              v-model="form.day_of_month"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-gray-900 dark:text-white dark:bg-gray-800 focus:ring-2 focus:ring-brand-500"
            >
              <option v-for="day in 28" :key="day" :value="day">{{ day }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Status
            </label>
            <select
              v-model="form.status"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-gray-900 dark:text-white dark:bg-gray-800 focus:ring-2 focus:ring-brand-500"
            >
              <option value="active">Active</option>
              <option value="paused">Paused</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>

        <!-- Dates -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              End Date (optional)
            </label>
            <input
              v-model="form.end_date"
              type="date"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-gray-900 dark:text-white dark:bg-gray-800 focus:ring-2 focus:ring-brand-500"
            />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave empty for indefinite billing</p>
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Max Invoices (optional)
            </label>
            <input
              v-model.number="form.max_invoices"
              type="number"
              min="1"
              placeholder="No limit"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-gray-900 dark:text-white dark:bg-gray-800 focus:ring-2 focus:ring-brand-500"
            />
          </div>
        </div>

        <!-- Invoice Template -->
        <div class="border-t dark:border-gray-700 pt-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Invoice Template</h3>

          <!-- Line Items -->
          <div class="mb-6">
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
              Line Items
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
                        v-if="form.items.length > 1"
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
                    <td colspan="3" class="px-4 py-3 text-right font-medium">Total per Invoice:</td>
                    <td class="px-4 py-3 text-right font-bold text-lg text-gray-900 dark:text-white">
                      {{ formatCurrency(total) }}
                    </td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <!-- Tax -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Tax Rate (%)
              </label>
              <input
                v-model.number="form.tax_rate"
                type="number"
                step="0.01"
                min="0"
                max="100"
                placeholder="0"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-gray-900 dark:text-white dark:bg-gray-800 focus:ring-2 focus:ring-brand-500"
              />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Payment Due
              </label>
              <select
                v-model="form.payment_terms"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-gray-900 dark:text-white dark:bg-gray-800 focus:ring-2 focus:ring-brand-500"
              >
                <option value="due_on_receipt">Due on Receipt</option>
                <option value="net_15">Net 15</option>
                <option value="net_30">Net 30</option>
                <option value="net_45">Net 45</option>
                <option value="net_60">Net 60</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Notes (included on each invoice)
          </label>
          <textarea
            v-model="form.notes"
            rows="3"
            placeholder="Notes to include on generated invoices..."
            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-gray-900 dark:text-white dark:bg-gray-800 focus:ring-2 focus:ring-brand-500"
          ></textarea>
        </div>

        <!-- Settings -->
        <div class="border-t dark:border-gray-700 pt-6 space-y-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Settings</h3>

          <label class="flex items-center gap-3">
            <input
              v-model="form.auto_send"
              type="checkbox"
              class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"
            />
            <span class="text-sm text-gray-700 dark:text-gray-300">Automatically send invoice to client when generated</span>
          </label>

          <label class="flex items-center gap-3">
            <input
              v-model="form.auto_payment"
              type="checkbox"
              class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"
            />
            <span class="text-sm text-gray-700 dark:text-gray-300">Automatically charge saved payment method</span>
          </label>
        </div>

        <!-- Error Message -->
        <div v-if="error" class="p-4 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400">
          {{ error }}
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-4 pt-4 border-t dark:border-gray-700">
          <router-link
            :to="`/recurring-invoices/${route.params.id}`"
            class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
          >
            Cancel
          </router-link>
          <button
            type="submit"
            :disabled="saving"
            class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 disabled:opacity-50 flex items-center gap-2"
          >
            <svg v-if="saving" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useRecurringInvoiceStore } from '@/stores/recurringInvoices'
import { useClientStore } from '@/stores/clients'
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue'

const route = useRoute()
const router = useRouter()
const recurringStore = useRecurringInvoiceStore()
const clientStore = useClientStore()

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const clients = ref([])

const form = ref({
  client_id: '',
  title: '',
  frequency: 'monthly',
  day_of_month: 1,
  status: 'active',
  end_date: '',
  max_invoices: null,
  items: [],
  tax_rate: 0,
  payment_terms: 'net_30',
  notes: '',
  auto_send: false,
  auto_payment: false
})

const total = computed(() => {
  const subtotal = form.value.items.reduce((sum, item) => {
    return sum + (item.quantity * item.rate)
  }, 0)
  const tax = subtotal * (form.value.tax_rate / 100)
  return subtotal + tax
})

function formatCurrency(amount) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(amount || 0)
}

function addItem() {
  form.value.items.push({ description: '', quantity: 1, rate: 0 })
}

function removeItem(index) {
  form.value.items.splice(index, 1)
}

async function handleSubmit() {
  saving.value = true
  error.value = ''

  try {
    await recurringStore.updateSchedule(route.params.id, {
      ...form.value,
      amount: total.value
    })
    router.push(`/recurring-invoices/${route.params.id}`)
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to update schedule'
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    await clientStore.fetchClients()
    clients.value = clientStore.clients

    const schedule = await recurringStore.fetchSchedule(route.params.id)
    form.value = {
      client_id: schedule.client_id,
      title: schedule.title,
      frequency: schedule.frequency,
      day_of_month: schedule.day_of_month,
      status: schedule.status,
      end_date: schedule.end_date?.split('T')[0] || '',
      max_invoices: schedule.max_invoices,
      items: schedule.items || [{ description: '', quantity: 1, rate: 0 }],
      tax_rate: schedule.tax_rate || 0,
      payment_terms: schedule.payment_terms || 'net_30',
      notes: schedule.notes || '',
      auto_send: schedule.auto_send ?? false,
      auto_payment: schedule.auto_payment ?? false
    }
  } catch (err) {
    console.error('Failed to load schedule:', err)
  } finally {
    loading.value = false
  }
})
</script>
