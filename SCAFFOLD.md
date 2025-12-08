# SCAFFOLD: openclient Repository Structure & Claude Code Workflow

## 1. Repo Layout

```text
openclient/
├─ app/
│  ├─ Config/
│  ├─ Controllers/
│  ├─ Domain/
│  ├─ Entities/
│  ├─ Filters/
│  ├─ Models/
│  └─ Views/
├─ public/
│  └─ index.php            # from CodeIgniter skeleton (via composer)
├─ writable/
├─ database/
│  ├─ migrations/
│  └─ seeds/
├─ resources/
│  ├─ css/
│  └─ js/
├─ docker/
│  ├─ docker-compose.yml
│  ├─ php-fpm.Dockerfile
│  └─ nginx.conf
├─ tests/
│  ├─ unit/
│  └─ feature/
├─ package.json
├─ tailwind.config.cjs
├─ postcss.config.cjs
├─ composer.json
├─ phpunit.xml
├─ PR.md
├─ SCAFFOLD.md
└─ README.md
```

> The actual CodeIgniter `system/` directory and `public/index.php` come from `composer install` or `composer create-project`. This scaffold focuses on app code, config, and resources.

## 2. Database Config (PostgreSQL)

`app/Config/Database.php` must configure the default connection to PostgreSQL.

Override example (if using `Database.local.php`):

```php
<?php

namespace App\Config;

use CodeIgniter\Database\Config;

class Database extends Config\Database
{
    public array $default = [
        'DSN'      => '',
        'hostname' => 'localhost',
        'username' => 'openclient',
        'password' => 'openclient',
        'database' => 'openclient',
        'DBDriver' => 'Postgre',
        'DBPrefix' => '',
        'pConnect' => false,
        'DBDebug'  => (ENVIRONMENT !== 'production'),
        'charset'  => 'utf8',
        'DBCollat' => 'utf8_general_ci',
        'swapPre'  => '',
        'encrypt'  => false,
        'compress' => false,
        'strictOn' => false,
        'failover' => [],
        'port'     => 5432,
    ];
}
```

## 3. Frontend Build with Vue.js + Vite

### 3.1 package.json

```json
{
  "name": "openclient-ui",
  "version": "0.1.0",
  "private": true,
  "type": "module",
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "watch": "vite build --watch"
  },
  "dependencies": {
    "vue": "^3.5.0",
    "pinia": "^2.2.0",
    "axios": "^1.7.0"
  },
  "devDependencies": {
    "@vitejs/plugin-vue": "^5.0.0",
    "tailwindcss": "^3.4.0",
    "postcss": "^8.4.0",
    "autoprefixer": "^10.4.0",
    "vite": "^6.0.0"
  }
}
```

### 3.2 vite.config.js

```js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'resources/js'),
      'vue': 'vue/dist/vue.esm-bundler.js'
    }
  },
  build: {
    manifest: true,
    outDir: 'public/assets',
    rollupOptions: {
      input: {
        app: resolve(__dirname, 'resources/js/app.js')
      }
    }
  },
  server: {
    port: 5173,
    strictPort: true,
    hmr: {
      host: 'localhost'
    }
  }
})
```

### 3.3 tailwind.config.cjs

```js
module.exports = {
  content: [
    "./app/Views/**/*.php",
    "./resources/js/**/*.{js,vue}",
    "./theme-tailadmin-vuejs/**/*.{vue,js}"
  ],
  theme: {
    extend: {
      // TailAdmin theme extensions (colors, spacing, etc.)
    }
  },
  plugins: []
};
```

### 3.4 postcss.config.cjs

```js
module.exports = {
  plugins: {
    tailwindcss: {},
    autoprefixer: {}
  }
};
```

### 3.5 CSS Entry

`resources/css/app.css`:

```css
@tailwind base;
@tailwind components;
@tailwind utilities;

/* TailAdmin custom styles */
@import '../tailadmin/styles/tailadmin.css';
```

### 3.6 Vue.js Entry Point

`resources/js/app.js`:

```js
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import axios from 'axios'

// Configure Axios defaults
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
axios.defaults.withCredentials = true

// Make axios available globally
window.axios = axios

// Global Pinia instance
const pinia = createPinia()

// Export for use in views
export { createApp, pinia }
```

