<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div class="text-center">
        <h2 class="mt-6 text-3xl font-extrabold text-gray-900 dark:text-white">
          Client Portal
        </h2>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          Access your invoices, proposals, and payment history
        </p>
      </div>

      <!-- Request Access Form -->
      <div v-if="!linkSent" class="mt-8 space-y-6">
        <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-8">
          <form @submit.prevent="requestAccess" class="space-y-6">
            <div>
              <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Email Address
              </label>
              <input
                id="email"
                v-model="email"
                type="email"
                required
                placeholder="Enter your email address"
                class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-brand-500 focus:border-brand-500 dark:bg-gray-700 dark:text-white"
              />
            </div>

            <div v-if="error" class="text-sm text-red-600 dark:text-red-400">
              {{ error }}
            </div>

            <button
              type="submit"
              :disabled="loading"
              class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-brand-600 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <svg v-if="loading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              {{ loading ? 'Sending...' : 'Send Access Link' }}
            </button>
          </form>

          <div class="mt-6 text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">
              We'll send you a secure link to access your account.
              <br />No password needed.
            </p>
          </div>
        </div>
      </div>

      <!-- Link Sent Confirmation -->
      <div v-else class="mt-8">
        <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-8 text-center">
          <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 dark:bg-green-900/20">
            <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">
            Check Your Email
          </h3>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            We've sent a secure access link to
            <span class="font-medium text-gray-900 dark:text-white">{{ email }}</span>
          </p>
          <p class="mt-4 text-xs text-gray-400 dark:text-gray-500">
            The link will expire in 24 hours. If you don't see the email, check your spam folder.
          </p>
          <button
            @click="linkSent = false"
            class="mt-6 text-sm font-medium text-brand-600 hover:text-brand-500"
          >
            Use a different email address
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { usePortalStore } from '@/stores/portal'

const router = useRouter()
const route = useRoute()
const portalStore = usePortalStore()

const email = ref('')
const loading = ref(false)
const error = ref('')
const linkSent = ref(false)

async function requestAccess() {
  if (!email.value) return

  loading.value = true
  error.value = ''

  try {
    await portalStore.requestMagicLink(email.value)
    linkSent.value = true
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to send access link. Please try again.'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  // Check for magic link token in URL
  const token = route.query.token
  if (token) {
    try {
      await portalStore.authenticateWithToken(token)
      router.push('/portal/dashboard')
    } catch (err) {
      error.value = 'Invalid or expired access link. Please request a new one.'
    }
  }

  // Check for existing session
  if (!token && await portalStore.restoreSession()) {
    router.push('/portal/dashboard')
  }
})
</script>
