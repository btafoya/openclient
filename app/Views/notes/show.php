<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="container mx-auto px-4 py-8 max-w-4xl">
    <!-- Header with Actions -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="/notes" class="text-gray-600 hover:text-gray-900">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <h1 class="text-3xl font-bold text-gray-900"><?= esc($title) ?></h1>
            <?php if ($note['is_pinned']): ?>
            <svg class="w-6 h-6 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 2a.75.75 0 01.75.75v2.5h2.5a.75.75 0 010 1.5h-2.5v2.5a.75.75 0 01-1.5 0v-2.5h-2.5a.75.75 0 010-1.5h2.5v-2.5A.75.75 0 0110 2z"/>
            </svg>
            <?php endif; ?>
        </div>
        <?php if ($permissions['canEdit']): ?>
        <div class="flex gap-2">
            <button onclick="togglePin('<?= $note['id'] ?>')" class="px-4 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition">
                <?= $note['is_pinned'] ? 'Unpin' : 'Pin' ?> Note
            </button>
            <a href="/notes/<?= $note['id'] ?>/edit" class="px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                Edit Note
            </a>
        </div>
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
            <!-- Note Content -->
            <div class="bg-white shadow rounded-lg p-6">
                <?php if (!empty($note['subject'])): ?>
                <h2 class="text-2xl font-semibold text-gray-900 mb-4"><?= esc($note['subject']) ?></h2>
                <?php endif; ?>
                <div class="prose max-w-none">
                    <p class="text-gray-700 whitespace-pre-wrap"><?= esc($note['content']) ?></p>
                </div>
            </div>

            <!-- Entity Information -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Attached To</h3>
                <?php if (!empty($note['client_id'])): ?>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Client</p>
                        <p class="mt-1 text-gray-900"><?= esc($note['client_name']) ?></p>
                        <?php if (!empty($note['client_company'])): ?>
                        <p class="text-sm text-gray-500"><?= esc($note['client_company']) ?></p>
                        <?php endif; ?>
                    </div>
                    <a href="/clients/<?= $note['client_id'] ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        View Client →
                    </a>
                </div>
                <?php elseif (!empty($note['contact_id'])): ?>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Contact</p>
                        <p class="mt-1 text-gray-900"><?= esc($note['contact_first_name']) ?> <?= esc($note['contact_last_name']) ?></p>
                        <?php if (!empty($note['contact_email'])): ?>
                        <p class="text-sm text-gray-500"><?= esc($note['contact_email']) ?></p>
                        <?php endif; ?>
                    </div>
                    <a href="/contacts/<?= $note['contact_id'] ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        View Contact →
                    </a>
                </div>
                <?php elseif (!empty($note['project_id'])): ?>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Project</p>
                        <p class="mt-1 text-gray-900"><?= esc($note['project_name']) ?></p>
                        <?php if (!empty($note['project_description'])): ?>
                        <p class="text-sm text-gray-500"><?= esc(substr($note['project_description'], 0, 100)) ?></p>
                        <?php endif; ?>
                    </div>
                    <a href="/projects/<?= $note['project_id'] ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        View Project →
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Note Info -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Note Info</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Created By</label>
                        <p class="mt-1 text-sm text-gray-900">
                            <?= esc($note['user_first_name']) ?> <?= esc($note['user_last_name']) ?>
                        </p>
                        <?php if (!empty($note['user_email'])): ?>
                        <p class="text-xs text-gray-500"><?= esc($note['user_email']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Created</label>
                        <p class="mt-1 text-sm text-gray-900"><?= date('M d, Y g:i A', strtotime($note['created_at'])) ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Last Updated</label>
                        <p class="mt-1 text-sm text-gray-900"><?= date('M d, Y g:i A', strtotime($note['updated_at'])) ?></p>
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <?php if ($permissions['canDelete']): ?>
            <div class="bg-white shadow rounded-lg p-6 border-2 border-red-200">
                <h3 class="text-lg font-semibold text-red-900 mb-4">Danger Zone</h3>
                <p class="text-sm text-gray-600 mb-4">Once you delete a note, there is no going back. Please be certain.</p>
                <button onclick="confirm('Are you sure you want to delete this note?') && fetch('/notes/<?= $note['id'] ?>', {method: 'DELETE', headers: {'X-Requested-With': 'XMLHttpRequest'}}).then(() => location.href='/notes')" class="w-full px-4 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition">
                    Delete Note
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function togglePin(noteId) {
    fetch(`/notes/${noteId}/toggle-pin`, {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(response => response.json())
    .then(data => {
        if (data.message) {
            location.reload();
        }
    });
}
</script>

<?= $this->endSection() ?>
