<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold">Clients</h1>
    <a href="/clients/create" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded bg-gray-900 text-white hover:bg-black">
        New Client
    </a>
</div>

<div class="bg-white shadow rounded-lg overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left font-medium text-gray-600">Name</th>
                <th class="px-4 py-2 text-left font-medium text-gray-600">Email</th>
                <th class="px-4 py-2 text-left font-medium text-gray-600">Phone</th>
                <th class="px-4 py-2 text-left font-medium text-gray-600">Website</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
        <?php if (! empty($clients)): ?>
            <?php foreach ($clients as $client): ?>
                <tr class="border-t">
                    <td class="px-4 py-2"><?= esc($client['name']) ?></td>
                    <td class="px-4 py-2"><?= esc($client['email']) ?></td>
                    <td class="px-4 py-2"><?= esc($client['phone']) ?></td>
                    <td class="px-4 py-2"><?= esc($client['website']) ?></td>
                    <td class="px-4 py-2 text-right">
                        <a href="/clients/edit/<?= $client['id'] ?>" class="text-sm text-blue-600 hover:underline">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">
                    No clients yet.
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?= $this->endSection() ?>
