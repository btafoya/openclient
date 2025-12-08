<script setup>
import { ref } from 'vue'
import { useUserStore } from '@/stores/user'
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import {
  Bars3Icon,
  BellIcon,
  UserCircleIcon,
  Cog6ToothIcon,
  ArrowRightOnRectangleIcon
} from '@heroicons/vue/24/outline'

const emit = defineEmits(['toggle-sidebar'])
const userStore = useUserStore()

const notifications = ref([
  { id: 1, message: 'New invoice #1234 created', time: '5 min ago', unread: true },
  { id: 2, message: 'Payment received for invoice #1230', time: '1 hour ago', unread: true },
  { id: 3, message: 'Project "Website Redesign" completed', time: '2 hours ago', unread: false },
])

const unreadCount = ref(notifications.value.filter(n => n.unread).length)

const toggleSidebar = () => {
  emit('toggle-sidebar')
}

const logout = () => {
  // POST to logout endpoint
  const form = document.createElement('form')
  form.method = 'POST'
  form.action = '/auth/logout'
  document.body.appendChild(form)
  form.submit()
}
</script>

<template>
  <header class="bg-white shadow-sm border-b border-gray-200">
    <div class="flex items-center justify-between px-6 py-4">
      <!-- Left: Mobile menu button + Page title -->
      <div class="flex items-center">
        <button
          @click="toggleSidebar"
          class="mr-4 p-2 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 lg:hidden"
        >
          <Bars3Icon class="w-6 h-6" />
        </button>

        <div>
          <h2 class="text-xl font-semibold text-gray-800">
            <slot name="title">Dashboard</slot>
          </h2>
          <p class="text-sm text-gray-500 mt-0.5">
            <slot name="subtitle"></slot>
          </p>
        </div>
      </div>

      <!-- Right: Notifications + User dropdown -->
      <div class="flex items-center space-x-4">
        <!-- Notifications Dropdown -->
        <Menu as="div" class="relative">
          <MenuButton class="relative p-2 rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100">
            <BellIcon class="w-6 h-6" />
            <span v-if="unreadCount > 0" class="absolute top-1 right-1 flex h-4 w-4">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-4 w-4 bg-red-500 text-white text-xs items-center justify-center">
                {{ unreadCount }}
              </span>
            </span>
          </MenuButton>

          <transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
          >
            <MenuItems class="absolute right-0 mt-2 w-80 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
              <div class="px-4 py-3 border-b border-gray-100">
                <p class="text-sm font-semibold text-gray-900">Notifications</p>
              </div>
              <div class="py-1 max-h-96 overflow-y-auto">
                <MenuItem v-for="notification in notifications" :key="notification.id">
                  <a
                    href="#"
                    class="block px-4 py-3 hover:bg-gray-50"
                    :class="{ 'bg-blue-50': notification.unread }"
                  >
                    <p class="text-sm text-gray-900">{{ notification.message }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ notification.time }}</p>
                  </a>
                </MenuItem>
              </div>
              <div class="px-4 py-3 border-t border-gray-100">
                <a href="/notifications" class="text-sm text-blue-600 hover:text-blue-800">
                  View all notifications
                </a>
              </div>
            </MenuItems>
          </transition>
        </Menu>

        <!-- User Dropdown -->
        <Menu as="div" class="relative">
          <MenuButton class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-100">
            <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-semibold">
              {{ userStore.initials }}
            </div>
            <div class="hidden md:block text-left">
              <p class="text-sm font-medium text-gray-700">{{ userStore.fullName }}</p>
              <p class="text-xs text-gray-500">{{ userStore.roleDisplay }}</p>
            </div>
          </MenuButton>

          <transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
          >
            <MenuItems class="absolute right-0 mt-2 w-56 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
              <div class="px-4 py-3 border-b border-gray-100">
                <p class="text-sm font-semibold text-gray-900">{{ userStore.fullName }}</p>
                <p class="text-xs text-gray-500 truncate">{{ userStore.email }}</p>
              </div>

              <div class="py-1">
                <MenuItem v-slot="{ active }">
                  <a
                    href="/profile"
                    :class="[
                      active ? 'bg-gray-100' : '',
                      'flex items-center px-4 py-2 text-sm text-gray-700'
                    ]"
                  >
                    <UserCircleIcon class="w-5 h-5 mr-3 text-gray-400" />
                    Your Profile
                  </a>
                </MenuItem>

                <MenuItem v-slot="{ active }" v-if="userStore.canManageAgencySettings">
                  <a
                    href="/agency/settings"
                    :class="[
                      active ? 'bg-gray-100' : '',
                      'flex items-center px-4 py-2 text-sm text-gray-700'
                    ]"
                  >
                    <Cog6ToothIcon class="w-5 h-5 mr-3 text-gray-400" />
                    Settings
                  </a>
                </MenuItem>
              </div>

              <div class="py-1 border-t border-gray-100">
                <MenuItem v-slot="{ active }">
                  <button
                    @click="logout"
                    :class="[
                      active ? 'bg-gray-100' : '',
                      'flex items-center w-full px-4 py-2 text-sm text-gray-700'
                    ]"
                  >
                    <ArrowRightOnRectangleIcon class="w-5 h-5 mr-3 text-gray-400" />
                    Sign out
                  </button>
                </MenuItem>
              </div>
            </MenuItems>
          </transition>
        </Menu>
      </div>
    </div>
  </header>
</template>

<style scoped>
/* Custom transition for dropdown menus */
</style>
