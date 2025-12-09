<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateContactsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'UUID',
                'default' => new \CodeIgniter\Database\RawSql('uuid_generate_v4()'),
            ],
            'client_id' => [
                'type' => 'UUID',
                'null' => false,
            ],
            'agency_id' => [
                'type' => 'UUID',
                'null' => false,
            ],
            'first_name' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
                'null' => false,
            ],
            'last_name' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
                'null' => false,
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true,
            ],
            'phone' => [
                'type' => 'VARCHAR',
                'constraint' => '50',
                'null' => true,
            ],
            'mobile' => [
                'type' => 'VARCHAR',
                'constraint' => '50',
                'null' => true,
            ],
            'job_title' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
                'null' => true,
            ],
            'department' => [
                'type' => 'VARCHAR',
                'constraint' => '100',
                'null' => true,
            ],
            'is_primary' => [
                'type' => 'BOOLEAN',
                'default' => false,
                'null' => false,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'is_active' => [
                'type' => 'BOOLEAN',
                'default' => true,
                'null' => false,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
            'deleted_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('client_id', 'clients', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('agency_id', 'agencies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('contacts');

        // Add indexes for performance
        $this->db->query('CREATE INDEX idx_contacts_client_id ON contacts(client_id)');
        $this->db->query('CREATE INDEX idx_contacts_agency_id ON contacts(agency_id)');
        $this->db->query('CREATE INDEX idx_contacts_is_active ON contacts(is_active)');
        $this->db->query('CREATE INDEX idx_contacts_deleted_at ON contacts(deleted_at)');
        $this->db->query('CREATE INDEX idx_contacts_is_primary ON contacts(is_primary)');

        // Enable Row-Level Security
        $this->db->query('ALTER TABLE contacts ENABLE ROW LEVEL SECURITY');

        // Create RLS policy for agency isolation
        $this->db->query("
            CREATE POLICY contacts_agency_isolation ON contacts
            FOR ALL
            USING (
                agency_id = NULLIF(current_setting('app.current_agency_id', true), '')::uuid
                OR current_setting('app.current_agency_id', true) = ''
            )
        ");
    }

    public function down()
    {
        $this->forge->dropTable('contacts', true);
    }
}
