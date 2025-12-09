<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="container mx-auto px-4 py-8 max-w-3xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="/contacts/<?= $contact['id'] ?>" class="text-gray-600 hover:text-gray-900">
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
        <form method="POST" action="/contacts/<?= $contact['id'] ?>" class="space-y-6">
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="PUT">

            <!-- Client Selection -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Client *</label>
                <select name="client_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Select a client...</option>
                    <?php foreach ($clients as $client): ?>
                    <option value="<?= $client['id'] ?>" <?= old('client_id', $contact['client_id']) === $client['id'] ? 'selected' : '' ?>>
                        <?= esc($client['name']) ?><?= !empty($client['company']) ? ' (' . esc($client['company']) . ')' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($validation) && $validation->hasError('client_id')): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $validation->getError('client_id') ?></p>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- First Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                    <input type="text" name="first_name" value="<?= old('first_name', $contact['first_name']) ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <?php if (isset($validation) && $validation->hasError('first_name')): ?>
                        <p class="mt-1 text-sm text-red-600"><?= $validation->getError('first_name') ?></p>
                    <?php endif; ?>
                </div>

                <!-- Last Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                    <input type="text" name="last_name" value="<?= old('last_name', $contact['last_name']) ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <?php if (isset($validation) && $validation->hasError('last_name')): ?>
                        <p class="mt-1 text-sm text-red-600"><?= $validation->getError('last_name') ?></p>
                    <?php endif; ?>
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="<?= old('email', $contact['email']) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <?php if (isset($validation) && $validation->hasError('email')): ?>
                        <p class="mt-1 text-sm text-red-600"><?= $validation->getError('email') ?></p>
                    <?php endif; ?>
                </div>

                <!-- Phone -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="tel" name="phone" value="<?= old('phone', $contact['phone']) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Mobile -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mobile</label>
                    <input type="tel" name="mobile" value="<?= old('mobile', $contact['mobile']) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Job Title -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Job Title</label>
                    <input type="text" name="job_title" value="<?= old('job_title', $contact['job_title']) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Department -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                    <input type="text" name="department" value="<?= old('department', $contact['department']) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Notes -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"><?= old('notes', $contact['notes']) ?></textarea>
                </div>

                <!-- Primary Contact -->
                <div class="md:col-span-2">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_primary" value="1" <?= old('is_primary', $contact['is_primary']) ? 'checked' : '' ?> class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-gray-700">Set as primary contact for this client</span>
                    </label>
                    <p class="mt-1 text-xs text-gray-500">Only one contact can be primary per client. Setting this will unset any existing primary contact.</p>
                </div>

                <!-- Active Status -->
                <div class="md:col-span-2">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" <?= old('is_active', $contact['is_active']) ? 'checked' : '' ?> class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-gray-700">Active</span>
                    </label>
                    <p class="mt-1 text-xs text-gray-500">Inactive contacts are hidden from most views but can be restored.</p>
                </div>
            </div>

            <div class="flex gap-3 justify-end pt-4 border-t">
                <a href="/contacts/<?= $contact['id'] ?>" class="px-6 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                    Update Contact
                </button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
