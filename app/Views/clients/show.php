<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="container mx-auto px-4 py-8">
    <!-- Header with Actions -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <div class="flex items-center gap-3">
                <a href="/clients" class="text-gray-600 hover:text-gray-900">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <h1 class="text-3xl font-bold text-gray-900"><?= esc($title) ?></h1>
                <?php if (!$client['is_active']): ?>
                <span class="px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex gap-2">
            <?php if ($permissions['canEdit']): ?>
            <a href="/clients/<?= $client['id'] ?>/edit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                Edit Client
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (session()->getFlashdata('success')): ?>
    <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">
        <?= session()->getFlashdata('success') ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Info -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Basic Information -->
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Name</label>
                        <p class="mt-1 text-gray-900"><?= esc($client['name']) ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Company</label>
                        <p class="mt-1 text-gray-900"><?= esc($client['company'] ?? '-') ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Email</label>
                        <p class="mt-1 text-gray-900"><?= esc($client['email'] ?? '-') ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Phone</label>
                        <p class="mt-1 text-gray-900"><?= esc($client['phone'] ?? '-') ?></p>
                    </div>
                </div>
            </div>

            <!-- Address Information -->
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Address</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-gray-500">Street Address</label>
                        <p class="mt-1 text-gray-900"><?= esc($client['address'] ?? '-') ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">City</label>
                        <p class="mt-1 text-gray-900"><?= esc($client['city'] ?? '-') ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">State</label>
                        <p class="mt-1 text-gray-900"><?= esc($client['state'] ?? '-') ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Postal Code</label>
                        <p class="mt-1 text-gray-900"><?= esc($client['postal_code'] ?? '-') ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Country</label>
                        <p class="mt-1 text-gray-900"><?= esc($client['country'] ?? 'United States') ?></p>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <?php if (!empty($client['notes'])): ?>
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Notes</h2>
                <p class="text-gray-700 whitespace-pre-wrap"><?= esc($client['notes']) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Quick Stats -->
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Quick Stats</h2>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Status</label>
                        <p class="mt-1">
                            <?php if ($client['is_active']): ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                            <?php else: ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Created</label>
                        <p class="mt-1 text-sm text-gray-900"><?= date('M d, Y', strtotime($client['created_at'])) ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Last Updated</label>
                        <p class="mt-1 text-sm text-gray-900"><?= date('M d, Y', strtotime($client['updated_at'])) ?></p>
                    </div>
                </div>
            </div>

            <!-- Assigned Users -->
            <?php if (!empty($assignedUsers)): ?>
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Assigned Users</h2>
                <div class="space-y-3">
                    <?php foreach ($assignedUsers as $user): ?>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900"><?= esc($user['name']) ?></p>
                            <p class="text-xs text-gray-500"><?= esc($user['email']) ?></p>
                        </div>
                        <span class="px-2 py-1 text-xs font-medium rounded bg-gray-100 text-gray-700">
                            <?= ucfirst($user['role']) ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Danger Zone -->
            <?php if ($permissions['canDelete']): ?>
            <div class="bg-white shadow rounded-lg p-6 border-2 border-red-200">
                <h2 class="text-lg font-semibold text-red-900 mb-4">Danger Zone</h2>
                <p class="text-sm text-gray-600 mb-4">Once you delete a client, there is no going back. Please be certain.</p>
                <button onclick="confirm('Are you sure you want to delete this client?') && fetch('/clients/<?= $client['id'] ?>', {method: 'DELETE'}).then(() => location.href='/clients')" class="w-full px-4 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition">
                    Delete Client
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
