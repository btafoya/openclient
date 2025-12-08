<header class="bg-white border-b px-6 py-3 flex items-center justify-between">
    <div class="text-lg font-semibold">
        openclient
    </div>
    <div class="flex items-center gap-4">
        <span class="text-sm text-gray-600">
            <?= esc(session('user_name') ?? 'Guest') ?>
        </span>
        <?php if (session('user_id')): ?>
            <a href="/auth/logout" class="text-sm text-blue-600 hover:underline">Logout</a>
        <?php endif; ?>
    </div>
</header>
