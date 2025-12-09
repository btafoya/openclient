# Milestone 2: Core Revenue Features - Detailed Implementation Plan

**Generated**: 2025-12-08
**Timeline**: 12 weeks (Weeks 17-28)
**Status**: Implementation Ready

---

## Executive Summary

Milestone 2 builds on the completed RBAC foundation (Milestone 1) to deliver core revenue-generating features:

1. **CRM** (Clients, Contacts, Notes, Timeline, CSV import/export)
2. **Projects & Tasks** (Project management, task lists, time tracking)
3. **Invoices** (PDF generation, email delivery, tax calculation)
4. **Stripe Integration** (Payment processing, webhook handling)

**Current Status**:
- CRM Backend: 95% complete
- CRM Frontend: 20% complete (stores created, views needed)
- Projects & Tasks: 0% complete
- Invoices: 10% complete (basic controller only)
- Stripe: 0% complete

---

## Week 17-18: Complete CRM Frontend

### Objectives
- Complete Vue component library for Clients, Contacts, Notes
- Implement CSV import/export UI
- Add timeline visualization
- Integrate with existing AdminLayout

### Deliverables

#### Week 17: Client Management UI

**File Structure**:
```
resources/js/src/views/CRM/
├── Clients/
│   ├── ClientList.vue
│   ├── ClientCreate.vue
│   ├── ClientEdit.vue
│   └── ClientView.vue
├── Contacts/
│   ├── ContactList.vue
│   ├── ContactCreate.vue
│   ├── ContactEdit.vue
│   └── ContactView.vue
├── Notes/
│   ├── NoteList.vue
│   ├── NoteForm.vue
│   └── NoteCard.vue
└── Timeline/
    ├── TimelineView.vue
    └── TimelineEvent.vue
```

**Components to Build**:

1. **ClientList.vue** - Main clients list view
   - Data table with search, sort, filter
   - Actions: Create, Edit, View, Delete, Toggle Active
   - Pagination support
   - Export to CSV button
   - Uses `useClientStore` from Pinia

2. **ClientCreate.vue** - Create new client form
   - Form validation
   - Address autocomplete (optional)
   - Save & Continue vs Save & Close
   - Success/error notifications

3. **ClientEdit.vue** - Edit existing client
   - Pre-populate form with current data
   - Track changes
   - Confirmation on unsaved changes

4. **ClientView.vue** - Client detail view
   - Client information display
   - Related contacts list
   - Notes timeline
   - Projects list (when implemented)
   - Quick actions (Edit, Delete, Toggle Active)

#### Week 18: Contacts, Notes, CSV UI

**Components to Build**:

1. **ContactList.vue** - Contacts management
   - Filterable by client
   - Primary contact indicator
   - Inline edit capability
   - Bulk operations

2. **ContactForm.vue** - Contact create/edit
   - Client selection dropdown
   - Primary contact toggle
   - Email/phone validation

3. **NoteCard.vue** - Reusable note display
   - Pin/unpin toggle
   - Edit/delete actions
   - Timestamp display
   - Rich text support (optional)

4. **TimelineView.vue** - Activity timeline
   - Chronological event list
   - Filter by entity type
   - Date range selector
   - Infinite scroll/pagination

5. **CsvImportWizard.vue** - CSV import interface
   - File upload
   - Field mapping UI
   - Preview before import
   - Progress indicator
   - Error handling display

6. **CsvExportDialog.vue** - CSV export options
   - Entity type selection
   - Field selection
   - Date range filter
   - Download trigger

**Routes to Add**:
```javascript
// resources/js/src/router/index.js
{
  path: '/crm',
  component: AdminLayout,
  children: [
    { path: 'clients', component: ClientList },
    { path: 'clients/create', component: ClientCreate },
    { path: 'clients/:id', component: ClientView },
    { path: 'clients/:id/edit', component: ClientEdit },
    { path: 'contacts', component: ContactList },
    { path: 'contacts/create', component: ContactCreate },
    { path: 'contacts/:id', component: ContactView },
    { path: 'timeline', component: TimelineView }
  ]
}
```

**Sidebar Navigation**:
```javascript
// Add to AppSidebar.vue
{
  title: 'CRM',
  icon: 'UserGroupIcon',
  children: [
    { title: 'Clients', path: '/crm/clients' },
    { title: 'Contacts', path: '/crm/contacts' },
    { title: 'Timeline', path: '/crm/timeline' }
  ]
}
```

