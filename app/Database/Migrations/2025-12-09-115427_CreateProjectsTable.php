<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProjectsTable extends Migration
{
    public function up()
    {
        // Create projects table
        $this->forge->addField([
            'id' => [
                'type' => 'UUID',
                'null' => false,
            ],
            'agency_id' => [
                'type' => 'UUID',
                'null' => false,
            ],
            'client_id' => [
                'type' => 'UUID',
                'null' => false,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => false,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'default' => 'active',
                'comment' => 'active, completed, on_hold, cancelled',
            ],
            'start_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'due_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'budget' => [
                'type' => 'DECIMAL',
                'constraint' => '12,2',
                'null' => true,
            ],
            'hourly_rate' => [
                'type' => 'DECIMAL',
                'constraint' => '8,2',
                'null' => true,
            ],
            'is_billable' => [
                'type' => 'BOOLEAN',
                'default' => true,
            ],
            'is_active' => [
                'type' => 'BOOLEAN',
                'default' => true,
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
        $this->forge->addKey('status');
        $this->forge->addKey('is_active');
        $this->forge->addKey('deleted_at');

        $this->forge->createTable('projects');

        // Add foreign keys
        $this->forge->addForeignKey('agency_id', 'agencies', 'id', 'CASCADE', 'CASCADE', 'fk_projects_agency');
        $this->forge->addForeignKey('client_id', 'clients', 'id', 'CASCADE', 'CASCADE', 'fk_projects_client');
        $this->forge->processIndexes('projects');

        // Enable RLS
        $this->db->query('ALTER TABLE projects ENABLE ROW LEVEL SECURITY');

        // Create RLS policy for agency isolation
        $this->db->query("
            CREATE POLICY projects_agency_isolation ON projects
            USING (agency_id = NULLIF(current_setting('app.current_agency_id', true), '')::uuid)
        ");
    }

    public function down()
    {
        // Drop RLS policy
        $this->db->query('DROP POLICY IF EXISTS projects_agency_isolation ON projects');

        // Drop table
        $this->forge->dropTable('projects', true);
    }
}
