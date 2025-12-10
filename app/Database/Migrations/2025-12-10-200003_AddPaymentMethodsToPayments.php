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
        // Add payment method column to payments table
        $this->forge->addColumn('payments', [
            'payment_method' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'default' => 'stripe',
                'after' => 'status',
            ],
            'payment_method_details' => [
                'type' => 'JSONB',
                'null' => true,
                'after' => 'payment_method',
            ],
            'paypal_order_id' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
                'after' => 'stripe_payment_intent_id',
            ],
            'paypal_capture_id' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
                'after' => 'paypal_order_id',
            ],
            'manual_reference' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
                'after' => 'paypal_capture_id',
            ],
            'verified_by' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => true,
                'after' => 'manual_reference',
            ],
            'verified_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
                'after' => 'verified_by',
            ],
        ]);

        // Add index for payment method
        $this->db->query("CREATE INDEX payments_payment_method_idx ON payments (payment_method)");
        $this->db->query("CREATE INDEX payments_paypal_order_id_idx ON payments (paypal_order_id)");
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
