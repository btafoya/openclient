<script setup>
import { useUserStore } from '@/stores/user'
import { useRouter, useRoute } from 'vue-router'
import {
  HomeIcon,
  UsersIcon,
  FolderIcon,
  DocumentTextIcon,
  CurrencyDollarIcon,
  CreditCardIcon,
  Cog6ToothIcon,
  UserGroupIcon,
  ArrowRightOnRectangleIcon
} from '@heroicons/vue/24/outline'

const userStore = useUserStore()
const router = useRouter()
const route = useRoute()

const isActive = (path) => route.path === path || route.path.startsWith(path + '/')

const logout = async () => {
  // POST to logout endpoint
  const form = document.createElement('form')
  form.method = 'POST'
  form.action = '/auth/logout'
  document.body.appendChild(form)
  form.submit()
}
</script>

<template>
  <aside class="w-64 bg-gray-800 text-white min-h-screen flex flex-col">
    <!-- Logo -->
    <div class="p-4 border-b border-gray-700">
      <h1 class="text-2xl font-bold text-white">openclient</h1>
      <p class="text-xs text-gray-400 mt-1">Multi-Agency CRM</p>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 p-4 overflow-y-auto">
      <!-- Dashboard (everyone) -->
      <router-link
        to="/dashboard"
        class="flex items-center px-4 py-3 mb-2 rounded transition-colors"
        :class="isActive('/dashboard') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'"
      >
        <HomeIcon class="w-5 h-5 mr-3" />
        <span>Dashboard</span>
      </router-link>

      <!-- Clients (everyone) -->
      <router-link
        to="/clients"
        class="flex items-center px-4 py-3 mb-2 rounded transition-colors"
        :class="isActive('/clients') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'"
      >
        <UsersIcon class="w-5 h-5 mr-3" />
        <span>Clients</span>
      </router-link>

      <!-- Projects (everyone) -->
      <router-link
        to="/projects"
        class="flex items-center px-4 py-3 mb-2 rounded transition-colors"
        :class="isActive('/projects') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'"
      >
        <FolderIcon class="w-5 h-5 mr-3" />
        <span>Projects</span>
      </router-link>

      <!-- Financial section (hidden for End Clients) -->
      <template v-if="userStore.canViewFinancials">
        <div class="mt-6 mb-2 px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
          Financial
        </div>

        <router-link
          to="/invoices"
          class="flex items-center px-4 py-3 mb-2 rounded transition-colors"
          :class="isActive('/invoices') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'"
        >
          <DocumentTextIcon class="w-5 h-5 mr-3" />
          <span>Invoices</span>
        </router-link>

        <router-link
          to="/quotes"
          class="flex items-center px-4 py-3 mb-2 rounded transition-colors"
          :class="isActive('/quotes') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'"
        >
          <DocumentTextIcon class="w-5 h-5 mr-3" />
          <span>Quotes</span>
        </router-link>

        <router-link
          to="/payments"
          class="flex items-center px-4 py-3 mb-2 rounded transition-colors"
          :class="isActive('/payments') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'"
        >
          <CreditCardIcon class="w-5 h-5 mr-3" />
          <span>Payments</span>
        </router-link>
      </template>

      <!-- Admin section (Owner only) -->
      <template v-if="userStore.isOwner">
        <div class="mt-6 mb-2 px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
          Administration
        </div>

        <router-link
          to="/admin/users"
          class="flex items-center px-4 py-3 mb-2 rounded transition-colors"
          :class="isActive('/admin/users') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'"
        >
          <UserGroupIcon class="w-5 h-5 mr-3" />
          <span>Users</span>
        </router-link>

        <router-link
          to="/admin/agencies"
          class="flex items-center px-4 py-3 mb-2 rounded transition-colors"
          :class="isActive('/admin/agencies') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'"
        >
          <UsersIcon class="w-5 h-5 mr-3" />
          <span>Agencies</span>
        </router-link>

        <router-link
          to="/admin/settings"
          class="flex items-center px-4 py-3 mb-2 rounded transition-colors"
          :class="isActive('/admin/settings') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'"
        >
          <Cog6ToothIcon class="w-5 h-5 mr-3" />
          <span>Settings</span>
        </router-link>
      </template>

      <!-- Agency Settings (Agency role) -->
      <template v-if="userStore.isAgency">
        <div class="mt-6 mb-2 px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">
          Settings
        </div>

        <router-link
          to="/agency/settings"
          class="flex items-center px-4 py-3 mb-2 rounded transition-colors"
          :class="isActive('/agency/settings') ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white'"
        >
          <Cog6ToothIcon class="w-5 h-5 mr-3" />
          <span>Agency Settings</span>
        </router-link>
      </template>
    </nav>

    <!-- User info footer + Logout -->
    <div class="border-t border-gray-700 bg-gray-900">
      <!-- User Info -->
      <div class="p-4">
        <div class="flex items-center">
          <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-semibold">
            {{ userStore.initials }}
          </div>
          <div class="ml-3 flex-1 min-w-0">
            <p class="text-sm font-semibold text-white truncate">{{ userStore.fullName }}</p>
            <p class="text-xs text-gray-400 truncate">{{ userStore.roleDisplay }}</p>
          </div>
        </div>
      </div>

      <!-- Logout Button -->
      <button
        @click="logout"
        class="w-full flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors border-t border-gray-700"
      >
        <ArrowRightOnRectangleIcon class="w-5 h-5 mr-3" />
        <span>Logout</span>
      </button>
    </div>
  </aside>
</template>

<style scoped>
/* Smooth transitions for active state */
.router-link-active {
  @apply bg-blue-600 text-white;
}
</style>
