# Milestone 3: Expansion Features - Detailed Implementation Plan

**Generated**: 2025-12-10
**Timeline**: 12 weeks (Weeks 29-40)
**Status**: Implementation Ready

---

## Executive Summary

Milestone 3 expands OpenClient with advanced features for sales management, automation, and client engagement:

1. **Pipelines & Deals** (Week 29-31) - Sales pipeline with drag-and-drop stages
2. **Proposals** (Week 32-34) - Proposal builder with templates and approval workflow
3. **Recurring Invoices** (Week 35-36) - Automated billing with schedules
4. **Client Portal** (Week 37-38) - Self-service portal for clients
5. **Additional Payment Methods** (Week 39-40) - PayPal, Zelle, Stripe ACH

---

## Week 29-31: Pipelines & Deals

### Objectives
- Implement sales pipeline with customizable stages
- Create deal management with value tracking
- Build Kanban-style drag-and-drop interface
- Enable deal-to-project conversion

### Database Schema

**Tables to Create**:

1. **pipelines**
```sql
CREATE TABLE pipelines (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    agency_id UUID NOT NULL REFERENCES agencies(id),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_default BOOLEAN DEFAULT FALSE,
    sort_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- RLS Policy
ALTER TABLE pipelines ENABLE ROW LEVEL SECURITY;
CREATE POLICY pipelines_agency_isolation ON pipelines
    USING (agency_id = current_setting('app.current_agency_id')::uuid);
```

