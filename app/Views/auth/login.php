<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= esc($title ?? 'Login') ?> - openclient</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md bg-white shadow rounded-lg p-6">
        <h1 class="text-xl font-semibold mb-4 text-center">Sign in to openclient</h1>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="mb-4 text-sm text-red-600">
                <?= esc(session()->getFlashdata('error')) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/auth/login" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" required class="mt-1 block w-full border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="password" required class="mt-1 block w-full border rounded px-3 py-2 text-sm">
            </div>
            <button type="submit" class="w-full py-2 px-4 text-sm font-semibold bg-gray-900 text-white rounded hover:bg-black">
                Sign In
            </button>
        </form>
    </div>
</body>
</html>
