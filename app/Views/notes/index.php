<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="container mx-auto px-4 py-8">
    <!-- Header with Actions -->
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-900"><?= esc($title) ?></h1>
        <?php if ($permissions['canCreate']): ?>
        <a href="/notes/create" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            New Note
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

    <!-- Search and Filters -->
    <div class="bg-white p-4 rounded-lg shadow mb-6">
        <form method="GET" action="/notes" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Search -->
                <div class="md:col-span-2">
                    <input type="text" name="search" value="<?= esc($search ?? '') ?>" placeholder="Search by content, subject, or entity..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <!-- Pinned Filter -->
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="pinned" value="1" <?= $pinnedOnly ? 'checked' : '' ?> class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-gray-700">Pinned Only</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-2 bg-gray-800 text-white font-medium rounded-lg hover:bg-gray-900 transition">
                    Search
                </button>
            </div>
        </form>
    </div>

    <!-- Notes List -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <?php if (!empty($notes)): ?>
        <div class="divide-y divide-gray-200">
            <?php foreach ($notes as $note): ?>
            <div class="p-6 hover:bg-gray-50 transition">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <?php if ($note['is_pinned']): ?>
                            <svg class="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 2a.75.75 0 01.75.75v2.5h2.5a.75.75 0 010 1.5h-2.5v2.5a.75.75 0 01-1.5 0v-2.5h-2.5a.75.75 0 010-1.5h2.5v-2.5A.75.75 0 0110 2z"/>
                            </svg>
                            <?php endif; ?>
                            <?php if (!empty($note['subject'])): ?>
                            <h3 class="text-lg font-semibold text-gray-900"><?= esc($note['subject']) ?></h3>
                            <?php endif; ?>
                        </div>

                        <p class="text-gray-700 mb-3 line-clamp-2"><?= esc(substr($note['content'], 0, 200)) ?><?= strlen($note['content']) > 200 ? '...' : '' ?></p>

                        <div class="flex items-center gap-4 text-sm text-gray-500">
                            <span><?= esc($note['user_first_name'] ?? '') ?> <?= esc($note['user_last_name'] ?? '') ?></span>
                            <span>•</span>
                            <span><?= date('M d, Y', strtotime($note['created_at'])) ?></span>
                            <span>•</span>
                            <span class="font-medium text-gray-700">
                                <?php
                                if (!empty($note['client_id'])) {
                                    echo 'Client: ' . esc($note['client_name'] ?? 'Unknown');
                                } elseif (!empty($note['contact_id'])) {
                                    echo 'Contact: ' . esc(($note['contact_first_name'] ?? '') . ' ' . ($note['contact_last_name'] ?? ''));
                                } elseif (!empty($note['project_id'])) {
                                    echo 'Project: ' . esc($note['project_name'] ?? 'Unknown');
                                }
                                ?>
                            </span>
                        </div>
                    </div>

                    <div class="ml-4 flex items-center gap-2">
                        <a href="/notes/<?= $note['id'] ?>" class="text-blue-600 hover:text-blue-900 text-sm font-medium">View</a>
                        <a href="/notes/<?= $note['id'] ?>/edit" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">Edit</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="p-12 text-center text-gray-500">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p class="mt-2 text-lg font-medium">No notes found</p>
            <p class="mt-1">Get started by creating a new note.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
