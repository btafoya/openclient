# PRD: openclient – Open Source Taskip-Style Platform

## 1. Overview

**Project name:** openclient
**Goal:** Build a fully open source, self-hostable alternative to Taskip-style all-in-one agency tools (CRM + projects + billing + support + client portal), using:

- **Backend**: PHP 8.2+, CodeIgniter 4
- **Database**: PostgreSQL
- **Frontend**: Vue.js 3 (Composition API) with TailAdmin template
- **UI Framework**: TailwindCSS 3
- **Build Tool**: Vite
- **State Management**: Pinia
- **HTTP Client**: Axios
- **Auth**: CI4 auth + session (JWT for API later)
- **Architecture**: Hybrid - PHP/CodeIgniter renders views, Vue.js components embedded for interactivity
- **Deploy**: Docker or bare metal, no vendor lock-in

Primary users:

- Freelancers and agencies (1–20 seats)
- Internal teams who need a client & work hub
- Self-hosters who want full data ownership

## 2. Core Feature Pillars

### 2.1 CRM & Contacts

- Organizations (clients) & individual contacts
- Tags, custom fields, notes
- Timeline of interactions: notes, tasks, deals, tickets
- Import/export via CSV

### 2.2 Pipelines & Deals

- Multiple sales pipelines (e.g., Projects, Retainers)
- Stages as Kanban columns
- Deal value, close date, probability, source
- Link to client, contacts, tasks, docs

### 2.3 Projects & Tasks

- Projects scoped to a client
- Task lists, tasks, subtasks
- Assignee, due date, priority, status
- Time tracking (manual entry in v1)
- Comments and file attachments

### 2.4 Invoices & Quotes

- Quotes/estimates → convert to invoice
- Line items, tax, discount
- Status: draft, sent, paid, overdue, partially paid
- Simple recurring invoices
- PDF export
- **Multiple payment flows:**
  - Invoice-based payment (client receives invoice → clicks "Pay Now" → chooses gateway)
  - Upfront payment required (payment before invoice sent - for deposits/milestones)
  - Manual payment recording (for checks, wire transfers, cash)
  - Payment links (standalone payment collection without invoice)

### 2.4.1 Payment Gateway Integration

**Supported Gateways:**

