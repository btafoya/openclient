<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateClientUsersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'UUID',
                'default' => new \CodeIgniter\Database\RawSql('gen_random_uuid()'),
            ],
            'client_id' => [
                'type' => 'UUID',
            ],
            'user_id' => [
                'type' => 'UUID',
            ],
            'role' => [
                'type' => 'VARCHAR',
                'constraint' => '50',
                'default' => 'member',
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
        $this->forge->addForeignKey('client_id', 'clients', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addUniqueKey(['client_id', 'user_id']);
        $this->forge->createTable('client_users');
    }

    public function down()
    {
        $this->forge->dropTable('client_users');
    }
}
