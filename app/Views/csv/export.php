<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="container mx-auto px-4 py-8 max-w-3xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-900"><?= esc($title) ?></h1>
        <a href="/csv/history" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
            View Import History
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

    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Export Data to CSV</h2>

        <form method="POST" action="/csv/export" class="space-y-6">
            <?= csrf_field() ?>

            <!-- Entity Type Selection -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">What do you want to export? *</label>
                <select name="entity_type" id="entityType" required onchange="loadFields()" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Select entity type...</option>
                    <?php foreach ($entity_types as $key => $label): ?>
                    <option value="<?= esc($key) ?>"><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Field Selection -->
            <div id="fieldSelection" style="display: none;">
                <label class="block text-sm font-medium text-gray-700 mb-2">Select Fields to Export</label>
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <div class="mb-3">
                        <label class="flex items-center">
                            <input type="checkbox" id="selectAllFields" onchange="toggleAllFields()" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                            <span class="ml-2 text-sm font-semibold text-gray-700">Select All Fields</span>
                        </label>
                    </div>
                    <div id="fieldsContainer" class="grid grid-cols-2 gap-2 max-h-60 overflow-y-auto"></div>
                </div>
                <p class="mt-1 text-sm text-gray-500">If no fields are selected, all fields will be exported</p>
            </div>

            <!-- Export Filters -->
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Export Filters (Optional)</h3>

                <div class="space-y-4">
                    <!-- Active Only -->
                    <label class="flex items-center">
                        <input type="checkbox" name="active_only" value="1" checked class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-gray-700">Export only active records</span>
                    </label>

                    <!-- Date Range -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Created After</label>
                            <input type="date" name="created_after" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Created Before</label>
                            <input type="date" name="created_before" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <!-- Search Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Search Filter</label>
                        <input type="text" name="search" placeholder="Filter by name, email, etc." class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>

            <div class="flex gap-3 justify-end pt-4 border-t">
                <a href="/dashboard" class="px-6 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition">
                    <svg class="w-5 h-5 inline-block mr-2 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    Download CSV Export
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let allFields = {};

async function loadFields() {
    const typeSelect = document.getElementById('entityType');
    const fieldSelection = document.getElementById('fieldSelection');
    const fieldsContainer = document.getElementById('fieldsContainer');
    const type = typeSelect.value;

    if (!type) {
        fieldSelection.style.display = 'none';
        return;
    }

    try {
        const response = await fetch('/csv/export/fields?type=' + type);
        const data = await response.json();

        if (data.fields) {
            allFields = data.fields;
            fieldsContainer.innerHTML = '';

            Object.keys(data.fields).forEach(fieldKey => {
                const fieldLabel = data.fields[fieldKey];
                const checkbox = document.createElement('label');
                checkbox.className = 'flex items-center';
                checkbox.innerHTML = `
                    <input type="checkbox" name="fields[]" value="${fieldKey}" class="field-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                    <span class="ml-2 text-sm text-gray-700">${fieldLabel}</span>
                `;
                fieldsContainer.appendChild(checkbox);
            });

            fieldSelection.style.display = 'block';
        }
    } catch (error) {
        console.error('Failed to load fields:', error);
    }
}

function toggleAllFields() {
    const selectAll = document.getElementById('selectAllFields');
    const checkboxes = document.querySelectorAll('.field-checkbox');

    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}
</script>

<?= $this->endSection() ?>
