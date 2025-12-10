<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Dashboard::index');

$routes->group('auth', ['namespace' => 'App\Controllers'], static function($routes) {
    // Login routes
    $routes->get('login', 'AuthController::showLogin');
    $routes->post('login', 'AuthController::login');
    $routes->get('logout', 'AuthController::logout');

    // Password reset routes
    $routes->get('forgot-password', 'AuthController::showForgotPassword');
    $routes->post('forgot-password', 'AuthController::forgotPassword');
    $routes->get('reset-password/(:any)', 'AuthController::showResetPassword/$1');
    $routes->post('reset-password', 'AuthController::resetPassword');
});

$routes->group('', ['filter' => 'auth'], static function($routes) {
    $routes->get('dashboard', 'Dashboard::index');

    // Client routes
    $routes->group('clients', ['namespace' => 'App\Controllers\Clients'], static function($routes) {
        $routes->get('/', 'ClientController::index');
        $routes->get('create', 'ClientController::create');
        $routes->post('/', 'ClientController::store');
        $routes->get('(:segment)', 'ClientController::show/$1');
        $routes->get('(:segment)/edit', 'ClientController::edit/$1');
        $routes->put('(:segment)', 'ClientController::update/$1');
        $routes->delete('(:segment)', 'ClientController::delete/$1');
        $routes->post('(:segment)/restore', 'ClientController::restore/$1');
        $routes->post('(:segment)/toggle-active', 'ClientController::toggleActive/$1');
    });

    // Contact routes
    $routes->group('contacts', ['namespace' => 'App\Controllers\Contacts'], static function($routes) {
        $routes->get('/', 'ContactController::index');
        $routes->get('create', 'ContactController::create');
        $routes->post('/', 'ContactController::store');
        $routes->get('(:segment)', 'ContactController::show/$1');
        $routes->get('(:segment)/edit', 'ContactController::edit/$1');
        $routes->put('(:segment)', 'ContactController::update/$1');
        $routes->delete('(:segment)', 'ContactController::delete/$1');
        $routes->post('(:segment)/restore', 'ContactController::restore/$1');
        $routes->post('(:segment)/toggle-active', 'ContactController::toggleActive/$1');
    });

    // Note routes
    $routes->group('notes', ['namespace' => 'App\Controllers\Notes'], static function($routes) {
        $routes->get('/', 'NoteController::index');
        $routes->get('create', 'NoteController::create');
        $routes->post('/', 'NoteController::store');
        $routes->get('(:segment)', 'NoteController::show/$1');
        $routes->get('(:segment)/edit', 'NoteController::edit/$1');
        $routes->put('(:segment)', 'NoteController::update/$1');
        $routes->delete('(:segment)', 'NoteController::delete/$1');
        $routes->post('(:segment)/restore', 'NoteController::restore/$1');
        $routes->post('(:segment)/toggle-pin', 'NoteController::togglePin/$1');
    });

    // Timeline routes
    $routes->group('timeline', ['namespace' => 'App\Controllers\Timeline'], static function($routes) {
        $routes->get('/', 'TimelineController::index');
        $routes->get('create', 'TimelineController::create');
        $routes->post('/', 'TimelineController::store');
        $routes->get('(:segment)', 'TimelineController::show/$1');
        $routes->delete('(:segment)', 'TimelineController::delete/$1');
    });

    // CSV Import routes
    $routes->group('csv/import', ['namespace' => 'App\Controllers\CsvImport'], static function($routes) {
        $routes->get('/', 'CsvImportController::index');
        $routes->post('upload', 'CsvImportController::upload');
        $routes->get('(:segment)/mapping', 'CsvImportController::mapping/$1');
        $routes->post('(:segment)/mapping', 'CsvImportController::saveMapping/$1');
        $routes->get('(:segment)', 'CsvImportController::show/$1');
        $routes->post('(:segment)/cancel', 'CsvImportController::cancel/$1');
        $routes->delete('(:segment)', 'CsvImportController::delete/$1');
        $routes->get('template/download', 'CsvImportController::downloadTemplate');
    });

    // CSV Export routes
    $routes->group('csv/export', ['namespace' => 'App\Controllers\CsvImport'], static function($routes) {
        $routes->get('/', 'CsvExportController::index');
        $routes->post('/', 'CsvExportController::export');
        $routes->get('fields', 'CsvExportController::getFields');
    });

    // CSV History route
    $routes->get('csv/history', 'CsvImport\CsvImportController::history');

    // API routes
    $routes->group('api', static function($routes) {
        $routes->group('clients', ['namespace' => 'App\Controllers\Clients'], static function($routes) {
            $routes->get('/', 'ClientController::apiIndex');
            $routes->get('(:segment)', 'ClientController::apiShow/$1');
        });
        $routes->group('contacts', ['namespace' => 'App\Controllers\Contacts'], static function($routes) {
            $routes->get('/', 'ContactController::apiIndex');
            $routes->get('(:segment)', 'ContactController::apiShow/$1');
        });
        $routes->group('notes', ['namespace' => 'App\Controllers\Notes'], static function($routes) {
            $routes->get('/', 'NoteController::apiIndex');
            $routes->get('(:segment)', 'NoteController::apiShow/$1');
        });
        $routes->group('timeline', ['namespace' => 'App\Controllers\Timeline'], static function($routes) {
            $routes->get('/', 'TimelineController::apiIndex');
            $routes->get('(:segment)', 'TimelineController::apiShow/$1');
            $routes->get('statistics', 'TimelineController::apiStatistics');
        });
        $routes->group('csv', ['namespace' => 'App\Controllers\CsvImport'], static function($routes) {
            $routes->get('import/statistics', 'CsvImportController::apiStatistics');
            $routes->get('imports', 'CsvImportController::apiHistory');
            $routes->post('import/upload', 'CsvImportController::apiUpload');
            $routes->get('import/(:segment)', 'CsvImportController::apiShow/$1');
            $routes->post('import/(:segment)/mapping', 'CsvImportController::apiSaveMapping/$1');
            $routes->post('import/(:segment)/cancel', 'CsvImportController::apiCancel/$1');
            $routes->delete('import/(:segment)', 'CsvImportController::apiDelete/$1');
            $routes->get('import/template/(:segment)', 'CsvImportController::apiDownloadTemplate/$1');
            $routes->get('export/fields', 'CsvExportController::getFields');
            $routes->post('export', 'CsvExportController::apiExport');
        });

        // Projects API routes
        $routes->group('projects', ['namespace' => 'App\Controllers\Projects'], static function($routes) {
            $routes->get('stats', 'ProjectController::stats');
            $routes->get('/', 'ProjectController::index');
            $routes->post('/', 'ProjectController::store');
            $routes->get('(:segment)', 'ProjectController::show/$1');
            $routes->put('(:segment)', 'ProjectController::update/$1');
            $routes->patch('(:segment)', 'ProjectController::update/$1');
            $routes->patch('(:segment)/status', 'ProjectController::updateStatus/$1');
            $routes->delete('(:segment)', 'ProjectController::delete/$1');
            $routes->post('(:segment)/restore', 'ProjectController::restore/$1');
            $routes->post('(:segment)/toggle-active', 'ProjectController::toggleActive/$1');

            // Kanban board for specific project
            $routes->get('(:segment)/kanban', 'TaskController::getKanbanBoard/$1');

            // Tasks reorder for project
            $routes->post('(:segment)/tasks/reorder', 'TaskController::reorderTasks/$1');

            // Time entries summary for project
            $routes->get('(:segment)/time-entries/summary', 'TimeEntryController::getBillableSummary/$1');
        });

        // Tasks API routes
        $routes->group('tasks', ['namespace' => 'App\Controllers\Projects'], static function($routes) {
            $routes->get('overdue', 'TaskController::getOverdue');
            $routes->get('/', 'TaskController::index');
            $routes->post('/', 'TaskController::store');
            $routes->get('(:segment)', 'TaskController::show/$1');
            $routes->put('(:segment)', 'TaskController::update/$1');
            $routes->patch('(:segment)', 'TaskController::update/$1');
            $routes->patch('(:segment)/status', 'TaskController::updateStatus/$1');
            $routes->patch('(:segment)/assign', 'TaskController::assignToUser/$1');
            $routes->patch('(:segment)/sort-order', 'TaskController::updateSortOrder/$1');
            $routes->delete('(:segment)', 'TaskController::delete/$1');
            $routes->post('(:segment)/toggle-active', 'TaskController::toggleActive/$1');
        });

        // Time Entries API routes
        $routes->group('time-entries', ['namespace' => 'App\Controllers\Projects'], static function($routes) {
            // Timer endpoints (must come before generic routes)
            $routes->post('timer/start', 'TimeEntryController::startTimer');
            $routes->post('timer/stop', 'TimeEntryController::stopTimer');
            $routes->get('timer/running', 'TimeEntryController::getRunningTimer');

            // CRUD endpoints
            $routes->get('/', 'TimeEntryController::index');
            $routes->post('/', 'TimeEntryController::store');
            $routes->get('(:segment)', 'TimeEntryController::show/$1');
            $routes->put('(:segment)', 'TimeEntryController::update/$1');
            $routes->patch('(:segment)', 'TimeEntryController::update/$1');
            $routes->patch('(:segment)/toggle-billable', 'TimeEntryController::toggleBillable/$1');
            $routes->delete('(:segment)', 'TimeEntryController::delete/$1');
        });

        // User time entry statistics
        $routes->get('users/(:segment)/time-entries/stats', 'Projects\TimeEntryController::getUserStats/$1');

        // Invoices API routes
        $routes->group('invoices', ['namespace' => 'App\Controllers\Invoices'], static function($routes) {
            // Statistics and special routes (must come before generic routes)
            $routes->get('stats', 'InvoiceController::stats');
            $routes->get('overdue', 'InvoiceController::overdue');
            $routes->post('from-project/(:segment)', 'InvoiceController::createFromProject/$1');

            // CRUD endpoints
            $routes->get('/', 'InvoiceController::index');
            $routes->post('/', 'InvoiceController::store');
            $routes->get('(:segment)', 'InvoiceController::show/$1');
            $routes->put('(:segment)', 'InvoiceController::update/$1');
            $routes->patch('(:segment)', 'InvoiceController::update/$1');
            $routes->delete('(:segment)', 'InvoiceController::delete/$1');

            // Status and workflow endpoints
            $routes->patch('(:segment)/status', 'InvoiceController::updateStatus/$1');
            $routes->post('(:segment)/send', 'InvoiceController::send/$1');
            $routes->post('(:segment)/resend', 'InvoiceController::resend/$1');
            $routes->post('(:segment)/mark-paid', 'InvoiceController::markPaid/$1');

            // PDF endpoints
            $routes->get('(:segment)/pdf', 'InvoiceController::pdf/$1');
            $routes->get('(:segment)/preview', 'InvoiceController::preview/$1');

            // Line items nested routes
            $routes->get('(:segment)/line-items', 'InvoiceLineItemController::index/$1');
            $routes->post('(:segment)/line-items', 'InvoiceLineItemController::store/$1');
            $routes->put('(:segment)/line-items/(:segment)', 'InvoiceLineItemController::update/$1/$2');
            $routes->delete('(:segment)/line-items/(:segment)', 'InvoiceLineItemController::delete/$1/$2');
            $routes->post('(:segment)/line-items/reorder', 'InvoiceLineItemController::reorder/$1');

            // Invoice payments
            $routes->get('(:segment)/payments', 'Payments\PaymentController::getByInvoice/$1');
        });

        // Payments API routes
        $routes->group('payments', ['namespace' => 'App\Controllers\Payments'], static function($routes) {
            // Configuration
            $routes->get('config', 'PaymentController::config');
            $routes->get('stats', 'PaymentController::stats');

            // Stripe Checkout flow
            $routes->post('checkout', 'PaymentController::createCheckout');
            $routes->get('success', 'PaymentController::success');
            $routes->get('cancel', 'PaymentController::cancel');

            // PayPal routes
            $routes->post('paypal/create-order', 'PaymentController::paypalCreateOrder');
            $routes->post('paypal/capture', 'PaymentController::paypalCapture');

            // Stripe ACH routes
            $routes->post('ach/create-intent', 'PaymentController::achCreateIntent');
            $routes->post('ach/verify-microdeposits', 'PaymentController::achVerifyMicrodeposits');

            // Manual payment routes (Zelle, check, wire)
            $routes->post('manual/record', 'PaymentController::manualRecord');
            $routes->get('manual/pending', 'PaymentController::pendingManualPayments');
            $routes->post('manual/(:segment)/verify', 'PaymentController::manualVerify/$1');
            $routes->post('manual/(:segment)/reject', 'PaymentController::manualReject/$1');

            // Payment CRUD
            $routes->get('/', 'PaymentController::index');
            $routes->get('(:segment)', 'PaymentController::show/$1');
            $routes->post('(:segment)/refund', 'PaymentController::refund/$1');
        });

        // Invoice payment methods and instructions
        $routes->get('invoices/(:segment)/payment-methods', 'Payments\PaymentController::availableMethods/$1');
        $routes->get('invoices/(:segment)/payment-instructions/(:segment)', 'Payments\PaymentController::paymentInstructions/$1/$2');

        // Pipelines API routes
        $routes->group('pipelines', ['namespace' => 'App\Controllers\Pipelines'], static function($routes) {
            $routes->get('/', 'PipelineController::index');
            $routes->post('/', 'PipelineController::store');
            $routes->get('(:segment)', 'PipelineController::show/$1');
            $routes->put('(:segment)', 'PipelineController::update/$1');
            $routes->patch('(:segment)', 'PipelineController::update/$1');
            $routes->delete('(:segment)', 'PipelineController::delete/$1');
            $routes->get('(:segment)/stats', 'PipelineController::stats/$1');

            // Pipeline stages
            $routes->post('(:segment)/stages', 'PipelineController::addStage/$1');
            $routes->put('(:segment)/stages/reorder', 'PipelineController::reorderStages/$1');
            $routes->put('(:segment)/stages/(:segment)', 'PipelineController::updateStage/$1/$2');
            $routes->delete('(:segment)/stages/(:segment)', 'PipelineController::deleteStage/$1/$2');
        });

        // Deals API routes
        $routes->group('deals', ['namespace' => 'App\Controllers\Pipelines'], static function($routes) {
            // Special routes (must come before generic routes)
            $routes->get('stats', 'DealController::stats');
            $routes->get('closing-soon', 'DealController::closingSoon');
            $routes->get('overdue', 'DealController::overdue');
            $routes->put('reorder', 'DealController::reorder');
            $routes->get('kanban/(:segment)', 'DealController::kanban/$1');

            // CRUD endpoints
            $routes->get('/', 'DealController::index');
            $routes->post('/', 'DealController::store');
            $routes->get('(:segment)', 'DealController::show/$1');
            $routes->put('(:segment)', 'DealController::update/$1');
            $routes->patch('(:segment)', 'DealController::update/$1');
            $routes->delete('(:segment)', 'DealController::delete/$1');

            // Deal workflow
            $routes->post('(:segment)/move', 'DealController::move/$1');
            $routes->post('(:segment)/won', 'DealController::markWon/$1');
            $routes->post('(:segment)/lost', 'DealController::markLost/$1');
            $routes->post('(:segment)/convert', 'DealController::convert/$1');

            // Deal activities
            $routes->get('(:segment)/activities', 'DealController::getActivities/$1');
            $routes->post('(:segment)/activities', 'DealController::addActivity/$1');
        });

        // Proposals API routes
        $routes->group('proposals', ['namespace' => 'App\Controllers\Proposals'], static function($routes) {
            // Templates
            $routes->get('templates', 'ProposalController::templates');

            // CRUD endpoints
            $routes->get('/', 'ProposalController::index');
            $routes->post('/', 'ProposalController::store');
            $routes->get('(:segment)', 'ProposalController::show/$1');
            $routes->put('(:segment)', 'ProposalController::update/$1');
            $routes->patch('(:segment)', 'ProposalController::update/$1');
            $routes->delete('(:segment)', 'ProposalController::delete/$1');

            // Proposal workflow
            $routes->post('(:segment)/send', 'ProposalController::send/$1');
            $routes->post('(:segment)/convert-to-invoice', 'ProposalController::convertToInvoice/$1');

            // Proposal sections
            $routes->get('(:segment)/sections', 'ProposalSectionController::index/$1');
            $routes->post('(:segment)/sections', 'ProposalSectionController::store/$1');
            $routes->put('(:segment)/sections/(:segment)', 'ProposalSectionController::update/$1/$2');
            $routes->delete('(:segment)/sections/(:segment)', 'ProposalSectionController::delete/$1/$2');
            $routes->post('(:segment)/sections/reorder', 'ProposalSectionController::reorder/$1');
        });

        // Proposal Templates API routes
        $routes->group('proposal-templates', ['namespace' => 'App\Controllers\Proposals'], static function($routes) {
            $routes->get('/', 'ProposalTemplateController::index');
            $routes->post('/', 'ProposalTemplateController::store');
            $routes->get('(:segment)', 'ProposalTemplateController::show/$1');
            $routes->put('(:segment)', 'ProposalTemplateController::update/$1');
            $routes->patch('(:segment)', 'ProposalTemplateController::update/$1');
            $routes->delete('(:segment)', 'ProposalTemplateController::delete/$1');
            $routes->post('(:segment)/duplicate', 'ProposalTemplateController::duplicate/$1');
        });

        // Recurring Invoices API routes
        $routes->group('recurring-invoices', ['namespace' => 'App\Controllers\RecurringInvoices'], static function($routes) {
            $routes->get('/', 'RecurringInvoiceController::index');
            $routes->post('/', 'RecurringInvoiceController::store');
            $routes->get('(:segment)', 'RecurringInvoiceController::show/$1');
            $routes->put('(:segment)', 'RecurringInvoiceController::update/$1');
            $routes->patch('(:segment)', 'RecurringInvoiceController::update/$1');
            $routes->delete('(:segment)', 'RecurringInvoiceController::delete/$1');

            // Recurring invoice workflow
            $routes->post('(:segment)/pause', 'RecurringInvoiceController::pause/$1');
            $routes->post('(:segment)/resume', 'RecurringInvoiceController::resume/$1');
            $routes->post('(:segment)/cancel', 'RecurringInvoiceController::cancel/$1');
            $routes->post('(:segment)/process', 'RecurringInvoiceController::process/$1');
        });

        // Portal Access Management API routes (for agency admins)
        $routes->group('clients/(:segment)/portal-access', ['namespace' => 'App\Controllers\Portal'], static function($routes) {
            $routes->get('/', 'PortalAccessController::index/$1');
            $routes->post('/', 'PortalAccessController::store/$1');
            $routes->put('(:segment)', 'PortalAccessController::update/$1/$2');
            $routes->delete('(:segment)', 'PortalAccessController::delete/$1/$2');
            $routes->post('(:segment)/send-link', 'PortalAccessController::sendLink/$1/$2');
            $routes->delete('/', 'PortalAccessController::revokeAll/$1');
        });

        // Files API routes
        $routes->group('files', ['namespace' => 'App\Controllers\Files'], static function($routes) {
            $routes->get('stats', 'FileController::stats');
            $routes->get('recent', 'FileController::recent');
            $routes->get('search', 'FileController::search');
            $routes->get('/', 'FileController::index');
            $routes->post('/', 'FileController::upload');
            $routes->get('(:segment)', 'FileController::show/$1');
            $routes->get('(:segment)/download', 'FileController::download/$1');
            $routes->put('(:segment)', 'FileController::update/$1');
            $routes->patch('(:segment)', 'FileController::update/$1');
            $routes->delete('(:segment)', 'FileController::delete/$1');
        });

        // Tickets API routes
        $routes->group('tickets', ['namespace' => 'App\Controllers\Tickets'], static function($routes) {
            $routes->get('stats', 'TicketController::stats');
            $routes->get('overdue', 'TicketController::overdue');
            $routes->get('/', 'TicketController::index');
            $routes->post('/', 'TicketController::store');
            $routes->get('(:segment)', 'TicketController::show/$1');
            $routes->put('(:segment)', 'TicketController::update/$1');
            $routes->patch('(:segment)', 'TicketController::update/$1');
            $routes->delete('(:segment)', 'TicketController::delete/$1');
            $routes->post('(:segment)/assign', 'TicketController::assign/$1');
            $routes->post('(:segment)/resolve', 'TicketController::resolve/$1');
            $routes->post('(:segment)/close', 'TicketController::close/$1');
            $routes->post('(:segment)/reopen', 'TicketController::reopen/$1');
            $routes->get('(:segment)/messages', 'TicketController::getMessages/$1');
            $routes->post('(:segment)/messages', 'TicketController::addMessage/$1');
        });

        // Activity Log API routes
        $routes->group('activity', ['namespace' => 'App\Controllers\Activity'], static function($routes) {
            $routes->get('stats', 'ActivityController::stats');
            $routes->get('search', 'ActivityController::search');
            $routes->get('entity', 'ActivityController::forEntity');
            $routes->get('/', 'ActivityController::index');
            $routes->get('user/(:segment)', 'ActivityController::forUser/$1');
            $routes->get('action/(:segment)', 'ActivityController::byAction/$1');
        });
    });
});

