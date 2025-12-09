<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="container mx-auto px-4 py-8 max-w-4xl">
    <!-- Header with Actions -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="/timeline" class="text-gray-600 hover:text-gray-900">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <h1 class="text-3xl font-bold text-gray-900"><?= esc($title) ?></h1>
        </div>
        <?php if ($permissions['canDelete']): ?>
        <button onclick="confirm('Are you sure you want to delete this timeline entry? This action cannot be undone.') && fetch('/timeline/<?= $entry['id'] ?>', {method: 'DELETE', headers: {'X-Requested-With': 'XMLHttpRequest'}}).then(() => location.href='/timeline')" class="px-4 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition">
            Delete Entry
        </button>
        <?php endif; ?>
    </div>

    <!-- Success/Error Messages -->
    <?php if (session()->getFlashdata('success')): ?>
    <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">
        <?= session()->getFlashdata('success') ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Event Details -->
            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php
                        echo match($entry['event_type']) {
                            'created' => 'bg-green-100 text-green-800',
                            'updated' => 'bg-blue-100 text-blue-800',
                            'deleted' => 'bg-red-100 text-red-800',
                            'restored' => 'bg-purple-100 text-purple-800',
                            'pinned' => 'bg-yellow-100 text-yellow-800',
                            'unpinned' => 'bg-gray-100 text-gray-800',
                            'status_changed' => 'bg-indigo-100 text-indigo-800',
                            default => 'bg-gray-100 text-gray-800',
                        };
                    ?>">
                        <?= ucfirst(esc($entry['event_type'])) ?>
                    </span>

                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                        <?= ucfirst(esc($entry['entity_type'])) ?>
                    </span>
                </div>

                <h2 class="text-xl font-semibold text-gray-900 mb-4">Description</h2>
                <p class="text-gray-700 whitespace-pre-wrap"><?= esc($entry['description']) ?></p>
            </div>

            <!-- Metadata (if present) -->
            <?php if (!empty($entry['metadata'])): ?>
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Additional Data</h3>
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <pre class="text-sm text-gray-700 overflow-x-auto"><?= esc(json_encode($entry['metadata'], JSON_PRETTY_PRINT)) ?></pre>
                </div>
            </div>
            <?php endif; ?>

            <!-- Entity Information -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Related Entity</h3>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500"><?= ucfirst(esc($entry['entity_type'])) ?></p>
                        <p class="mt-1 text-gray-900"><?= esc($entry['entity_name']) ?></p>
                    </div>
                    <?php if (!empty($entry['entity_url']) && $entry['entity_url'] !== '#'): ?>
                    <a href="<?= esc($entry['entity_url']) ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        View <?= ucfirst(esc($entry['entity_type'])) ?> →
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Timeline Entry Info -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Entry Info</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Created By</label>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= esc($entry['user_first_name']) ?> <?= esc($entry['user_last_name']) ?>
                        </p>
                        <?php if (!empty($entry['user_email'])): ?>
                        <p class="text-xs text-gray-500"><?= esc($entry['user_email']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Timestamp</label>
                        <p class="mt-1 text-sm text-gray-900"><?= date('M d, Y g:i:s A', strtotime($entry['created_at'])) ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Entry ID</label>
                        <p class="mt-1 text-xs text-gray-600 font-mono"><?= esc($entry['id']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Note -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-sm text-blue-800">
                    <strong>Note:</strong> Timeline entries are immutable and cannot be edited. They serve as an audit trail of system activity.
                </p>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
