<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates proposal-related tables for sales proposal management.
 * Part of Milestone 3 - Expansion Features.
 */
class CreateProposalTables extends Migration
{
    public function up(): void
    {
        // Proposal Templates table
        $this->forge->addField([
            'id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
            ],
            'agency_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'content' => [
                'type' => 'JSONB',
                'null' => true,
            ],
            'default_sections' => [
                'type' => 'JSONB',
                'null' => true,
            ],
            'default_terms' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'is_active' => [
                'type' => 'BOOLEAN',
                'default' => true,
            ],
            'created_by' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
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
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('agency_id');
        $this->forge->addKey('is_active');
        $this->forge->createTable('proposal_templates');

        // Proposals table
        $this->forge->addField([
            'id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
            ],
            'agency_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
            ],
            'client_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
            ],
            'deal_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => true,
            ],
            'template_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => true,
            ],
            'proposal_number' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
            ],
            'title' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'introduction' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'conclusion' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'terms_conditions' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'subtotal' => [
                'type' => 'DECIMAL',
                'constraint' => '15,2',
                'default' => 0,
            ],
            'discount_percent' => [
                'type' => 'DECIMAL',
                'constraint' => '5,2',
                'default' => 0,
            ],
            'discount_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '15,2',
                'default' => 0,
            ],
            'tax_rate' => [
                'type' => 'DECIMAL',
                'constraint' => '5,2',
                'default' => 0,
            ],
            'tax_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '15,2',
                'default' => 0,
            ],
            'total_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '15,2',
                'default' => 0,
            ],
            'currency' => [
                'type' => 'VARCHAR',
                'constraint' => 3,
                'default' => 'USD',
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'draft',
            ],
            'valid_until' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'sent_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'viewed_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'accepted_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'rejected_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'rejection_reason' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'signature_data' => [
                'type' => 'JSONB',
                'null' => true,
            ],
            'signed_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'signed_email' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'signed_ip' => [
                'type' => 'VARCHAR',
                'constraint' => 45,
                'null' => true,
            ],
            'signed_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'access_token' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'null' => true,
            ],
            'converted_to_invoice_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => true,
            ],
            'metadata' => [
                'type' => 'JSONB',
                'null' => true,
            ],
            'created_by' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
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
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('agency_id');
        $this->forge->addKey('client_id');
        $this->forge->addKey('deal_id');
        $this->forge->addKey('status');
        $this->forge->addUniqueKey(['agency_id', 'proposal_number']);
        $this->forge->addKey('access_token');
        $this->forge->createTable('proposals');

        // Proposal Sections table
        $this->forge->addField([
            'id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
            ],
            'proposal_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
            ],
            'title' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'content' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'quantity' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'default' => 1,
            ],
            'unit_price' => [
                'type' => 'DECIMAL',
                'constraint' => '15,2',
                'default' => 0,
            ],
            'total_price' => [
                'type' => 'DECIMAL',
                'constraint' => '15,2',
                'default' => 0,
            ],
            'is_optional' => [
                'type' => 'BOOLEAN',
                'default' => false,
            ],
            'is_selected' => [
                'type' => 'BOOLEAN',
                'default' => true,
            ],
            'sort_order' => [
                'type' => 'INT',
                'default' => 0,
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
        $this->forge->addKey('proposal_id');
        $this->forge->addKey('sort_order');
        $this->forge->createTable('proposal_sections');

        // Add RLS policies for proposals
        $this->db->query("ALTER TABLE proposal_templates ENABLE ROW LEVEL SECURITY");
        $this->db->query("
            CREATE POLICY proposal_templates_agency_isolation ON proposal_templates
            FOR ALL
            USING (agency_id = current_setting('app.current_agency_id', true)::varchar)
        ");

        $this->db->query("ALTER TABLE proposals ENABLE ROW LEVEL SECURITY");
        $this->db->query("
            CREATE POLICY proposals_agency_isolation ON proposals
            FOR ALL
            USING (agency_id = current_setting('app.current_agency_id', true)::varchar)
        ");
    }

    public function down(): void
    {
        // Drop RLS policies
        $this->db->query("DROP POLICY IF EXISTS proposals_agency_isolation ON proposals");
        $this->db->query("DROP POLICY IF EXISTS proposal_templates_agency_isolation ON proposal_templates");

        // Drop tables
        $this->forge->dropTable('proposal_sections', true);
        $this->forge->dropTable('proposals', true);
        $this->forge->dropTable('proposal_templates', true);
    }
}
