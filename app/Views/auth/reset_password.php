<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - openclient</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-gray-100 antialiased">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Header -->
            <div class="text-center">
                <h2 class="text-3xl font-extrabold text-gray-900">
                    Set new password
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Choose a strong password for your account
                </p>
            </div>

            <!-- Flash Messages -->
            <?php if (session()->getFlashdata('error')): ?>
                <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded" role="alert">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700">
                                <?= esc(session()->getFlashdata('error')) ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('success')): ?>
                <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded" role="alert">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-green-700">
                                <?= esc(session()->getFlashdata('success')) ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Reset Password Form -->
            <div class="bg-white shadow-md rounded-lg px-8 pt-6 pb-8">
                <form method="POST" action="/auth/reset-password" class="space-y-6">
                    <?= csrf_field() ?>

                    <!-- Hidden Token Field -->
                    <input type="hidden" name="token" value="<?= esc($token ?? '') ?>">

                    <!-- Password Requirements Notice -->
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-blue-700">
                                    Password must be at least 12 characters and include:
                                </p>
                                <ul class="text-sm text-blue-700 list-disc list-inside mt-1">
                                    <li>At least one uppercase letter</li>
                                    <li>At least one lowercase letter</li>
                                    <li>At least one number</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- New Password Field -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            New password
                        </label>
                        <div class="mt-1">
                            <input
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="new-password"
                                required
                                minlength="12"
                                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                placeholder="Enter new password"
                            >
                        </div>
                    </div>

                    <!-- Confirm Password Field -->
                    <div>
                        <label for="password_confirm" class="block text-sm font-medium text-gray-700">
                            Confirm new password
                        </label>
                        <div class="mt-1">
                            <input
                                id="password_confirm"
                                name="password_confirm"
                                type="password"
                                autocomplete="new-password"
                                required
                                minlength="12"
                                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                placeholder="Confirm new password"
                            >
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div>
                        <button
                            type="submit"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
                        >
                            Reset password
                        </button>
                    </div>
                </form>
            </div>

            <!-- Footer Links -->
            <div class="text-center text-sm text-gray-600">
                <p>
                    Remember your password?
                    <a href="/auth/login" class="font-medium text-blue-600 hover:text-blue-500">
                        Sign in
                    </a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
