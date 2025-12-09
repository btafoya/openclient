<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="container mx-auto px-4 py-8 max-w-3xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-900"><?= esc($title) ?></h1>
        <a href="/csv/history" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
            View History
        </a>
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

    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Import Data from CSV</h2>

        <form method="POST" action="/csv/import/upload" enctype="multipart/form-data" class="space-y-6">
            <?= csrf_field() ?>

            <!-- Entity Type Selection -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">What are you importing? *</label>
                <select name="entity_type" id="entityType" required onchange="updateTemplateLink()" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Select entity type...</option>
                    <?php foreach ($entity_types as $key => $label): ?>
                    <option value="<?= esc($key) ?>" <?= old('entity_type') === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- CSV File Upload -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">CSV File *</label>
                <input type="file" name="csv_file" accept=".csv,.txt" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <p class="mt-1 text-sm text-gray-500">Maximum file size: 10MB</p>
            </div>

            <!-- Import Options -->
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Import Options</h3>

                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="checkbox" name="skip_duplicates" value="1" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-gray-700">Skip duplicate entries (based on email)</span>
                    </label>

                    <label class="flex items-center">
                        <input type="checkbox" name="update_existing" value="1" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-gray-700">Update existing entries if found (based on email)</span>
                    </label>
                </div>
            </div>

            <!-- Template Download Link -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-sm text-blue-800 mb-2">
                    <strong>Need a template?</strong> Download a CSV template with the correct column headers for your import.
                </p>
                <a href="#" id="templateLink" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition disabled">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                    </svg>
                    Download Template
                </a>
            </div>

            <div class="flex gap-3 justify-end pt-4 border-t">
                <a href="/dashboard" class="px-6 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                    Upload and Continue
                </button>
            </div>
        </form>
    </div>

    <!-- Help Section -->
    <div class="bg-white shadow rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">Import Guidelines</h3>
        <ul class="space-y-2 text-sm text-gray-700">
            <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span><strong>CSV Format:</strong> Ensure your file is in CSV format with comma-separated values</span>
            </li>
            <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span><strong>Headers Required:</strong> First row must contain column headers matching your entity fields</span>
            </li>
            <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span><strong>Required Fields:</strong> Clients require 'name', Contacts require 'first_name' and 'last_name', Notes require 'content'</span>
            </li>
            <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span><strong>Validation:</strong> Email addresses must be valid, phone numbers should be in standard format</span>
            </li>
            <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span><strong>File Size:</strong> Maximum file size is 10MB (approximately 50,000 rows)</span>
            </li>
        </ul>
    </div>
</div>

<script>
function updateTemplateLink() {
    const typeSelect = document.getElementById('entityType');
    const templateLink = document.getElementById('templateLink');
    const type = typeSelect.value;

    if (type) {
        templateLink.href = '/csv/import/template/download?type=' + type;
        templateLink.classList.remove('opacity-50', 'cursor-not-allowed');
    } else {
        templateLink.href = '#';
        templateLink.classList.add('opacity-50', 'cursor-not-allowed');
    }
}
</script>

<?= $this->endSection() ?>
