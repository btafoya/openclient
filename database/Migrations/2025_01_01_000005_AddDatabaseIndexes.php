<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Add Database Indexes
 *
 * Creates performance indexes for common query patterns.
 * Improves query performance for user lookups, role filtering, and agency isolation.
 */
class AddDatabaseIndexes extends Migration
{
    public function up()
    {
        // Users table indexes (additional to keys already created)
        $this->db->query('CREATE INDEX idx_users_agency_role ON users(agency_id, role)');
        $this->db->query('CREATE INDEX idx_users_is_active ON users(is_active)');
        $this->db->query('CREATE INDEX idx_users_created_at ON users(created_at)');
        $this->db->query('CREATE INDEX idx_users_deleted_at ON users(deleted_at) WHERE deleted_at IS NULL');

        // Agencies table indexes
        $this->db->query('CREATE INDEX idx_agencies_name ON agencies(name)');
        $this->db->query('CREATE INDEX idx_agencies_deleted_at ON agencies(deleted_at) WHERE deleted_at IS NULL');

        // Sessions table - timestamp already has key, add composite for cleanup
        $this->db->query('CREATE INDEX idx_sessions_timestamp_ip ON ci_sessions(timestamp, ip_address)');

        // Webhook events table indexes (additional to keys already created)
        $this->db->query('CREATE INDEX idx_webhook_gateway_type ON webhook_events(gateway, event_type)');
        $this->db->query('CREATE INDEX idx_webhook_unprocessed ON webhook_events(is_processed, created_at) WHERE is_processed = false');
    }

    public function down()
    {
        // Drop indexes
        $this->db->query('DROP INDEX IF EXISTS idx_users_agency_role');
        $this->db->query('DROP INDEX IF EXISTS idx_users_is_active');
        $this->db->query('DROP INDEX IF EXISTS idx_users_created_at');
        $this->db->query('DROP INDEX IF EXISTS idx_users_deleted_at');

        $this->db->query('DROP INDEX IF EXISTS idx_agencies_name');
        $this->db->query('DROP INDEX IF EXISTS idx_agencies_deleted_at');

        $this->db->query('DROP INDEX IF EXISTS idx_sessions_timestamp_ip');

        $this->db->query('DROP INDEX IF EXISTS idx_webhook_gateway_type');
        $this->db->query('DROP INDEX IF EXISTS idx_webhook_unprocessed');
    }
}
