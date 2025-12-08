#!/usr/bin/env php
<?php

/**
 * RLS Verification Script
 *
 * Verifies that Row-Level Security is properly configured and enforced.
 */

echo "=================================================\n";
echo "Row-Level Security Verification\n";
echo "=================================================\n\n";

$pdo = new PDO('pgsql:host=localhost;dbname=openclient_db', 'openclient_user', 'dev_password_change_in_production', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// Check if clients table has RLS enabled
echo "1. Checking if RLS is enabled on clients table...\n";
$stmt = $pdo->query("
    SELECT relname, relrowsecurity
    FROM pg_class
    WHERE relname = 'clients'
");
$result = $stmt->fetch();

if ($result && $result['relrowsecurity']) {
    echo "   ✓ RLS is ENABLED on clients table\n\n";
} else {
    echo "   ✗ RLS is NOT enabled on clients table\n\n";
    exit(1);
}

// Check RLS policies
echo "2. Checking RLS policies...\n";
$stmt = $pdo->query("
    SELECT policyname, cmd, qual, with_check
    FROM pg_policies
    WHERE tablename = 'clients'
    ORDER BY policyname
");
$policies = $stmt->fetchAll();

echo "   Found " . count($policies) . " policies:\n";
foreach ($policies as $policy) {
    echo "   - {$policy['policyname']} ({$policy['cmd']})\n";
}
echo "\n";

// Check current user permissions
echo "3. Checking database user permissions...\n";
$stmt = $pdo->query("SELECT current_user, usesuper FROM pg_user WHERE usename = current_user");
$user = $stmt->fetch();

echo "   Current user: {$user['current_user']}\n";
echo "   Is superuser: " . ($user['usesuper'] ? 'YES' : 'NO') . "\n\n";

if ($user['usesuper']) {
    echo "   ⚠️  WARNING: Current user is a superuser!\n";
    echo "   RLS policies are BYPASSED for superusers.\n";
    echo "   This is expected in development but NOT in production.\n\n";
}

// Test RLS enforcement
echo "4. Testing RLS enforcement...\n";

// Temporarily set as owner to create test data
$pdo->exec("SET app.current_user_role = 'owner'");

// Create test agencies if they don't exist
$pdo->exec("
    INSERT INTO agencies (id, name)
    VALUES ('77777777-7777-7777-7777-777777777777', 'RLS Test Agency A')
    ON CONFLICT (id) DO NOTHING
");

$pdo->exec("
    INSERT INTO agencies (id, name)
    VALUES ('88888888-8888-8888-8888-888888888888', 'RLS Test Agency B')
    ON CONFLICT (id) DO NOTHING
");

// Create test clients (as owner to bypass RLS during setup)
$pdo->exec("
    INSERT INTO clients (id, agency_id, name, email)
    VALUES (
        '99999999-9999-9999-9999-999999999999',
        '77777777-7777-7777-7777-777777777777',
        'RLS Test Client A',
        'testA@rls.com'
    )
    ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name
");

$pdo->exec("
    INSERT INTO clients (id, agency_id, name, email)
    VALUES (
        'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        '88888888-8888-8888-8888-888888888888',
        'RLS Test Client B',
        'testB@rls.com'
    )
    ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name
");

// Test as Agency A
echo "   Testing as Agency A user...\n";
$pdo->exec("SET app.current_user_role = 'agency'");
$pdo->exec("SET app.current_agency_id = '77777777-7777-7777-7777-777777777777'");

$stmt = $pdo->query("SELECT name FROM clients WHERE name LIKE 'RLS Test%' ORDER BY name");
$clients = $stmt->fetchAll();

echo "   Visible clients: " . count($clients) . "\n";
foreach ($clients as $client) {
    echo "     - {$client['name']}\n";
}

if (count($clients) === 2 && $user['usesuper']) {
    echo "   ⚠️  Seeing both clients because user is superuser (RLS bypassed)\n";
} elseif (count($clients) === 1 && $clients[0]['name'] === 'RLS Test Client A') {
    echo "   ✓ RLS is working! Only Agency A's client visible\n";
} else {
    echo "   ✗ Unexpected result\n";
}
echo "\n";

// Test as Owner
echo "   Testing as Owner user...\n";
$pdo->exec("SET app.current_user_role = 'owner'");
// Owner role doesn't need agency_id set - the policy allows owner to see all

$stmt = $pdo->query("SELECT name FROM clients WHERE name LIKE 'RLS Test%' ORDER BY name");
$clients = $stmt->fetchAll();

echo "   Visible clients: " . count($clients) . "\n";
foreach ($clients as $client) {
    echo "     - {$client['name']}\n";
}

if (count($clients) === 2) {
    echo "   ✓ Owner can see all clients\n";
} else {
    echo "   ✗ Owner should see all clients\n";
}
echo "\n";

// Clean up
$pdo->exec("DELETE FROM clients WHERE id IN ('99999999-9999-9999-9999-999999999999', 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa')");
$pdo->exec("DELETE FROM agencies WHERE id IN ('77777777-7777-7777-7777-777777777777', '88888888-8888-8888-8888-888888888888')");

echo "=================================================\n";
echo "Verification Complete\n";
echo "=================================================\n";
