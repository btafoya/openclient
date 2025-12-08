<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>

<div id="dashboard-app"></div>

<script type="module">
  import { createApp } from 'vue'
  import { createPinia } from 'pinia'
  import Dashboard from '/assets/js/components/dashboard/Dashboard.vue'
  import { useUserStore } from '/assets/js/stores/user.js'

  const app = createApp(Dashboard)
  const pinia = createPinia()

  app.use(pinia)

  // Initialize user store with server session data
  const userStore = useUserStore()
  <?php if (isset($user)): ?>
  userStore.init(<?= json_encode($user) ?>)
  <?php endif; ?>

  app.mount('#dashboard-app')
</script>

<?= $this->endSection() ?>
