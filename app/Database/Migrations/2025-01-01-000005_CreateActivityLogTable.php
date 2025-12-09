<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateActivityLogTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'UUID',
                'null' => false,
            ],
            'user_id' => [
                'type' => 'UUID',
                'null' => true,
            ],
            'agency_id' => [
                'type' => 'UUID',
                'null' => true,
            ],
            'action' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'comment' => 'created, updated, deleted, viewed, etc.',
            ],
            'resource_type' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'comment' => 'client, project, invoice, user, etc.',
            ],
            'resource_id' => [
                'type' => 'UUID',
                'null' => true,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'metadata' => [
                'type' => 'JSON',
                'null' => true,
                'comment' => 'Additional context: changed fields, old/new values',
            ],
            'ip_address' => [
                'type' => 'VARCHAR',
                'constraint' => 45,
                'null' => true,
            ],
            'user_agent' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('agency_id');
        $this->forge->addKey('resource_type');
        $this->forge->addKey('resource_id');
        $this->forge->addKey('created_at');

        $this->forge->createTable('activity_log');

        // Enable Row-Level Security for agency isolation
        $this->db->query('ALTER TABLE activity_log ENABLE ROW LEVEL SECURITY');

        // Create RLS policy
        $this->db->query("
            CREATE POLICY agency_isolation_activity_log ON activity_log
            USING (
                agency_id = current_setting('app.current_agency_id', true)::uuid
                OR current_setting('app.current_user_role', true) = 'owner'
            )
        ");
    }

    public function down()
    {
        $this->forge->dropTable('activity_log');
    }
}
