<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTimelineTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'UUID',
                'default' => new \CodeIgniter\Database\RawSql('uuid_generate_v4()'),
            ],
            'agency_id' => [
                'type' => 'UUID',
                'null' => false,
            ],
            'user_id' => [
                'type' => 'UUID',
                'null' => false,
                'comment' => 'User who triggered this event',
            ],
            'entity_type' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => false,
                'comment' => 'Type of entity: client, contact, project, note',
            ],
            'entity_id' => [
                'type' => 'UUID',
                'null' => false,
                'comment' => 'ID of the related entity',
            ],
            'event_type' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => false,
                'comment' => 'Event type: created, updated, deleted, restored, status_changed, pinned, unpinned',
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => false,
                'comment' => 'Human-readable description of the event',
            ],
            'metadata' => [
                'type' => 'JSONB',
                'null' => true,
                'comment' => 'Additional event data (changes, old/new values, etc.)',
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
                'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
            'deleted_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
                'comment' => 'Soft delete timestamp (rarely used, for GDPR compliance)',
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->createTable('timeline');

        // Foreign key constraints
        $this->db->query('
            ALTER TABLE timeline
            ADD CONSTRAINT fk_timeline_agency
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE
        ');

        $this->db->query('
            ALTER TABLE timeline
            ADD CONSTRAINT fk_timeline_user
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ');

        // Performance indexes
        $this->db->query('CREATE INDEX idx_timeline_agency_id ON timeline(agency_id)');
        $this->db->query('CREATE INDEX idx_timeline_user_id ON timeline(user_id)');
        $this->db->query('CREATE INDEX idx_timeline_entity ON timeline(entity_type, entity_id)');
        $this->db->query('CREATE INDEX idx_timeline_event_type ON timeline(event_type)');
        $this->db->query('CREATE INDEX idx_timeline_created_at ON timeline(created_at DESC)');
        $this->db->query('CREATE INDEX idx_timeline_deleted_at ON timeline(deleted_at) WHERE deleted_at IS NULL');

        // Composite index for common queries (entity timeline)
        $this->db->query('CREATE INDEX idx_timeline_entity_created ON timeline(entity_type, entity_id, created_at DESC) WHERE deleted_at IS NULL');

        // Row-Level Security (RLS) policy for multi-agency isolation
        $this->db->query('ALTER TABLE timeline ENABLE ROW LEVEL SECURITY');
        $this->db->query("
            CREATE POLICY timeline_agency_isolation ON timeline
            USING (agency_id = current_setting('app.current_agency_id')::uuid)
        ");
    }

    public function down()
    {
        $this->forge->dropTable('timeline');
    }
}
