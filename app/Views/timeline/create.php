<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="container mx-auto px-4 py-8 max-w-3xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="/timeline" class="text-gray-600 hover:text-gray-900">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
        </a>
        <h1 class="text-3xl font-bold text-gray-900"><?= esc($title) ?></h1>
    </div>

    <?php if (session()->getFlashdata('error')): ?>
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">
        <?= session()->getFlashdata('error') ?>
    </div>
    <?php endif; ?>

    <div class="bg-white shadow rounded-lg p-6">
        <form method="POST" action="/timeline" class="space-y-6">
            <?= csrf_field() ?>

            <!-- Entity Selection -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Entity *</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <select name="entity_type" id="entityType" required onchange="updateEntityOptions()" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Select entity type...</option>
                        <option value="client" <?= old('entity_type') === 'client' ? 'selected' : '' ?>>Client</option>
                        <option value="contact" <?= old('entity_type') === 'contact' ? 'selected' : '' ?>>Contact</option>
                        <option value="project" <?= old('entity_type') === 'project' ? 'selected' : '' ?>>Project</option>
                        <option value="note" <?= old('entity_type') === 'note' ? 'selected' : '' ?>>Note</option>
                    </select>

                    <select name="entity_id" id="entityId" required class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Select entity...</option>
                    </select>
                </div>
                <?php if (isset($validation) && $validation->hasError('entity_id')): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $validation->getError('entity_id') ?></p>
                <?php endif; ?>
            </div>

            <!-- Event Type -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Event Type *</label>
                <select name="event_type" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Select event type...</option>
                    <option value="created" <?= old('event_type') === 'created' ? 'selected' : '' ?>>Created</option>
                    <option value="updated" <?= old('event_type') === 'updated' ? 'selected' : '' ?>>Updated</option>
                    <option value="deleted" <?= old('event_type') === 'deleted' ? 'selected' : '' ?>>Deleted</option>
                    <option value="restored" <?= old('event_type') === 'restored' ? 'selected' : '' ?>>Restored</option>
                    <option value="pinned" <?= old('event_type') === 'pinned' ? 'selected' : '' ?>>Pinned</option>
                    <option value="unpinned" <?= old('event_type') === 'unpinned' ? 'selected' : '' ?>>Unpinned</option>
                    <option value="status_changed" <?= old('event_type') === 'status_changed' ? 'selected' : '' ?>>Status Changed</option>
                    <option value="custom" <?= old('event_type') === 'custom' ? 'selected' : '' ?>>Custom Event</option>
                </select>
                <?php if (isset($validation) && $validation->hasError('event_type')): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $validation->getError('event_type') ?></p>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                <textarea name="description" rows="4" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Describe what happened..."><?= old('description') ?></textarea>
                <?php if (isset($validation) && $validation->hasError('description')): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $validation->getError('description') ?></p>
                <?php endif; ?>
            </div>

            <!-- Metadata (Optional) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Metadata (Optional, JSON)</label>
                <textarea name="metadata" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono text-sm" placeholder='{"key": "value", "changes": ["field1", "field2"]}'><?= old('metadata') ?></textarea>
                <p class="mt-1 text-sm text-gray-500">Optional JSON data for additional event information (e.g., changed fields, old/new values)</p>
                <?php if (isset($validation) && $validation->hasError('metadata')): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $validation->getError('metadata') ?></p>
                <?php endif; ?>
            </div>

            <div class="flex gap-3 justify-end pt-4 border-t">
                <a href="/timeline" class="px-6 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                    Create Timeline Entry
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const entities = {
    client: <?= json_encode($clients) ?>,
    contact: <?= json_encode($contacts) ?>,
    project: <?= json_encode($projects) ?>,
    note: <?= json_encode($notes) ?>
};

function updateEntityOptions() {
    const typeSelect = document.getElementById('entityType');
    const idSelect = document.getElementById('entityId');
    const type = typeSelect.value;

    idSelect.innerHTML = '<option value="">Select entity...</option>';

    if (type && entities[type]) {
        entities[type].forEach(entity => {
            const option = document.createElement('option');
            option.value = entity.id;

            if (type === 'client') {
                option.textContent = entity.name + (entity.company ? ` (${entity.company})` : '');
            } else if (type === 'contact') {
                option.textContent = `${entity.first_name} ${entity.last_name}` + (entity.client_name ? ` - ${entity.client_name}` : '');
            } else if (type === 'project') {
                option.textContent = entity.name + (entity.client_name ? ` (${entity.client_name})` : '');
            } else if (type === 'note') {
                option.textContent = (entity.subject || entity.content.substring(0, 50) + '...') + (entity.client_name ? ` - ${entity.client_name}` : '');
            }

            const preselected = '<?= old('entity_id') ?>';
            if (preselected && entity.id === preselected) {
                option.selected = true;
            }

            idSelect.appendChild(option);
        });
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', updateEntityOptions);
</script>

<?= $this->endSection() ?>
