<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<h1 class="text-2xl font-semibold mb-4">New Client</h1>

<form method="post" action="/clients/store" class="space-y-4 max-w-lg">
    <div>
        <label class="block text-sm font-medium text-gray-700">Name</label>
        <input type="text" name="name" required class="mt-1 block w-full border rounded px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Email</label>
        <input type="email" name="email" class="mt-1 block w-full border rounded px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Phone</label>
        <input type="text" name="phone" class="mt-1 block w-full border rounded px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Website</label>
        <input type="text" name="website" class="mt-1 block w-full border rounded px-3 py-2 text-sm">
    </div>
    <div class="flex gap-2">
        <button type="submit" class="px-4 py-2 text-sm font-semibold bg-gray-900 text-white rounded hover:bg-black">
            Save
        </button>
        <a href="/clients" class="px-4 py-2 text-sm text-gray-700">Cancel</a>
    </div>
</form>

<?= $this->endSection() ?>