### 3.7 Pinia Store Setup

`resources/js/stores/user.js`:

```js
import { defineStore } from 'pinia'
import axios from 'axios'

export const useUserStore = defineStore('user', {
  state: () => ({
    user: null,
    permissions: [],
    role: null,
    isAuthenticated: false
  }),

  getters: {
    canViewFinancials: (state) => {
      return ['Owner', 'Agency', 'DirectClient'].includes(state.role)
    },
    isEndClient: (state) => state.role === 'EndClient'
  },

  actions: {
    async fetchUser() {
      const response = await axios.get('/api/user')
      this.user = response.data.user
      this.role = response.data.role
      this.permissions = response.data.permissions
      this.isAuthenticated = true
    },

    logout() {
      this.user = null
      this.role = null
      this.permissions = []
      this.isAuthenticated = false
    }
  }
})
```

`resources/js/stores/ui.js`:

```js
import { defineStore } from 'pinia'

export const useUIStore = defineStore('ui', {
  state: () => ({
    sidebarOpen: true,
    theme: 'light',
    notifications: []
  }),

  actions: {
    toggleSidebar() {
      this.sidebarOpen = !this.sidebarOpen
    },

    addNotification(notification) {
      this.notifications.push({
        id: Date.now(),
        ...notification
      })
    }
  }
})
```

### 3.8 Axios API Client

`resources/js/utils/api.js`:

```js
import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  }
})

// Request interceptor for CSRF token
api.interceptors.request.use(config => {
  const token = document.querySelector('meta[name="csrf-token"]')?.content
  if (token) {
    config.headers['X-CSRF-TOKEN'] = token
  }
  return config
})

// Response interceptor for error handling
api.interceptors.response.use(
  response => response,
  error => {
    if (error.response?.status === 401) {
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default api
```

The built assets end up at `public/assets/`.

## 4. Shared Layout with Vue.js Integration

Create these shared views:

- `app/Views/layouts/app.php` - Main layout with Vue.js initialization
- `app/Views/layouts/partials/header.php`
- `app/Views/layouts/partials/sidebar.php`
- `app/Views/layouts/partials/footer.php`

**app/Views/layouts/app.php:**

```php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= esc($title ?? 'openclient') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="bg-gray-100 min-h-screen flex">

    <?= $this->include('layouts/partials/sidebar') ?>

    <div class="flex flex-col flex-1 min-h-screen">

        <?= $this->include('layouts/partials/header') ?>

        <main class="p-6 flex-1">
            <?= $this->renderSection('content') ?>
        </main>

        <?= $this->include('layouts/partials/footer') ?>
    </div>

    <!-- Vue.js base scripts -->
    <script type="module" src="/assets/app.js"></script>

    <!-- Page-specific Vue.js components -->
    <?= $this->renderSection('scripts') ?>

</body>
</html>
```

All authenticated views must extend this layout and define a `content` section.

### Example Vue.js Component Integration

**app/Views/dashboard/index.php:**

```php
<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div id="dashboard-app">
    <dashboard-component :initial-data='<?= json_encode($dashboardData) ?>'></dashboard-component>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script type="module">
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import DashboardComponent from '@/components/dashboard/DashboardComponent.vue'

const app = createApp({
    components: { DashboardComponent }
})
app.use(createPinia())
app.mount('#dashboard-app')
</script>
<?= $this->endSection() ?>
```

### Vue.js Component Structure

`resources/js/components/dashboard/DashboardComponent.vue`:

```vue
<template>
  <div class="dashboard">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
      <stats-card
        v-for="stat in stats"
        :key="stat.label"
        :label="stat.label"
        :value="stat.value"
        :icon="stat.icon"
        :trend="stat.trend"
      />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <recent-activity :activities="recentActivities" />
      <quick-actions :actions="quickActions" />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useUserStore } from '@/stores/user'
import StatsCard from '@/components/shared/StatsCard.vue'
import RecentActivity from './RecentActivity.vue'
import QuickActions from './QuickActions.vue'
import api from '@/utils/api'

const props = defineProps({
  initialData: {
    type: Object,
    required: true
  }
})

const userStore = useUserStore()
const stats = ref(props.initialData.stats || [])
const recentActivities = ref(props.initialData.activities || [])
const quickActions = ref(props.initialData.actions || [])

onMounted(async () => {
  // Fetch user data for RBAC
  await userStore.fetchUser()

  // Optionally refresh dashboard data
  // const response = await api.get('/dashboard/refresh')
  // stats.value = response.data.stats
})
</script>
```

