<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePipelineStagesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'UUID',
            ],
            'pipeline_id' => [
                'type' => 'UUID',
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'color' => [
                'type' => 'VARCHAR',
                'constraint' => 7,
                'default' => '#6366f1',
            ],
            'probability' => [
                'type' => 'INTEGER',
                'default' => 0,
            ],
            'sort_order' => [
                'type' => 'INTEGER',
                'default' => 0,
            ],
            'is_won' => [
                'type' => 'BOOLEAN',
                'default' => false,
            ],
            'is_lost' => [
                'type' => 'BOOLEAN',
                'default' => false,
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
        $this->forge->addForeignKey('pipeline_id', 'pipelines', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('pipeline_stages');

        // Set default value for UUID
        $this->db->query("ALTER TABLE pipeline_stages ALTER COLUMN id SET DEFAULT uuid_generate_v4()");

        // Create indexes
        $this->db->query("CREATE INDEX idx_pipeline_stages_pipeline ON pipeline_stages(pipeline_id)");
        $this->db->query("CREATE INDEX idx_pipeline_stages_sort ON pipeline_stages(pipeline_id, sort_order)");
    }

    public function down()
    {
        $this->forge->dropTable('pipeline_stages', true);
    }
}
