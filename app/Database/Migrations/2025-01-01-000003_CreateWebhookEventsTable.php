<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWebhookEventsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'event_id' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => false,
            ],
            'gateway' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'comment' => 'stripe, paypal, zelle',
            ],
            'event_type' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'comment' => 'payment_intent.succeeded, charge.refunded, etc.',
            ],
            'invoice_id' => [
                'type' => 'UUID',
                'null' => true,
            ],
            'payload' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'pending',
                'comment' => 'pending, processed, failed',
            ],
            'error_message' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'processed_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
            ],
        ]);

        $this->forge->addPrimaryKey('event_id');
        $this->forge->addKey('gateway');
        $this->forge->addKey('status');
        $this->forge->addKey('processed_at');
        $this->forge->addKey('created_at');

        $this->forge->createTable('webhook_events');
    }

    public function down()
    {
        $this->forge->dropTable('webhook_events');
    }
}
