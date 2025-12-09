<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNotesTable extends Migration
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
                'comment' => 'Agency this note belongs to (for RLS)',
            ],
            'user_id' => [
                'type' => 'UUID',
                'null' => false,
                'comment' => 'User who created the note',
            ],
            // Polymorphic relationships - exactly one must be non-null
            'client_id' => [
                'type' => 'UUID',
                'null' => true,
                'comment' => 'Associated client (if note is for client)',
            ],
            'contact_id' => [
                'type' => 'UUID',
                'null' => true,
                'comment' => 'Associated contact (if note is for contact)',
            ],
            'project_id' => [
                'type' => 'UUID',
                'null' => true,
                'comment' => 'Associated project (if note is for project)',
            ],
            'subject' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true,
                'comment' => 'Optional note subject/title',
            ],
            'content' => [
                'type' => 'TEXT',
                'null' => false,
                'comment' => 'Note content (required)',
            ],
            'is_pinned' => [
                'type' => 'BOOLEAN',
                'default' => false,
                'null' => false,
                'comment' => 'Whether this note is pinned to top',
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
        $this->forge->addForeignKey('agency_id', 'agencies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('client_id', 'clients', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('contact_id', 'contacts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('project_id', 'projects', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('notes');

        // Add CHECK constraint to ensure exactly one entity FK is set
        $this->db->query("
            ALTER TABLE notes
            ADD CONSTRAINT notes_entity_check
            CHECK (
                (client_id IS NOT NULL)::int +
                (contact_id IS NOT NULL)::int +
                (project_id IS NOT NULL)::int = 1
            )
        ");

        // Performance indexes
        $this->db->query('CREATE INDEX idx_notes_agency_id ON notes(agency_id)');
        $this->db->query('CREATE INDEX idx_notes_user_id ON notes(user_id)');
        $this->db->query('CREATE INDEX idx_notes_client_id ON notes(client_id) WHERE client_id IS NOT NULL');
        $this->db->query('CREATE INDEX idx_notes_contact_id ON notes(contact_id) WHERE contact_id IS NOT NULL');
        $this->db->query('CREATE INDEX idx_notes_project_id ON notes(project_id) WHERE project_id IS NOT NULL');
        $this->db->query('CREATE INDEX idx_notes_is_pinned ON notes(is_pinned) WHERE is_pinned = true');
        $this->db->query('CREATE INDEX idx_notes_created_at ON notes(created_at DESC)');
        $this->db->query('CREATE INDEX idx_notes_deleted_at ON notes(deleted_at) WHERE deleted_at IS NULL');

        // Enable Row Level Security
        $this->db->query('ALTER TABLE notes ENABLE ROW LEVEL SECURITY');

        // Create RLS policy for agency isolation
        $this->db->query("
            CREATE POLICY notes_agency_isolation ON notes FOR ALL
            USING (
                agency_id = NULLIF(current_setting('app.current_agency_id', true), '')::uuid
                OR current_setting('app.current_agency_id', true) = ''
            )
        ");
    }

    public function down()
    {
        $this->forge->dropTable('notes', true);
    }
}
