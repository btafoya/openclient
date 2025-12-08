<?php

namespace Config;

use CodeIgniter\Database\Config;

/**
 * Database Configuration
 *
 * Extends CodeIgniter's base database configuration to add PostgreSQL RLS
 * session variable management for multi-agency data isolation.
 */
class Database extends Config
{
    /**
     * The directory that holds the Migrations and Seeds directories.
     */
    public string $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;

    /**
     * Lets you choose which connection group to use if no other is specified.
     */
    public string $defaultGroup = 'default';

    /**
     * The default database connection.
     *
     * Configuration is loaded from .env file for flexibility across environments.
     *
     * @var array<string, mixed>
     */
    public array $default = [
        'DSN'          => '',
        'hostname'     => 'localhost',
        'username'     => '',
        'password'     => '',
        'database'     => '',
        'DBDriver'     => 'Postgre',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => true,
        'charset'      => 'utf8',
        'DBCollat'     => '',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => false,
        'failover'     => [],
        'port'         => 5432,
        'schema'       => 'public',
        'dateFormat'   => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    /**
     * This database connection is used when running PHPUnit database tests.
     *
     * @var array<string, mixed>
     */
    public array $tests = [
        'DSN'         => '',
        'hostname'    => '127.0.0.1',
        'username'    => '',
        'password'    => '',
        'database'    => ':memory:',
        'DBDriver'    => 'SQLite3',
        'DBPrefix'    => 'db_',
        'pConnect'    => false,
        'DBDebug'     => true,
        'charset'     => 'utf8',
        'DBCollat'    => '',
        'swapPre'     => '',
        'encrypt'     => false,
        'compress'    => false,
        'strictOn'    => false,
        'failover'    => [],
        'port'        => 3306,
        'foreignKeys' => true,
        'busyTimeout' => 1000,
        'dateFormat'  => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    public function __construct()
    {
        parent::__construct();

        // Ensure that we always set the database group to 'tests' if
        // we are currently running an automated test suite, so that
        // we don't overwrite live data on accident.
        if (ENVIRONMENT === 'testing') {
            $this->defaultGroup = 'tests';
        }
    }

    /**
     * Override the connect method to set PostgreSQL RLS session variables
     *
     * This method is called whenever a new database connection is established.
     * It sets session-level variables that PostgreSQL RLS policies use for
     * multi-agency data isolation.
     *
     * Session variables set:
     * - app.current_user_id: UUID of the authenticated user
     * - app.current_user_role: User role (owner, agency, end_client, direct_client)
     * - app.current_agency_id: UUID of user's agency (if applicable)
     *
     * These variables are used by RLS policies to automatically filter queries
     * based on the user's agency assignment and role.
     *
     * @param string|null $group Database group name
     * @param bool $getShared Whether to use shared connection
     * @return \CodeIgniter\Database\BaseConnection
     */
    public static function connect($group = null, bool $getShared = true)
    {
        // Get the database connection using parent class
        $db = parent::connect($group, $getShared);

        // Only set session variables for PostgreSQL connections
        if ($db->DBDriver !== 'Postgre') {
            return $db;
        }

        // Skip session variable setup in testing and CLI environment
        if (ENVIRONMENT === 'testing' || is_cli()) {
            return $db;
        }

        // Set PostgreSQL session variables if user is logged in
        $user = session()->get('user');
        if ($user && is_array($user)) {
            try {
                // Set current user ID for audit trails and RLS policies
                if (isset($user['id']) && !empty($user['id'])) {
                    $db->query("SET app.current_user_id = ?", [$user['id']]);
                }

                // Set current user role for RLS policies
                if (isset($user['role']) && !empty($user['role'])) {
                    $db->query("SET app.current_user_role = ?", [$user['role']]);
                }

                // Set current agency ID for RLS policies (if user has agency assignment)
                if (isset($user['agency_id']) && !empty($user['agency_id'])) {
                    $db->query("SET app.current_agency_id = ?", [$user['agency_id']]);
                }
            } catch (\Exception $e) {
                // Log error but don't break the connection
                // This allows the application to continue functioning even if
                // session variable setup fails
                log_message('error', 'Failed to set PostgreSQL session variables: ' . $e->getMessage());
            }
        }

        return $db;
    }
}
