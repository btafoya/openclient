# RBAC Layer 4: Frontend Permissions (UX Layer)

## ⚠️ CRITICAL SECURITY WARNING

**Frontend permission checks are for USER EXPERIENCE ONLY.**

### Frontend ≠ Security

Frontend permissions DO NOT provide security. An attacker with browser DevTools can:
- Inspect and modify Vue.js component state
- Unhide permission-restricted UI elements with CSS/DOM manipulation
- Call API endpoints directly (bypassing all frontend logic)
- Modify JavaScript to enable disabled buttons
- Override computed properties in the Pinia store
- Remove `v-if` directives through console manipulation

### Real Security Enforcement

**Backend layers enforce actual security:**

1. **Layer 1: PostgreSQL RLS** (Week 7-8)
   - Row-Level Security policies filter data at SQL level
   - Cannot be bypassed by any application code
   - Automatically filters by `agency_id` for tenant isolation

2. **Layer 2: HTTP Middleware** (Week 9-10)
   - `RBACFilter` blocks unauthorized routes
   - End Clients blocked from financial routes
   - Non-Owners blocked from admin routes
   - Runs before controller logic

3. **Layer 3: Service Guards** (Week 11-12)
   - `InvoiceGuard`, `ProjectGuard`, `ClientGuard`
   - Fine-grained resource-specific authorization
   - Check permissions before business logic execution
   - SecurityLogger captures all violations

**Frontend permissions (Layer 4) improve UX by:**
- Hiding irrelevant features for the current role
- Preventing accidental unauthorized actions
- Providing clear visual feedback on user capabilities
- Reducing confusion and improving usability

## Architecture Position

```
┌─────────────────────────────────────────────────────────────┐
│                    Request Flow                              │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  Frontend (UX Only) ← Layer 4 (THIS DOCUMENT)               │
│     ↓ User clicks "Delete Invoice" button                   │
│     ↓ (Button hidden if !canDeleteInvoices)                 │
│     ↓                                                         │
│  1. HTTP Request → Backend                                   │
│     ↓                                                         │
│  2. Authentication (LoginFilter)                             │
│     ↓                                                         │
│  3. HTTP Authorization (RBACFilter) ← Layer 2               │
│     ↓                                                         │
│  4. Controller Logic                                         │
│     ↓                                                         │
│  5. Authorization Guards ← Layer 3                          │
│     ↓                                                         │
│  6. Business Logic / Model Operations                        │
│     ↓                                                         │
│  7. Database RLS Enforcement ← Layer 1                      │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

## Implementation

### Pinia User Store

**Location**: `resources/js/stores/user.js`

Central store for authenticated user data and permission computed properties.

```javascript
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useUserStore = defineStore('user', () => {
  // State
  const id = ref(null)
  const email = ref(null)
  const role = ref(null)
  const agencyId = ref(null)

  // Computed properties (permission checks)
  const canViewFinancials = computed(() => {
    return ['owner', 'agency', 'direct_client'].includes(role.value)
  })

  const canManageUsers = computed(() => {
    return role.value === 'owner'
  })

  const isOwner = computed(() => role.value === 'owner')
  const isAgency = computed(() => role.value === 'agency')
  const isEndClient = computed(() => role.value === 'end_client')

  // Actions
  function init(userData) {
    id.value = userData.id
    email.value = userData.email
    role.value = userData.role
    agencyId.value = userData.agency_id
  }

  return {
    id, email, role, agencyId,
    canViewFinancials, canManageUsers,
    isOwner, isAgency, isEndClient,
    init
  }
})
```

**Key Features:**
- Initialized once from server session data
- Computed properties for permission checks
- Role convenience checks (isOwner, isAgency, etc.)
- Display helpers (fullName, initials, roleDisplay)

### Permission Composable

**Location**: `resources/js/composables/usePermissions.js`

Reusable permission checking logic for components.

```javascript
import { useUserStore } from '@/stores/user'

