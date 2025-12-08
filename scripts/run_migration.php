#!/usr/bin/env php
<?php

/**
 * Migration Runner Script
 *
 * Runs database migrations using direct PDO connection.
 */

echo "Running database migrations...\n\n";

// Database configuration
$host = getenv('database.default.hostname') ?: 'localhost';
$dbname = getenv('database.default.database') ?: 'openclient_db';
$username = getenv('database.default.username') ?: 'openclient_user';
$password = getenv('database.default.password') ?: 'dev_password_change_in_production';

$dsn = "pgsql:host={$host};dbname={$dbname}";

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "✓ Database connection established\n\n";
} catch (PDOException $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Check if clients table already exists
$stmt = $pdo->query("
    SELECT table_name
    FROM information_schema.tables
    WHERE table_schema = 'public' AND table_name = 'clients'
");
$exists = $stmt->fetch();

if ($exists) {
    echo "ℹ Table 'clients' already exists. Skipping migration.\n";
    echo "To re-run, manually drop the table first: DROP TABLE clients CASCADE;\n";
    exit(0);
}

echo "Creating clients table with RLS...\n";

// Create trigger_set_timestamp function if it doesn't exist
$pdo->exec("
    CREATE OR REPLACE FUNCTION trigger_set_timestamp()
    RETURNS TRIGGER AS \$\$
    BEGIN
        NEW.updated_at = CURRENT_TIMESTAMP;
        RETURN NEW;
    END;
    \$\$ LANGUAGE plpgsql
");
echo "  ✓ Timestamp trigger function created/verified\n";

// Create clients table
$pdo->exec("
    CREATE TABLE clients (
        id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
        agency_id UUID NOT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        phone VARCHAR(50),
        company VARCHAR(255),
        address TEXT,
        city VARCHAR(100),
        state VARCHAR(50),
        postal_code VARCHAR(20),
        country VARCHAR(100) DEFAULT 'United States',
        notes TEXT,
        is_active BOOLEAN NOT NULL DEFAULT true,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP,
        FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE ON UPDATE CASCADE
    )
");
echo "  ✓ Table created\n";

// Create updated_at trigger
$pdo->exec("
    CREATE TRIGGER set_timestamp_clients
    BEFORE UPDATE ON clients
    FOR EACH ROW
    EXECUTE FUNCTION trigger_set_timestamp()
");
echo "  ✓ Updated_at trigger created\n";

// Create performance indexes
$pdo->exec("CREATE INDEX idx_clients_agency_id ON clients(agency_id)");
$pdo->exec("CREATE INDEX idx_clients_email ON clients(email)");
$pdo->exec("CREATE INDEX idx_clients_name ON clients(name)");
$pdo->exec("CREATE INDEX idx_clients_active ON clients(is_active)");
echo "  ✓ Indexes created\n";

// Enable Row-Level Security
$pdo->exec("ALTER TABLE clients ENABLE ROW LEVEL SECURITY");
echo "  ✓ RLS enabled\n";

// Force RLS even for table owner
$pdo->exec("ALTER TABLE clients FORCE ROW LEVEL SECURITY");
echo "  ✓ FORCE RLS enabled (enforces for table owner)\n";

// Create RLS policy for SELECT
$pdo->exec("
    CREATE POLICY agency_isolation_clients ON clients
    USING (
        agency_id = current_setting('app.current_agency_id', true)::uuid
        OR current_setting('app.current_user_role', true) = 'owner'
    )
");
echo "  ✓ RLS SELECT policy created\n";

// Create RLS policy for INSERT
$pdo->exec("
    CREATE POLICY agency_isolation_clients_insert ON clients
    FOR INSERT
    WITH CHECK (
        agency_id = current_setting('app.current_agency_id', true)::uuid
        OR current_setting('app.current_user_role', true) = 'owner'
    )
");
echo "  ✓ RLS INSERT policy created\n";

// Create RLS policy for UPDATE
$pdo->exec("
    CREATE POLICY agency_isolation_clients_update ON clients
    FOR UPDATE
    USING (
        agency_id = current_setting('app.current_agency_id', true)::uuid
        OR current_setting('app.current_user_role', true) = 'owner'
    )
");
echo "  ✓ RLS UPDATE policy created\n";

// Create RLS policy for DELETE
$pdo->exec("
    CREATE POLICY agency_isolation_clients_delete ON clients
    FOR DELETE
    USING (
        agency_id = current_setting('app.current_agency_id', true)::uuid
        OR current_setting('app.current_user_role', true) = 'owner'
    )
");
echo "  ✓ RLS DELETE policy created\n";

echo "\n✓ Migration completed successfully!\n\n";
echo "Clients table now has:\n";
echo "  - Multi-agency isolation via agency_id column\n";
echo "  - Row-Level Security (RLS) enabled\n";
echo "  - 4 RLS policies (SELECT, INSERT, UPDATE, DELETE)\n";
echo "  - Performance indexes on key columns\n";
echo "  - Automatic timestamps with triggers\n";

exit(0);