### TailAdmin Component Adaptation

**Using TailAdmin Components:**

```vue
<template>
  <div>
    <!-- Import TailAdmin components -->
    <tailadmin-button @click="handleAction">
      Click Me
    </tailadmin-button>

    <!-- Adapt to custom components -->
    <custom-data-table
      :columns="tableColumns"
      :data="tableData"
      :pagination="tablePagination"
    />
  </div>
</template>

<script setup>
import TailadminButton from '@/tailadmin/components/Button.vue'
import CustomDataTable from '@/components/shared/DataTable.vue'
// ... component logic
</script>
```

## 5. Minimal Feature Skeleton

### 5.1 Controllers

- `app/Controllers/Dashboard.php`
- `app/Controllers/Auth/LoginController.php`
- `app/Controllers/Clients/ClientController.php`

### 5.2 Models

- `app/Models/UserModel.php`
- `app/Models/ClientModel.php`

### 5.3 Views

- `app/Views/auth/login.php`
- `app/Views/dashboard/index.php`
- `app/Views/clients/index.php`
- `app/Views/clients/create.php`
- `app/Views/clients/edit.php`

### 5.4 Filters

- `app/Filters/AuthFilter.php` to protect authenticated routes.

## 6. Initial Migrations

Create migrations for:

1. Users
2. Workspaces
3. UserWorkspace (pivot)
4. Clients
5. Contacts
6. Pipelines
7. PipelineStages
8. Deals
9. Projects (with RBAC support)
10. ProjectUsers (project-user assignments with roles)
11. **Payment Gateways Configuration**
12. **Invoices** (with payment gateway support)
13. **Invoice Line Items**
14. **Payments** (transaction records)
15. **Payment Links** (standalone payment requests)
16. **Recurring Billing** (subscriptions/retainers)

Each table should include:

- `id` (UUID or serial, your choice)
- `created_at`, `updated_at`, `deleted_at` (soft delete)
- Foreign keys where applicable

### 6.1 RBAC-Specific Schema Requirements

**Users Table:**
```sql
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL, -- 'Owner', 'Agency', 'EndClient', 'DirectClient'
    agency_id UUID NULL, -- Foreign key to agencies table (for multi-agency isolation)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);
```

**Agencies Table (for multi-agency isolation):**
```sql
CREATE TABLE agencies (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);
```

**Projects Table:**
```sql
CREATE TABLE projects (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    project_type VARCHAR(50) NOT NULL, -- 'Agency' or 'Client'
    agency_id UUID NULL, -- For Agency Projects: links to the agency
    client_id UUID NOT NULL, -- Foreign key to clients table
    owner_id UUID NOT NULL, -- Foreign key to users table (the Owner)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (agency_id) REFERENCES agencies(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (owner_id) REFERENCES users(id)
);
```

**ProjectUsers Table (project-user assignments with roles):**
```sql
CREATE TABLE project_users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    project_id UUID NOT NULL,
    user_id UUID NOT NULL,
    role VARCHAR(50) NOT NULL, -- 'Owner', 'Agency', 'EndClient', 'DirectClient'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(project_id, user_id)
);
```

### 6.1.1 Payment Gateway Schema

**Payment Gateway Configuration Table:**
```sql
CREATE TABLE payment_gateways (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id UUID NOT NULL,
    gateway_type VARCHAR(50) NOT NULL, -- 'stripe', 'paypal', 'zelle', 'stripe_ach', 'venmo'
    gateway_mode VARCHAR(20) NOT NULL, -- 'test', 'live'
    is_enabled BOOLEAN DEFAULT false,
    config JSONB NOT NULL, -- Gateway-specific configuration (API keys, etc.)
    fee_structure JSONB NULL, -- {percentage: 2.9, fixed: 0.30, currency: 'USD'}
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    UNIQUE(workspace_id, gateway_type, gateway_mode)
);
```

