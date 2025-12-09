<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="container mx-auto px-4 py-8 max-w-4xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="/csv/history" class="text-gray-600 hover:text-gray-900">
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

    <?php if (session()->getFlashdata('success')): ?>
    <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">
        <?= session()->getFlashdata('success') ?>
    </div>
    <?php endif; ?>

    <!-- Import Summary -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-gray-900">Import Summary</h2>
            <span class="px-3 py-1 text-sm font-semibold rounded-full <?php
                echo match($import['status']) {
                    'completed' => 'bg-green-100 text-green-800',
                    'processing' => 'bg-yellow-100 text-yellow-800',
                    'failed' => 'bg-red-100 text-red-800',
                    'cancelled' => 'bg-gray-100 text-gray-800',
                    'pending' => 'bg-blue-100 text-blue-800',
                    default => 'bg-gray-100 text-gray-800',
                };
            ?>">
                <?= esc(ucfirst($import['status'])) ?>
            </span>
        </div>

        <div class="grid grid-cols-2 gap-6">
            <div>
                <h3 class="text-sm font-medium text-gray-500 mb-1">File Information</h3>
                <div class="space-y-2 text-sm">
                    <div><strong>Filename:</strong> <?= esc($import['filename']) ?></div>
                    <div><strong>File Size:</strong> <?= number_format($import['file_size'] / 1024, 2) ?> KB</div>
                    <div><strong>Entity Type:</strong> <?= esc(ucfirst($import['entity_type'])) ?></div>
                    <div><strong>Imported By:</strong> <?= esc($import['user_name']) ?></div>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-medium text-gray-500 mb-1">Import Results</h3>
                <div class="space-y-2 text-sm">
                    <?php if ($import['status'] === 'completed' || $import['status'] === 'processing'): ?>
                    <div><strong>Total Rows:</strong> <?= number_format($import['total_rows']) ?></div>
                    <div class="text-green-600"><strong>✅ Successfully Imported:</strong> <?= number_format($import['processed_rows']) ?></div>
                    <?php if ($import['failed_rows'] > 0): ?>
                    <div class="text-red-600"><strong>❌ Failed:</strong> <?= number_format($import['failed_rows']) ?></div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="text-gray-500">Import not started or failed</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($import['status'] === 'processing'): ?>
        <div class="mt-6">
            <div class="flex justify-between text-sm mb-1">
                <span>Progress</span>
                <span><?= number_format(($import['processed_rows'] / $import['total_rows']) * 100, 1) ?>%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-blue-600 h-2 rounded-full" style="width: <?= ($import['processed_rows'] / $import['total_rows']) * 100 ?>%"></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Import Dates -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Timeline</h2>
        <div class="space-y-3 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-600">Upload Date:</span>
                <span class="font-medium"><?= date('M j, Y g:i A', strtotime($import['created_at'])) ?></span>
            </div>
            <?php if ($import['started_at']): ?>
            <div class="flex justify-between">
                <span class="text-gray-600">Started Processing:</span>
                <span class="font-medium"><?= date('M j, Y g:i A', strtotime($import['started_at'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($import['completed_at']): ?>
            <div class="flex justify-between">
                <span class="text-gray-600">Completed:</span>
                <span class="font-medium"><?= date('M j, Y g:i A', strtotime($import['completed_at'])) ?></span>
            </div>
            <?php if ($import['started_at']): ?>
            <div class="flex justify-between">
                <span class="text-gray-600">Duration:</span>
                <span class="font-medium">
                    <?php
                    $start = new DateTime($import['started_at']);
                    $end = new DateTime($import['completed_at']);
                    $diff = $start->diff($end);
                    echo $diff->format('%H:%I:%S');
                    ?>
                </span>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Import Options -->
    <?php if (!empty($import['import_options'])): ?>
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Import Options</h2>
        <div class="space-y-2 text-sm">
            <?php if ($import['import_options']['skip_duplicates'] ?? false): ?>
            <div class="flex items-center text-gray-700">
                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Skip duplicate entries
            </div>
            <?php endif; ?>
            <?php if ($import['import_options']['update_existing'] ?? false): ?>
            <div class="flex items-center text-gray-700">
                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Update existing entries
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Validation Errors -->
    <?php if (!empty($import['validation_errors'])): ?>
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-red-900 mb-4">Validation Errors</h2>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 max-h-96 overflow-y-auto">
            <div class="space-y-3 text-sm">
                <?php foreach ($import['validation_errors'] as $row => $errors): ?>
                <div class="border-b border-red-100 pb-2">
                    <div class="font-medium text-red-800"><?= esc(ucfirst(str_replace('_', ' ', $row))) ?>:</div>
                    <ul class="mt-1 ml-4 list-disc text-red-700">
                        <?php foreach ((array)$errors as $error): ?>
                        <li><?= esc($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="flex gap-3 justify-end">
        <a href="/csv/history" class="px-6 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition">
            Back to History
        </a>
        <?php if ($canCancel): ?>
        <form method="POST" action="/csv/import/<?= esc($import['id']) ?>/cancel" class="inline">
            <?= csrf_field() ?>
            <button type="submit" class="px-6 py-2 bg-yellow-600 text-white font-medium rounded-lg hover:bg-yellow-700 transition" onclick="return confirm('Cancel this import?')">
                Cancel Import
            </button>
        </form>
        <?php endif; ?>
        <?php if ($canDelete): ?>
        <form method="POST" action="/csv/import/<?= esc($import['id']) ?>" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="px-6 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition" onclick="return confirm('Delete this import record? This cannot be undone.')">
                Delete Import Record
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