export function usePermissions() {
  const userStore = useUserStore()

  const can = (permission) => {
    const permissions = {
      'view-financials': userStore.canViewFinancials,
      'create-invoices': ['owner', 'agency'].includes(userStore.role),
      'delete-invoices': userStore.isOwner,
      // ... more permissions
    }

    return permissions[permission] ?? false
  }

  return { can }
}
```

**Usage in Components:**
```vue
<script setup>
import { usePermissions } from '@/composables/usePermissions'

const { can } = usePermissions()
</script>

<template>
  <!-- Button only shown if user has delete permission -->
  <button v-if="can('delete-invoices')" @click="deleteInvoice">
    Delete
  </button>
</template>
```

### Sidebar Component

**Location**: `resources/js/components/layout/Sidebar.vue`

Navigation sidebar with role-based menu items.

```vue
<script setup>
import { useUserStore } from '@/stores/user'

const userStore = useUserStore()
</script>

<template>
  <aside class="sidebar">
    <!-- Everyone sees these -->
    <router-link to="/dashboard">Dashboard</router-link>
    <router-link to="/clients">Clients</router-link>
    <router-link to="/projects">Projects</router-link>

    <!-- Financial section (hidden for End Clients) -->
    <template v-if="userStore.canViewFinancials">
      <div class="section-header">Financial</div>
      <router-link to="/invoices">Invoices</router-link>
      <router-link to="/quotes">Quotes</router-link>
      <router-link to="/payments">Payments</router-link>
    </template>

    <!-- Admin section (Owner only) -->
    <template v-if="userStore.isOwner">
      <div class="section-header">Administration</div>
      <router-link to="/admin/users">Users</router-link>
      <router-link to="/admin/settings">Settings</router-link>
    </template>
  </aside>
</template>
```

**Key Features:**
- Navigation items shown/hidden based on role
- Financial section hidden for End Clients
- Admin section visible only for Owner
- Agency settings for Agency role

### Initialize from Server Data

**Location**: `app/Views/layouts/app.php`

PHP layout initializes Pinia store with server session data.

```php
<!-- Initialize Vue.js + Pinia + User Store -->
<script type="module">
  import { createApp } from 'vue'
  import { createPinia } from 'pinia'
  import { useUserStore } from '/assets/js/stores/user.js'

  const app = createApp({})
  const pinia = createPinia()
  app.use(pinia)

  // Initialize user store with server-side session data
  const userStore = useUserStore()

  <?php
  $user = session()->get('user');
  if ($user):
  ?>
  userStore.init(<?= json_encode($user) ?>)
  <?php endif; ?>

  app.mount('#app')
</script>
```

**Data Flow:**
1. User logs in → session data stored server-side
2. PHP renders page → includes session data in JSON
3. Vue.js mounts → Pinia store initialized with session data
4. Components access store → permission checks available

## Permission Patterns

### v-if Directives

**✅ Correct: Use v-if to remove from DOM**
```vue
<button v-if="can('delete-invoices')" @click="deleteInvoice">
  Delete
</button>
```

**❌ Wrong: Use CSS to hide (still in DOM)**
```vue
<!-- Attacker can unhide with CSS -->
<button :style="{ display: can('delete-invoices') ? 'block' : 'none' }">
  Delete
</button>
```

### Computed Properties

```vue
<script setup>
import { computed } from 'vue'
import { useUserStore } from '@/stores/user'

const userStore = useUserStore()

const showDeleteButton = computed(() => {
  return userStore.isOwner
})
</script>

<template>
  <button v-if="showDeleteButton">Delete</button>
</template>
```

### Template Sections

```vue
<template v-if="userStore.canViewFinancials">
  <div class="financial-section">
    <!-- Multiple financial elements -->
  </div>
</template>
```

## Testing Strategy

### Unit Tests - User Store

```javascript
// tests/unit/stores/user.test.js
import { setActivePinia, createPinia } from 'pinia'
import { useUserStore } from '@/stores/user'

