<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Enable PostgreSQL Row-Level Security (RLS)
 *
 * Implements database-level multi-agency data isolation.
 * This is the FIRST layer of our 4-layer RBAC security:
 *
 * 1. Database RLS (this migration) - Hard database-level enforcement
 * 2. HTTP Middleware (Week 9-10) - Route-level authorization
 * 3. Service Guards (Week 11-12) - Business logic authorization
 * 4. Frontend Permissions (Week 13) - UX-only visibility control
 *
 * RLS Policy Logic:
 * - Owner role: Can see ALL users across ALL agencies
 * - Agency role: Can see ONLY users in their own agency
 * - Direct/End Client: Can see ONLY themselves
 * - All users can always see their own record
 */
class EnableRowLevelSecurity extends Migration
{
    public function up()
    {
        // Enable RLS on users table
        $this->db->query('ALTER TABLE users ENABLE ROW LEVEL SECURITY');

        // Policy 1: Agency Isolation Policy
        // Users can see other users in their agency, OR they're owner, OR it's their own record
        $this->db->query("
            CREATE POLICY agency_isolation_users ON users
            FOR ALL
            USING (
                -- Owner can see all users
                current_setting('app.current_user_role', true) = 'owner'
                OR
                -- Agency users can see users in their agency
                (
                    current_setting('app.current_user_role', true) = 'agency'
                    AND agency_id = current_setting('app.current_agency_id', true)::uuid
                )
                OR
                -- Direct clients can see users in their agency
                (
                    current_setting('app.current_user_role', true) = 'direct_client'
                    AND agency_id = current_setting('app.current_agency_id', true)::uuid
                )
                OR
                -- End clients can only see themselves
                (
                    current_setting('app.current_user_role', true) = 'end_client'
                    AND id = current_setting('app.current_user_id', true)::uuid
                )
                OR
                -- Everyone can see their own record
                id = current_setting('app.current_user_id', true)::uuid
            )
        ");

        // Policy 2: Modification Policy
        // Only owner and agency roles can modify users, and agency roles only in their agency
        $this->db->query("
            CREATE POLICY modify_users_policy ON users
            FOR INSERT
            WITH CHECK (
                current_setting('app.current_user_role', true) = 'owner'
                OR
                (
                    current_setting('app.current_user_role', true) = 'agency'
                    AND agency_id = current_setting('app.current_agency_id', true)::uuid
                )
            )
        ");

        $this->db->query("
            CREATE POLICY update_users_policy ON users
            FOR UPDATE
            USING (
                current_setting('app.current_user_role', true) = 'owner'
                OR
                (
                    current_setting('app.current_user_role', true) = 'agency'
                    AND agency_id = current_setting('app.current_agency_id', true)::uuid
                )
                OR
                -- Users can update their own profile (except role and agency_id)
                id = current_setting('app.current_user_id', true)::uuid
            )
        ");

        // Policy 3: Deletion Policy
        // Only owner can delete users
        $this->db->query("
            CREATE POLICY delete_users_policy ON users
            FOR DELETE
            USING (
                current_setting('app.current_user_role', true) = 'owner'
            )
        ");

        // Enable RLS on agencies table
        $this->db->query('ALTER TABLE agencies ENABLE ROW LEVEL SECURITY');

        // Policy: Only owner can manage agencies
        $this->db->query("
            CREATE POLICY owner_only_agencies ON agencies
            FOR ALL
            USING (
                current_setting('app.current_user_role', true) = 'owner'
                OR
                -- Agency users can see their own agency
                id = current_setting('app.current_agency_id', true)::uuid
            )
        ");

        // Webhook events - only accessible to owner and agency roles
        $this->db->query('ALTER TABLE webhook_events ENABLE ROW LEVEL SECURITY');

        $this->db->query("
            CREATE POLICY webhook_access_policy ON webhook_events
            FOR ALL
            USING (
                current_setting('app.current_user_role', true) IN ('owner', 'agency')
            )
        ");

        // Sessions table does NOT need RLS - it's managed by CodeIgniter session handler
    }

    public function down()
    {
        // Drop policies
        $this->db->query('DROP POLICY IF EXISTS agency_isolation_users ON users');
        $this->db->query('DROP POLICY IF EXISTS modify_users_policy ON users');
        $this->db->query('DROP POLICY IF EXISTS update_users_policy ON users');
        $this->db->query('DROP POLICY IF EXISTS delete_users_policy ON users');
        $this->db->query('DROP POLICY IF EXISTS owner_only_agencies ON agencies');
        $this->db->query('DROP POLICY IF EXISTS webhook_access_policy ON webhook_events');

        // Disable RLS
        $this->db->query('ALTER TABLE users DISABLE ROW LEVEL SECURITY');
        $this->db->query('ALTER TABLE agencies DISABLE ROW LEVEL SECURITY');
        $this->db->query('ALTER TABLE webhook_events DISABLE ROW LEVEL SECURITY');
    }
}
