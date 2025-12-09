<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="container mx-auto px-4 py-8">
    <!-- Header with Actions -->
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-900"><?= esc($title) ?></h1>
        <?php if ($permissions['canCreate']): ?>
        <a href="/timeline/create" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            New Timeline Entry
        </a>
        <?php endif; ?>
    </div>

    <!-- Success/Error Messages -->
    <?php if (session()->getFlashdata('success')): ?>
    <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">
        <?= session()->getFlashdata('success') ?>
    </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">
        <?= session()->getFlashdata('error') ?>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="bg-white p-4 rounded-lg shadow mb-6">
        <form method="GET" action="/timeline" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Search -->
                <div>
                    <input type="text" name="search" value="<?= esc($filters['search'] ?? '') ?>" placeholder="Search descriptions..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <!-- Entity Type Filter -->
                <div>
                    <select name="entity_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">All Entity Types</option>
                        <option value="client" <?= ($filters['entity_type'] ?? '') === 'client' ? 'selected' : '' ?>>Clients</option>
                        <option value="contact" <?= ($filters['entity_type'] ?? '') === 'contact' ? 'selected' : '' ?>>Contacts</option>
                        <option value="project" <?= ($filters['entity_type'] ?? '') === 'project' ? 'selected' : '' ?>>Projects</option>
                        <option value="note" <?= ($filters['entity_type'] ?? '') === 'note' ? 'selected' : '' ?>>Notes</option>
                    </select>
                </div>

                <!-- Event Type Filter -->
                <div>
                    <select name="event_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">All Event Types</option>
                        <option value="created" <?= ($filters['event_type'] ?? '') === 'created' ? 'selected' : '' ?>>Created</option>
                        <option value="updated" <?= ($filters['event_type'] ?? '') === 'updated' ? 'selected' : '' ?>>Updated</option>
                        <option value="deleted" <?= ($filters['event_type'] ?? '') === 'deleted' ? 'selected' : '' ?>>Deleted</option>
                        <option value="restored" <?= ($filters['event_type'] ?? '') === 'restored' ? 'selected' : '' ?>>Restored</option>
                        <option value="pinned" <?= ($filters['event_type'] ?? '') === 'pinned' ? 'selected' : '' ?>>Pinned</option>
                        <option value="unpinned" <?= ($filters['event_type'] ?? '') === 'unpinned' ? 'selected' : '' ?>>Unpinned</option>
                        <option value="status_changed" <?= ($filters['event_type'] ?? '') === 'status_changed' ? 'selected' : '' ?>>Status Changed</option>
                    </select>
                </div>

                <!-- Search Button -->
                <div>
                    <button type="submit" class="w-full px-6 py-2 bg-gray-800 text-white font-medium rounded-lg hover:bg-gray-900 transition">
                        Filter
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Timeline List -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <?php if (!empty($timeline)): ?>
        <div class="divide-y divide-gray-200">
            <?php foreach ($timeline as $entry): ?>
            <div class="p-6 hover:bg-gray-50 transition">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <!-- Event Badge -->
                        <div class="flex items-center gap-3 mb-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php
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

                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                <?= ucfirst(esc($entry['entity_type'])) ?>
                            </span>
                        </div>

                        <!-- Description -->
                        <p class="text-gray-900 mb-2"><?= esc($entry['description']) ?></p>

                        <!-- Entity Link -->
                        <?php if (!empty($entry['entity_name']) && !empty($entry['entity_url'])): ?>
                        <a href="<?= esc($entry['entity_url']) ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            → <?= esc($entry['entity_name']) ?>
                        </a>
                        <?php endif; ?>

                        <!-- Meta Info -->
                        <div class="flex items-center gap-4 text-sm text-gray-500 mt-3">
                            <span><?= esc($entry['user_first_name'] ?? '') ?> <?= esc($entry['user_last_name'] ?? '') ?></span>
                            <span>•</span>
                            <span><?= date('M d, Y g:i A', strtotime($entry['created_at'])) ?></span>
                        </div>
                    </div>

                    <div class="ml-4 flex items-center gap-2">
                        <a href="/timeline/<?= $entry['id'] ?>" class="text-blue-600 hover:text-blue-900 text-sm font-medium">View</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="p-12 text-center text-gray-500">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="mt-2 text-lg font-medium">No timeline entries found</p>
            <p class="mt-1">Timeline entries will appear here as actions occur.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
