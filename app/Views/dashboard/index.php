<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<h1 class="text-2xl font-semibold mb-4">Dashboard</h1>
<p class="text-sm text-gray-600 mb-4">
    Welcome to openclient. This is a placeholder dashboard.
</p>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="bg-white shadow rounded p-4">
        <h2 class="text-sm font-semibold mb-2">Clients</h2>
        <p class="text-2xl font-bold">0</p>
    </div>
    <div class="bg-white shadow rounded p-4">
        <h2 class="text-sm font-semibold mb-2">Open Deals</h2>
        <p class="text-2xl font-bold">0</p>
    </div>
    <div class="bg-white shadow rounded p-4">
        <h2 class="text-sm font-semibold mb-2">Open Tickets</h2>
        <p class="text-2xl font-bold">0</p>
    </div>
</div>

<?= $this->endSection() ?>
