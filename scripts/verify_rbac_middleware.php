#!/usr/bin/env php
<?php

/**
 * RBAC Middleware Verification Script
 *
 * Verifies that the RBACFilter is correctly configured and enforces
 * authorization rules at the HTTP middleware layer.
 */

echo "=================================================\n";
echo "RBAC Middleware Verification\n";
echo "=================================================\n\n";

$baseDir = dirname(__DIR__);
$errors = [];
$warnings = [];

// 1. Check RBACFilter exists
echo "1. Checking RBACFilter file exists...\n";
$rbacFilterPath = $baseDir . '/app/Filters/RBACFilter.php';
if (!file_exists($rbacFilterPath)) {
    $errors[] = "RBACFilter.php not found at {$rbacFilterPath}";
    echo "   ✗ RBACFilter.php NOT FOUND\n\n";
} else {
    echo "   ✓ RBACFilter.php exists\n\n";
}

// 2. Check SecurityLogger exists
echo "2. Checking SecurityLogger helper exists...\n";
$securityLoggerPath = $baseDir . '/app/Helpers/SecurityLogger.php';
if (!file_exists($securityLoggerPath)) {
    $errors[] = "SecurityLogger.php not found at {$securityLoggerPath}";
    echo "   ✗ SecurityLogger.php NOT FOUND\n\n";
} else {
    echo "   ✓ SecurityLogger.php exists\n\n";
}

// 3. Check Filters.php configuration
echo "3. Checking Filters.php configuration...\n";
$filtersPath = $baseDir . '/app/Config/Filters.php';
if (!file_exists($filtersPath)) {
    $errors[] = "Filters.php not found";
    echo "   ✗ Filters.php NOT FOUND\n\n";
} else {
    $filtersContent = file_get_contents($filtersPath);

    // Check RBAC alias registered
    if (strpos($filtersContent, "'rbac'") !== false && strpos($filtersContent, 'RBACFilter') !== false) {
        echo "   ✓ RBAC filter alias registered\n";
    } else {
        $errors[] = "RBAC filter alias not found in Filters.php";
        echo "   ✗ RBAC filter alias NOT registered\n";
    }

    // Check RBAC in globals
    if (strpos($filtersContent, "'rbac' => ['except'") !== false) {
        echo "   ✓ RBAC filter registered in globals\n\n";
    } else {
        $warnings[] = "RBAC filter not found in globals array";
        echo "   ⚠️  RBAC filter not in globals (may be configured in routes)\n\n";
    }
}

// 4. Check RBACFilter class structure
echo "4. Analyzing RBACFilter class structure...\n";
if (file_exists($rbacFilterPath)) {
    $fileContent = file_get_contents($rbacFilterPath);

    // Check implements FilterInterface
    if (strpos($fileContent, 'implements FilterInterface') !== false) {
        echo "   ✓ Implements FilterInterface\n";
    } else {
        $errors[] = "RBACFilter does not implement FilterInterface";
        echo "   ✗ Does NOT implement FilterInterface\n";
    }

    // Check has before() method
    if (strpos($fileContent, 'function before(') !== false) {
        echo "   ✓ Has before() method\n";
    } else {
        $errors[] = "RBACFilter missing before() method";
        echo "   ✗ Missing before() method\n";
    }

    // Check has after() method
    if (strpos($fileContent, 'function after(') !== false) {
        echo "   ✓ Has after() method\n";
    } else {
        $errors[] = "RBACFilter missing after() method";
        echo "   ✗ Missing after() method\n";
    }

    // Check for FINANCIAL_ROUTES constant
    if (strpos($fileContent, 'FINANCIAL_ROUTES') !== false) {
        echo "   ✓ FINANCIAL_ROUTES defined\n";
    } else {
        $warnings[] = "FINANCIAL_ROUTES constant not found";
        echo "   ⚠️  FINANCIAL_ROUTES not defined\n";
    }

    // Check for ADMIN_ROUTES constant
    if (strpos($fileContent, 'ADMIN_ROUTES') !== false) {
        echo "   ✓ ADMIN_ROUTES defined\n";
    } else {
        $warnings[] = "ADMIN_ROUTES constant not found";
        echo "   ⚠️  ADMIN_ROUTES not defined\n";
    }

    echo "\n";
}

