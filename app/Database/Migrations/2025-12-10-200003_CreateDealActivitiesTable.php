<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDealActivitiesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'UUID',
            ],
            'deal_id' => [
                'type' => 'UUID',
            ],
            'user_id' => [
                'type' => 'UUID',
            ],
            'activity_type' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
            ],
            'subject' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'scheduled_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'completed_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'metadata' => [
                'type' => 'JSONB',
                'default' => '{}',
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
        $this->forge->addForeignKey('deal_id', 'deals', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('deal_activities');

        // Set default value for UUID
        $this->db->query("ALTER TABLE deal_activities ALTER COLUMN id SET DEFAULT uuid_generate_v4()");

        // Create indexes
        $this->db->query("CREATE INDEX idx_deal_activities_deal ON deal_activities(deal_id)");
        $this->db->query("CREATE INDEX idx_deal_activities_user ON deal_activities(user_id)");
        $this->db->query("CREATE INDEX idx_deal_activities_type ON deal_activities(activity_type)");
        $this->db->query("CREATE INDEX idx_deal_activities_scheduled ON deal_activities(scheduled_at) WHERE scheduled_at IS NOT NULL");
    }

    public function down()
    {
        $this->forge->dropTable('deal_activities', true);
    }
}
