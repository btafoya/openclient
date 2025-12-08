<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Create Clients Table
 *
 * First tenant-scoped table with Row-Level Security (RLS) implementation.
 * Demonstrates the pattern that all future tenant-scoped tables will follow.
 *
 * RLS ensures that:
 * - Agency users can only see/modify clients belonging to their agency
 * - Owner users can see/modify all clients across all agencies
 * - SQL injection cannot bypass these restrictions
 */
class CreateClientsTable extends Migration
{
    public function up()
    {
        // Create clients table
        $this->forge->addField([
            'id' => [
                'type' => 'UUID',
                'null' => false,
            ],
            'agency_id' => [
                'type' => 'UUID',
                'null' => false,
                'comment' => 'REQUIRED for RLS - links client to owning agency',
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => false,
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'phone' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ],
            'company' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'address' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'city' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'state' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ],
            'postal_code' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
            ],
            'country' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
                'default' => 'United States',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'is_active' => [
                'type' => 'BOOLEAN',
                'default' => true,
                'null' => false,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
            ],
            'deleted_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);

        // Set primary key
        $this->forge->addPrimaryKey('id');

        // Add foreign key to agencies table
        $this->forge->addForeignKey('agency_id', 'agencies', 'id', 'CASCADE', 'CASCADE');

        // Create the table
        $this->forge->createTable('clients');

        // Set default UUID generation for id column
        $this->db->query("
            ALTER TABLE clients
            ALTER COLUMN id SET DEFAULT uuid_generate_v4()
        ");

        // Set default timestamps
        $this->db->query("
            ALTER TABLE clients
            ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP
        ");

        $this->db->query("
            ALTER TABLE clients
            ALTER COLUMN updated_at SET DEFAULT CURRENT_TIMESTAMP
        ");

        // Create updated_at trigger
        $this->db->query("
            CREATE TRIGGER set_timestamp_clients
            BEFORE UPDATE ON clients
            FOR EACH ROW
            EXECUTE FUNCTION trigger_set_timestamp()
        ");

        // Create performance indexes
        // CRITICAL: agency_id index required for RLS performance
        $this->db->query("CREATE INDEX idx_clients_agency_id ON clients(agency_id)");
        $this->db->query("CREATE INDEX idx_clients_email ON clients(email)");
        $this->db->query("CREATE INDEX idx_clients_name ON clients(name)");
        $this->db->query("CREATE INDEX idx_clients_active ON clients(is_active)");

        // Enable Row-Level Security on clients table
        $this->db->query("ALTER TABLE clients ENABLE ROW LEVEL SECURITY");

        // Force RLS even for table owner (CRITICAL for security)
        $this->db->query("ALTER TABLE clients FORCE ROW LEVEL SECURITY");

        // Create RLS policy for multi-agency isolation
        // This policy ensures that:
        // 1. Agency users only see clients where agency_id matches their session variable
        // 2. Owner users bypass the filter and see all clients
        $this->db->query("
            CREATE POLICY agency_isolation_clients ON clients
            USING (
                agency_id = current_setting('app.current_agency_id', true)::uuid
                OR current_setting('app.current_user_role', true) = 'owner'
            )
        ");

        // Create policy for INSERT operations
        // Ensures new clients are created with the correct agency_id
        $this->db->query("
            CREATE POLICY agency_isolation_clients_insert ON clients
            FOR INSERT
            WITH CHECK (
                agency_id = current_setting('app.current_agency_id', true)::uuid
                OR current_setting('app.current_user_role', true) = 'owner'
            )
        ");

        // Create policy for UPDATE operations
        // Ensures clients can only be updated by their owning agency or owner
        $this->db->query("
            CREATE POLICY agency_isolation_clients_update ON clients
            FOR UPDATE
            USING (
                agency_id = current_setting('app.current_agency_id', true)::uuid
                OR current_setting('app.current_user_role', true) = 'owner'
            )
        ");

        // Create policy for DELETE operations
        // Ensures clients can only be deleted by their owning agency or owner
        $this->db->query("
            CREATE POLICY agency_isolation_clients_delete ON clients
            FOR DELETE
            USING (
                agency_id = current_setting('app.current_agency_id', true)::uuid
                OR current_setting('app.current_user_role', true) = 'owner'
            )
        ");
    }

    public function down()
    {
        // Drop RLS policies first
        $this->db->query("DROP POLICY IF EXISTS agency_isolation_clients ON clients");
        $this->db->query("DROP POLICY IF EXISTS agency_isolation_clients_insert ON clients");
        $this->db->query("DROP POLICY IF EXISTS agency_isolation_clients_update ON clients");
        $this->db->query("DROP POLICY IF EXISTS agency_isolation_clients_delete ON clients");

        // Drop the table (CASCADE will drop indexes and constraints)
        $this->forge->dropTable('clients', true);
    }
}
