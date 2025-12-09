<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTimeEntriesTable extends Migration
{
    public function up()
    {
        // Create time_entries table
        $this->forge->addField([
            'id' => [
                'type' => 'UUID',
                'null' => false,
            ],
            'agency_id' => [
                'type' => 'UUID',
                'null' => false,
            ],
            'user_id' => [
                'type' => 'UUID',
                'null' => false,
            ],
            'project_id' => [
                'type' => 'UUID',
                'null' => false,
            ],
            'task_id' => [
                'type' => 'UUID',
                'null' => true,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'hours' => [
                'type' => 'DECIMAL',
                'constraint' => '6,2',
                'null' => true,
            ],
            'start_time' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'end_time' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'is_billable' => [
                'type' => 'BOOLEAN',
                'default' => true,
            ],
            'hourly_rate' => [
                'type' => 'DECIMAL',
                'constraint' => '8,2',
                'null' => true,
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
        $this->forge->addKey('user_id');
        $this->forge->addKey('project_id');
        $this->forge->addKey('task_id');
        $this->forge->addKey('is_billable');
        $this->forge->addKey('is_active');
        $this->forge->addKey('deleted_at');

        $this->forge->createTable('time_entries');

        // Add foreign keys
        $this->forge->addForeignKey('agency_id', 'agencies', 'id', 'CASCADE', 'CASCADE', 'fk_time_entries_agency');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE', 'fk_time_entries_user');
        $this->forge->addForeignKey('project_id', 'projects', 'id', 'CASCADE', 'CASCADE', 'fk_time_entries_project');
        $this->forge->addForeignKey('task_id', 'tasks', 'id', 'SET NULL', 'CASCADE', 'fk_time_entries_task');
        $this->forge->processIndexes('time_entries');

        // Enable RLS
        $this->db->query('ALTER TABLE time_entries ENABLE ROW LEVEL SECURITY');

        // Create RLS policy for agency isolation
        $this->db->query("
            CREATE POLICY time_entries_agency_isolation ON time_entries
            USING (agency_id = NULLIF(current_setting('app.current_agency_id', true), '')::uuid)
        ");
    }

    public function down()
    {
        // Drop RLS policy
        $this->db->query('DROP POLICY IF EXISTS time_entries_agency_isolation ON time_entries');

        // Drop table
        $this->forge->dropTable('time_entries', true);
    }
}