1. **Stripe** (Primary - Full Featured)
   - One-time card payments (Visa, Mastercard, Amex, Discover)
   - Recurring billing / subscriptions (for retainers)
   - Stripe Checkout (hosted payment page - PCI compliant)
   - Multiple payment methods:
     - Credit/debit cards
     - ACH Direct Debit (bank transfers)
     - Apple Pay
     - Google Pay
     - Link (Stripe's 1-click payment)
   - Automatic payment confirmation via webhooks
   - Refund processing
   - Test mode (sandbox)

2. **PayPal** (Alternative)
   - PayPal Standard (redirect to PayPal → client pays → redirect back)
   - One-time payments only (v1)
   - Automatic payment confirmation via webhooks
   - PayPal sandbox for testing

3. **Zelle** (Manual Entry)
   - Display Zelle email/phone on invoice
   - Client sends payment via Zelle app (external)
   - Manual payment confirmation in openclient
   - No processing fees

4. **Stripe ACH Direct Debit** (Zelle Alternative)
   - Bank-to-bank transfers with API integration
   - Lower fees than cards (~0.8%)
   - Automated payment confirmation
   - Multi-day settlement (like Zelle)

5. **Venmo for Business** (Optional)
   - API-based integration
   - Popular with younger clients
   - Low fees
   - Automatic payment confirmation

**Gateway Selection:**
- Client chooses payment method at checkout
- Invoice displays all enabled gateways with transparent fee disclosure:
  - Example: "Pay $1,000 via Stripe ($29 fee) | PayPal ($35 fee) | Zelle ($0 fee)"
- Client optimizes for cost/convenience

**Payment Features:**
- **Automatic Confirmation**: Stripe/PayPal webhooks auto-mark invoices paid
- **Partial Payments**: Track multiple payments per invoice, remaining balance
- **Refund Handling**: Process refunds directly through Stripe/PayPal
- **Payment Reconciliation**: Reports showing payment history, fees, gateway status
- **Fee Transparency**: Display gateway fees at checkout, client chooses optimal method
- **Test/Sandbox Mode**: Stripe test mode + PayPal sandbox for development/demos

**Payment Status Tracking:**
- Draft → Sent → Awaiting Payment → Partially Paid → Paid → Refunded → Overdue
- Webhook-driven status updates (Stripe/PayPal)
- Manual status updates (Zelle, checks, wire)

### 2.5 Proposals

- Template-based proposals (Markdown or simple blocks)
- Merge fields: client, project, pricing table
- Accept/decline via client portal
- Record acceptance event (time, IP, name)

### 2.6 Forms & Onboarding

- Simple form builder (input, textarea, select, checkbox, file)
- Public links for intake/onboarding
- Responses wired to a client/deal/project

### 2.7 Documents

- Per-client and per-project folders
- Upload/download, tagging, search
- Internal vs client-visible flags

### 2.8 Tickets & Support

- Ticket categories & statuses
- Assignment to team members
- Internal notes vs public replies
- Client portal view & reply

### 2.9 Discussions

- Discussion threads at client/project/deal/ticket level
- @mentions and notifications

### 2.10 Meetings & Calendar

- Meetings per client/contact/deal/project
- Basic ICS feed per user

### 2.11 Client Portal

- Separate login for clients
- View projects, tasks (client-visible), invoices, quotes, proposals, tickets, documents
- Fill and submit forms
- Light branding (logo, colors)

## 3. Non-Goals (v1)

- No multi-tenant SaaS billing layer (single tenant/self-host)
- No complex automation builder (just manual flows in v1)
- No native mobile apps (must be mobile-friendly though)

## 4. Architecture Notes

### 4.1 Backend Architecture (PHP/CodeIgniter 4)

- Domain-oriented structure under `app/Domain/*` for business logic
- HTTP controllers under `app/Controllers/*` are thin
- Controllers render views with data, Vue.js handles interactivity
- PostgreSQL schema with UUID primary keys, soft deletes, audit fields
- Config-based role/capability mapping

### 4.2 Frontend Architecture (Vue.js 3 + TailAdmin)

**Hybrid Approach**: PHP/CodeIgniter renders HTML views, Vue.js components embedded for rich interactivity

**Tech Stack**:
- **Vue.js 3** - Composition API for reactive components
- **TailAdmin Template** - Pre-built Vue components and layouts
- **Vite** - Fast build tool and dev server
- **Pinia** - State management (user session, app state)
- **Axios** - HTTP client for API calls to PHP backend
- **TailwindCSS 3** - Utility-first CSS framework

**Integration Pattern**:
```php
<!-- CodeIgniter View: app/Views/dashboard/index.php -->
<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div id="dashboard-app">
    <dashboard-component :initial-data='<?= json_encode($dashboardData) ?>'></dashboard-component>
</div>

<script type="module">
import { createApp } from 'vue'
import DashboardComponent from '@/components/DashboardComponent.vue'

createApp({
    components: { DashboardComponent }
}).mount('#dashboard-app')
</script>

<?= $this->endSection() ?>
```

**Component Strategy**:
1. **Use TailAdmin Components**: Leverage pre-built TailAdmin Vue components for common UI patterns
2. **Custom Adapters**: Create wrapper components to adapt TailAdmin to openclient's data models
3. **Layout Structure**: Adopt TailAdmin's sidebar, header, navigation patterns
4. **Design System**: Follow TailAdmin's design language and TailwindCSS configuration

**State Management with Pinia**:
- **Required Stores**:
  - **User Store**: Auth state, permissions, current user data, RBAC getters (`canViewFinancials`, `isEndClient`)
  - **UI Store**: Sidebar state, theme preferences, notifications, global UI state
- **Optional Feature Stores** (add only when multiple components need shared data):
  - **Entity Stores**: Clients, projects, invoices (use when components make API calls and share data)
  - **Pattern**: Start with PHP props → add stores only when you see repeated API calls or shared state needs

### Roles & Role-Based Access Control (RBAC)

openclient supports two distinct project types with corresponding role-based access:

#### Project Types

1. **Agency Projects** - Subcontractor work where you bill an agency
   - Relationship: YOU → Agency → End Client
   - Access: Owner (you) + Agency + optional End Client

2. **Client Projects** - Direct client work where you bill the client directly
   - Relationship: YOU → Direct Client
   - Access: Owner (you) + Direct Client

#### Role Definitions

- **Owner** – Full access to all features across all project types (you/admin)
- **Agency** – Full access within Agency Projects (your paying client when subcontracting)
- **End Client** – Limited access within Agency Projects (agency's end client, restricted from financial data)
- **Direct Client** – Full access within Client Projects (your direct paying client)

#### Feature Access Matrix

| Feature Category | Owner | Agency | End Client | Direct Client |
|------------------|-------|--------|------------|---------------|
| **Financial Features** |
| Billing rates/costs | ✅ | ✅ | ❌ | ✅ |
| Invoices | ✅ | ✅ | ❌ | ✅ |
| Payment tracking | ✅ | ✅ | ❌ | ✅ |
| Profit margins | ✅ | ✅ | ❌ | ✅ |
| **Time Tracking** |
| Time entry details | ✅ | ✅ | ❌ | ✅ |
| Time summaries | ✅ | ✅ | ❌ | ✅ |
| **Project Management** |
| Project status/progress | ✅ | ✅ | ✅ | ✅ |
| Milestone completion | ✅ | ✅ | ✅ | ✅ |
| Deliverables/files | ✅ | ✅ | ✅ | ✅ |
| Project communication | ✅ | ✅ | ✅ | ✅ |

#### RBAC Implementation Requirements

1. **Role Assignment**
   - Manual role assignment when creating users and projects
   - No automatic role defaults
   - Project type must be specified at project creation

2. **Multi-Agency Isolation**
   - Each agency sees only their assigned projects
   - Agency A cannot access Agency B's projects
   - Requires agency-level data segregation

3. **Access Control Logic**
   - Role determines feature visibility, NOT project type
   - End Clients in Agency Projects have restricted views (no financials)
   - Direct Clients in Client Projects have full access (they're paying directly)
   - Owner always has full access across all project types

4. **UI/UX Requirements**
   - Role-based navigation menus (hide financial sections for End Clients)
   - Project filtering by agency for multi-agency scenarios
   - Permission-based component rendering throughout the application

## 5. UX & Layout

**Design System**: TailAdmin Vue.js template with TailwindCSS 3

**Layout Structure** (PHP/CodeIgniter base with Vue.js components):
- `app/Views/layouts/app.php` - Main layout with Vue.js initialization
- `app/Views/layouts/partials/header.php` - Header with Vue.js nav components
- `app/Views/layouts/partials/sidebar.php` - Sidebar with Vue.js menu components
- `app/Views/layouts/partials/footer.php` - Footer

**Vue.js Component Organization**:
```
resources/
├── js/
│   ├── app.js                    # Main Vue app entry point
│   ├── components/               # Custom Vue components
│   │   ├── layout/
│   │   │   ├── Sidebar.vue
│   │   │   ├── Header.vue
│   │   │   └── Footer.vue
│   │   ├── dashboard/
│   │   │   ├── StatsCard.vue
│   │   │   ├── RecentActivity.vue
│   │   │   └── QuickActions.vue
│   │   ├── clients/
│   │   │   ├── ClientList.vue
│   │   │   ├── ClientForm.vue
│   │   │   └── ClientCard.vue
│   │   └── shared/
│   │       ├── DataTable.vue
│   │       ├── Modal.vue
│   │       └── Dropdown.vue
│   ├── stores/                   # Pinia stores
│   │   ├── user.js
│   │   ├── ui.js
│   │   └── clients.js
│   └── utils/                    # Utilities
│       ├── api.js                # Axios instance
│       └── permissions.js        # RBAC helpers
├── css/
│   └── app.css                   # TailwindCSS entry (with TailAdmin config)
└── tailadmin/                    # TailAdmin template components
    ├── components/
    ├── layouts/
    └── ...
```

**All authenticated views must**:

```php
<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<div id="page-app">
    <!-- Vue.js component with server-side data -->
    <component-name :initial-data='<?= json_encode($data) ?>'></component-name>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script type="module">
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import ComponentName from '@/components/path/ComponentName.vue'

const app = createApp({
    components: { ComponentName }
})
app.use(createPinia())
app.mount('#page-app')
</script>
<?= $this->endSection() ?>
```

**TailAdmin Integration**:
- Use TailAdmin's pre-built Vue components for common UI patterns (tables, forms, modals, dropdowns)
- Adapt TailAdmin components to openclient's data models and business logic
- Follow TailAdmin's design language, color palette, and spacing system
- Leverage TailAdmin's responsive layouts and mobile-first approach

## 6. Phased Delivery (High Level)

1. Foundation & Auth: CI4 + Postgres, auth, workspace, base layout.
2. CRM: clients, contacts, basic notes, search, list/detail views.
3. Pipeline & Deals: pipelines, stages, deals list + board.
4. Projects & Tasks: projects, tasks, time tracking, comments.
5. Billing & Proposals: quotes, invoices, proposals, PDFs.
6. Tickets, Forms, Documents, Discussions, Meetings, Portal.

## 7. Claude Code Instructions (Summary)

- Work in small, reviewable steps.
- Before coding: inspect, propose a plan, then implement.
- After coding: summarize changes and suggest follow-ups.
- Keep layout DRY via shared partials.
- Prefer services in `app/Domain/*` over fat controllers.
