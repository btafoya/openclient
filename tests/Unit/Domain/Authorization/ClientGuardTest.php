<?php

namespace Tests\Unit\Domain\Authorization;

use App\Domain\Clients\Authorization\ClientGuard;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for ClientGuard
 *
 * Tests fine-grained authorization logic for client operations.
 * Mock database interactions to isolate business logic testing.
 */
class ClientGuardTest extends TestCase
{
    private ClientGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new ClientGuard();
    }

    // ===== canView() Tests =====

    public function test_owner_can_view_any_client(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '2'];

        $this->assertTrue($this->guard->canView($owner, $client));
    }

    public function test_agency_can_view_own_agency_client(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '1'];

        $this->assertTrue($this->guard->canView($agency, $client));
    }

    public function test_agency_cannot_view_other_agency_client(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '2'];

        $this->assertFalse($this->guard->canView($agency, $client));
    }

    public function test_agency_cannot_view_client_without_agency_id(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $client = ['id' => '100'];  // Missing agency_id

        $this->assertFalse($this->guard->canView($agency, $client));
    }

    public function test_direct_client_cannot_view_client_without_id(): void
    {
        $directClient = ['id' => '3', 'role' => 'direct_client', 'agency_id' => '1'];
        $client = ['agency_id' => '1'];  // Missing id

        $this->assertFalse($this->guard->canView($directClient, $client));
    }

    public function test_end_client_cannot_view_client_without_id(): void
    {
        $endClient = ['id' => '4', 'role' => 'end_client', 'agency_id' => '1'];
        $client = ['agency_id' => '1'];  // Missing id

        $this->assertFalse($this->guard->canView($endClient, $client));
    }

    public function test_unknown_role_cannot_view_client(): void
    {
        $unknownUser = ['id' => '10', 'role' => 'unknown_role', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canView($unknownUser, $client));
    }

    // ===== canCreate() Tests =====

    public function test_owner_can_create_clients(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];

        $this->assertTrue($this->guard->canCreate($owner));
    }

    public function test_agency_can_create_clients(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];

        $this->assertTrue($this->guard->canCreate($agency));
    }

    public function test_direct_client_cannot_create_clients(): void
    {
        $directClient = ['id' => '3', 'role' => 'direct_client', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canCreate($directClient));
    }

    public function test_end_client_cannot_create_clients(): void
    {
        $endClient = ['id' => '4', 'role' => 'end_client', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canCreate($endClient));
    }

    // ===== canEdit() Tests =====

    public function test_owner_can_edit_any_client(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '2'];

        $this->assertTrue($this->guard->canEdit($owner, $client));
    }

    public function test_agency_can_edit_own_agency_client(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '1'];

        $this->assertTrue($this->guard->canEdit($agency, $client));
    }

    public function test_agency_cannot_edit_other_agency_client(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '2'];

        $this->assertFalse($this->guard->canEdit($agency, $client));
    }

    public function test_agency_cannot_edit_client_without_agency_id(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $client = ['id' => '100'];  // Missing agency_id

        $this->assertFalse($this->guard->canEdit($agency, $client));
    }

    public function test_direct_client_cannot_edit_clients(): void
    {
        $directClient = ['id' => '3', 'role' => 'direct_client', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canEdit($directClient, $client));
    }

    public function test_end_client_cannot_edit_clients(): void
    {
        $endClient = ['id' => '4', 'role' => 'end_client', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canEdit($endClient, $client));
    }

    // ===== canDelete() Tests =====

    public function test_owner_can_delete_any_client(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '2'];

        $this->assertTrue($this->guard->canDelete($owner, $client));
    }

    public function test_agency_cannot_delete_clients(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canDelete($agency, $client));
    }

    public function test_direct_client_cannot_delete_clients(): void
    {
        $directClient = ['id' => '3', 'role' => 'direct_client', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canDelete($directClient, $client));
    }

    public function test_end_client_cannot_delete_clients(): void
    {
        $endClient = ['id' => '4', 'role' => 'end_client', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canDelete($endClient, $client));
    }

    // ===== canManageUsers() Tests =====

    public function test_owner_can_manage_users_on_any_client(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '2'];

        $this->assertTrue($this->guard->canManageUsers($owner, $client));
    }

    public function test_agency_can_manage_users_on_own_agency_client(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '1'];

        $this->assertTrue($this->guard->canManageUsers($agency, $client));
    }

    public function test_agency_cannot_manage_users_on_other_agency_client(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '2'];

        $this->assertFalse($this->guard->canManageUsers($agency, $client));
    }

    public function test_agency_cannot_manage_users_on_client_without_agency_id(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $client = ['id' => '100'];  // Missing agency_id

        $this->assertFalse($this->guard->canManageUsers($agency, $client));
    }

    public function test_direct_client_cannot_manage_users(): void
    {
        $directClient = ['id' => '3', 'role' => 'direct_client', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canManageUsers($directClient, $client));
    }

    public function test_end_client_cannot_manage_users(): void
    {
        $endClient = ['id' => '4', 'role' => 'end_client', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canManageUsers($endClient, $client));
    }

    // ===== getPermissionSummary() Tests =====

    public function test_permission_summary_without_client(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];

        $summary = $this->guard->getPermissionSummary($owner);

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('canCreate', $summary);
        $this->assertTrue($summary['canCreate']);
        $this->assertArrayNotHasKey('canView', $summary);
    }

    public function test_permission_summary_with_client_for_owner(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '1'];

        $summary = $this->guard->getPermissionSummary($owner, $client);

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('canCreate', $summary);
        $this->assertArrayHasKey('canView', $summary);
        $this->assertArrayHasKey('canEdit', $summary);
        $this->assertArrayHasKey('canDelete', $summary);
        $this->assertArrayHasKey('canManageUsers', $summary);
        $this->assertTrue($summary['canCreate']);
        $this->assertTrue($summary['canView']);
        $this->assertTrue($summary['canEdit']);
        $this->assertTrue($summary['canDelete']);
        $this->assertTrue($summary['canManageUsers']);
    }

    public function test_permission_summary_with_client_for_agency(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '1'];

        $summary = $this->guard->getPermissionSummary($agency, $client);

        $this->assertTrue($summary['canCreate']);
        $this->assertTrue($summary['canView']);
        $this->assertTrue($summary['canEdit']);
        $this->assertFalse($summary['canDelete']);
        $this->assertTrue($summary['canManageUsers']);
    }

    /**
     * @group integration
     * @skip This test requires database connection for client_users table query.
     *       Move to integration tests or implement database mocking.
     */
    public function test_permission_summary_for_direct_client(): void
    {
        $this->markTestSkipped(
            'This test requires database access to check client_users assignments. ' .
            'Should be moved to integration tests with test database.'
        );

        $directClient = ['id' => '3', 'role' => 'direct_client', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '1'];

        $summary = $this->guard->getPermissionSummary($directClient, $client);

        $this->assertFalse($summary['canCreate']);
        $this->assertFalse($summary['canView']);  // Would need client assignment check
        $this->assertFalse($summary['canEdit']);
        $this->assertFalse($summary['canDelete']);
        $this->assertFalse($summary['canManageUsers']);
    }

    /**
     * @group integration
     * @skip This test requires database connection for client_users table query.
     *       Move to integration tests or implement database mocking.
     */
    public function test_permission_summary_for_end_client(): void
    {
        $this->markTestSkipped(
            'This test requires database access to check client_users assignments. ' .
            'Should be moved to integration tests with test database.'
        );

        $endClient = ['id' => '4', 'role' => 'end_client', 'agency_id' => '1'];
        $client = ['id' => '100', 'agency_id' => '1'];

        $summary = $this->guard->getPermissionSummary($endClient, $client);

        $this->assertFalse($summary['canCreate']);
        $this->assertFalse($summary['canView']);  // Would need client assignment check
        $this->assertFalse($summary['canEdit']);
        $this->assertFalse($summary['canDelete']);
        $this->assertFalse($summary['canManageUsers']);
    }

    // ===== Object Conversion Tests =====

    public function test_can_view_with_object_client(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $client = (object) ['id' => '100', 'agency_id' => '2'];

        $this->assertTrue($this->guard->canView($owner, $client));
    }

    public function test_can_edit_with_object_client(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $client = (object) ['id' => '100', 'agency_id' => '2'];

        $this->assertTrue($this->guard->canEdit($owner, $client));
    }

    public function test_can_manage_users_with_object_client(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $client = (object) ['id' => '100', 'agency_id' => '2'];

        $this->assertTrue($this->guard->canManageUsers($owner, $client));
    }
}