**Invoices Table (Enhanced for Payments):**
```sql
CREATE TABLE invoices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    client_id UUID NOT NULL,
    project_id UUID NULL,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    status VARCHAR(50) NOT NULL, -- 'draft', 'sent', 'awaiting_payment', 'partially_paid', 'paid', 'refunded', 'overdue'
    payment_flow VARCHAR(50) NOT NULL, -- 'invoice_based', 'upfront_required', 'manual', 'payment_link'
    subtotal DECIMAL(10,2) NOT NULL,
    tax DECIMAL(10,2) DEFAULT 0,
    discount DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) DEFAULT 0,
    amount_due DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    due_date DATE NULL,
    sent_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
);
```

**Invoice Line Items Table:**
```sql
CREATE TABLE invoice_line_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    invoice_id UUID NOT NULL,
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);
```

**Payments Table (Transaction Records):**
```sql
CREATE TABLE payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    invoice_id UUID NULL, -- NULL for payment links
    payment_link_id UUID NULL,
    client_id UUID NOT NULL,
    gateway_type VARCHAR(50) NOT NULL, -- 'stripe', 'paypal', 'zelle', 'stripe_ach', 'venmo', 'manual'
    gateway_mode VARCHAR(20) NOT NULL, -- 'test', 'live', 'manual'
    gateway_transaction_id VARCHAR(255) NULL, -- Stripe charge ID, PayPal transaction ID, etc.
    gateway_fee DECIMAL(10,2) DEFAULT 0,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    status VARCHAR(50) NOT NULL, -- 'pending', 'completed', 'failed', 'refunded', 'partially_refunded'
    payment_method VARCHAR(50) NULL, -- 'card', 'ach', 'apple_pay', 'google_pay', 'paypal', 'zelle', 'manual'
    metadata JSONB NULL, -- Additional gateway-specific data
    paid_at TIMESTAMP NULL,
    refunded_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);
```

**Payment Links Table (Standalone Payment Requests):**
```sql
CREATE TABLE payment_links (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workspace_id UUID NOT NULL,
    client_id UUID NULL, -- Optional - can be used for ad-hoc payments
    description TEXT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    allowed_gateways JSONB NULL, -- ['stripe', 'paypal'] - NULL means all enabled
    slug VARCHAR(100) UNIQUE NOT NULL, -- Short URL slug
    expires_at TIMESTAMP NULL,
    max_uses INT NULL,
    current_uses INT DEFAULT 0,
    status VARCHAR(50) DEFAULT 'active', -- 'active', 'expired', 'completed', 'cancelled'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
);
```

**Recurring Billing Table (Subscriptions/Retainers):**
```sql
CREATE TABLE recurring_billing (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    client_id UUID NOT NULL,
    project_id UUID NULL,
    gateway_type VARCHAR(50) NOT NULL, -- 'stripe' (PayPal recurring in future)
    gateway_subscription_id VARCHAR(255) NOT NULL, -- Stripe subscription ID
    frequency VARCHAR(50) NOT NULL, -- 'monthly', 'quarterly', 'annual'
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    status VARCHAR(50) NOT NULL, -- 'active', 'paused', 'cancelled', 'expired'
    started_at TIMESTAMP NOT NULL,
    next_billing_date DATE NULL,
    ended_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
);
```

**Webhook Events Table (Audit Log):**
```sql
CREATE TABLE webhook_events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    gateway_type VARCHAR(50) NOT NULL,
    event_type VARCHAR(100) NOT NULL, -- 'payment.succeeded', 'invoice.payment_failed', etc.
    gateway_event_id VARCHAR(255) NOT NULL,
    payload JSONB NOT NULL,
    processed BOOLEAN DEFAULT false,
    processed_at TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(gateway_type, gateway_event_id)
);
```

### 6.2 RBAC Implementation Guidance

**Permission Checking in Controllers:**

Create a helper service `app/Domain/Auth/RBACService.php`:

