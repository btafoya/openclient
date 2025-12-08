<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Create Webhook Events Table
 *
 * Tracks all webhook events from payment gateways for idempotency.
 * Prevents duplicate processing of the same webhook event.
 *
 * Supported gateways: stripe, paypal, stripe_ach
 */
class CreateWebhookEventsTable extends Migration
{
    public function up()
    {
        // Create gateway enum type
        $this->db->query("
            DO $$ BEGIN
                CREATE TYPE payment_gateway AS ENUM ('stripe', 'paypal', 'stripe_ach', 'zelle');
            EXCEPTION
                WHEN duplicate_object THEN null;
            END $$;
        ");

        $this->forge->addField([
            'event_id' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => false,
                'comment' => 'Unique event ID from payment gateway',
            ],
            'gateway' => [
                'type' => 'payment_gateway',
                'null' => false,
            ],
            'event_type' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => false,
                'comment' => 'e.g., payment_intent.succeeded, charge.refunded',
            ],
            'invoice_id' => [
                'type' => 'UUID',
                'null' => true,
                'comment' => 'Reference to invoices table (created in later migration)',
            ],
            'payload' => [
                'type' => 'JSONB',
                'null' => false,
                'comment' => 'Full webhook payload for debugging',
            ],
            'signature' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'HMAC signature for verification',
            ],
            'is_processed' => [
                'type' => 'BOOLEAN',
                'null' => false,
                'default' => false,
            ],
            'processed_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'error_message' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ],
        ]);

        $this->forge->addPrimaryKey('event_id');
        $this->forge->addKey('gateway');
        $this->forge->addKey('event_type');
        $this->forge->addKey('processed_at');
        $this->forge->addKey('created_at');
        $this->forge->createTable('webhook_events', true);

        // Create index for JSONB payload queries
        $this->db->query("
            CREATE INDEX idx_webhook_events_payload ON webhook_events USING GIN (payload);
        ");
    }

    public function down()
    {
        $this->forge->dropTable('webhook_events', true);
        $this->db->query('DROP TYPE IF EXISTS payment_gateway');
    }
}
