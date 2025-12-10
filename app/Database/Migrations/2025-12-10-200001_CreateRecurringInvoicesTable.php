<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates recurring_invoices table for automated billing.
 * Part of Milestone 3 - Expansion Features.
 */
class CreateRecurringInvoicesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
            ],
            'agency_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
            ],
            'client_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
            ],
            'project_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => true,
            ],
            'title' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'line_items' => [
                'type' => 'JSONB',
            ],
            'subtotal' => [
                'type' => 'DECIMAL',
                'constraint' => '15,2',
                'default' => 0,
            ],
            'tax_rate' => [
                'type' => 'DECIMAL',
                'constraint' => '5,2',
                'default' => 0,
            ],
            'tax_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '15,2',
                'default' => 0,
            ],
            'discount_percent' => [
                'type' => 'DECIMAL',
                'constraint' => '5,2',
                'default' => 0,
            ],
            'discount_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '15,2',
                'default' => 0,
            ],
            'total_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '15,2',
                'default' => 0,
            ],
            'currency' => [
                'type' => 'VARCHAR',
                'constraint' => 3,
                'default' => 'USD',
            ],
            'frequency' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
            ],
            'interval_count' => [
                'type' => 'INT',
                'default' => 1,
            ],
            'day_of_week' => [
                'type' => 'INT',
                'null' => true,
            ],
            'day_of_month' => [
                'type' => 'INT',
                'null' => true,
            ],
            'start_date' => [
                'type' => 'DATE',
            ],
            'end_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'next_run_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'last_run_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'active',
            ],
            'auto_send' => [
                'type' => 'BOOLEAN',
                'default' => true,
            ],
            'payment_terms_days' => [
                'type' => 'INT',
                'default' => 30,
            ],
            'last_invoice_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => true,
            ],
            'invoice_count' => [
                'type' => 'INT',
                'default' => 0,
            ],
            'max_occurrences' => [
                'type' => 'INT',
                'null' => true,
            ],
            'email_recipients' => [
                'type' => 'JSONB',
                'null' => true,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'metadata' => [
                'type' => 'JSONB',
                'null' => true,
            ],
            'created_by' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('agency_id');
        $this->forge->addKey('client_id');
        $this->forge->addKey('project_id');
        $this->forge->addKey('status');
        $this->forge->addKey('next_run_date');
        $this->forge->createTable('recurring_invoices');

        // Add RLS policy
        $this->db->query("ALTER TABLE recurring_invoices ENABLE ROW LEVEL SECURITY");
        $this->db->query("
            CREATE POLICY recurring_invoices_agency_isolation ON recurring_invoices
            FOR ALL
            USING (agency_id = current_setting('app.current_agency_id', true)::varchar)
        ");
    }

    public function down(): void
    {
        $this->db->query("DROP POLICY IF EXISTS recurring_invoices_agency_isolation ON recurring_invoices");
        $this->forge->dropTable('recurring_invoices', true);
    }
}