```php
<?php

namespace App\Domain\Auth;

class RBACService
{
    /**
     * Check if user can view financial features
     */
    public function canViewFinancials($user, $project): bool
    {
        // Owner always has access
        if ($user->role === 'Owner') {
            return true;
        }

        // Agency role has access in Agency Projects
        if ($user->role === 'Agency' && $project->project_type === 'Agency') {
            return true;
        }

        // Direct Client has access in Client Projects
        if ($user->role === 'DirectClient' && $project->project_type === 'Client') {
            return true;
        }

        // End Client never has financial access
        return false;
    }

    /**
     * Filter projects by user's agency (multi-agency isolation)
     */
    public function filterProjectsByAgency($query, $user)
    {
        if ($user->role === 'Owner') {
            return $query; // Owners see all
        }

        if ($user->agency_id) {
            return $query->where('agency_id', $user->agency_id);
        }

        return $query;
    }

    /**
     * Check if user can access specific project
     */
    public function canAccessProject($user, $project): bool
    {
        // Owner can access everything
        if ($user->role === 'Owner') {
            return true;
        }

        // Check if user is assigned to this project
        $assignment = db_connect()
            ->table('project_users')
            ->where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->get()
            ->getRow();

        return $assignment !== null;
    }
}
```

**View Helpers for Role-Based Rendering:**

Create `app/Helpers/rbac_helper.php`:

```php
<?php

if (!function_exists('can_view_financials')) {
    function can_view_financials($user, $project): bool
    {
        $rbac = service('RBACService');
        return $rbac->canViewFinancials($user, $project);
    }
}

if (!function_exists('is_end_client')) {
    function is_end_client($user): bool
    {
        return $user->role === 'EndClient';
    }
}
```

**Example Controller Usage:**

```php
<?php

namespace App\Controllers\Projects;

use App\Controllers\BaseController;
use App\Domain\Auth\RBACService;

class ProjectController extends BaseController
{
    protected $rbacService;

    public function __construct()
    {
        $this->rbacService = service('RBACService');
    }

    public function view($projectId)
    {
        $user = auth()->user();
        $project = model('ProjectModel')->find($projectId);

        // Check access
        if (!$this->rbacService->canAccessProject($user, $project)) {
            return redirect()->to('/dashboard')->with('error', 'Access denied');
        }

        $data = [
            'project' => $project,
            'canViewFinancials' => $this->rbacService->canViewFinancials($user, $project)
        ];

        return view('projects/view', $data);
    }
}
```

**Example View Usage:**

```php
<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div class="container">
    <h1><?= esc($project->name) ?></h1>

    <!-- Always visible to all users -->
    <section>
        <h2>Project Status</h2>
        <p><?= esc($project->status) ?></p>
    </section>

    <section>
        <h2>Milestones</h2>
        <!-- Milestone list -->
    </section>

    <!-- Conditionally visible based on role -->
    <?php if ($canViewFinancials): ?>
        <section class="border-t pt-4 mt-4">
            <h2>Financial Information</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label>Billing Rate:</label>
                    <span><?= esc($project->billing_rate) ?></span>
                </div>
                <div>
                    <label>Total Invoiced:</label>
                    <span><?= esc($project->total_invoiced) ?></span>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
```

## 7. Docker (Optional)

`docker/docker-compose.yml` provides:

- `app` (php-fpm)
- `web` (nginx)
- `postgres` (PostgreSQL 16)

Use `php-fpm.Dockerfile` to install PHP extensions for PostgreSQL.

## 7.1 Payment Gateway Implementation Guidance

### Stripe Integration

**Required Packages:**
```bash
composer require stripe/stripe-php
```

**Environment Configuration (`env` file):**
```
STRIPE_PUBLISHABLE_KEY_TEST=pk_test_...
STRIPE_SECRET_KEY_TEST=sk_test_...
STRIPE_PUBLISHABLE_KEY_LIVE=pk_live_...
STRIPE_SECRET_KEY_LIVE=sk_live_...
STRIPE_WEBHOOK_SECRET_TEST=whsec_...
STRIPE_WEBHOOK_SECRET_LIVE=whsec_...
```