describe('User Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('initializes correctly with owner data', () => {
    const store = useUserStore()
    store.init({ id: '1', role: 'owner', email: 'owner@example.com' })

    expect(store.isOwner).toBe(true)
    expect(store.canViewFinancials).toBe(true)
    expect(store.canManageUsers).toBe(true)
  })

  it('restricts end client from financials', () => {
    const store = useUserStore()
    store.init({ id: '2', role: 'end_client', email: 'client@example.com' })

    expect(store.isEndClient).toBe(true)
    expect(store.canViewFinancials).toBe(false)
    expect(store.canManageUsers).toBe(false)
  })
})
```

### Component Tests - Sidebar

```javascript
// tests/unit/components/Sidebar.test.js
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import Sidebar from '@/components/layout/Sidebar.vue'
import { useUserStore } from '@/stores/user'

describe('Sidebar Component', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('hides financial section for end clients', () => {
    const userStore = useUserStore()
    userStore.init({ role: 'end_client' })

    const wrapper = mount(Sidebar)

    expect(wrapper.text()).not.toContain('Invoices')
    expect(wrapper.text()).not.toContain('Payments')
  })

  it('shows admin section for owner', () => {
    const userStore = useUserStore()
    userStore.init({ role: 'owner' })

    const wrapper = mount(Sidebar)

    expect(wrapper.text()).toContain('Administration')
    expect(wrapper.text()).toContain('Users')
    expect(wrapper.text()).toContain('Settings')
  })
})
```

### E2E Tests - Backend Enforcement

**Critical: Test that backend STILL enforces even if frontend bypassed**

```javascript
// tests/e2e/security/authorization.spec.js
import { test, expect } from '@playwright/test'

test('backend blocks delete even if frontend bypassed', async ({ page }) => {
  // Login as agency user
  await page.goto('/auth/login')
  await page.fill('[name="email"]', 'agency@example.com')
  await page.fill('[name="password"]', 'password')
  await page.click('button[type="submit"]')

  // Bypass frontend: directly call API endpoint
  const response = await page.request.delete('/api/invoices/123')

  // Backend should return 403 Forbidden
  expect(response.status()).toBe(403)

  // Verify security log captured the attempt
  // (In real test, would check security log file)
})

test('end client blocked from invoices even if URL direct accessed', async ({ page }) => {
  // Login as end client
  await page.goto('/auth/login')
  await page.fill('[name="email"]', 'endclient@example.com')
  await page.fill('[name="password"]', 'password')
  await page.click('button[type="submit"]')

  // Bypass frontend: directly navigate to invoice route
  await page.goto('/invoices')

  // Backend middleware should redirect to dashboard
  await expect(page).toHaveURL('/dashboard')
  await expect(page.locator('text=Access denied')).toBeVisible()
})
```

## Common Mistakes to Avoid

### ❌ DON'T: Rely on Frontend for Security

```vue
<!-- INSECURE: Only frontend check -->
<button v-if="can('delete')" @click="deleteInvoice">Delete</button>
```

```javascript
// ❌ Backend doesn't validate
async deleteInvoice(id) {
  await db.invoices.delete(id)  // No authorization check!
}
```

**Problem**: Attacker bypasses frontend, calls API directly, data deleted.

### ❌ DON'T: Make API Calls Without Backend Authorization

```vue
<template>
  <button v-if="isOwner" @click="deleteUser">Delete User</button>
</template>
```

```php
// ❌ Controller doesn't check permissions
public function deleteUser($id) {
    $this->userModel->delete($id);  // No guard check!
}
```

**Problem**: Frontend hides button, but API endpoint still accessible.

### ❌ DON'T: Hide with CSS Instead of v-if

```vue
<!-- ❌ Still in DOM, can be unhidden -->
<button :class="{ 'hidden': !canDelete }">Delete</button>

