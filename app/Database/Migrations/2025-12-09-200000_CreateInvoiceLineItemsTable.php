<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Create Invoice Line Items Table
 *
 * Stores individual line items for invoices with quantity, unit price, and amount calculations.
 * Supports drag-drop reordering via sort_order field.
 */
class CreateInvoiceLineItemsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'UUID',
                'default' => new \CodeIgniter\Database\RawSql('gen_random_uuid()'),
            ],
            'invoice_id' => [
                'type' => 'UUID',
            ],
            'description' => [
                'type' => 'TEXT',
            ],
            'quantity' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'default' => 1.00,
            ],
            'unit_price' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
            ],
            'amount' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
            ],
            'sort_order' => [
                'type' => 'INT',
                'default' => 0,
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
        $this->forge->addKey('invoice_id');
        $this->forge->addForeignKey('invoice_id', 'invoices', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('invoice_line_items');
    }

    public function down()
    {
        $this->forge->dropTable('invoice_line_items');
    }
}
