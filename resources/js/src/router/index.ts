import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to, from, savedPosition) {
    return savedPosition || { left: 0, top: 0 }
  },
  routes: [
    {
      path: '/',
      name: 'Ecommerce',
      component: () => import('../views/Ecommerce.vue'),
      meta: {
        title: 'eCommerce Dashboard',
      },
    },
    {
      path: '/calendar',
      name: 'Calendar',
      component: () => import('../views/Others/Calendar.vue'),
      meta: {
        title: 'Calendar',
      },
    },
    {
      path: '/profile',
      name: 'Profile',
      component: () => import('../views/Others/UserProfile.vue'),
      meta: {
        title: 'Profile',
      },
    },
    {
      path: '/form-elements',
      name: 'Form Elements',
      component: () => import('../views/Forms/FormElements.vue'),
      meta: {
        title: 'Form Elements',
      },
    },
    {
      path: '/basic-tables',
      name: 'Basic Tables',
      component: () => import('../views/Tables/BasicTables.vue'),
      meta: {
        title: 'Basic Tables',
      },
    },
    {
      path: '/line-chart',
      name: 'Line Chart',
      component: () => import('../views/Chart/LineChart/LineChart.vue'),
    },
    {
      path: '/bar-chart',
      name: 'Bar Chart',
      component: () => import('../views/Chart/BarChart/BarChart.vue'),
    },
    {
      path: '/alerts',
      name: 'Alerts',
      component: () => import('../views/UiElements/Alerts.vue'),
      meta: {
        title: 'Alerts',
      },
    },
    {
      path: '/avatars',
      name: 'Avatars',
      component: () => import('../views/UiElements/Avatars.vue'),
      meta: {
        title: 'Avatars',
      },
    },
    {
      path: '/badge',
      name: 'Badge',
      component: () => import('../views/UiElements/Badges.vue'),
      meta: {
        title: 'Badge',
      },
    },

    {
      path: '/buttons',
      name: 'Buttons',
      component: () => import('../views/UiElements/Buttons.vue'),
      meta: {
        title: 'Buttons',
      },
    },

    {
      path: '/images',
      name: 'Images',
      component: () => import('../views/UiElements/Images.vue'),
      meta: {
        title: 'Images',
      },
    },
    {
      path: '/videos',
      name: 'Videos',
      component: () => import('../views/UiElements/Videos.vue'),
      meta: {
        title: 'Videos',
      },
    },
    {
      path: '/blank',
      name: 'Blank',
      component: () => import('../views/Pages/BlankPage.vue'),
      meta: {
        title: 'Blank',
      },
    },

    {
      path: '/error-404',
      name: '404 Error',
      component: () => import('../views/Errors/FourZeroFour.vue'),
      meta: {
        title: '404 Error',
      },
    },

    {
      path: '/signin',
      name: 'Signin',
      component: () => import('../views/Auth/Signin.vue'),
      meta: {
        title: 'Signin',
      },
    },
    {
      path: '/signup',
      name: 'Signup',
      component: () => import('../views/Auth/Signup.vue'),
      meta: {
        title: 'Signup',
      },
    },

    // CRM Routes
    {
      path: '/crm/clients',
      name: 'Clients',
      component: () => import('../views/CRM/Clients/ClientList.vue'),
      meta: {
        title: 'Clients',
      },
    },
    {
      path: '/crm/clients/create',
      name: 'Create Client',
      component: () => import('../views/CRM/Clients/ClientCreate.vue'),
      meta: {
        title: 'Create Client',
      },
    },
    {
      path: '/crm/clients/:id/edit',
      name: 'Edit Client',
      component: () => import('../views/CRM/Clients/ClientEdit.vue'),
      meta: {
        title: 'Edit Client',
      },
    },
    {
      path: '/crm/clients/:id',
      name: 'View Client',
      component: () => import('../views/CRM/Clients/ClientView.vue'),
      meta: {
        title: 'Client Details',
      },
    },
    {
      path: '/crm/contacts',
      name: 'Contacts',
      component: () => import('../views/CRM/Contacts/ContactList.vue'),
      meta: {
        title: 'Contacts',
      },
    },
    {
      path: '/crm/contacts/create',
      name: 'Create Contact',
      component: () => import('../views/CRM/Contacts/ContactCreate.vue'),
      meta: {
        title: 'Create Contact',
      },
    },
    {
      path: '/crm/contacts/:id/edit',
      name: 'Edit Contact',
      component: () => import('../views/CRM/Contacts/ContactEdit.vue'),
      meta: {
        title: 'Edit Contact',
      },
    },
    {
      path: '/crm/contacts/:id',
      name: 'View Contact',
      component: () => import('../views/CRM/Contacts/ContactView.vue'),
      meta: {
        title: 'Contact Details',
      },
    },
    {
      path: '/crm/notes',
      name: 'Notes',
      component: () => import('../views/CRM/Notes/NoteList.vue'),
      meta: {
        title: 'Notes',
      },
    },
    {
      path: '/crm/timeline',
      name: 'Timeline',
      component: () => import('../views/CRM/Timeline/TimelineView.vue'),
      meta: {
        title: 'Timeline',
      },
    },
    {
      path: '/crm/csv/import',
      name: 'CSV Import',
      component: () => import('../views/CRM/CSV/CsvImport.vue'),
      meta: {
        title: 'Import Data',
      },
    },
    {
      path: '/crm/csv/export',
      name: 'CSV Export',
      component: () => import('../views/CRM/CSV/CsvExport.vue'),
      meta: {
        title: 'Export Data',
      },
    },
    {
      path: '/crm/csv/history',
      name: 'CSV History',
      component: () => import('../views/CRM/CSV/CsvHistory.vue'),
      meta: {
        title: 'Import History',
      },
    },

    // Projects Routes
    {
      path: '/projects',
      name: 'Projects',
      component: () => import('../views/Projects/ProjectList.vue'),
      meta: {
        title: 'Projects',
      },
    },
    {
      path: '/projects/create',
      name: 'Create Project',
      component: () => import('../views/Projects/ProjectCreate.vue'),
      meta: {
        title: 'Create Project',
      },
    },
    {
      path: '/projects/timesheet',
      name: 'Timesheet',
      component: () => import('../views/Projects/TimesheetView.vue'),
      meta: {
        title: 'Timesheet',
      },
    },
    {
      path: '/projects/:id',
      name: 'View Project',
      component: () => import('../views/Projects/ProjectView.vue'),
      meta: {
        title: 'Project Details',
      },
    },
    {
      path: '/projects/:id/edit',
      name: 'Edit Project',
      component: () => import('../views/Projects/ProjectEdit.vue'),
      meta: {
        title: 'Edit Project',
      },
    },

    // Invoices Routes
    {
      path: '/invoices',
      name: 'Invoices',
      component: () => import('../views/Invoices/InvoiceList.vue'),
      meta: {
        title: 'Invoices',
      },
    },
    {
      path: '/invoices/create',
      name: 'Create Invoice',
      component: () => import('../views/Invoices/InvoiceCreate.vue'),
      meta: {
        title: 'Create Invoice',
      },
    },
    {
      path: '/invoices/:id',
      name: 'View Invoice',
      component: () => import('../views/Invoices/InvoiceView.vue'),
      meta: {
        title: 'Invoice Details',
      },
    },
    {
      path: '/invoices/:id/edit',
      name: 'Edit Invoice',
      component: () => import('../views/Invoices/InvoiceEdit.vue'),
      meta: {
        title: 'Edit Invoice',
      },
    },

    // Payment Routes
    {
      path: '/payments/success',
      name: 'Payment Success',
      component: () => import('../views/Payments/PaymentSuccess.vue'),
      meta: {
        title: 'Payment Successful',
      },
    },
    {
      path: '/payments/cancel',
      name: 'Payment Cancelled',
      component: () => import('../views/Payments/PaymentCancel.vue'),
      meta: {
        title: 'Payment Cancelled',
      },
    },

    // Proposals Routes
    {
      path: '/proposals',
      name: 'Proposals',
      component: () => import('../views/Proposals/ProposalList.vue'),
      meta: {
        title: 'Proposals',
      },
    },
    {
      path: '/proposals/create',
      name: 'Create Proposal',
      component: () => import('../views/Proposals/ProposalCreate.vue'),
      meta: {
        title: 'Create Proposal',
      },
    },
    {
      path: '/proposals/:id',
      name: 'View Proposal',
      component: () => import('../views/Proposals/ProposalView.vue'),
      meta: {
        title: 'Proposal Details',
      },
    },
    {
      path: '/proposals/:id/edit',
      name: 'Edit Proposal',
      component: () => import('../views/Proposals/ProposalEdit.vue'),
      meta: {
        title: 'Edit Proposal',
      },
    },

    // Recurring Invoices Routes
    {
      path: '/recurring-invoices',
      name: 'Recurring Invoices',
      component: () => import('../views/RecurringInvoices/RecurringList.vue'),
      meta: {
        title: 'Recurring Invoices',
      },
    },
    {
      path: '/recurring-invoices/create',
      name: 'Create Recurring Invoice',
      component: () => import('../views/RecurringInvoices/RecurringCreate.vue'),
      meta: {
        title: 'Create Schedule',
      },
    },
    {
      path: '/recurring-invoices/:id',
      name: 'View Recurring Invoice',
      component: () => import('../views/RecurringInvoices/RecurringView.vue'),
      meta: {
        title: 'Schedule Details',
      },
    },
    {
      path: '/recurring-invoices/:id/edit',
      name: 'Edit Recurring Invoice',
      component: () => import('../views/RecurringInvoices/RecurringEdit.vue'),
      meta: {
        title: 'Edit Schedule',
      },
    },

    // Client Portal Routes (Public/External)
    {
      path: '/portal',
      name: 'Portal Login',
      component: () => import('../views/Portal/PortalLogin.vue'),
      meta: {
        title: 'Client Portal',
        layout: 'blank',
      },
    },
    {
      path: '/portal/dashboard',
      name: 'Portal Dashboard',
      component: () => import('../views/Portal/PortalDashboard.vue'),
      meta: {
        title: 'Client Portal',
        layout: 'portal',
      },
    },
    {
      path: '/portal/invoices/:id',
      name: 'Portal Invoice',
      component: () => import('../views/Portal/PortalInvoiceView.vue'),
      meta: {
        title: 'Invoice',
        layout: 'portal',
      },
    },
    {
      path: '/portal/proposals/:id',
      name: 'Portal Proposal',
      component: () => import('../views/Portal/PortalProposalView.vue'),
      meta: {
        title: 'Proposal',
        layout: 'portal',
      },
    },

    // Pipelines Routes
    {
      path: '/pipelines',
      name: 'Pipelines',
      component: () => import('../views/Pipelines/PipelineList.vue'),
      meta: {
        title: 'Pipelines',
      },
    },
    {
      path: '/pipelines/create',
      name: 'Create Pipeline',
      component: () => import('../views/Pipelines/PipelineCreate.vue'),
      meta: {
        title: 'Create Pipeline',
      },
    },
    {
      path: '/pipelines/:id/edit',
      name: 'Edit Pipeline',
      component: () => import('../views/Pipelines/PipelineEdit.vue'),
      meta: {
        title: 'Edit Pipeline',
      },
    },

    // Deals Routes
    {
      path: '/deals',
      name: 'Deals',
      component: () => import('../views/Pipelines/DealsKanban.vue'),
      meta: {
        title: 'Deals',
      },
    },
    {
      path: '/deals/:id',
      name: 'View Deal',
      component: () => import('../views/Pipelines/DealDetail.vue'),
      meta: {
        title: 'Deal Details',
      },
    },

    // Files Routes
    {
      path: '/files',
      name: 'Files',
      component: () => import('../views/Files/FileList.vue'),
      meta: {
        title: 'Files & Documents',
      },
    },

    // Tickets Routes
    {
      path: '/tickets',
      name: 'Tickets',
      component: () => import('../views/Tickets/TicketList.vue'),
      meta: {
        title: 'Support Tickets',
      },
    },
    {
      path: '/tickets/create',
      name: 'Create Ticket',
      component: () => import('../views/Tickets/TicketCreate.vue'),
      meta: {
        title: 'Create Ticket',
      },
    },
    {
      path: '/tickets/:id',
      name: 'View Ticket',
      component: () => import('../views/Tickets/TicketView.vue'),
      meta: {
        title: 'Ticket Details',
      },
    },
  ],
})

export default router

router.beforeEach((to, from, next) => {
  document.title = `Vue.js ${to.meta.title} | TailAdmin - Vue.js Tailwind CSS Dashboard Template`
  next()
})
