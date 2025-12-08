<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= esc($title ?? 'openclient - Multi-Agency CRM') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?= csrf_hash() ?>">

    <!-- Tailwind CSS (Vite build) -->
    <link rel="stylesheet" href="/assets/app.css">

    <!-- Additional CSS -->
    <?= $this->renderSection('styles') ?>
</head>
<body class="bg-gray-100">
    <!-- Vue.js Application Mount Point -->
    <div id="app">
        <?= $this->renderSection('content') ?>
    </div>

    <!-- Initialize Vue.js + Pinia + User Store -->
    <script type="module">
        import { createApp } from 'vue'
        import { createPinia } from 'pinia'
        import { useUserStore } from '/assets/js/stores/user.js'

        // Create Vue application
        const app = createApp({
            // Root component configuration
        })

        // Install Pinia
        const pinia = createPinia()
        app.use(pinia)

        // Initialize user store with server-side session data
        const userStore = useUserStore()

        <?php
        $user = session()->get('user');
        if ($user):
        ?>
        userStore.init(<?= json_encode($user) ?>)
        <?php else: ?>
        console.warn('No user session data available for user store initialization')
        <?php endif; ?>

        // Mount application
        app.mount('#app')
    </script>

    <!-- Additional JavaScript -->
    <?= $this->renderSection('scripts') ?>
</body>
</html>
