<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 overflow-y-auto"
    @click.self="$emit('close')"
  >
    <div class="flex min-h-screen items-center justify-center p-4">
      <!-- Backdrop -->
      <div class="fixed inset-0 bg-black/50 transition-opacity" @click="$emit('close')"></div>

      <!-- Modal -->
      <div class="relative w-full max-w-2xl transform rounded-xl bg-white shadow-2xl transition-all dark:bg-gray-900">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ deal?.id ? 'Edit Deal' : 'Create Deal' }}
          </h3>
          <button
            @click="$emit('close')"
            class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleSubmit" class="p-6">
          <div class="grid grid-cols-2 gap-4">
            <!-- Deal Name -->
            <div class="col-span-2">
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Deal Name *
              </label>
              <input
                v-model="formData.name"
                type="text"
                required
                class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                placeholder="Enter deal name"
              />
            </div>

            <!-- Client -->
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Client
              </label>
              <select
                v-model="formData.client_id"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
              >
                <option value="">Select a client</option>
                <option v-for="client in clients" :key="client.id" :value="client.id">
                  {{ client.name }}
                </option>
              </select>
            </div>

            <!-- Stage -->
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Stage *
              </label>
              <select
                v-model="formData.stage_id"
                required
                class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
              >
                <option value="">Select a stage</option>
                <option v-for="stage in stages" :key="stage.id" :value="stage.id">
                  {{ stage.name }} ({{ stage.probability }}%)
                </option>
              </select>
            </div>

            <!-- Value -->
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Deal Value
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                <input
                  v-model.number="formData.value"
                  type="number"
                  min="0"
                  step="0.01"
                  class="w-full rounded-lg border border-gray-300 pl-7 pr-3 py-2 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                  placeholder="0.00"
                />
              </div>
            </div>

            <!-- Currency -->
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Currency
              </label>
              <select
                v-model="formData.currency"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
              >
                <option value="USD">USD - US Dollar</option>
                <option value="EUR">EUR - Euro</option>
                <option value="GBP">GBP - British Pound</option>
                <option value="CAD">CAD - Canadian Dollar</option>
                <option value="AUD">AUD - Australian Dollar</option>
              </select>
            </div>

            <!-- Expected Close Date -->
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Expected Close Date
              </label>
              <input
                v-model="formData.expected_close_date"
                type="date"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
              />
            </div>

            <!-- Priority -->
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Priority
              </label>
              <select
                v-model="formData.priority"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
              >
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
              </select>
            </div>

            <!-- Source -->
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Source
              </label>
              <input
                v-model="formData.source"
                type="text"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                placeholder="e.g., Website, Referral, Cold Call"
              />
            </div>

            <!-- Assigned To -->
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Assigned To
              </label>
              <select
                v-model="formData.assigned_to"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
              >
                <option value="">Unassigned</option>
                <option v-for="user in users" :key="user.id" :value="user.id">
                  {{ user.name }}
                </option>
              </select>
            </div>

            <!-- Description -->
            <div class="col-span-2">
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Description
              </label>
              <textarea
                v-model="formData.description"
                rows="3"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                placeholder="Add any notes or description..."
              ></textarea>
            </div>
          </div>

          <!-- Footer -->
          <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-200 pt-4 dark:border-gray-700">
            <button
              type="button"
              @click="$emit('close')"
              class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {{ isSubmitting ? 'Saving...' : (deal?.id ? 'Update Deal' : 'Create Deal') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, watch } from 'vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  deal: {
    type: Object,
    default: null
  },
  pipelineId: {
    type: String,
    required: true
  },
  stages: {
    type: Array,
    default: () => []
  },
  clients: {
    type: Array,
    default: () => []
  },
  users: {
    type: Array,
    default: () => []
  },
  defaultStageId: {
    type: String,
    default: null
  }
})

const emit = defineEmits(['close', 'save'])

const isSubmitting = ref(false)

const getInitialFormData = () => ({
  name: '',
  client_id: '',
  stage_id: props.defaultStageId || '',
  value: null,
  currency: 'USD',
  expected_close_date: '',
  priority: 'medium',
  source: '',
  assigned_to: '',
  description: ''
})

const formData = reactive(getInitialFormData())

// Reset form when modal opens/closes or deal changes
watch(
  () => [props.isOpen, props.deal],
  () => {
    if (props.isOpen) {
      if (props.deal) {
        Object.assign(formData, {
          name: props.deal.name || '',
          client_id: props.deal.client_id || '',
          stage_id: props.deal.stage_id || props.defaultStageId || '',
          value: props.deal.value || null,
          currency: props.deal.currency || 'USD',
          expected_close_date: props.deal.expected_close_date || '',
          priority: props.deal.priority || 'medium',
          source: props.deal.source || '',
          assigned_to: props.deal.assigned_to || '',
          description: props.deal.description || ''
        })
      } else {
        Object.assign(formData, getInitialFormData())
        formData.stage_id = props.defaultStageId || (props.stages[0]?.id || '')
      }
    }
  },
  { immediate: true }
)

async function handleSubmit() {
  isSubmitting.value = true

  try {
    const data = {
      ...formData,
      pipeline_id: props.pipelineId
    }

    if (props.deal?.id) {
      data.id = props.deal.id
    }

    emit('save', data)
  } finally {
    isSubmitting.value = false
  }
}
</script>
