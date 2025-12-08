<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Create Sessions Table
 *
 * Database-backed session storage for CodeIgniter 4.
 * Sessions expire after 30 minutes of inactivity (configured in .env).
 */
class CreateSessionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'VARCHAR',
                'constraint' => 128,
                'null' => false,
            ],
            'ip_address' => [
                'type' => 'VARCHAR',
                'constraint' => 45,
                'null' => false,
            ],
            'timestamp' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'null' => false,
                'default' => 0,
            ],
            'data' => [
                'type' => 'TEXT',
                'null' => false,
                'default' => '',
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('timestamp');
        $this->forge->createTable('ci_sessions', true);
    }

    public function down()
    {
        $this->forge->dropTable('ci_sessions', true);
    }
}