**Service Implementation (`app/Domain/Payments/StripeService.php`):**
```php
<?php

namespace App\Domain\Payments;

use Stripe\StripeClient;
use Stripe\Checkout\Session;
use Stripe\Webhook;

class StripeService
{
    protected $stripe;
    protected $mode; // 'test' or 'live'

    public function __construct($mode = 'test')
    {
        $this->mode = $mode;
        $secretKey = $mode === 'live'
            ? getenv('STRIPE_SECRET_KEY_LIVE')
            : getenv('STRIPE_SECRET_KEY_TEST');

        $this->stripe = new StripeClient($secretKey);
    }

    /**
     * Create Stripe Checkout session for invoice payment
     */
    public function createCheckoutSession($invoice, $successUrl, $cancelUrl)
    {
        $lineItems = [];
        foreach ($invoice->lineItems as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => strtolower($invoice->currency),
                    'unit_amount' => (int)($item->unit_price * 100), // Convert to cents
                    'product_data' => [
                        'name' => $item->description,
                    ],
                ],
                'quantity' => $item->quantity,
            ];
        }

        $session = $this->stripe->checkout->sessions->create([
            'payment_method_types' => ['card', 'us_bank_account'], // ACH enabled
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => $invoice->id,
            'metadata' => [
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
            ],
        ]);

        return $session;
    }

    /**
     * Create recurring subscription for retainer
     */
    public function createSubscription($client, $amount, $frequency, $metadata = [])
    {
        // Create or retrieve Stripe customer
        $customer = $this->stripe->customers->create([
            'email' => $client->email,
            'name' => $client->name,
            'metadata' => ['client_id' => $client->id],
        ]);

        // Create price for subscription
        $price = $this->stripe->prices->create([
            'unit_amount' => (int)($amount * 100),
            'currency' => 'usd',
            'recurring' => ['interval' => $frequency], // 'month', 'year'
            'product_data' => [
                'name' => "Retainer for {$client->name}",
            ],
        ]);

        // Create subscription
        $subscription = $this->stripe->subscriptions->create([
            'customer' => $customer->id,
            'items' => [['price' => $price->id]],
            'metadata' => $metadata,
        ]);

        return $subscription;
    }

    /**
     * Process refund
     */
    public function createRefund($paymentIntentId, $amount = null)
    {
        $refundData = ['payment_intent' => $paymentIntentId];
        if ($amount !== null) {
            $refundData['amount'] = (int)($amount * 100);
        }

        return $this->stripe->refunds->create($refundData);
    }

    /**
     * Verify webhook signature and parse event
     */
    public function constructWebhookEvent($payload, $signature)
    {
        $webhookSecret = $this->mode === 'live'
            ? getenv('STRIPE_WEBHOOK_SECRET_LIVE')
            : getenv('STRIPE_WEBHOOK_SECRET_TEST');

        return Webhook::constructEvent($payload, $signature, $webhookSecret);
    }
}
```

**Webhook Handler (`app/Controllers/Webhooks/StripeWebhookController.php`):**
```php
<?php

namespace App\Controllers\Webhooks;

use App\Controllers\BaseController;
use App\Domain\Payments\StripeService;

class StripeWebhookController extends BaseController
{
    public function handle()
    {
        $payload = @file_get_contents('php://input');
        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        try {
            $stripeService = new StripeService(getenv('STRIPE_MODE') ?? 'test');
            $event = $stripeService->constructWebhookEvent($payload, $signature);

            // Log webhook event
            $webhookModel = model('WebhookEventModel');
            $webhookModel->insert([
                'gateway_type' => 'stripe',
                'event_type' => $event->type,
                'gateway_event_id' => $event->id,
                'payload' => json_encode($event->data->object),
                'processed' => false,
            ]);

            // Handle different event types
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handleCheckoutCompleted($event->data->object);
                    break;
                case 'payment_intent.succeeded':
                    $this->handlePaymentSucceeded($event->data->object);
                    break;
                case 'charge.refunded':
                    $this->handleRefund($event->data->object);
                    break;
                // Add more event types as needed
            }

            return $this->response->setJSON(['received' => true]);

        } catch (\Exception $e) {
            log_message('error', 'Stripe webhook error: ' . $e->getMessage());
            return $this->response->setStatusCode(400)->setJSON(['error' => $e->getMessage()]);
        }
    }

    protected function handleCheckoutCompleted($session)
    {
        $invoiceId = $session->metadata->invoice_id ?? null;
        if (!$invoiceId) return;

        $invoiceModel = model('InvoiceModel');
        $paymentModel = model('PaymentModel');

        // Record payment
        $paymentModel->insert([
            'invoice_id' => $invoiceId,
            'client_id' => $session->metadata->client_id,
            'gateway_type' => 'stripe',
            'gateway_mode' => getenv('STRIPE_MODE') ?? 'test',
            'gateway_transaction_id' => $session->payment_intent,
            'amount' => $session->amount_total / 100,
            'currency' => strtoupper($session->currency),
            'status' => 'completed',
            'payment_method' => $session->payment_method_types[0] ?? 'card',
            'paid_at' => date('Y-m-d H:i:s'),
        ]);

        // Update invoice status
        $invoice = $invoiceModel->find($invoiceId);
        $newAmountPaid = $invoice->amount_paid + ($session->amount_total / 100);

        $invoiceModel->update($invoiceId, [
            'amount_paid' => $newAmountPaid,
            'amount_due' => $invoice->total - $newAmountPaid,
            'status' => $newAmountPaid >= $invoice->total ? 'paid' : 'partially_paid',
            'paid_at' => $newAmountPaid >= $invoice->total ? date('Y-m-d H:i:s') : null,
        ]);
    }

    protected function handlePaymentSucceeded($paymentIntent)
    {
        // Similar logic to handleCheckoutCompleted
    }

    protected function handleRefund($charge)
    {
        // Update payment and invoice status for refunds
    }
}
```