2. **pipeline_stages**
```sql
CREATE TABLE pipeline_stages (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    pipeline_id UUID NOT NULL REFERENCES pipelines(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    color VARCHAR(7) DEFAULT '#6366f1',
    probability INTEGER DEFAULT 0, -- 0-100%
    sort_order INTEGER DEFAULT 0,
    is_won BOOLEAN DEFAULT FALSE,
    is_lost BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

3. **deals**
```sql
CREATE TABLE deals (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    agency_id UUID NOT NULL REFERENCES agencies(id),
    pipeline_id UUID NOT NULL REFERENCES pipelines(id),
    stage_id UUID NOT NULL REFERENCES pipeline_stages(id),
    client_id UUID REFERENCES clients(id),
    contact_id UUID REFERENCES contacts(id),
    assigned_to UUID REFERENCES users(id),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    value DECIMAL(15,2) DEFAULT 0,
    currency VARCHAR(3) DEFAULT 'USD',
    expected_close_date DATE,
    actual_close_date DATE,
    won_reason TEXT,
    lost_reason TEXT,
    probability INTEGER DEFAULT 0,
    source VARCHAR(100), -- 'referral', 'website', 'cold_call', etc.
    priority VARCHAR(20) DEFAULT 'medium', -- 'low', 'medium', 'high'
    sort_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- RLS Policy
ALTER TABLE deals ENABLE ROW LEVEL SECURITY;
CREATE POLICY deals_agency_isolation ON deals
    USING (agency_id = current_setting('app.current_agency_id')::uuid);

-- Indexes
CREATE INDEX idx_deals_pipeline ON deals(pipeline_id);
CREATE INDEX idx_deals_stage ON deals(stage_id);
CREATE INDEX idx_deals_client ON deals(client_id);
CREATE INDEX idx_deals_assigned ON deals(assigned_to);
```

4. **deal_activities**
```sql
CREATE TABLE deal_activities (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    deal_id UUID NOT NULL REFERENCES deals(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id),
    activity_type VARCHAR(50) NOT NULL, -- 'note', 'email', 'call', 'meeting', 'task', 'stage_change'
    subject VARCHAR(255),
    description TEXT,
    scheduled_at TIMESTAMP,
    completed_at TIMESTAMP,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_deal_activities_deal ON deal_activities(deal_id);
```

### Backend Implementation

**Models**:
- `app/Models/PipelineModel.php`
- `app/Models/PipelineStageModel.php`
- `app/Models/DealModel.php`
- `app/Models/DealActivityModel.php`

**Controllers**:
- `app/Controllers/Pipelines/PipelineController.php`
- `app/Controllers/Pipelines/DealController.php`

**Guards**:
- `app/Domain/Pipelines/Authorization/PipelineGuard.php`
- `app/Domain/Pipelines/Authorization/DealGuard.php`

### API Routes

```php
// Pipelines
$routes->group('api/pipelines', ['namespace' => 'App\Controllers\Pipelines'], function($routes) {
    $routes->get('/', 'PipelineController::index');
    $routes->post('/', 'PipelineController::store');
    $routes->get('(:segment)', 'PipelineController::show/$1');
    $routes->put('(:segment)', 'PipelineController::update/$1');
    $routes->delete('(:segment)', 'PipelineController::delete/$1');
    $routes->get('(:segment)/stages', 'PipelineController::stages/$1');
    $routes->post('(:segment)/stages', 'PipelineController::addStage/$1');
    $routes->put('(:segment)/stages/reorder', 'PipelineController::reorderStages/$1');
    $routes->get('(:segment)/stats', 'PipelineController::stats/$1');
});

// Deals
$routes->group('api/deals', ['namespace' => 'App\Controllers\Pipelines'], function($routes) {
    $routes->get('/', 'DealController::index');
    $routes->post('/', 'DealController::store');
    $routes->get('stats', 'DealController::stats');
    $routes->get('(:segment)', 'DealController::show/$1');
    $routes->put('(:segment)', 'DealController::update/$1');
    $routes->patch('(:segment)/stage', 'DealController::updateStage/$1');
    $routes->patch('(:segment)/won', 'DealController::markWon/$1');
    $routes->patch('(:segment)/lost', 'DealController::markLost/$1');
    $routes->delete('(:segment)', 'DealController::delete/$1');
    $routes->post('(:segment)/convert-to-project', 'DealController::convertToProject/$1');

    // Activities
    $routes->get('(:segment)/activities', 'DealController::activities/$1');
    $routes->post('(:segment)/activities', 'DealController::addActivity/$1');
});
```

### Frontend Implementation

**Pinia Stores**:
- `resources/js/src/stores/pipelines.js`
- `resources/js/src/stores/deals.js`

**Vue Components**:
```
resources/js/src/views/Pipelines/
├── PipelineList.vue        # List of all pipelines
├── PipelineSettings.vue    # Pipeline configuration (stages, colors)
├── DealBoard.vue           # Kanban board for deals
├── DealView.vue            # Deal detail view
├── DealCreate.vue          # Create new deal
└── DealEdit.vue            # Edit deal

resources/js/src/components/pipelines/
├── DealCard.vue            # Card for Kanban board
├── StageColumn.vue         # Kanban column
├── DealActivityLog.vue     # Activity timeline
├── DealForm.vue            # Reusable deal form
└── PipelineSelector.vue    # Dropdown to switch pipelines
```

### Vue Router Routes

```javascript
// Pipelines Routes
{
  path: '/pipelines',
  name: 'Pipelines',
  component: () => import('../views/Pipelines/PipelineList.vue'),
  meta: { title: 'Pipelines' }
},
{
  path: '/pipelines/:id/board',
  name: 'Deal Board',
  component: () => import('../views/Pipelines/DealBoard.vue'),
  meta: { title: 'Deal Board' }
},
{
  path: '/pipelines/:id/settings',
  name: 'Pipeline Settings',
  component: () => import('../views/Pipelines/PipelineSettings.vue'),
  meta: { title: 'Pipeline Settings' }
},
{
  path: '/deals/create',
  name: 'Create Deal',
  component: () => import('../views/Pipelines/DealCreate.vue'),
  meta: { title: 'Create Deal' }
},
{
  path: '/deals/:id',
  name: 'View Deal',
  component: () => import('../views/Pipelines/DealView.vue'),
  meta: { title: 'Deal Details' }
},
{
  path: '/deals/:id/edit',
  name: 'Edit Deal',
  component: () => import('../views/Pipelines/DealEdit.vue'),
  meta: { title: 'Edit Deal' }
}
```

---

## Week 32-34: Proposals

### Objectives
- Create proposal builder with templates
- Implement approval workflow
- Enable PDF generation and e-signature
- Track proposal views and status

### Database Schema

**Tables to Create**:

1. **proposal_templates**
```sql
CREATE TABLE proposal_templates (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    agency_id UUID NOT NULL REFERENCES agencies(id),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    content JSONB NOT NULL DEFAULT '{}', -- Template structure
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);
```

2. **proposals**
```sql
CREATE TABLE proposals (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    agency_id UUID NOT NULL REFERENCES agencies(id),
    client_id UUID NOT NULL REFERENCES clients(id),
    contact_id UUID REFERENCES contacts(id),
    deal_id UUID REFERENCES deals(id),
    template_id UUID REFERENCES proposal_templates(id),
    created_by UUID NOT NULL REFERENCES users(id),
    proposal_number VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    content JSONB NOT NULL DEFAULT '{}',
    subtotal DECIMAL(15,2) DEFAULT 0,
    discount_type VARCHAR(20) DEFAULT 'percentage', -- 'percentage', 'fixed'
    discount_value DECIMAL(15,2) DEFAULT 0,
    tax_rate DECIMAL(5,2) DEFAULT 0,
    total DECIMAL(15,2) DEFAULT 0,
    currency VARCHAR(3) DEFAULT 'USD',
    valid_until DATE,
    status VARCHAR(20) DEFAULT 'draft', -- 'draft', 'sent', 'viewed', 'accepted', 'rejected', 'expired'
    sent_at TIMESTAMP,
    viewed_at TIMESTAMP,
    accepted_at TIMESTAMP,
    rejected_at TIMESTAMP,
    rejection_reason TEXT,
    signature_data TEXT, -- Base64 signature image
    signed_by VARCHAR(255),
    signed_at TIMESTAMP,
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- RLS Policy
ALTER TABLE proposals ENABLE ROW LEVEL SECURITY;
CREATE POLICY proposals_agency_isolation ON proposals
    USING (agency_id = current_setting('app.current_agency_id')::uuid);
```

3. **proposal_sections**
```sql
CREATE TABLE proposal_sections (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    proposal_id UUID NOT NULL REFERENCES proposals(id) ON DELETE CASCADE,
    section_type VARCHAR(50) NOT NULL, -- 'text', 'pricing', 'terms', 'signature'
    title VARCHAR(255),
    content TEXT,
    pricing_items JSONB DEFAULT '[]',
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## Week 35-36: Recurring Invoices

### Objectives
- Implement recurring invoice schedules
- Build automated invoice generation
- Add notification system for upcoming invoices
- Track recurring invoice history

### Database Schema

**Tables to Create**:

1. **recurring_invoices**
```sql
CREATE TABLE recurring_invoices (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    agency_id UUID NOT NULL REFERENCES agencies(id),
    client_id UUID NOT NULL REFERENCES clients(id),
    project_id UUID REFERENCES projects(id),
    template_invoice_id UUID REFERENCES invoices(id),
    name VARCHAR(255) NOT NULL,
    frequency VARCHAR(20) NOT NULL, -- 'weekly', 'biweekly', 'monthly', 'quarterly', 'yearly'
    interval_count INTEGER DEFAULT 1,
    start_date DATE NOT NULL,
    end_date DATE,
    next_invoice_date DATE NOT NULL,
    last_invoice_date DATE,
    total_invoices_generated INTEGER DEFAULT 0,
    auto_send BOOLEAN DEFAULT FALSE,
    days_before_due INTEGER DEFAULT 30,
    status VARCHAR(20) DEFAULT 'active', -- 'active', 'paused', 'completed', 'cancelled'
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- RLS Policy
ALTER TABLE recurring_invoices ENABLE ROW LEVEL SECURITY;
CREATE POLICY recurring_invoices_agency_isolation ON recurring_invoices
    USING (agency_id = current_setting('app.current_agency_id')::uuid);
```

2. **recurring_invoice_items**
```sql
CREATE TABLE recurring_invoice_items (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    recurring_invoice_id UUID NOT NULL REFERENCES recurring_invoices(id) ON DELETE CASCADE,
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(15,2) NOT NULL,
    tax_rate DECIMAL(5,2) DEFAULT 0,
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## Week 37-38: Client Portal

### Objectives
- Create secure client-facing portal
- Enable clients to view invoices, proposals, projects
- Implement file sharing between agency and clients
- Add messaging/communication system

### Features

1. **Authentication**
   - Separate client login
   - Magic link authentication option
   - Password reset flow

2. **Dashboard**
   - Overview of active projects
   - Outstanding invoices
   - Recent proposals

3. **Invoice View**
   - View and download invoices
   - Make payments (Stripe integration)
   - Payment history

4. **Project View**
   - Project progress tracking
   - Task visibility (if enabled)
   - Time tracking summary

5. **File Sharing**
   - Shared documents
   - File upload capability
   - Version history

---

## Week 39-40: Additional Payment Methods

### Objectives
- Integrate PayPal payments
- Add Zelle payment tracking
- Implement Stripe ACH (bank transfers)

### PayPal Integration

**Configuration**:
- PayPal SDK integration
- Checkout flow
- Webhook handling

**API Routes**:
```php
$routes->group('api/payments/paypal', ['namespace' => 'App\Controllers\Payments'], function($routes) {
    $routes->post('create-order', 'PayPalController::createOrder');
    $routes->post('capture-order', 'PayPalController::captureOrder');
});

$routes->post('webhooks/paypal', 'Webhooks\WebhookController::paypal');
```

### Zelle Integration

Since Zelle doesn't have an API, implement manual payment tracking:
- Record Zelle payment details
- Confirmation workflow
- Manual reconciliation

### Stripe ACH

**Features**:
- Bank account verification
- ACH Direct Debit setup
- Microdeposit verification flow

---

## Implementation Checklist

### Week 29-31: Pipelines & Deals
- [ ] Create database migrations
- [ ] Implement PipelineModel and PipelineStageModel
- [ ] Implement DealModel and DealActivityModel
- [ ] Create PipelineGuard and DealGuard
- [ ] Implement PipelineController
- [ ] Implement DealController
- [ ] Add API routes
- [ ] Create Pinia stores (pipelines.js, deals.js)
- [ ] Build PipelineList.vue
- [ ] Build DealBoard.vue (Kanban)
- [ ] Build DealView.vue
- [ ] Build DealCreate.vue and DealEdit.vue
- [ ] Build component library (DealCard, StageColumn, etc.)
- [ ] Add Vue Router routes
- [ ] Update sidebar navigation
- [ ] Run tests and build

### Week 32-34: Proposals
- [ ] Create database migrations
- [ ] Implement ProposalTemplateModel
- [ ] Implement ProposalModel and ProposalSectionModel
- [ ] Create ProposalGuard
- [ ] Implement ProposalController
- [ ] Create ProposalPdfService
- [ ] Add API routes
- [ ] Create Pinia store (proposals.js)
- [ ] Build proposal Vue components
- [ ] Implement e-signature capture
- [ ] Run tests and build

### Week 35-36: Recurring Invoices
- [ ] Create database migrations
- [ ] Implement RecurringInvoiceModel
- [ ] Implement RecurringInvoiceItemModel
- [ ] Create RecurringInvoiceService
- [ ] Implement CLI command for invoice generation
- [ ] Add API routes
- [ ] Create Pinia store (recurringInvoices.js)
- [ ] Build recurring invoice Vue components
- [ ] Run tests and build

### Week 37-38: Client Portal
- [ ] Design portal authentication flow
- [ ] Create portal-specific routes and controllers
- [ ] Implement portal dashboard
- [ ] Build invoice viewing and payment
- [ ] Build project viewing
- [ ] Implement file sharing
- [ ] Run tests and build

### Week 39-40: Additional Payment Methods
- [ ] Integrate PayPal SDK
- [ ] Implement PayPal checkout flow
- [ ] Add Zelle payment tracking
- [ ] Implement Stripe ACH
- [ ] Update payment Vue components
- [ ] Run tests and build

---

## Quality Standards

- All features must have RBAC integration (4-layer security)
- 95% test coverage for new code
- PHPStan level 6 compliance
- ESLint clean
- Responsive UI (mobile-first)
- Accessibility compliance (WCAG 2.1 AA)

---

**Generated**: 2025-12-10
**Format Version**: 1.0
