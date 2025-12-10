<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePipelinesTable extends Migration
{
    public function up()
    {
        // Pipelines table
        $this->forge->addField([
            'id' => [
                'type' => 'UUID',
            ],
            'agency_id' => [
                'type' => 'UUID',
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'is_default' => [
                'type' => 'BOOLEAN',
                'default' => false,
            ],
            'sort_order' => [
                'type' => 'INTEGER',
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

        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('agency_id', 'agencies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('pipelines');

        // Set default value for UUID
        $this->db->query("ALTER TABLE pipelines ALTER COLUMN id SET DEFAULT uuid_generate_v4()");

        // Enable RLS
        $this->db->query("ALTER TABLE pipelines ENABLE ROW LEVEL SECURITY");

        // Create RLS policy
        $this->db->query("
            CREATE POLICY pipelines_agency_isolation ON pipelines
            USING (agency_id = NULLIF(current_setting('app.current_agency_id', true), '')::uuid)
        ");

        // Create indexes
        $this->db->query("CREATE INDEX idx_pipelines_agency ON pipelines(agency_id)");
        $this->db->query("CREATE INDEX idx_pipelines_active ON pipelines(is_active) WHERE is_active = true");
    }

    public function down()
    {
        $this->db->query("DROP POLICY IF EXISTS pipelines_agency_isolation ON pipelines");
        $this->forge->dropTable('pipelines', true);
    }
}