### PayPal Integration

**Required Packages:**
```bash
composer require paypal/rest-api-sdk-php
```

**Environment Configuration:**
```
PAYPAL_CLIENT_ID_TEST=...
PAYPAL_CLIENT_SECRET_TEST=...
PAYPAL_CLIENT_ID_LIVE=...
PAYPAL_CLIENT_SECRET_LIVE=...
PAYPAL_MODE=sandbox # or live
```

**Service Implementation (`app/Domain/Payments/PayPalService.php`):**
```php
<?php

namespace App\Domain\Payments;

use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;

class PayPalService
{
    protected $apiContext;
    protected $mode;

    public function __construct($mode = 'sandbox')
    {
        $this->mode = $mode;

        $clientId = $mode === 'live'
            ? getenv('PAYPAL_CLIENT_ID_LIVE')
            : getenv('PAYPAL_CLIENT_ID_TEST');

        $clientSecret = $mode === 'live'
            ? getenv('PAYPAL_CLIENT_SECRET_LIVE')
            : getenv('PAYPAL_CLIENT_SECRET_TEST');

        $this->apiContext = new ApiContext(
            new OAuthTokenCredential($clientId, $clientSecret)
        );

        $this->apiContext->setConfig(['mode' => $mode]);
    }

    public function createPayment($invoice, $returnUrl, $cancelUrl)
    {
        // PayPal payment creation logic
        // Return PayPal approval URL for redirect
    }

    public function executePayment($paymentId, $payerId)
    {
        // Execute payment after PayPal approval
    }
}
```

### Zelle Manual Entry

**UI Display (`app/Views/invoices/view.php`):**
```php
<?php if ($invoice->status !== 'paid'): ?>
    <div class="payment-options">
        <h3>Payment Options</h3>

        <!-- Stripe -->
        <button onclick="payWithStripe()">
            Pay with Stripe - $<?= number_format($invoice->total + $stripeFee, 2) ?>
            <span class="fee-note">(+$<?= number_format($stripeFee, 2) ?> fee)</span>
        </button>

        <!-- PayPal -->
        <button onclick="payWithPayPal()">
            Pay with PayPal - $<?= number_format($invoice->total + $paypalFee, 2) ?>
            <span class="fee-note">(+$<?= number_format($paypalFee, 2) ?> fee)</span>
        </button>

        <!-- Zelle -->
        <div class="zelle-option">
            <p><strong>Pay with Zelle (No Fees)</strong></p>
            <p>Send $<?= number_format($invoice->total, 2) ?> to:</p>
            <p class="zelle-details"><?= esc($workspace->zelle_email) ?></p>
            <p class="text-sm">Note: Payment may take 1-2 business days to process</p>
        </div>
    </div>
<?php endif; ?>
```

