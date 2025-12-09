<template>
  <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
    <div class="flex items-start justify-between gap-4">
      <div class="flex-1">
        <div class="flex items-center gap-2 mb-2">
          <p class="text-sm font-medium text-gray-900 dark:text-white">{{ note.author_name || 'Unknown' }}</p>
          <span class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(note.created_at) }}</span>
          <button v-if="note.is_pinned" @click="$emit('update', { ...note, is_pinned: false })" class="ml-auto p-1 text-yellow-600 hover:text-yellow-700" title="Unpin">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 0a2 2 0 012 2v2h3a1 1 0 01.707 1.707l-2.586 2.586 2.586 2.586A1 1 0 0115 12h-3v6a2 2 0 01-4 0v-6H5a1 1 0 01-.707-1.707l2.586-2.586L4.293 5.121A1 1 0 015 4h3V2a2 2 0 012-2z" /></svg>
          </button>
          <button v-else @click="$emit('update', { ...note, is_pinned: true })" class="ml-auto p-1 text-gray-400 hover:text-yellow-600" title="Pin">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" /></svg>
          </button>
        </div>
        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ note.content }}</p>
      </div>
      <div class="flex gap-2">
        <button @click="$emit('edit', note)" class="p-1 text-gray-400 hover:text-brand-600" title="Edit">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
        </button>
        <button @click="$emit('delete', note)" class="p-1 text-gray-400 hover:text-red-600" title="Delete">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({ note: { type: Object, required: true } })
defineEmits(['update', 'edit', 'delete'])

function formatDate(dateString) {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>