### Testing Requirements

**Unit Tests** (Vitest):
- Pinia store tests (already created: clients.js, contacts.js, notes.js)
- Component mounting tests
- Form validation tests

**E2E Tests** (Playwright):
- Create client flow
- Edit client flow
- CSV import flow
- CSV export flow
- Timeline navigation

**Quality Gates**:
- ✅ All CRM components render without errors
- ✅ CRUD operations work for clients, contacts, notes
- ✅ CSV import/export functional
- ✅ Timeline displays events correctly
- ✅ 95% test coverage maintained

---

## Week 19-22: Projects & Tasks Implementation

### Objectives
- Design and implement projects & tasks backend
- Build frontend project management UI
- Add time tracking functionality
- Implement file attachment support

### Database Schema

**Tables to Create**:

1. **projects**
```sql
CREATE TABLE projects (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    agency_id UUID NOT NULL REFERENCES agencies(id),
    client_id UUID NOT NULL REFERENCES clients(id),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status VARCHAR(50) DEFAULT 'active', -- active, completed, on_hold, cancelled
    start_date DATE,
    due_date DATE,
    budget DECIMAL(12,2),
    hourly_rate DECIMAL(8,2),
    is_billable BOOLEAN DEFAULT true,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- RLS Policy
CREATE POLICY projects_agency_isolation ON projects
    USING (agency_id = current_setting('app.current_agency_id')::uuid);
```

2. **tasks**
```sql
CREATE TABLE tasks (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    agency_id UUID NOT NULL REFERENCES agencies(id),
    project_id UUID NOT NULL REFERENCES projects(id),
    assigned_to UUID REFERENCES users(id),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status VARCHAR(50) DEFAULT 'todo', -- todo, in_progress, completed, blocked
    priority VARCHAR(20) DEFAULT 'medium', -- low, medium, high, urgent
    due_date TIMESTAMP,
    estimated_hours DECIMAL(6,2),
    actual_hours DECIMAL(6,2),
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

CREATE POLICY tasks_agency_isolation ON tasks
    USING (agency_id = current_setting('app.current_agency_id')::uuid);
```

3. **time_entries**
```sql
CREATE TABLE time_entries (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    agency_id UUID NOT NULL REFERENCES agencies(id),
    user_id UUID NOT NULL REFERENCES users(id),
    project_id UUID NOT NULL REFERENCES projects(id),
    task_id UUID REFERENCES tasks(id),
    description TEXT,
    hours DECIMAL(6,2) NOT NULL,
    start_time TIMESTAMP,
    end_time TIMESTAMP,
    is_billable BOOLEAN DEFAULT true,
    hourly_rate DECIMAL(8,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

CREATE POLICY time_entries_agency_isolation ON time_entries
    USING (agency_id = current_setting('app.current_agency_id')::uuid);
```

### Backend Implementation

**Models** (Week 19):

1. **ProjectModel.php**
   - CRUD operations
   - RLS integration
   - Timeline logging
   - Relationship methods (client, tasks, time_entries)
   - Validation rules
   - Budget tracking methods

2. **TaskModel.php**
   - CRUD operations
   - Status workflow
   - Assignment logic
   - Sort order management
   - Timeline integration

3. **TimeEntryModel.php**
   - Time tracking CRUD
   - Billable hours calculation
   - User/project/task relationships
   - Validation (start < end, hours > 0)

**Guards** (Week 19):

1. **ProjectGuard.php**
   - canView(project_id): Check agency access
   - canCreate(): Check role permissions
   - canUpdate(project_id): Agency + ownership check
   - canDelete(project_id): Agency + no invoices check

2. **TaskGuard.php**
   - canView(task_id): Via project agency check
   - canAssign(task_id, user_id): User in same agency
   - canUpdate(task_id): Assigned user or manager
   - canDelete(task_id): Creator or manager

**Controllers** (Week 20):

1. **ProjectController.php**
   - index(): List projects (with filters)
   - show(id): Get project with tasks, time entries
   - store(): Create new project
   - update(id): Update project
   - destroy(id): Soft delete project
   - statistics(id): Budget, hours, completion %

2. **TaskController.php**
   - index(project_id): List tasks for project
   - show(id): Get task details
   - store(): Create task
   - update(id): Update task
   - updateStatus(id): Change task status
   - reorder(): Bulk update sort_order

