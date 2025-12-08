<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * RBAC Test Data Seeder
 *
 * Creates test accounts for Week 16 Phase 3 manual RBAC testing.
 *
 * Test Accounts Created:
 * - Owner: admin@openclient.test (password: admin123)
 * - Agency A User: agency1@openclient.test (password: agency123)
 * - Agency B User: agency2@openclient.test (password: agency123)
 * - Direct Client: client1@openclient.test (password: client123)
 * - End Client: endclient1@openclient.test (password: endclient123)
 *
 * Usage:
 * php spark db:seed RBACTestSeeder
 */
class RBACTestSeeder extends Seeder
{
    public function run()
    {
        // Clear existing test data
        $this->db->table('users')->where('email LIKE', '%@openclient.test')->delete();
        $this->db->table('agencies')->where('name LIKE', 'Test Agency%')->delete();
        $this->db->table('clients')->where('name LIKE', 'Test Client%')->delete();

        // Create Owner user
        $ownerId = $this->createUser([
            'first_name' => 'System',
            'last_name' => 'Owner',
            'email' => 'admin@openclient.test',
            'password_hash' => password_hash('admin123', PASSWORD_BCRYPT),
            'role' => 'owner',
            'agency_id' => null,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Create Agency A
        $agencyAId = $this->createAgency([
            'name' => 'Test Agency A',
            'created_by' => $ownerId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Create Agency A user
        $agencyAUserId = $this->createUser([
            'first_name' => 'Agency A',
            'last_name' => 'User',
            'email' => 'agency1@openclient.test',
            'password_hash' => password_hash('agency123', PASSWORD_BCRYPT),
            'role' => 'agency',
            'agency_id' => $agencyAId,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Create Agency B
        $agencyBId = $this->createAgency([
            'name' => 'Test Agency B',
            'created_by' => $ownerId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Create Agency B user
        $agencyBUserId = $this->createUser([
            'first_name' => 'Agency B',
            'last_name' => 'User',
            'email' => 'agency2@openclient.test',
            'password_hash' => password_hash('agency123', PASSWORD_BCRYPT),
            'role' => 'agency',
            'agency_id' => $agencyBId,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Create Direct Client for Agency A
        $directClientId = $this->createClient([
            'name' => 'Test Direct Client',
            'client_type' => 'direct',
            'agency_id' => $agencyAId,
            'created_by' => $agencyAUserId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Create Direct Client user
        $directClientUserId = $this->createUser([
            'first_name' => 'Direct Client',
            'last_name' => 'User',
            'email' => 'client1@openclient.test',
            'password_hash' => password_hash('client123', PASSWORD_BCRYPT),
            'role' => 'direct_client',
            'agency_id' => null,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Link Direct Client user to client
        $this->linkUserToClient($directClientUserId, $directClientId, 'admin');

        // Create End Client for Agency A
        $endClientId = $this->createClient([
            'name' => 'Test End Client',
            'client_type' => 'end',
            'agency_id' => $agencyAId,
            'parent_client_id' => $directClientId,
            'created_by' => $agencyAUserId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Create End Client user
        $endClientUserId = $this->createUser([
            'first_name' => 'End Client',
            'last_name' => 'User',
            'email' => 'endclient1@openclient.test',
            'password_hash' => password_hash('endclient123', PASSWORD_BCRYPT),
            'role' => 'end_client',
            'agency_id' => null,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Link End Client user to client
        $this->linkUserToClient($endClientUserId, $endClientId, 'member');

        // Create test project for Agency A
        $projectId = $this->createProject([
            'name' => 'Test Project - Agency A',
            'description' => 'Test project for RBAC validation',
            'agency_id' => $agencyAId,
            'client_id' => $directClientId,
            'status' => 'active',
            'created_by' => $agencyAUserId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Create test invoice for Direct Client
        $invoiceId = $this->createInvoice([
            'invoice_number' => 'INV-TEST-001',
            'client_id' => $directClientId,
            'project_id' => $projectId,
            'agency_id' => $agencyAId,
            'status' => 'draft',
            'subtotal' => 1000.00,
            'tax_amount' => 80.00,
            'total' => 1080.00,
            'currency' => 'USD',
            'issue_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'created_by' => $agencyAUserId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Output summary
        echo "\n✅ RBAC Test Data Seeded Successfully\n\n";
        echo "Test Accounts Created:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Owner:         admin@openclient.test        (password: admin123)\n";
        echo "Agency A User: agency1@openclient.test      (password: agency123)\n";
        echo "Agency B User: agency2@openclient.test      (password: agency123)\n";
        echo "Direct Client: client1@openclient.test      (password: client123)\n";
        echo "End Client:    endclient1@openclient.test   (password: endclient123)\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        echo "Test Data Created:\n";
        echo "- 2 Agencies (Test Agency A, Test Agency B)\n";
        echo "- 3 Clients (Direct Client, End Client, Agency B implicit)\n";
        echo "- 1 Project (Test Project - Agency A)\n";
        echo "- 1 Invoice (INV-TEST-001)\n\n";
        echo "Login URL: http://localhost:8080/auth/login\n\n";
    }

    private function createUser(array $data): string
    {
        $builder = $this->db->table('users');
        $builder->set($data);
        $sql = $builder->getCompiledInsert() . ' RETURNING id';
        $result = $this->db->query($sql);
        return $result->getRow()->id;
    }

    private function createAgency(array $data): string
    {
        $builder = $this->db->table('agencies');
        $builder->set($data);
        $sql = $builder->getCompiledInsert() . ' RETURNING id';
        $result = $this->db->query($sql);
        return $result->getRow()->id;
    }

    private function createClient(array $data): string
    {
        $builder = $this->db->table('clients');
        $builder->set($data);
        $sql = $builder->getCompiledInsert() . ' RETURNING id';
        $result = $this->db->query($sql);
        return $result->getRow()->id;
    }

    private function createProject(array $data): string
    {
        $builder = $this->db->table('projects');
        $builder->set($data);
        $sql = $builder->getCompiledInsert() . ' RETURNING id';
        $result = $this->db->query($sql);
        return $result->getRow()->id;
    }

    private function createInvoice(array $data): string
    {
        $builder = $this->db->table('invoices');
        $builder->set($data);
        $sql = $builder->getCompiledInsert() . ' RETURNING id';
        $result = $this->db->query($sql);
        return $result->getRow()->id;
    }

    private function linkUserToClient(string $userId, string $clientId, string $role): void
    {
        $this->db->table('client_users')->insert([
            'client_id' => $clientId,
            'user_id' => $userId,
            'role' => $role,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
