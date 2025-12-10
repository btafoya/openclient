<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates client portal access tables.
 * Part of Milestone 3 - Expansion Features.
 */
class CreateClientPortalTables extends Migration
{
    public function up(): void
    {
        // Portal Access Tokens table
        $this->forge->addField([
            'id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
            ],
            'client_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
            ],
            'contact_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => true,
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'token' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
            ],
            'token_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'access',
            ],
            'permissions' => [
                'type' => 'JSONB',
                'null' => true,
            ],
            'expires_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'last_used_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
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
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('client_id');
        $this->forge->addKey('contact_id');
        $this->forge->addUniqueKey('token');
        $this->forge->addKey('email');
        $this->forge->addKey('is_active');
        $this->forge->createTable('portal_access_tokens');

        // Portal Sessions table
        $this->forge->addField([
            'id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
            ],
            'access_token_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
            ],
            'session_token' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
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
            'expires_at' => [
                'type' => 'TIMESTAMP',
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('access_token_id');
        $this->forge->addUniqueKey('session_token');
        $this->forge->addKey('expires_at');
        $this->forge->createTable('portal_sessions');

        // Portal Activity Log
        $this->forge->addField([
            'id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
            ],
            'client_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
            ],
            'access_token_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => true,
            ],
            'action' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
            ],
            'resource_type' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ],
            'resource_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => true,
            ],
            'details' => [
                'type' => 'JSONB',
                'null' => true,
            ],
            'ip_address' => [
                'type' => 'VARCHAR',
                'constraint' => 45,
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('client_id');
        $this->forge->addKey('access_token_id');
        $this->forge->addKey('action');
        $this->forge->addKey('created_at');
        $this->forge->createTable('portal_activity_log');
    }

    public function down(): void
    {
        $this->forge->dropTable('portal_activity_log', true);
        $this->forge->dropTable('portal_sessions', true);
        $this->forge->dropTable('portal_access_tokens', true);
    }
}