3. **TimeEntryController.php**
   - index(project_id, task_id): List time entries
   - store(): Log time entry
   - update(id): Edit time entry
   - destroy(id): Delete time entry
   - summary(project_id): Total hours by user

### Frontend Implementation (Week 21-22)

**Pinia Stores**:

1. **stores/projects.js** - Project state management
2. **stores/tasks.js** - Task state management
3. **stores/timeEntries.js** - Time tracking state

**Vue Components**:

1. **ProjectList.vue** - Projects grid/list view
2. **ProjectKanban.vue** - Kanban board view (optional)
3. **ProjectView.vue** - Project detail with tasks
4. **TaskList.vue** - Task list with inline edit
5. **TaskBoard.vue** - Kanban task board
6. **TimeTracker.vue** - Time tracking widget
7. **TimesheetView.vue** - Time entry list/edit

**Routes**:
```javascript
{
  path: '/projects',
  children: [
    { path: '', component: ProjectList },
    { path: ':id', component: ProjectView },
    { path: ':id/tasks', component: TaskBoard }
  ]
},
{
  path: '/timesheet',
  component: TimesheetView
}
```

### Quality Gates (Week 22)
- ✅ All project CRUD operations functional
- ✅ Task board drag-and-drop working
- ✅ Time tracking accurate to nearest 0.25 hours
- ✅ Budget warnings display correctly
- ✅ 95% test coverage maintained
- ✅ E2E test: Create project → add tasks → log time → view summary

---

## Week 23-26: Invoices Implementation

### Objectives
- Complete invoice backend with line items
- Implement PDF generation service
- Build invoice creation UI
- Add email delivery functionality

### Database Schema

**Tables to Create**:

1. **invoices**
```sql
CREATE TABLE invoices (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    agency_id UUID NOT NULL REFERENCES agencies(id),
    client_id UUID NOT NULL REFERENCES clients(id),
    project_id UUID REFERENCES projects(id),
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    status VARCHAR(50) DEFAULT 'draft', -- draft, sent, viewed, paid, overdue, cancelled
    subtotal DECIMAL(12,2) DEFAULT 0,
    tax_rate DECIMAL(5,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) DEFAULT 0,
    notes TEXT,
    terms TEXT,
    sent_at TIMESTAMP NULL,
    viewed_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

CREATE POLICY invoices_agency_isolation ON invoices
    USING (agency_id = current_setting('app.current_agency_id')::uuid);
```

