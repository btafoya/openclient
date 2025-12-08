<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Create Users Table
 *
 * Users belong to agencies and have role-based access control (RBAC).
 * Roles: owner, agency, end_client, direct_client
 *
 * - owner: Platform administrator with full access
 * - agency: Agency staff with multi-client access
 * - direct_client: Client with direct relationship (can see financials)
 * - end_client: End-user client (cannot see financials)
 */
class CreateUsersTable extends Migration
{
    public function up()
    {
        // Create role enum type
        $this->db->query("
            DO $$ BEGIN
                CREATE TYPE user_role AS ENUM ('owner', 'agency', 'end_client', 'direct_client');
            EXCEPTION
                WHEN duplicate_object THEN null;
            END $$;
        ");

        $this->forge->addField([
            'id' => [
                'type' => 'UUID',
                'null' => false,
                'default' => 'uuid_generate_v4()',
            ],
            'agency_id' => [
                'type' => 'UUID',
                'null' => true,
                'comment' => 'NULL for owner role, required for others',
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => false,
                'unique' => true,
            ],
            'password_hash' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => false,
            ],
            'role' => [
                'type' => 'user_role',
                'null' => false,
                'default' => 'end_client',
            ],
            'first_name' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => false,
            ],
            'last_name' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => false,
            ],
            'phone' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ],
            'avatar' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'is_active' => [
                'type' => 'BOOLEAN',
                'null' => false,
                'default' => true,
            ],
            'failed_login_attempts' => [
                'type' => 'INT',
                'null' => false,
                'default' => 0,
            ],
            'locked_until' => [
                'type' => 'TIMESTAMP',
                'null' => true,
                'comment' => 'Account locked after 5 failed attempts for 15 minutes',
            ],
            'last_login_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'last_login_ip' => [
                'type' => 'VARCHAR',
                'constraint' => 45,
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
            ],
            'deleted_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('agency_id');
        $this->forge->addKey('email');
        $this->forge->addKey('role');
        $this->forge->addForeignKey('agency_id', 'agencies', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('users', true);

        // Create updated_at trigger
        $this->db->query("
            CREATE TRIGGER update_users_updated_at BEFORE UPDATE
            ON users FOR EACH ROW
            EXECUTE FUNCTION update_updated_at_column();
        ");

        // Create constraint: owner role must have null agency_id
        $this->db->query("
            ALTER TABLE users ADD CONSTRAINT check_owner_no_agency
            CHECK (role != 'owner' OR agency_id IS NULL);
        ");

        // Create constraint: non-owner roles must have agency_id
        $this->db->query("
            ALTER TABLE users ADD CONSTRAINT check_non_owner_has_agency
            CHECK (role = 'owner' OR agency_id IS NOT NULL);
        ");
    }

    public function down()
    {
        $this->db->query('DROP TRIGGER IF EXISTS update_users_updated_at ON users');
        $this->forge->dropTable('users', true);
        $this->db->query('DROP TYPE IF EXISTS user_role');
    }
}