## 8. Claude Code First Sprint Checklist

### Phase 1: Backend Foundation
1. Ensure `composer install` and basic CI4 app boots.
2. Configure Postgres in `Database.php`.
3. Implement migrations for:
   - users (with `role` and `agency_id` fields)
   - agencies
   - workspaces
   - clients
   - projects (with `project_type` and `agency_id` fields)
   - project_users (pivot table with role assignment)
   - pipelines/deals
4. Implement Auth (login/logout) + `AuthFilter`.
5. Implement RBAC:
   - Create `RBACService` in `app/Domain/Auth/`
   - Create `rbac_helper.php` with permission checking functions
   - Register RBAC service in `app/Config/Services.php`

### Phase 2: Frontend Setup (Vue.js + TailAdmin)
6. **Install Node.js dependencies:**
   - Run `npm install` to install Vue.js, Vite, Pinia, Axios, TailwindCSS
   - Copy TailAdmin template files to `resources/tailadmin/`
   - Configure Vite (`vite.config.js`) with Vue plugin and aliases
   - Configure TailwindCSS to include Vue.js files and TailAdmin components
7. **Setup Vue.js architecture:**
   - Create `resources/js/app.js` entry point with Pinia initialization
   - Create Pinia stores (`user.js`, `ui.js`) in `resources/js/stores/`
   - Create Axios API client (`resources/js/utils/api.js`) with CSRF and error handling
   - Setup component directory structure (`components/layout/`, `components/shared/`, etc.)
8. **Build frontend assets:**
   - Run `npm run build` to compile Vue.js components and TailwindCSS
   - Verify assets are output to `public/assets/`
   - Test hot module replacement with `npm run dev`

### Phase 3: Layout & Components
9. Implement shared layout (header, sidebar, footer) with Vue.js integration:
   - Update `app/Views/layouts/app.php` with Vue.js script imports
   - Add CSRF meta tag for Axios
   - Create Vue.js layout components (Sidebar.vue, Header.vue)
   - Implement role-based navigation using Pinia user store
10. Implement Dashboard with Vue.js components:
   - Create `DashboardComponent.vue` with TailAdmin components
   - Pass initial data from PHP controller to Vue.js component
   - Implement reactive stats cards, recent activity, quick actions
   - Test Pinia state management and API calls

### Phase 4: Core Features
11. Implement Clients module with Vue.js:
    - Create `ClientList.vue`, `ClientForm.vue` components
    - Use TailAdmin table and form components
    - Implement CRUD operations with Axios API client
    - Add client search and filtering
12. Implement Projects module with RBAC:
    - Create Vue.js components for project management
    - Project creation with manual project type selection
    - User assignment with manual role selection
    - Financial data visibility based on user role (using Pinia getters)
    - Conditional rendering of financial sections in Vue.js templates

### Phase 5: Payment Gateway Integration
13. **Install payment packages:**
    - `composer require stripe/stripe-php paypal/rest-api-sdk-php`
    - Create payment gateway migrations (payment_gateways, payments, payment_links, recurring_billing, webhook_events)
14. **Implement payment services:**
    - Implement `StripeService` in `app/Domain/Payments/`
    - Implement `PayPalService` in `app/Domain/Payments/`
    - Create webhook controllers for Stripe and PayPal
    - Update Invoices migration with payment fields
15. **Build payment UI with Vue.js:**
    - Create `InvoicePayment.vue` component with gateway selection
    - Implement fee transparency display
    - Add Zelle manual entry display
    - Integrate Stripe Checkout with Vue.js
    - Handle webhook responses and update UI reactively
16. **Configure external services:**
    - Configure webhook endpoints in Stripe/PayPal dashboards
    - Test payment flows in sandbox/test mode

### Phase 6: Testing
17. Add tests:
    - Simple test hitting `/` and `/clients`
    - RBAC permission tests for financial data visibility
    - Multi-agency isolation tests
    - **Payment gateway tests:**
      - Stripe Checkout session creation
      - Webhook event processing
      - Invoice payment status updates
      - Partial payment tracking
      - Refund processing
    - **Vue.js component tests:**
      - Pinia store state management
      - Component props and events
      - API integration tests