<!-- ❌ Still in DOM, can be unhidden -->
<button :style="{ display: canDelete ? 'block' : 'none' }">Delete</button>
```

**Problem**: Attacker inspects DOM, removes CSS, clicks button.

### ✅ DO: Use v-if to Remove from DOM

```vue
<!-- ✅ Removed from DOM entirely -->
<button v-if="can('delete')">Delete</button>
```

**Note**: Even this doesn't provide security, just better UX.

### ✅ DO: Trust Backend Authorization

```vue
<template>
  <button v-if="can('delete')" @click="deleteInvoice">Delete</button>
</template>

<script setup>
const deleteInvoice = async (id) => {
  try {
    await axios.delete(`/api/invoices/${id}`)
    // Success
  } catch (error) {
    // Backend returned 403 Forbidden
    alert('Access denied: ' + error.response.data.error)
  }
}
</script>
```

```php
// ✅ Controller enforces authorization
public function delete($id) {
    $user = session()->get('user');
    $invoice = $this->model->find($id);

    if (!$this->guard->canDelete($user, $invoice)) {
        SecurityLogger::logAccessDenied($user, "Invoice #{$id}", 'Delete attempt');
        return $this->response->setStatusCode(403)->setJSON(['error' => 'Access denied']);
    }

    $this->model->delete($id);
}
```

### ✅ DO: Test Backend Blocks Access

Always write E2E tests that verify backend enforcement even when frontend bypassed.

## Permission Summary for API Responses

Backend can return permission summary in API responses for frontend use:

```php
// InvoicesController.php
public function show($id) {
    $user = session()->get('user');
    $invoice = $this->model->find($id);

    if (!$this->guard->canView($user, $invoice)) {
        return $this->response->setStatusCode(403)->setJSON(['error' => 'Access denied']);
    }

    return $this->response->setJSON([
        'invoice' => $invoice,
        'permissions' => $this->guard->getPermissionSummary($user, $invoice)
    ]);
}
```

Frontend uses permissions for UI:

```vue
<script setup>
const invoice = ref(null)
const permissions = ref({})

const fetchInvoice = async (id) => {
  const response = await axios.get(`/api/invoices/${id}`)
  invoice.value = response.data.invoice
  permissions.value = response.data.permissions
}
</script>

<template>
  <button v-if="permissions.canEdit" @click="editInvoice">Edit</button>
  <button v-if="permissions.canDelete" @click="deleteInvoice">Delete</button>
</template>
```

## Compliance Considerations

### Audit Requirements

Frontend permissions support audit requirements by:
- Reducing accidental unauthorized actions
- Providing clear visual feedback on user capabilities
- Improving usability and reducing confusion

**However**: Frontend does NOT satisfy security audit requirements. Backend authorization (Layers 1-3) satisfies audits.

### Security Questionnaires

When answering security questionnaires:

**Q: "How do you enforce authorization?"**
**A**: "Three-layer defense-in-depth: PostgreSQL RLS, HTTP middleware, and service-level guards. Frontend permission checks improve UX but do not provide security."

**Q: "Can users bypass authorization?"**
**A**: "Frontend can be bypassed, but backend authorization cannot. All API endpoints enforce authorization via HTTP middleware and service guards. Database RLS provides final layer."

## Summary

Frontend permission checks (RBAC Layer 4) provide user experience improvements, NOT security.

**Key Points:**
- ✅ Use frontend permissions to improve UX
- ✅ Hide irrelevant features for current role
- ✅ Prevent accidental unauthorized actions
- ❌ Do NOT rely on frontend for security
- ❌ Do NOT make API calls without backend authorization
- ❌ Do NOT skip backend validation because "frontend blocks it"

**Real Security:**
- Layer 1: PostgreSQL RLS (database)
- Layer 2: HTTP Middleware (routes)
- Layer 3: Service Guards (business logic)

**Frontend:**
- Layer 4: Permission checks (UX only)

Always test that backend blocks access even when frontend bypassed. Frontend permissions are a convenience feature, not a security feature.

For questions or security concerns, refer to backend authorization documentation:
- `docs/architecture/rbac-http-middleware.md` (Layer 2)
- `docs/architecture/rbac-service-guards.md` (Layer 3)
