<script setup>
import { computed } from 'vue'

const props = defineProps({
  title: {
    type: String,
    required: true
  },
  value: {
    type: [String, Number],
    required: true
  },
  icon: {
    type: String,
    required: true
  },
  trend: {
    type: Object,
    default: null,
    validator: (value) => {
      if (!value) return true
      return ['up', 'down', 'neutral'].includes(value.direction) &&
             typeof value.value === 'string'
    }
  },
  color: {
    type: String,
    default: 'primary',
    validator: (value) => {
      return ['primary', 'success', 'warning', 'danger', 'info'].includes(value)
    }
  }
})

const iconColorClass = computed(() => {
  const colors = {
    primary: 'text-primary-600 bg-primary-100',
    success: 'text-green-600 bg-green-100',
    warning: 'text-yellow-600 bg-yellow-100',
    danger: 'text-red-600 bg-red-100',
    info: 'text-blue-600 bg-blue-100'
  }
  return colors[props.color] || colors.primary
})

const trendColorClass = computed(() => {
  if (!props.trend) return ''

  const colors = {
    up: 'text-green-600',
    down: 'text-red-600',
    neutral: 'text-gray-600'
  }
  return colors[props.trend.direction] || colors.neutral
})

const trendIcon = computed(() => {
  if (!props.trend) return null

  const icons = {
    up: 'M5 10l7-7m0 0l7 7m-7-7v18',
    down: 'M19 14l-7 7m0 0l-7-7m7 7V3',
    neutral: 'M5 12h14'
  }
  return icons[props.trend.direction]
})
</script>

<template>
  <div class="card">
    <div class="card-body">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <div :class="['w-12 h-12 rounded-lg flex items-center justify-center', iconColorClass]">
            <svg
              class="w-6 h-6"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                :d="icon"
              />
            </svg>
          </div>
        </div>

        <div class="ml-5 w-0 flex-1">
          <dl>
            <dt class="text-sm font-medium text-gray-500 truncate">
              {{ title }}
            </dt>
            <dd class="flex items-baseline">
              <div class="text-2xl font-semibold text-gray-900">
                {{ value }}
              </div>

              <div
                v-if="trend"
                :class="['ml-2 flex items-baseline text-sm font-semibold', trendColorClass]"
              >
                <svg
                  class="self-center flex-shrink-0 h-4 w-4"
                  fill="currentColor"
                  viewBox="0 0 20 20"
                  aria-hidden="true"
                >
                  <path
                    fill-rule="evenodd"
                    :d="trendIcon"
                    clip-rule="evenodd"
                  />
                </svg>
                <span class="ml-1">
                  {{ trend.value }}
                </span>
              </div>
            </dd>
          </dl>
        </div>
      </div>
    </div>
  </div>
</template>
