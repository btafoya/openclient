<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Create Agencies Table
 *
 * Agencies represent the top-level organizational entities.
 * Each agency can have multiple users, clients, and projects.
 */
class CreateAgenciesTable extends Migration
{
    public function up()
    {
        // Enable UUID extension if not already enabled
        $this->db->query('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');

        $this->forge->addField([
            'id' => [
                'type' => 'UUID',
                'null' => false,
                'default' => 'uuid_generate_v4()',
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => false,
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'phone' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ],
            'address' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'city' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'state' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'postal_code' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
            ],
            'country' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
                'default' => 'USA',
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ],
            'deleted_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('agencies', true);

        // Create updated_at trigger
        $this->db->query("
            CREATE OR REPLACE FUNCTION update_updated_at_column()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
            $$ language 'plpgsql';
        ");

        $this->db->query("
            CREATE TRIGGER update_agencies_updated_at BEFORE UPDATE
            ON agencies FOR EACH ROW
            EXECUTE FUNCTION update_updated_at_column();
        ");
    }

    public function down()
    {
        $this->db->query('DROP TRIGGER IF EXISTS update_agencies_updated_at ON agencies');
        $this->forge->dropTable('agencies', true);
    }
}
