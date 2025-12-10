<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\RecurringInvoiceService;

/**
 * Generate Recurring Invoices Command
 *
 * CLI command to process and generate recurring invoices.
 * Should be scheduled to run daily via cron job.
 *
 * Usage:
 *   php spark recurring:generate
 *   php spark recurring:generate --dry-run
 *   php spark recurring:generate --force
 */
class GenerateRecurringInvoices extends BaseCommand
{
    /**
     * The Command's Group
     */
    protected $group = 'Invoices';

    /**
     * The Command's Name
     */
    protected $name = 'recurring:generate';

    /**
     * The Command's Description
     */
    protected $description = 'Generate invoices from recurring schedules that are due.';

    /**
     * The Command's Usage
     */
    protected $usage = 'recurring:generate [options]';

    /**
     * The Command's Arguments
     */
    protected $arguments = [];

    /**
     * The Command's Options
     */
    protected $options = [
        '--dry-run' => 'Preview which invoices would be generated without creating them.',
        '--force'   => 'Force generation even if not scheduled for today.',
        '--send'    => 'Send generated invoices to clients immediately.',
        '--quiet'   => 'Suppress output except for errors.',
    ];

    /**
     * Execute the command
     */
    public function run(array $params): int
    {
        $dryRun = CLI::getOption('dry-run') !== false;
        $force = CLI::getOption('force') !== false;
        $send = CLI::getOption('send') !== false;
        $quiet = CLI::getOption('quiet') !== false;

        if (!$quiet) {
            CLI::write('==============================================', 'cyan');
            CLI::write('  Recurring Invoice Generator', 'cyan');
            CLI::write('==============================================', 'cyan');
            CLI::newLine();

            if ($dryRun) {
                CLI::write('[DRY RUN] No invoices will be created.', 'yellow');
                CLI::newLine();
            }
        }

        try {
            $service = new RecurringInvoiceService();

            if ($dryRun) {
                return $this->dryRun($service, $quiet);
            }

            return $this->processInvoices($service, $send, $quiet);

        } catch (\Exception $e) {
            CLI::error('Error: ' . $e->getMessage());
            log_message('error', 'recurring:generate failed: ' . $e->getMessage());
            return EXIT_ERROR;
        }
    }

    /**
     * Perform dry run - show what would be generated
     */
    protected function dryRun(RecurringInvoiceService $service, bool $quiet): int
    {
        $upcoming = $service->getUpcomingInvoices(1); // Just today's

        if (empty($upcoming)) {
            if (!$quiet) {
                CLI::write('No recurring invoices are due for generation today.', 'green');
            }
            return EXIT_SUCCESS;
        }

        if (!$quiet) {
            CLI::write('The following invoices would be generated:', 'white');
            CLI::newLine();

            $headers = ['Schedule ID', 'Client ID', 'Amount', 'Next Date', 'Frequency'];
            $rows = [];

            foreach ($upcoming as $schedule) {
                $preview = $service->previewNextInvoice($schedule['id']);
                $rows[] = [
                    substr($schedule['id'], 0, 8) . '...',
                    substr($schedule['client_id'], 0, 8) . '...',
                    '$' . number_format($preview['total'] ?? 0, 2),
                    $schedule['next_invoice_date'],
                    $schedule['frequency'],
                ];
            }

            CLI::table($rows, $headers);
            CLI::newLine();
            CLI::write('Total: ' . count($upcoming) . ' invoice(s) would be generated.', 'cyan');
        }

        return EXIT_SUCCESS;
    }

    /**
     * Process and generate invoices
     */
    protected function processInvoices(RecurringInvoiceService $service, bool $send, bool $quiet): int
    {
        $startTime = microtime(true);

        if (!$quiet) {
            CLI::write('Processing recurring invoices...', 'white');
            CLI::newLine();
        }

        $results = $service->processDueInvoices();

        $elapsed = round(microtime(true) - $startTime, 2);

        if (!$quiet) {
            CLI::newLine();
            CLI::write('==============================================', 'cyan');
            CLI::write('  Results', 'cyan');
            CLI::write('==============================================', 'cyan');
            CLI::newLine();

            CLI::write("Processed:  {$results['processed']} schedule(s)", 'white');
            CLI::write("Generated:  {$results['generated']} invoice(s)", 'green');

            if ($results['failed'] > 0) {
                CLI::write("Failed:     {$results['failed']} invoice(s)", 'red');
            }

            CLI::write("Time:       {$elapsed}s", 'white');
            CLI::newLine();

            if (!empty($results['invoices'])) {
                CLI::write('Generated Invoices:', 'white');
                foreach ($results['invoices'] as $invoice) {
                    CLI::write("  - {$invoice['invoice_number']}: \${$invoice['total']}", 'green');
                }
                CLI::newLine();
            }

            if (!empty($results['errors'])) {
                CLI::write('Errors:', 'red');
                foreach ($results['errors'] as $error) {
                    CLI::write("  - {$error}", 'red');
                }
                CLI::newLine();
            }
        }

        // Log results
        log_message('info', "recurring:generate completed - Generated: {$results['generated']}, Failed: {$results['failed']}");

        return $results['failed'] > 0 ? EXIT_ERROR : EXIT_SUCCESS;
    }
}