2. **invoice_line_items**
```sql
CREATE TABLE invoice_line_items (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    invoice_id UUID NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Backend Implementation (Week 23-24)

**Models**:

1. **InvoiceModel.php**
   - CRUD operations with line items
   - Status workflow (draft → sent → paid)
   - Number generation (INV-2025-0001)
   - Total calculation from line items
   - PDF generation trigger
   - Email sending

2. **InvoiceLineItemModel.php**
   - Line item management
   - Amount calculation (quantity * unit_price)
   - Sort order handling

**Services**:

1. **InvoicePdfService.php**
   - Generate PDF from invoice data
   - Use TCPDF or DomPDF library
   - Custom template with agency branding
   - Save to storage/invoices/

2. **InvoiceEmailService.php**
   - Send invoice via email
   - Attach PDF
   - Track sent/viewed status
   - Reminder emails for overdue

**Guards**:

1. **InvoiceGuard.php**
   - canView(): Agency check
   - canCreate(): Client access check
   - canUpdate(): Only draft invoices
   - canDelete(): Only draft invoices
   - canSend(): Draft → sent transition

**Controllers**:

1. **InvoiceController.php**
   - index(): List invoices with filters
   - show(id): Get invoice with line items
   - store(): Create draft invoice
   - update(id): Update draft invoice
   - destroy(id): Delete draft invoice
   - send(id): Generate PDF, send email, update status
   - pdf(id): Download PDF
   - preview(id): Preview before sending

### Frontend Implementation (Week 25-26)

**Pinia Stores**:

1. **stores/invoices.js** - Invoice state management
2. **stores/invoiceLineItems.js** - Line items management

**Vue Components**:

1. **InvoiceList.vue** - Invoices grid with status filters
2. **InvoiceCreate.vue** - Invoice builder
3. **InvoiceBuilder.vue** - Line item editor (drag-drop reorder)
4. **InvoicePrev iew.vue** - PDF preview before sending
5. **InvoiceView.vue** - View sent invoice
6. **InvoiceEmailDialog.vue** - Email sending form

**Invoice Builder Features**:
- Client selection dropdown
- Project selection (auto-populate from time entries)
- Line item editor with add/remove/reorder
- Real-time total calculation
- Tax calculation
- Discount application
- Save as draft vs Send immediately
- PDF preview

**Routes**:
```javascript
{
  path: '/invoices',
  children: [
    { path: '', component: InvoiceList },
    { path: 'create', component: InvoiceCreate },
    { path: ':id', component: InvoiceView },
    { path: ':id/edit', component: InvoiceCreate },
    { path: ':id/preview', component: InvoicePreview }
  ]
}
```

### Quality Gates (Week 26)
- ✅ Invoice CRUD operations functional
- ✅ PDF generation working with correct calculations
- ✅ Email delivery successful
- ✅ Status workflow enforced (can't edit sent invoices)
- ✅ Line item totals calculate correctly
- ✅ Tax calculation accurate
- ✅ 95% test coverage maintained
- ✅ E2E test: Create invoice → preview → send → client receives

---

## Week 27-28: Stripe Integration

### Objectives
- Integrate Stripe SDK
- Build checkout flow
- Implement webhook handling
- Link payments to invoices

### Backend Implementation (Week 27)

**Dependencies**:
```bash
composer require stripe/stripe-php
```

**Configuration** (app/Config/Stripe.php):
```php
<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Stripe extends BaseConfig
{
    public string $secretKey;
    public string $publishableKey;
    public string $webhookSecret;
    public string $currency = 'usd';

    public function __construct()
    {
        $this->secretKey = getenv('STRIPE_SECRET_KEY');
        $this->publishableKey = getenv('STRIPE_PUBLISHABLE_KEY');
        $this->webhookSecret = getenv('STRIPE_WEBHOOK_SECRET');
    }
}
```

**Models**:

1. **PaymentModel.php**
```php
protected $allowedFields = [
    'invoice_id',
    'agency_id',
    'stripe_payment_intent_id',
    'stripe_charge_id',
    'amount',
    'currency',
    'status', // pending, succeeded, failed, refunded
    'payment_method',
    'metadata'
];
```

**Services**:

1. **StripePaymentService.php**
   - createPaymentIntent(invoice_id, amount)
   - confirmPayment(payment_intent_id)
   - refundPayment(payment_id, amount)
   - Customer management (create/retrieve)

2. **StripeWebhookService.php**
   - Verify webhook signature
   - Handle events:
     - payment_intent.succeeded
     - payment_intent.payment_failed
     - charge.refunded
   - Update invoice status
   - Log payment to PaymentModel

**Controllers**:

1. **PaymentController.php**
   - createCheckoutSession(invoice_id): Generate Stripe session
   - handleSuccess(): Return URL after payment
   - handleCancel(): Return URL if cancelled

2. **WebhookController.php**
   - stripe(): Handle Stripe webhooks
   - Verify signature
   - Delegate to StripeWebhookService
   - Return 200 OK to acknowledge

**Routes**:
```php
// app/Config/Routes.php
$routes->group('api/payments', function($routes) {
    $routes->post('create-session', 'PaymentController::createCheckoutSession');
    $routes->get('success', 'PaymentController::handleSuccess');
    $routes->get('cancel', 'PaymentController::handleCancel');
});

$routes->post('webhooks/stripe', 'WebhookController::stripe');
```

### Frontend Implementation (Week 28)

**Dependencies**:
```bash
pnpm add @stripe/stripe-js
```

**Pinia Store**:

1. **stores/payments.js** - Payment state management

**Vue Components**:

1. **StripeCheckout.vue** - Stripe Elements integration
2. **PaymentButton.vue** - Trigger payment for invoice
3. **PaymentStatus.vue** - Display payment result

**Payment Flow**:
1. User clicks "Pay Invoice" on invoice view
2. Frontend calls `/api/payments/create-session` with invoice_id
3. Backend creates Stripe Checkout Session
4. Redirect user to Stripe hosted checkout
5. After payment, Stripe redirects to success/cancel URL
6. Webhook receives payment_intent.succeeded event
7. Backend updates invoice status to "paid"
8. Frontend polls invoice status or uses WebSockets for real-time update

**Stripe Configuration** (.env):
```
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

### Testing (Week 28)

