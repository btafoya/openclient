<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDealsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'UUID',
            ],
            'agency_id' => [
                'type' => 'UUID',
            ],
            'pipeline_id' => [
                'type' => 'UUID',
            ],
            'stage_id' => [
                'type' => 'UUID',
            ],
            'client_id' => [
                'type' => 'UUID',
                'null' => true,
            ],
            'contact_id' => [
                'type' => 'UUID',
                'null' => true,
            ],
            'assigned_to' => [
                'type' => 'UUID',
                'null' => true,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'value' => [
                'type' => 'DECIMAL',
                'constraint' => '15,2',
                'default' => 0,
            ],
            'currency' => [
                'type' => 'VARCHAR',
                'constraint' => 3,
                'default' => 'USD',
            ],
            'expected_close_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'actual_close_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'won_reason' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'lost_reason' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'probability' => [
                'type' => 'INTEGER',
                'default' => 0,
            ],
            'source' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'priority' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'medium',
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
        $this->forge->addForeignKey('pipeline_id', 'pipelines', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('stage_id', 'pipeline_stages', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('client_id', 'clients', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('contact_id', 'contacts', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('assigned_to', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('deals');

        // Set default value for UUID
        $this->db->query("ALTER TABLE deals ALTER COLUMN id SET DEFAULT uuid_generate_v4()");

        // Enable RLS
        $this->db->query("ALTER TABLE deals ENABLE ROW LEVEL SECURITY");

        // Create RLS policy
        $this->db->query("
            CREATE POLICY deals_agency_isolation ON deals
            USING (agency_id = NULLIF(current_setting('app.current_agency_id', true), '')::uuid)
        ");

        // Create indexes
        $this->db->query("CREATE INDEX idx_deals_agency ON deals(agency_id)");
        $this->db->query("CREATE INDEX idx_deals_pipeline ON deals(pipeline_id)");
        $this->db->query("CREATE INDEX idx_deals_stage ON deals(stage_id)");
        $this->db->query("CREATE INDEX idx_deals_client ON deals(client_id)");
        $this->db->query("CREATE INDEX idx_deals_assigned ON deals(assigned_to)");
        $this->db->query("CREATE INDEX idx_deals_active ON deals(is_active) WHERE is_active = true");
        $this->db->query("CREATE INDEX idx_deals_expected_close ON deals(expected_close_date)");
    }

    public function down()
    {
        $this->db->query("DROP POLICY IF EXISTS deals_agency_isolation ON deals");
        $this->forge->dropTable('deals', true);
    }
}
