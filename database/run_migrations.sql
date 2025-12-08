-- OpenClient Database Schema Setup
-- Week 3-4: Database Schema Foundation
-- Run with: psql -U openclient_user -d openclient_db -f database/run_migrations.sql

BEGIN;

-- ============================================================================
-- Migration 1: Enable UUID Extension and Create Agencies Table
-- ============================================================================

CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Create updated_at trigger function (reusable for all tables)
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Create agencies table
CREATE TABLE IF NOT EXISTS agencies (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100) DEFAULT 'USA',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

-- Create updated_at trigger for agencies
CREATE TRIGGER update_agencies_updated_at BEFORE UPDATE
ON agencies FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();

-- ============================================================================
-- Migration 2: Create Users Table with RBAC
-- ============================================================================

-- Create role enum type
DO $$ BEGIN
    CREATE TYPE user_role AS ENUM ('owner', 'agency', 'end_client', 'direct_client');
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    agency_id UUID REFERENCES agencies(id) ON DELETE SET NULL ON UPDATE CASCADE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role user_role NOT NULL DEFAULT 'end_client',
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(50),
    avatar VARCHAR(255),
    is_active BOOLEAN NOT NULL DEFAULT true,
    failed_login_attempts INT NOT NULL DEFAULT 0,
    locked_until TIMESTAMP,
    last_login_at TIMESTAMP,
    last_login_ip VARCHAR(45),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

-- Create indexes for users
CREATE INDEX idx_users_agency ON users(agency_id);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);

-- Create updated_at trigger for users
CREATE TRIGGER update_users_updated_at BEFORE UPDATE
ON users FOR EACH ROW
EXECUTE FUNCTION update_updated_at_column();

-- Add constraints: owner role must have null agency_id
ALTER TABLE users ADD CONSTRAINT check_owner_no_agency
CHECK (role != 'owner' OR agency_id IS NULL);

-- Add constraints: non-owner roles must have agency_id
ALTER TABLE users ADD CONSTRAINT check_non_owner_has_agency
CHECK (role = 'owner' OR agency_id IS NOT NULL);

-- ============================================================================
-- Migration 3: Create Sessions Table
-- ============================================================================

CREATE TABLE IF NOT EXISTS ci_sessions (
    id VARCHAR(128) PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    timestamp BIGINT NOT NULL DEFAULT 0,
    data TEXT NOT NULL DEFAULT ''
);

CREATE INDEX idx_sessions_timestamp ON ci_sessions(timestamp);

-- ============================================================================
-- Migration 4: Create Webhook Events Table
-- ============================================================================

-- Create gateway enum type
DO $$ BEGIN
    CREATE TYPE payment_gateway AS ENUM ('stripe', 'paypal', 'stripe_ach', 'zelle');
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

-- Create webhook_events table
CREATE TABLE IF NOT EXISTS webhook_events (
    event_id VARCHAR(255) PRIMARY KEY,
    gateway payment_gateway NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    invoice_id UUID,
    payload JSONB NOT NULL,
    signature TEXT,
    is_processed BOOLEAN NOT NULL DEFAULT false,
    processed_at TIMESTAMP,
    error_message TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for webhook_events
CREATE INDEX idx_webhook_gateway ON webhook_events(gateway);
CREATE INDEX idx_webhook_event_type ON webhook_events(event_type);
CREATE INDEX idx_webhook_processed_at ON webhook_events(processed_at);
CREATE INDEX idx_webhook_created_at ON webhook_events(created_at);
CREATE INDEX idx_webhook_events_payload ON webhook_events USING GIN (payload);

-- ============================================================================
-- Migration 5: Add Performance Indexes
-- ============================================================================

-- Users table additional indexes
CREATE INDEX idx_users_agency_role ON users(agency_id, role);
CREATE INDEX idx_users_is_active ON users(is_active);
CREATE INDEX idx_users_created_at ON users(created_at);
CREATE INDEX idx_users_deleted_at ON users(deleted_at) WHERE deleted_at IS NULL;

-- Agencies table indexes
CREATE INDEX idx_agencies_name ON agencies(name);
CREATE INDEX idx_agencies_deleted_at ON agencies(deleted_at) WHERE deleted_at IS NULL;

-- Sessions table composite index
CREATE INDEX idx_sessions_timestamp_ip ON ci_sessions(timestamp, ip_address);

-- Webhook events table additional indexes
CREATE INDEX idx_webhook_gateway_type ON webhook_events(gateway, event_type);
CREATE INDEX idx_webhook_unprocessed ON webhook_events(is_processed, created_at) WHERE is_processed = false;

-- ============================================================================
-- Migration 6: Enable PostgreSQL Row-Level Security (RLS)
-- ============================================================================

-- Enable RLS on users table
ALTER TABLE users ENABLE ROW LEVEL SECURITY;

-- Policy: Agency Isolation for Users
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
);

-- Policy: User Modification
CREATE POLICY modify_users_policy ON users
FOR INSERT
WITH CHECK (
    current_setting('app.current_user_role', true) = 'owner'
    OR
    (
        current_setting('app.current_user_role', true) = 'agency'
        AND agency_id = current_setting('app.current_agency_id', true)::uuid
    )
);

-- Policy: User Updates
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
    -- Users can update their own profile
    id = current_setting('app.current_user_id', true)::uuid
);

-- Policy: User Deletion (Owner only)
CREATE POLICY delete_users_policy ON users
FOR DELETE
USING (
    current_setting('app.current_user_role', true) = 'owner'
);

-- Enable RLS on agencies table
ALTER TABLE agencies ENABLE ROW LEVEL SECURITY;

-- Policy: Agency Access
CREATE POLICY owner_only_agencies ON agencies
FOR ALL
USING (
    current_setting('app.current_user_role', true) = 'owner'
    OR
    -- Agency users can see their own agency
    id = current_setting('app.current_agency_id', true)::uuid
);

-- Enable RLS on webhook_events table
ALTER TABLE webhook_events ENABLE ROW LEVEL SECURITY;

-- Policy: Webhook Access (Owner and Agency only)
CREATE POLICY webhook_access_policy ON webhook_events
FOR ALL
USING (
    current_setting('app.current_user_role', true) IN ('owner', 'agency')
);

COMMIT;

-- ============================================================================
-- Verification Queries
-- ============================================================================

-- Show all tables
\dt

-- Show table structures
\d agencies
\d users
\d ci_sessions
\d webhook_events

-- Show RLS policies
SELECT schemaname, tablename, policyname, permissive, roles, cmd, qual
FROM pg_policies
WHERE tablename IN ('users', 'agencies', 'webhook_events')
ORDER BY tablename, policyname;

-- Success message
\echo 'Database schema created successfully!'
\echo 'All migrations applied:'
\echo '  1. Agencies table with UUID primary keys'
\echo '  2. Users table with RBAC (owner, agency, end_client, direct_client)'
\echo '  3. Sessions table for CodeIgniter session storage'
\echo '  4. Webhook events table for payment gateway idempotency'
\echo '  5. Performance indexes for common queries'
\echo '  6. Row-Level Security policies for multi-agency isolation'