**Stripe Test Mode**:
- Use test API keys
- Test card: 4242 4242 4242 4242
- Test webhooks using Stripe CLI: `stripe listen --forward-to https://ocdev.premadev.com/webhooks/stripe`

**E2E Test Flow**:
1. Create test invoice
2. Trigger payment
3. Use Stripe test card
4. Verify webhook received
5. Confirm invoice marked paid
6. Check payment record created

### Quality Gates (Week 28)
- ✅ Stripe checkout redirects correctly
- ✅ Test payment completes successfully
- ✅ Webhook signature verification working
- ✅ Invoice status updates on payment
- ✅ Payment records created correctly
- ✅ Error handling for failed payments
- ✅ 95% test coverage maintained
- ✅ E2E test: Full payment flow from invoice → Stripe → webhook → paid status

---

## Milestone 2 Quality Gate

### Comprehensive E2E Test Scenario

**Flow**:
1. Create client "Acme Corp"
2. Add contact "John Doe" as primary contact
3. Create project "Website Redesign" for Acme Corp
4. Add tasks to project
5. Log time entries on tasks
6. Create invoice from project time entries
7. Preview invoice PDF
8. Send invoice to client email
9. Client pays via Stripe checkout
10. Webhook confirms payment
11. Invoice marked as paid
12. Timeline shows complete history

**Success Criteria**:
- ✅ All steps complete without errors
- ✅ Data consistency across all entities
- ✅ RLS enforced (no cross-agency data leakage)
- ✅ Email delivered successfully
- ✅ Payment processed and recorded
- ✅ Timeline accurately reflects all events
- ✅ 95% test coverage across all features
- ✅ Performance: Page load < 5s, API response < 2s
- ✅ Accessibility: WCAG 2.1 AA compliant
- ✅ Zero high-severity security vulnerabilities

---

## Risk Mitigation

### Technical Risks

1. **Stripe Webhook Reliability**
   - Mitigation: Implement retry logic with exponential backoff
   - Fallback: Manual payment reconciliation UI for edge cases

2. **PDF Generation Performance**
   - Mitigation: Queue PDF generation for background processing
   - Use lightweight PDF library (DomPDF recommended)

3. **Email Deliverability**
   - Mitigation: Use transactional email service (SendGrid, Mailgun)
   - Implement SPF/DKIM/DMARC records
   - Provide alternative: Download PDF and send manually

4. **RLS Performance with Complex Queries**
   - Mitigation: Add database indexes on agency_id
   - Monitor query performance with EXPLAIN ANALYZE
   - Optimize N+1 queries with eager loading

### Schedule Risks

1. **Frontend Development Delay**
   - Mitigation: Use component library (Vuetify, Naive UI) for faster development
   - Prioritize MVP features, defer polish

2. **Testing Coverage Gap**
   - Mitigation: Write tests alongside implementation (TDD approach)
   - Set CI/CD to block merge if coverage < 95%

---

## Implementation Checklist

### Week 17-18: CRM Frontend
- [ ] Create Pinia stores (✅ DONE: clients.js, contacts.js, notes.js)
- [ ] Build ClientList.vue component
- [ ] Build ClientCreate.vue component
- [ ] Build ClientEdit.vue component
- [ ] Build ClientView.vue component
- [ ] Build ContactList.vue component
- [ ] Build ContactForm.vue component
- [ ] Build NoteCard.vue component
- [ ] Build TimelineView.vue component
- [ ] Build CsvImportWizard.vue component
- [ ] Build CsvExportDialog.vue component
- [ ] Add routes to router
- [ ] Add navigation to sidebar
- [ ] Write unit tests for components
- [ ] Write E2E tests for CRM flows
- [ ] Verify 95% test coverage

### Week 19-20: Projects & Tasks Backend
- [ ] Create database migrations (projects, tasks, time_entries)
- [ ] Implement ProjectModel.php
- [ ] Implement TaskModel.php
- [ ] Implement TimeEntryModel.php
- [ ] Implement ProjectGuard.php
- [ ] Implement TaskGuard.php
- [ ] Implement ProjectController.php
- [ ] Implement TaskController.php
- [ ] Implement TimeEntryController.php
- [ ] Add API routes
- [ ] Write unit tests for models
- [ ] Write unit tests for controllers
- [ ] Test RLS policies

