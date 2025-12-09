<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'UUID',
                'null' => false,
            ],
            'agency_id' => [
                'type' => 'UUID',
                'null' => true,
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'unique' => true,
            ],
            'password_hash' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'role' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'comment' => 'owner, agency, direct_client, end_client',
            ],
            'first_name' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
            ],
            'last_name' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
            ],
            'is_active' => [
                'type' => 'BOOLEAN',
                'default' => true,
            ],
            'failed_login_attempts' => [
                'type' => 'INT',
                'default' => 0,
            ],
            'locked_until' => [
                'type' => 'TIMESTAMP',
                'null' => true,
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

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('email');
        $this->forge->addKey('agency_id');
        $this->forge->addKey('role');
        $this->forge->addKey(['deleted_at']);

        $this->forge->createTable('users');

        // Enable Row-Level Security
        $this->db->query('ALTER TABLE users ENABLE ROW LEVEL SECURITY');

        // Create RLS policy for agency isolation
        $this->db->query("
            CREATE POLICY agency_isolation_users ON users
            USING (
                agency_id = current_setting('app.current_agency_id', true)::uuid
                OR current_setting('app.current_user_role', true) = 'owner'
                OR id = current_setting('app.current_user_id', true)::uuid
            )
        ");
    }

    public function down()
    {
        $this->forge->dropTable('users');
    }
}
