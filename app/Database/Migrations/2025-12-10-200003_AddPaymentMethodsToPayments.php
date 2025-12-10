<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds additional payment method support to payments table.
 * Part of Milestone 3 - Expansion Features.
 */
class AddPaymentMethodsToPayments extends Migration
{
    public function up(): void
    {
        // Check if columns already exist and add only missing ones
        $columnsToAdd = [];

        // Helper to check if column exists
        $columnExists = function($column) {
            $result = $this->db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'payments' AND column_name = ?", [$column]);
            return $result->getNumRows() > 0;
        };

        if (!$columnExists('paypal_order_id')) {
            $columnsToAdd['paypal_order_id'] = [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ];
        }

        if (!$columnExists('paypal_capture_id')) {
            $columnsToAdd['paypal_capture_id'] = [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ];
        }

        if (!$columnExists('manual_reference')) {
            $columnsToAdd['manual_reference'] = [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ];
        }

        if (!$columnExists('verified_by')) {
            $columnsToAdd['verified_by'] = [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => true,
            ];
        }

        if (!$columnExists('verified_at')) {
            $columnsToAdd['verified_at'] = [
                'type' => 'TIMESTAMP',
                'null' => true,
            ];
        }

        if (!empty($columnsToAdd)) {
            $this->forge->addColumn('payments', $columnsToAdd);
        }

        // Add indexes if they don't exist
        $this->db->query("CREATE INDEX IF NOT EXISTS payments_payment_method_idx ON payments (payment_method)");
        $this->db->query("CREATE INDEX IF NOT EXISTS payments_paypal_order_id_idx ON payments (paypal_order_id)");
    }

    public function down(): void
    {
        $this->db->query("DROP INDEX IF EXISTS payments_paypal_order_id_idx");
        $this->db->query("DROP INDEX IF EXISTS payments_payment_method_idx");

        $this->forge->dropColumn('payments', [
            'payment_method',
            'payment_method_details',
            'paypal_order_id',
            'paypal_capture_id',
            'manual_reference',
            'verified_by',
            'verified_at',
        ]);
    }
}
