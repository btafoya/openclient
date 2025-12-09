# Milestone 2: Core Revenue Features Implementation

**Start Date**: 2025-12-08
**Timeline**: 12 weeks (Weeks 17-28)
**Status**: In Progress

## Objective
Build core revenue-generating features: CRM, Projects & Tasks, Invoices, Stripe Integration

## Features Overview
1. **CRM** (Weeks 17-20): Clients, Contacts, Notes, Timeline, CSV import/export
2. **Projects & Tasks** (Weeks 21-24): Project management, task lists, time tracking, file attachments
3. **Invoices** (Weeks 25-27): Create, PDF generation, send to client, line items, tax, status workflow
4. **Stripe Integration** (Week 28): Checkout, payment, webhook confirmation

## Quality Gates
- E2E test: Client receives invoice → pays online → invoice marked paid
- 95% test coverage maintained
- Performance: Page load < 5s, API response < 2s

## Implementation Strategy
- Follow existing RBAC architecture patterns from Milestone 1
- Leverage PostgreSQL RLS for multi-agency isolation
- Apply 4-layer RBAC: Database RLS → HTTP Middleware → Service Guards → Frontend
- No Claude attribution in commits (per CLAUDE.md policy)
