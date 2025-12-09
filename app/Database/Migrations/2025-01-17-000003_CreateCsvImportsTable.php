<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * CSV Imports Migration
 *
 * Tracks CSV import operations with validation results and error logs.
 * Supports batch processing and rollback capabilities.
 *
 * RBAC: Enforces agency isolation via PostgreSQL RLS
 */
class CreateCsvImportsTable extends Migration
{
    public function up()
    {
        $this->db->query('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');

        $this->forge->addField([
            'id' => [
                'type' => 'UUID',
                'default' => new \CodeIgniter\Database\RawSql('uuid_generate_v4()'),
            ],
            'agency_id' => [
                'type' => 'UUID',
                'null' => false,
                'comment' => 'Agency that owns this import',
            ],
            'user_id' => [
                'type' => 'UUID',
                'null' => false,
                'comment' => 'User who initiated the import',
            ],
            'entity_type' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => false,
                'comment' => 'Type of entities being imported: clients, contacts, notes',
            ],
            'filename' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => false,
                'comment' => 'Original filename of uploaded CSV',
            ],
            'file_path' => [
                'type' => 'VARCHAR',
                'constraint' => 500,
                'null' => false,
                'comment' => 'Server path to uploaded CSV file',
            ],
            'file_size' => [
                'type' => 'INTEGER',
                'null' => false,
                'comment' => 'File size in bytes',
            ],
            'total_rows' => [
                'type' => 'INTEGER',
                'null' => false,
                'default' => 0,
                'comment' => 'Total rows in CSV (excluding header)',
            ],
            'processed_rows' => [
                'type' => 'INTEGER',
                'null' => false,
                'default' => 0,
                'comment' => 'Number of rows successfully processed',
            ],
            'failed_rows' => [
                'type' => 'INTEGER',
                'null' => false,
                'default' => 0,
                'comment' => 'Number of rows that failed validation',
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => false,
                'default' => 'pending',
                'comment' => 'Import status: pending, processing, completed, failed, cancelled',
            ],
            'field_mapping' => [
                'type' => 'JSONB',
                'null' => true,
                'comment' => 'CSV column to database field mapping',
            ],
            'validation_errors' => [
                'type' => 'JSONB',
                'null' => true,
                'comment' => 'Validation errors by row number',
            ],
            'import_options' => [
                'type' => 'JSONB',
                'null' => true,
                'comment' => 'Import options: skip_duplicates, update_existing, etc.',
            ],
            'started_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
                'comment' => 'When import processing started',
            ],
            'completed_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
                'comment' => 'When import processing completed',
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
                'comment' => 'Soft delete timestamp for GDPR compliance',
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('agency_id', 'agencies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('csv_imports');

        // Performance indexes
        $this->db->query('CREATE INDEX idx_csv_imports_agency ON csv_imports(agency_id) WHERE deleted_at IS NULL');
        $this->db->query('CREATE INDEX idx_csv_imports_user ON csv_imports(user_id) WHERE deleted_at IS NULL');
        $this->db->query('CREATE INDEX idx_csv_imports_entity_type ON csv_imports(entity_type) WHERE deleted_at IS NULL');
        $this->db->query('CREATE INDEX idx_csv_imports_status ON csv_imports(status) WHERE deleted_at IS NULL');
        $this->db->query('CREATE INDEX idx_csv_imports_created ON csv_imports(created_at DESC) WHERE deleted_at IS NULL');

        // Row-Level Security (RLS)
        $this->db->query('ALTER TABLE csv_imports ENABLE ROW LEVEL SECURITY');

        // Policy: Users can only see imports from their own agency
        $this->db->query("
            CREATE POLICY csv_imports_agency_isolation ON csv_imports
            USING (agency_id = current_setting('app.current_agency_id')::uuid)
        ");

        // Trigger for updated_at
        $this->db->query("
            CREATE OR REPLACE FUNCTION update_csv_imports_updated_at()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        $this->db->query("
            CREATE TRIGGER csv_imports_updated_at_trigger
            BEFORE UPDATE ON csv_imports
            FOR EACH ROW
            EXECUTE FUNCTION update_csv_imports_updated_at();
        ");
    }

    public function down()
    {
        $this->db->query('DROP TRIGGER IF EXISTS csv_imports_updated_at_trigger ON csv_imports');
        $this->db->query('DROP FUNCTION IF EXISTS update_csv_imports_updated_at()');
        $this->forge->dropTable('csv_imports', true);
    }
}
