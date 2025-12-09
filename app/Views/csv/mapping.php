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

    <!-- Import Info -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <div class="flex items-start">
            <svg class="w-5 h-5 text-blue-600 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div>
                <p class="text-sm text-blue-800">
                    <strong>File:</strong> <?= esc($import['filename']) ?><br>
                    <strong>Entity Type:</strong> <?= esc(ucfirst($import['entity_type'])) ?><br>
                    <strong>CSV Columns Found:</strong> <?= count($csv_headers) ?> columns
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Map CSV Columns to Database Fields</h2>
        <p class="text-sm text-gray-600 mb-6">
            Match your CSV column headers to the appropriate database fields. Required fields must be mapped to continue.
        </p>

        <form method="POST" action="/csv/import/<?= esc($import['id']) ?>/mapping" class="space-y-4">
            <?= csrf_field() ?>

            <!-- Required Fields Section -->
            <?php if (!empty($required_fields)): ?>
            <div class="border-l-4 border-red-400 bg-red-50 p-4 rounded">
                <h3 class="text-sm font-semibold text-red-800 mb-3">Required Fields *</h3>
                <div class="space-y-3">
                    <?php foreach ($required_fields as $field): ?>
                    <div class="grid grid-cols-2 gap-4 items-center">
                        <div class="text-sm font-medium text-gray-700">
                            <span class="text-red-600">*</span> <?= esc(ucfirst(str_replace('_', ' ', $field))) ?>
                        </div>
                        <select name="mapping[<?= esc($field) ?>]" required class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            <option value="">Select CSV column...</option>
                            <?php foreach ($csv_headers as $header): ?>
                            <option value="<?= esc($header) ?>" <?= strcasecmp($header, $field) === 0 ? 'selected' : '' ?>>
                                <?= esc($header) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Optional Fields Section -->
            <?php if (!empty($optional_fields)): ?>
            <div class="border-l-4 border-blue-400 bg-blue-50 p-4 rounded">
                <h3 class="text-sm font-semibold text-blue-800 mb-3">Optional Fields</h3>
                <div class="space-y-3">
                    <?php foreach ($optional_fields as $field): ?>
                    <div class="grid grid-cols-2 gap-4 items-center">
                        <div class="text-sm font-medium text-gray-700">
                            <?= esc(ucfirst(str_replace('_', ' ', $field))) ?>
                        </div>
                        <select name="mapping[<?= esc($field) ?>]" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Skip this field</option>
                            <?php foreach ($csv_headers as $header): ?>
                            <option value="<?= esc($header) ?>" <?= strcasecmp($header, $field) === 0 ? 'selected' : '' ?>>
                                <?= esc($header) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Help Section -->
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-900 mb-2">Mapping Tips</h3>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li>• The system will attempt to auto-match columns with the same names</li>
                    <li>• Required fields (marked with *) must be mapped to continue</li>
                    <li>• Optional fields can be skipped if not present in your CSV</li>
                    <li>• If a column doesn't match any field, you can leave it unmapped</li>
                </ul>
            </div>

            <div class="flex gap-3 justify-end pt-4 border-t">
                <a href="/csv/history" class="px-6 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                    Save Mapping and Start Import
                </button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