### Week 21-22: Projects & Tasks Frontend
- [ ] Create Pinia stores (projects.js, tasks.js, timeEntries.js)
- [ ] Build ProjectList.vue
- [ ] Build ProjectView.vue
- [ ] Build TaskBoard.vue (Kanban)
- [ ] Build TaskList.vue
- [ ] Build TimeTracker.vue
- [ ] Build TimesheetView.vue
- [ ] Add routes and navigation
- [ ] Write unit tests
- [ ] Write E2E tests
- [ ] Verify quality gates

### Week 23-24: Invoices Backend
- [ ] Create database migrations (invoices, invoice_line_items)
- [ ] Implement InvoiceModel.php
- [ ] Implement InvoiceLineItemModel.php
- [ ] Implement InvoicePdfService.php
- [ ] Implement InvoiceEmailService.php
- [ ] Implement InvoiceGuard.php
- [ ] Implement InvoiceController.php
- [ ] Add API routes
- [ ] Write unit tests
- [ ] Test PDF generation
- [ ] Test email delivery

### Week 25-26: Invoices Frontend
- [ ] Create Pinia stores (invoices.js, invoiceLineItems.js)
- [ ] Build InvoiceList.vue
- [ ] Build InvoiceCreate.vue
- [ ] Build InvoiceBuilder.vue
- [ ] Build InvoicePreview.vue
- [ ] Build InvoiceView.vue
- [ ] Build InvoiceEmailDialog.vue
- [ ] Add routes and navigation
- [ ] Write unit tests
- [ ] Write E2E tests
- [ ] Verify quality gates

### Week 27: Stripe Backend
- [ ] Install Stripe PHP SDK
- [ ] Create Stripe config
- [ ] Implement PaymentModel.php
- [ ] Implement StripePaymentService.php
- [ ] Implement StripeWebhookService.php
- [ ] Implement PaymentController.php
- [ ] Implement WebhookController.php
- [ ] Add payment routes
- [ ] Add webhook route
- [ ] Configure Stripe test keys in .env
- [ ] Test webhook signature verification
- [ ] Write unit tests

### Week 28: Stripe Frontend & Integration Testing
- [ ] Install @stripe/stripe-js
- [ ] Create payments.js Pinia store
- [ ] Build StripeCheckout.vue
- [ ] Build PaymentButton.vue
- [ ] Build PaymentStatus.vue
- [ ] Test checkout flow in test mode
- [ ] Test webhook handling with Stripe CLI
- [ ] Write E2E tests for payment flow
- [ ] Run full Milestone 2 E2E test
- [ ] Verify all quality gates
- [ ] Document any known issues
- [ ] Prepare Milestone 2 completion report

---

## Success Metrics

### Technical Metrics
- **Test Coverage**: ≥ 95% (PHPUnit + Vitest)
- **Code Quality**: PHPStan level 6, ESLint clean
- **Performance**: Page load < 5s (p95), API response < 2s (p95)
- **Security**: Zero high-severity vulnerabilities (OWASP scan)

### Functional Metrics
- **CRM**: All CRUD operations working, CSV import/export functional
- **Projects**: Project/task management operational, time tracking accurate
- **Invoices**: PDF generation working, email delivery successful
- **Stripe**: Payment processing functional, webhooks reliable

### Business Metrics
- **Feature Completeness**: All Milestone 2 features operational
- **User Acceptance**: Tested by at least 2 users before Milestone 3
- **Documentation**: API docs, user guides, deployment notes complete

---

## Next Steps After Milestone 2

Once Milestone 2 is complete and quality gates pass:

1. **User Acceptance Testing**
   - Deploy to staging environment (https://ocdev.premadev.com/)
   - Invite 2-3 test users
   - Collect feedback
   - Fix critical bugs

2. **Performance Optimization**
   - Run load tests with 10 concurrent users
   - Optimize slow queries
   - Add caching where needed
   - Review bundle size

3. **Security Audit**
   - Run OWASP ZAP scan
   - Review authentication flows
   - Test RLS edge cases
   - Validate Stripe integration security

4. **Documentation**
   - Update API documentation
   - Create user guide for CRM, Projects, Invoices
   - Document Stripe setup process
   - Update deployment guide

5. **Prepare for Milestone 3**
   - Review Milestone 3 requirements
   - Begin planning for Pipelines, Proposals, Recurring Invoices, Client Portal
   - Update project board and timeline

---

**Generated by**: Autonomous Milestone 2 Implementation Plan
**No Claude Attribution**: Per CLAUDE.md policy
**Ready for**: Human review and implementation execution
