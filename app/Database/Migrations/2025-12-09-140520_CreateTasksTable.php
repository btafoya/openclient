<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTasksTable extends Migration
{
    public function up()
    {
        // Create tasks table
        $this->forge->addField([
            'id' => [
                'type' => 'UUID',
                'null' => false,
            ],
            'agency_id' => [
                'type' => 'UUID',
                'null' => false,
            ],
            'project_id' => [
                'type' => 'UUID',
                'null' => false,
            ],
            'assigned_to' => [
                'type' => 'UUID',
                'null' => true,
            ],
            'title' => [
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
                'default' => 'todo',
                'comment' => 'todo, in_progress, completed, blocked',
            ],
            'priority' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'medium',
                'comment' => 'low, medium, high, urgent',
            ],
            'due_date' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'estimated_hours' => [
                'type' => 'DECIMAL',
                'constraint' => '6,2',
                'null' => true,
            ],
            'actual_hours' => [
                'type' => 'DECIMAL',
                'constraint' => '6,2',
                'null' => true,
            ],
            'sort_order' => [
                'type' => 'INT',
                'default' => 0,
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
        $this->forge->addKey('project_id');
        $this->forge->addKey('assigned_to');
        $this->forge->addKey('status');
        $this->forge->addKey('priority');
        $this->forge->addKey('is_active');
        $this->forge->addKey('deleted_at');
        $this->forge->addKey('sort_order');

        $this->forge->createTable('tasks');

        // Add foreign keys
        $this->forge->addForeignKey('agency_id', 'agencies', 'id', 'CASCADE', 'CASCADE', 'fk_tasks_agency');
        $this->forge->addForeignKey('project_id', 'projects', 'id', 'CASCADE', 'CASCADE', 'fk_tasks_project');
        $this->forge->addForeignKey('assigned_to', 'users', 'id', 'SET NULL', 'CASCADE', 'fk_tasks_user');
        $this->forge->processIndexes('tasks');

        // Enable RLS
        $this->db->query('ALTER TABLE tasks ENABLE ROW LEVEL SECURITY');

        // Create RLS policy for agency isolation
        $this->db->query("
            CREATE POLICY tasks_agency_isolation ON tasks
            USING (agency_id = NULLIF(current_setting('app.current_agency_id', true), '')::uuid)
        ");
    }

    public function down()
    {
        // Drop RLS policy
        $this->db->query('DROP POLICY IF EXISTS tasks_agency_isolation ON tasks');

        // Drop table
        $this->forge->dropTable('tasks', true);
    }
}