// Client Portal routes (separate authentication)
$routes->group('portal', ['namespace' => 'App\Controllers\Portal'], static function($routes) {
    // Auth endpoints (no auth required)
    $routes->post('auth/request-link', 'PortalController::requestMagicLink');
    $routes->post('auth/magic-link', 'PortalController::authMagicLink');
    $routes->post('auth/logout', 'PortalController::logout');

    // Protected portal endpoints
    $routes->get('me', 'PortalController::me');
    $routes->get('invoices', 'PortalController::invoices');
    $routes->get('invoices/(:segment)', 'PortalController::showInvoice/$1');
    $routes->get('proposals', 'PortalController::proposals');
    $routes->get('proposals/(:segment)', 'PortalController::showProposal/$1');
    $routes->post('proposals/(:segment)/accept', 'PortalController::acceptProposal/$1');
    $routes->post('proposals/(:segment)/reject', 'PortalController::rejectProposal/$1');
    $routes->get('projects', 'PortalController::projects');
    $routes->get('activity', 'PortalController::activity');
});

// Webhook routes (no authentication required - signature verification used)
$routes->group('webhooks', ['namespace' => 'App\Controllers\Webhooks'], static function($routes) {
    $routes->post('stripe', 'WebhookController::stripe');
    $routes->get('health', 'WebhookController::health');
});

// Vue SPA Catch-All Routes
// These must come AFTER all API routes to avoid conflicts
// Serve Vue SPA for all frontend routes (CRM, Projects, etc.)
$routes->get('crm', 'SpaController::index');
$routes->get('crm/(:any)', 'SpaController::index');
$routes->get('projects', 'SpaController::index');
$routes->get('projects/(:any)', 'SpaController::index');
$routes->get('invoices', 'SpaController::index');
$routes->get('invoices/(:any)', 'SpaController::index');
$routes->get('payments', 'SpaController::index');
$routes->get('payments/(:any)', 'SpaController::index');
$routes->get('pipelines', 'SpaController::index');
$routes->get('pipelines/(:any)', 'SpaController::index');
$routes->get('deals', 'SpaController::index');
$routes->get('deals/(:any)', 'SpaController::index');

// Catch-all for other SPA routes (add as needed)
// Example: $routes->get('settings/(:any)', 'SpaController::index');
