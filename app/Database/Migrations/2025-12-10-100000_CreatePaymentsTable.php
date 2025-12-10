<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePaymentsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'UUID',
                'default' => new \CodeIgniter\Database\RawSql('gen_random_uuid()'),
            ],
            'agency_id' => [
                'type' => 'UUID',
            ],
            'invoice_id' => [
                'type' => 'UUID',
            ],
            'stripe_payment_intent_id' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true,
            ],
            'stripe_charge_id' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true,
            ],
            'stripe_checkout_session_id' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true,
            ],
            'amount' => [
                'type' => 'DECIMAL',
                'constraint' => '15,2',
            ],
            'currency' => [
                'type' => 'VARCHAR',
                'constraint' => '3',
                'default' => 'USD',
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => '50',
                'default' => 'pending',
                // pending, processing, succeeded, failed, refunded, cancelled
            ],
            'payment_method' => [
                'type' => 'VARCHAR',
                'constraint' => '50',
                'null' => true,
                // card, bank_transfer, etc.
            ],
            'payment_method_details' => [
                'type' => 'JSON',
                'null' => true,
                // Card brand, last4, etc.
            ],
            'failure_code' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
                'null' => true,
            ],
            'failure_message' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'refund_id' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true,
            ],
            'refund_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '15,2',
                'null' => true,
            ],
            'refunded_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'metadata' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'processed_at' => [
                'type' => 'TIMESTAMP',
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
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('stripe_payment_intent_id');
        $this->forge->addKey('stripe_checkout_session_id');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('agency_id', 'agencies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('invoice_id', 'invoices', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('payments');

        // Add RLS policy for agency isolation (PostgreSQL)
        $this->db->query("
            ALTER TABLE payments ENABLE ROW LEVEL SECURITY;

            CREATE POLICY payments_agency_isolation ON payments
                USING (agency_id = current_setting('app.current_agency_id', true)::uuid);
        ");
    }

    public function down()
    {
        $this->db->query("DROP POLICY IF EXISTS payments_agency_isolation ON payments");
        $this->forge->dropTable('payments');
    }
}
