<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates Milestone 4 tables for Polish & Additional Features.
 *
 * Tables:
 * - email_queue: Email queuing and processing
 * - activity_log: System-wide activity logging
 * - files: File/document management
 * - tickets: Support ticket system
 * - ticket_messages: Ticket conversation messages
 */
class CreateMilestone4Tables extends Migration
{
    public function up(): void
    {
        // ================================================
        // Email Queue Table
        // ================================================
        if (!$this->db->tableExists('email_queue')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'UUID',
                    'null' => false,
                    'default' => new \CodeIgniter\Database\RawSql('gen_random_uuid()'),
                ],
                'agency_id' => [
                    'type' => 'UUID',
                    'null' => true,
                ],
                'to_email' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'to_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                ],
                'from_email' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'from_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                ],
                'reply_to' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                ],
                'cc' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'bcc' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'subject' => [
                    'type' => 'VARCHAR',
                    'constraint' => 500,
                ],
                'body_html' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'body_text' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'template' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                ],
                'template_data' => [
                    'type' => 'JSONB',
                    'null' => true,
                ],
                'attachments' => [
                    'type' => 'JSONB',
                    'null' => true,
                ],
                'priority' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'default' => "'normal'",
                ],
                'status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'default' => "'pending'",
                ],
                'attempts' => [
                    'type' => 'INT',
                    'default' => 0,
                ],
                'max_attempts' => [
                    'type' => 'INT',
                    'default' => 3,
                ],
                'error_message' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'scheduled_at' => [
                    'type' => 'TIMESTAMP',
                    'null' => true,
                ],
                'sent_at' => [
                    'type' => 'TIMESTAMP',
                    'null' => true,
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

            $this->forge->addPrimaryKey('id', 'pk_email_queue');
            $this->forge->createTable('email_queue');

            $this->db->query("CREATE INDEX email_queue_status_idx ON email_queue (status)");
            $this->db->query("CREATE INDEX email_queue_priority_idx ON email_queue (priority)");
            $this->db->query("CREATE INDEX email_queue_scheduled_idx ON email_queue (scheduled_at)");
            $this->db->query("CREATE INDEX email_queue_agency_idx ON email_queue (agency_id)");
        }

        // ================================================
        // Activity Log Table
        // ================================================
        if (!$this->db->tableExists('activity_log')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'UUID',
                    'null' => false,
                    'default' => new \CodeIgniter\Database\RawSql('gen_random_uuid()'),
                ],
                'agency_id' => [
                    'type' => 'UUID',
                    'null' => true,
                ],
                'user_id' => [
                    'type' => 'UUID',
                    'null' => true,
                ],
                'action' => [
                    'type' => 'VARCHAR',
                    'constraint' => 50,
                ],
                'entity_type' => [
                    'type' => 'VARCHAR',
                    'constraint' => 50,
                ],
                'entity_id' => [
                    'type' => 'UUID',
                    'null' => true,
                ],
                'entity_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                ],
                'description' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'old_values' => [
                    'type' => 'JSONB',
                    'null' => true,
                ],
                'new_values' => [
                    'type' => 'JSONB',
                    'null' => true,
                ],
                'metadata' => [
                    'type' => 'JSONB',
                    'null' => true,
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
                    'null' => true,
                ],
            ]);

            $this->forge->addPrimaryKey('id', 'pk_activity_log');
            $this->forge->createTable('activity_log');

            $this->db->query("CREATE INDEX activity_log_agency_idx ON activity_log (agency_id)");
            $this->db->query("CREATE INDEX activity_log_user_idx ON activity_log (user_id)");
            $this->db->query("CREATE INDEX activity_log_entity_idx ON activity_log (entity_type, entity_id)");
            $this->db->query("CREATE INDEX activity_log_action_idx ON activity_log (action)");
            $this->db->query("CREATE INDEX activity_log_created_idx ON activity_log (created_at DESC)");

            // RLS Policy
            $this->db->query('ALTER TABLE activity_log ENABLE ROW LEVEL SECURITY');
            $this->db->query("
                CREATE POLICY activity_log_agency_isolation ON activity_log
                USING (
                    agency_id = current_setting('app.current_agency_id', true)::uuid
                    OR current_setting('app.current_user_role', true) = 'owner'
                )
            ");
        }

        // ================================================
        // Files Table (Document Management)
        // ================================================
        if (!$this->db->tableExists('files')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'UUID',
                    'null' => false,
                    'default' => new \CodeIgniter\Database\RawSql('gen_random_uuid()'),
                ],
                'agency_id' => [
                    'type' => 'UUID',
                    'null' => false,
                ],
                'uploaded_by' => [
                    'type' => 'UUID',
                    'null' => true,
                ],
                'entity_type' => [
                    'type' => 'VARCHAR',
                    'constraint' => 50,
                    'null' => true,
                ],
                'entity_id' => [
                    'type' => 'UUID',
                    'null' => true,
                ],
                'original_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'stored_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'mime_type' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                ],
                'file_size' => [
                    'type' => 'BIGINT',
                ],
                'file_path' => [
                    'type' => 'VARCHAR',
                    'constraint' => 500,
                ],
                'disk' => [
                    'type' => 'VARCHAR',
                    'constraint' => 50,
                    'default' => "'local'",
                ],
                'folder' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                ],
                'description' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'is_public' => [
                    'type' => 'BOOLEAN',
                    'default' => false,
                ],
                'download_count' => [
                    'type' => 'INT',
                    'default' => 0,
                ],
                'metadata' => [
                    'type' => 'JSONB',
                    'null' => true,
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

            $this->forge->addPrimaryKey('id', 'pk_files');
            $this->forge->addForeignKey('agency_id', 'agencies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('files');

            $this->db->query("CREATE INDEX files_agency_idx ON files (agency_id)");
            $this->db->query("CREATE INDEX files_entity_idx ON files (entity_type, entity_id)");
            $this->db->query("CREATE INDEX files_folder_idx ON files (folder)");
            $this->db->query("CREATE INDEX files_mime_idx ON files (mime_type)");

            // RLS Policy
            $this->db->query('ALTER TABLE files ENABLE ROW LEVEL SECURITY');
            $this->db->query("
                CREATE POLICY files_agency_isolation ON files
                USING (
                    agency_id = current_setting('app.current_agency_id', true)::uuid
                    OR current_setting('app.current_user_role', true) = 'owner'
                    OR is_public = true
                )
            ");
        }

        // ================================================
        // Tickets Table (Support System)
        // ================================================
        if (!$this->db->tableExists('tickets')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'UUID',
                    'null' => false,
                    'default' => new \CodeIgniter\Database\RawSql('gen_random_uuid()'),
                ],
                'agency_id' => [
                    'type' => 'UUID',
                    'null' => false,
                ],
                'client_id' => [
                    'type' => 'UUID',
                    'null' => true,
                ],
                'project_id' => [
                    'type' => 'UUID',
                    'null' => true,
                ],
                'created_by' => [
                    'type' => 'UUID',
                    'null' => true,
                ],
                'assigned_to' => [
                    'type' => 'UUID',
                    'null' => true,
                ],
                'ticket_number' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                ],
                'subject' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'description' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'category' => [
                    'type' => 'VARCHAR',
                    'constraint' => 50,
                    'default' => "'general'",
                ],
                'priority' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'default' => "'normal'",
                ],
                'status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'default' => "'open'",
                ],
                'source' => [
                    'type' => 'VARCHAR',
                    'constraint' => 50,
                    'default' => "'web'",
                ],
                'due_date' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'resolved_at' => [
                    'type' => 'TIMESTAMP',
                    'null' => true,
                ],
                'closed_at' => [
                    'type' => 'TIMESTAMP',
                    'null' => true,
                ],
                'first_response_at' => [
                    'type' => 'TIMESTAMP',
                    'null' => true,
                ],
                'last_activity_at' => [
                    'type' => 'TIMESTAMP',
                    'null' => true,
                ],
                'metadata' => [
                    'type' => 'JSONB',
                    'null' => true,
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

            $this->forge->addPrimaryKey('id', 'pk_tickets');
            $this->forge->addForeignKey('agency_id', 'agencies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('client_id', 'clients', 'id', 'SET NULL', 'CASCADE');
            $this->forge->addForeignKey('project_id', 'projects', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('tickets');

            $this->db->query("CREATE UNIQUE INDEX tickets_number_idx ON tickets (ticket_number)");
            $this->db->query("CREATE INDEX tickets_agency_idx ON tickets (agency_id)");
            $this->db->query("CREATE INDEX tickets_client_idx ON tickets (client_id)");
            $this->db->query("CREATE INDEX tickets_status_idx ON tickets (status)");
            $this->db->query("CREATE INDEX tickets_priority_idx ON tickets (priority)");
            $this->db->query("CREATE INDEX tickets_assigned_idx ON tickets (assigned_to)");

            // RLS Policy
            $this->db->query('ALTER TABLE tickets ENABLE ROW LEVEL SECURITY');
            $this->db->query("
                CREATE POLICY tickets_agency_isolation ON tickets
                USING (
                    agency_id = current_setting('app.current_agency_id', true)::uuid
                    OR current_setting('app.current_user_role', true) = 'owner'
                )
            ");
        }

        // ================================================
        // Ticket Messages Table
        // ================================================
        if (!$this->db->tableExists('ticket_messages')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'UUID',
                    'null' => false,
                    'default' => new \CodeIgniter\Database\RawSql('gen_random_uuid()'),
                ],
                'ticket_id' => [
                    'type' => 'UUID',
                    'null' => false,
                ],
                'user_id' => [
                    'type' => 'UUID',
                    'null' => true,
                ],
                'message' => [
                    'type' => 'TEXT',
                ],
                'is_internal' => [
                    'type' => 'BOOLEAN',
                    'default' => false,
                ],
                'is_from_client' => [
                    'type' => 'BOOLEAN',
                    'default' => false,
                ],
                'attachments' => [
                    'type' => 'JSONB',
                    'null' => true,
                ],
                'metadata' => [
                    'type' => 'JSONB',
                    'null' => true,
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

            $this->forge->addPrimaryKey('id', 'pk_ticket_messages');
            $this->forge->addForeignKey('ticket_id', 'tickets', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('ticket_messages');

            $this->db->query("CREATE INDEX ticket_messages_ticket_idx ON ticket_messages (ticket_id)");
            $this->db->query("CREATE INDEX ticket_messages_user_idx ON ticket_messages (user_id)");
            $this->db->query("CREATE INDEX ticket_messages_created_idx ON ticket_messages (created_at)");
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('ticket_messages', true);
        $this->forge->dropTable('tickets', true);
        $this->forge->dropTable('files', true);
        $this->forge->dropTable('activity_log', true);
        $this->forge->dropTable('email_queue', true);
    }
}