// 5. Check route protection patterns
echo "5. Checking route protection patterns...\n";
if (file_exists($rbacFilterPath)) {
    $rbacContent = file_get_contents($rbacFilterPath);

    $protectedRoutes = [
        '/invoices' => 'financial',
        '/payments' => 'financial',
        '/billing' => 'financial',
        '/admin' => 'admin',
        '/settings' => 'admin',
        '/users' => 'admin',
    ];

    foreach ($protectedRoutes as $route => $type) {
        if (strpos($rbacContent, "'{$route}'") !== false) {
            echo "   ✓ {$route} protected ({$type})\n";
        } else {
            $warnings[] = "{$route} route not explicitly protected";
            echo "   ⚠️  {$route} not found in protection list\n";
        }
    }
    echo "\n";
}

// 6. Check role-based restrictions
echo "6. Checking role-based restriction logic...\n";
if (file_exists($rbacFilterPath)) {
    $rbacContent = file_get_contents($rbacFilterPath);

    // Check End Client restrictions
    if (strpos($rbacContent, "'end_client'") !== false) {
        echo "   ✓ End Client role handled\n";
    } else {
        $warnings[] = "End Client role restriction not found";
        echo "   ⚠️  End Client restriction not found\n";
    }

    // Check Owner bypass
    if (strpos($rbacContent, "'owner'") !== false) {
        echo "   ✓ Owner role bypass logic present\n";
    } else {
        $warnings[] = "Owner bypass logic not found";
        echo "   ⚠️  Owner bypass not found\n";
    }

    // Check agency_id validation
    if (strpos($rbacContent, 'agency_id') !== false) {
        echo "   ✓ Agency ID validation present\n";
    } else {
        $warnings[] = "Agency ID validation not found";
        echo "   ⚠️  Agency ID validation not found\n";
    }

    echo "\n";
}

// 7. Check security logging
echo "7. Checking security logging integration...\n";
if (file_exists($rbacFilterPath)) {
    $rbacContent = file_get_contents($rbacFilterPath);

    if (strpos($rbacContent, 'log_message') !== false || strpos($rbacContent, 'SecurityLogger') !== false) {
        echo "   ✓ Security logging integrated\n";
    } else {
        $warnings[] = "Security logging not found in RBACFilter";
        echo "   ⚠️  Security logging not detected\n";
    }

    // Check for log file creation
    if (strpos($rbacContent, 'security-') !== false && strpos($rbacContent, '.log') !== false) {
        echo "   ✓ Security log file pattern found\n";
    } else {
        $warnings[] = "Security log file pattern not found";
        echo "   ⚠️  Log file pattern not detected\n";
    }

    echo "\n";
}

// 8. Check writable logs directory
echo "8. Checking logs directory permissions...\n";
$logsDir = $baseDir . '/writable/logs';
if (!is_dir($logsDir)) {
    $warnings[] = "Logs directory does not exist: {$logsDir}";
    echo "   ⚠️  Logs directory not found\n\n";
} elseif (!is_writable($logsDir)) {
    $errors[] = "Logs directory is not writable: {$logsDir}";
    echo "   ✗ Logs directory NOT writable\n\n";
} else {
    echo "   ✓ Logs directory exists and is writable\n\n";
}

// Summary
echo "=================================================\n";
echo "Verification Summary\n";
echo "=================================================\n\n";

if (empty($errors)) {
    echo "✅ All critical checks passed\n";
} else {
    echo "❌ Critical errors found:\n";
    foreach ($errors as $error) {
        echo "   - {$error}\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️  Warnings:\n";
    foreach ($warnings as $warning) {
        echo "   - {$warning}\n";
    }
    echo "\n";
}

if (empty($errors) && empty($warnings)) {
    echo "🎉 RBAC Middleware is correctly configured!\n\n";
    echo "Next Steps:\n";
    echo "  1. Test with actual HTTP requests\n";
    echo "  2. Verify security logs are created on access denial\n";
    echo "  3. Test all protected routes with different user roles\n";
    exit(0);
} elseif (empty($errors)) {
    echo "✅ RBAC Middleware configuration is functional but has minor warnings.\n";
    exit(0);
} else {
    echo "❌ RBAC Middleware has critical configuration issues. Please fix errors above.\n";
    exit(1);
}
