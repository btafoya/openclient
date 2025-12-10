<template>
  <div
    :class="[
      'inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full',
      statusClasses
    ]"
  >
    <!-- Status Icon -->
    <component :is="statusIcon" class="w-3.5 h-3.5" />
    <span>{{ statusLabel }}</span>
  </div>
</template>

<script setup>
import { computed, h } from 'vue'
import { usePaymentStore } from '@/stores/payments'

const props = defineProps({
  status: {
    type: String,
    required: true
  }
})

const paymentStore = usePaymentStore()

// Computed
const statusDisplay = computed(() => paymentStore.getStatusDisplay(props.status))

const statusLabel = computed(() => statusDisplay.value.label)

const statusClasses = computed(() => {
  const colorMap = {
    yellow: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
    green: 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400',
    red: 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400',
    gray: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
  }
  return colorMap[statusDisplay.value.color] || colorMap.gray
})

// Status Icons
const PendingIcon = {
  render() {
    return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
      h('path', {
        'stroke-linecap': 'round',
        'stroke-linejoin': 'round',
        'stroke-width': '2',
        d: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'
      })
    ])
  }
}

const ProcessingIcon = {
  render() {
    return h('svg', { fill: 'none', viewBox: '0 0 24 24', class: 'animate-spin' }, [
      h('circle', {
        class: 'opacity-25',
        cx: '12',
        cy: '12',
        r: '10',
        stroke: 'currentColor',
        'stroke-width': '4'
      }),
      h('path', {
        class: 'opacity-75',
        fill: 'currentColor',
        d: 'M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z'
      })
    ])
  }
}

const SuccessIcon = {
  render() {
    return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
      h('path', {
        'stroke-linecap': 'round',
        'stroke-linejoin': 'round',
        'stroke-width': '2',
        d: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
      })
    ])
  }
}

const FailedIcon = {
  render() {
    return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
      h('path', {
        'stroke-linecap': 'round',
        'stroke-linejoin': 'round',
        'stroke-width': '2',
        d: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'
      })
    ])
  }
}

const RefundedIcon = {
  render() {
    return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
      h('path', {
        'stroke-linecap': 'round',
        'stroke-linejoin': 'round',
        'stroke-width': '2',
        d: 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6'
      })
    ])
  }
}

const CancelledIcon = {
  render() {
    return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
      h('path', {
        'stroke-linecap': 'round',
        'stroke-linejoin': 'round',
        'stroke-width': '2',
        d: 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636'
      })
    ])
  }
}

const statusIcon = computed(() => {
  const iconMap = {
    pending: PendingIcon,
    processing: ProcessingIcon,
    succeeded: SuccessIcon,
    failed: FailedIcon,
    refunded: RefundedIcon,
    cancelled: CancelledIcon
  }
  return iconMap[props.status] || PendingIcon
})
</script>
