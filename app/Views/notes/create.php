<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="container mx-auto px-4 py-8 max-w-3xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="/notes" class="text-gray-600 hover:text-gray-900">
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
        <form method="POST" action="/notes" class="space-y-6">
            <?= csrf_field() ?>

            <!-- Entity Selection -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Attach To *</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <select name="entity_type" id="entityType" required onchange="updateEntityOptions()" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Select entity type...</option>
                        <option value="client" <?= old('entity_type', $preselectedEntityType ?? '') === 'client' ? 'selected' : '' ?>>Client</option>
                        <option value="contact" <?= old('entity_type', $preselectedEntityType ?? '') === 'contact' ? 'selected' : '' ?>>Contact</option>
                        <option value="project" <?= old('entity_type', $preselectedEntityType ?? '') === 'project' ? 'selected' : '' ?>>Project</option>
                    </select>

                    <select name="entity_id" id="entityId" required class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Select entity...</option>
                    </select>
                </div>
                <?php if (isset($validation) && $validation->hasError('entity_id')): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $validation->getError('entity_id') ?></p>
                <?php endif; ?>
            </div>

            <!-- Subject -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Subject (Optional)</label>
                <input type="text" name="subject" value="<?= old('subject') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Enter note subject">
                <?php if (isset($validation) && $validation->hasError('subject')): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $validation->getError('subject') ?></p>
                <?php endif; ?>
            </div>

            <!-- Content -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Content *</label>
                <textarea name="content" rows="8" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Enter note content..."><?= old('content') ?></textarea>
                <?php if (isset($validation) && $validation->hasError('content')): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $validation->getError('content') ?></p>
                <?php endif; ?>
            </div>

            <!-- Is Pinned -->
            <div>
                <label class="flex items-center">
                    <input type="checkbox" name="is_pinned" value="1" <?= old('is_pinned') ? 'checked' : '' ?> class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm text-gray-700">Pin this note (pinned notes appear first)</span>
                </label>
            </div>

            <div class="flex gap-3 justify-end pt-4 border-t">
                <a href="/notes" class="px-6 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                    Create Note
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const entities = {
    client: <?= json_encode($clients) ?>,
    contact: <?= json_encode($contacts) ?>,
    project: <?= json_encode($projects) ?>
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
            }

            const preselected = '<?= old('entity_id', $preselectedEntityId ?? '') ?>';
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
