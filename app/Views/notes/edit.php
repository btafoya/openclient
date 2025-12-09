<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="container mx-auto px-4 py-8 max-w-3xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="/notes/<?= $note['id'] ?>" class="text-gray-600 hover:text-gray-900">
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
        <form method="POST" action="/notes/<?= $note['id'] ?>" class="space-y-6">
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="PUT">

            <!-- Entity Info (Read-only) -->
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                <label class="block text-sm font-medium text-gray-700 mb-2">Attached To</label>
                <p class="text-gray-900">
                    <?php
                    if (!empty($note['client_id'])) {
                        echo 'Client';
                    } elseif (!empty($note['contact_id'])) {
                        echo 'Contact';
                    } elseif (!empty($note['project_id'])) {
                        echo 'Project';
                    }
                    ?>
                </p>
                <p class="text-sm text-gray-500 mt-1">Entity cannot be changed after creation</p>
            </div>

            <!-- Subject -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Subject (Optional)</label>
                <input type="text" name="subject" value="<?= old('subject', $note['subject']) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Enter note subject">
                <?php if (isset($validation) && $validation->hasError('subject')): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $validation->getError('subject') ?></p>
                <?php endif; ?>
            </div>

            <!-- Content -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Content *</label>
                <textarea name="content" rows="8" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Enter note content..."><?= old('content', $note['content']) ?></textarea>
                <?php if (isset($validation) && $validation->hasError('content')): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $validation->getError('content') ?></p>
                <?php endif; ?>
            </div>

            <!-- Is Pinned -->
            <div>
                <label class="flex items-center">
                    <input type="checkbox" name="is_pinned" value="1" <?= old('is_pinned', $note['is_pinned']) ? 'checked' : '' ?> class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm text-gray-700">Pin this note (pinned notes appear first)</span>
                </label>
            </div>

            <div class="flex gap-3 justify-end pt-4 border-t">
                <a href="/notes/<?= $note['id'] ?>" class="px-6 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                    Update Note
                </button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
