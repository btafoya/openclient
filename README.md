# openclient

> **Open source, self-hostable client & project management platform for freelancers and agencies**

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://www.php.net/)
[![CodeIgniter 4](https://img.shields.io/badge/CodeIgniter-4-EE4623?logo=codeigniter)](https://codeigniter.com/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-336791?logo=postgresql)](https://www.postgresql.org/)
[![TailwindCSS](https://img.shields.io/badge/TailwindCSS-3-38B2AC?logo=tailwind-css)](https://tailwindcss.com/)

**openclient** is a fully open source alternative to platforms like Taskip, combining CRM, project management, billing, support tickets, and client portals into one self-hosted solution. Built for freelancers, agencies, and teams who want complete data ownership without vendor lock-in.

---

## ✨ Features

### 🏢 CRM & Contacts
- Manage organizations and individual contacts
- Custom fields, tags, notes, and interaction timelines
- CSV import/export

### 📊 Sales Pipelines & Deals
- Multiple customizable sales pipelines
- Kanban-style deal tracking
- Deal value, close dates, probability tracking

### 📋 Projects & Tasks
- Client-scoped projects with task lists
- Time tracking (manual entry)
- File attachments and comments
- Assignees, priorities, due dates

### 💰 Invoices & Quotes
- Quote/estimate creation with conversion to invoices
- Line items, taxes, discounts
- Status tracking (draft, sent, paid, overdue, partially paid)
- Recurring invoices
- PDF export
- **Integrated Payment Processing:**
  - **Stripe** - Cards, ACH, Apple Pay, Google Pay, subscriptions
  - **PayPal** - Standard payment flow
  - **Zelle** - Manual entry (zero fees)
  - **Stripe ACH** - Automated bank transfers
  - **Venmo for Business** - Optional alternative
- **Payment Features:**
  - Client choice with transparent fee disclosure
  - Automatic webhook confirmation
  - Partial payment tracking
  - Refund processing
  - Payment reconciliation reports
  - Test/sandbox mode support
- **Multiple Payment Flows:**
  - Invoice-based payment (standard)
  - Upfront payment required (deposits)
  - Manual payment recording (checks, wire)
  - Payment links (standalone quick payments)

### 📝 Proposals
- Template-based proposal system with Markdown support
- Client acceptance tracking (time, IP, signature)
- Merge fields for client/project data

### 📋 Forms & Onboarding
- Simple form builder for client intake
- Public submission links
- Automatic wiring to clients/deals/projects

### 📂 Document Management
- Per-client and per-project file organization
- Internal vs client-visible flags
- Tagging and search

### 🎫 Support Tickets
- Ticket categories and status tracking
- Team assignment
- Internal notes vs public replies
- Client portal integration

### 💬 Discussions
- Threaded discussions at client/project/deal/ticket level
- @mentions and notifications

### 📅 Calendar & Meetings
- Meeting scheduling per client/project/deal
- Basic ICS feed per user

### 🔐 Role-Based Access Control (RBAC)
**Two Project Types:**
- **Agency Projects** - For subcontractor work (you → agency → end client)
- **Client Projects** - For direct client work (you → client)

**Four User Roles:**
- **Owner** - Full access to everything
- **Agency** - Full access within Agency Projects
- **End Client** - Limited access (no financial data) in Agency Projects
- **Direct Client** - Full access within Client Projects

**Access Control:**
- End Clients cannot see billing rates, invoices, payments, or time tracking
- Multi-agency isolation (Agency A cannot see Agency B's projects)
- Manual role assignment per project

### 🌐 Client Portal
- Separate client login area
- View projects, tasks, invoices, quotes, proposals, tickets, documents
- Form submission
- Light branding (logo, colors)

---

## 🚀 Quick Start

### Prerequisites
- PHP 8.2 or higher
- PostgreSQL 16+
- Composer
- Node.js & npm (for Tailwind CSS)

### Installation

```bash
# 1. Clone the repository
git clone https://github.com/btafoya/openclient.git
cd openclient

# 2. Install PHP dependencies
composer install

# 3. Install JavaScript dependencies (Tailwind build)
npm install

# 4. Configure database
cp app/Config/Database.php app/Config/Database.local.php
# Edit Database.local.php with your PostgreSQL credentials

# 5. Run database migrations
php spark migrate

# 6. Build Tailwind CSS
npm run build

# 7. Start the development server
php spark serve
```

Point your browser to `http://localhost:8080`

---

## 🐳 Docker Setup (Optional)

```bash
# Start all services (app, nginx, postgres)
docker-compose -f docker/docker-compose.yml up -d

# Run migrations inside container
docker-compose exec app php spark migrate

# Access the app
open http://localhost:8080
```

---

## 📚 Documentation

- **[PR.md](PR.md)** - Full product requirements and feature specifications
- **[SCAFFOLD.md](SCAFFOLD.md)** - Architecture, database schema, and implementation guide

---

## 🛠️ Tech Stack

| Component | Technology |
|-----------|-----------|
| Backend | PHP 8.2+, CodeIgniter 4 |
| Database | PostgreSQL 16 |
| Frontend | TailwindCSS 3 |
| Auth | CodeIgniter 4 Shield (sessions + JWT for API) |
| Build Tools | Vite, PostCSS, Autoprefixer |
| Deployment | Docker or bare metal |

---

## 🏗️ Project Structure

```
openclient/
├── app/
│   ├── Config/           # Configuration files
│   ├── Controllers/      # HTTP controllers (thin layer)
│   ├── Domain/           # Business logic services
│   ├── Entities/         # Data entities
│   ├── Models/           # Database models
│   └── Views/            # PHP views (Tailwind CSS)
├── database/
│   ├── migrations/       # Database migrations
│   └── seeds/            # Database seeders
├── public/               # Web root (index.php, assets)
├── resources/
│   ├── css/              # Tailwind source
│   └── js/               # JavaScript
├── docker/               # Docker configuration
├── tests/                # Unit and feature tests
└── writable/             # Logs, cache, uploads
```

---

## 🧪 Testing

```bash
# Run all tests
composer test

# Run specific test suite
./vendor/bin/phpunit tests/unit/
```

---

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Guidelines
- Follow PSR-12 coding standards
- Write tests for new features
- Keep controllers thin, business logic in `app/Domain/`
- Use TailwindCSS for all styling (no custom CSS)
- Update documentation for significant changes

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgments

Inspired by Taskip and other all-in-one agency management platforms, built with a focus on:
- Self-hosting and data ownership
- No vendor lock-in
- Open source transparency
- Agency and freelancer workflows

---

## 📞 Support & Community

- **Issues**: [GitHub Issues](https://github.com/btafoya/openclient/issues)
- **Discussions**: [GitHub Discussions](https://github.com/btafoya/openclient/discussions)
- **Documentation**: See [PR.md](PR.md) and [SCAFFOLD.md](SCAFFOLD.md)

---

## 🗺️ Roadmap

### v1.0 (Current Focus)
- ✅ Foundation & Auth
- ✅ CRM (Clients & Contacts)
- 🔄 Pipelines & Deals
- ⏳ Projects & Tasks
- ⏳ Invoices & Quotes
- ⏳ Proposals
- ⏳ Tickets & Support
- ⏳ Client Portal

### Future Releases
- API endpoints (REST + GraphQL)
- Mobile-responsive improvements
- Advanced automation workflows
- Native mobile apps
- Multi-tenant SaaS mode (optional)

---

Made with ❤️ by freelancers, for freelancers and agencies.
