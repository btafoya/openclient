<?php

namespace Tests\Unit\Domain\Authorization;

use App\Domain\Projects\Authorization\ProjectGuard;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for ProjectGuard
 *
 * Tests fine-grained authorization logic for project operations.
 * Mock database interactions to isolate business logic testing.
 */
class ProjectGuardTest extends TestCase
{
    private ProjectGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new ProjectGuard();
    }

    // ===== canView() Tests =====

    public function test_owner_can_view_any_project(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $project = ['id' => '100', 'agency_id' => '2'];

        $this->assertTrue($this->guard->canView($owner, $project));
    }

    public function test_agency_can_view_own_agency_project(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $project = ['id' => '100', 'agency_id' => '1'];

        $this->assertTrue($this->guard->canView($agency, $project));
    }

    public function test_agency_cannot_view_other_agency_project(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $project = ['id' => '100', 'agency_id' => '2'];

        $this->assertFalse($this->guard->canView($agency, $project));
    }

    public function test_agency_cannot_view_project_without_agency_id(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $project = ['id' => '100'];  // Missing agency_id

        $this->assertFalse($this->guard->canView($agency, $project));
    }

    public function test_unknown_role_cannot_view_project(): void
    {
        $unknownUser = ['id' => '10', 'role' => 'unknown_role', 'agency_id' => '1'];
        $project = ['id' => '100', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canView($unknownUser, $project));
    }

    // ===== canCreate() Tests =====

    public function test_owner_can_create_projects(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];

        $this->assertTrue($this->guard->canCreate($owner));
    }

    public function test_agency_can_create_projects(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];

        $this->assertTrue($this->guard->canCreate($agency));
    }

    public function test_direct_client_cannot_create_projects(): void
    {
        $directClient = ['id' => '3', 'role' => 'direct_client', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canCreate($directClient));
    }

    public function test_end_client_cannot_create_projects(): void
    {
        $endClient = ['id' => '4', 'role' => 'end_client', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canCreate($endClient));
    }

    // ===== canEdit() Tests =====

    public function test_owner_can_edit_any_project(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $project = ['id' => '100', 'agency_id' => '2'];

        $this->assertTrue($this->guard->canEdit($owner, $project));
    }

    public function test_agency_can_edit_own_agency_project(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $project = ['id' => '100', 'agency_id' => '1'];

        $this->assertTrue($this->guard->canEdit($agency, $project));
    }

    public function test_agency_cannot_edit_other_agency_project(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $project = ['id' => '100', 'agency_id' => '2'];

        $this->assertFalse($this->guard->canEdit($agency, $project));
    }

    public function test_agency_cannot_edit_project_without_agency_id(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $project = ['id' => '100'];  // Missing agency_id

        $this->assertFalse($this->guard->canEdit($agency, $project));
    }

    public function test_direct_client_cannot_edit_project(): void
    {
        $directClient = ['id' => '3', 'role' => 'direct_client', 'agency_id' => '1'];
        $project = ['id' => '100', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canEdit($directClient, $project));
    }

    public function test_end_client_cannot_edit_project(): void
    {
        $endClient = ['id' => '4', 'role' => 'end_client', 'agency_id' => '1'];
        $project = ['id' => '100', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canEdit($endClient, $project));
    }

    // ===== canDelete() Tests =====

    public function test_owner_can_delete_any_project(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $project = ['id' => '100', 'agency_id' => '2'];

        $this->assertTrue($this->guard->canDelete($owner, $project));
    }

    public function test_agency_cannot_delete_projects(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $project = ['id' => '100', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canDelete($agency, $project));
    }

    public function test_direct_client_cannot_delete_projects(): void
    {
        $directClient = ['id' => '3', 'role' => 'direct_client', 'agency_id' => '1'];
        $project = ['id' => '100', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canDelete($directClient, $project));
    }

    public function test_end_client_cannot_delete_projects(): void
    {
        $endClient = ['id' => '4', 'role' => 'end_client', 'agency_id' => '1'];
        $project = ['id' => '100', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canDelete($endClient, $project));
    }

    // ===== canManageMembers() Tests =====

    public function test_owner_can_manage_members_on_any_project(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $project = ['id' => '100', 'agency_id' => '2'];

        $this->assertTrue($this->guard->canManageMembers($owner, $project));
    }

    public function test_agency_can_manage_members_on_own_agency_project(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $project = ['id' => '100', 'agency_id' => '1'];

        $this->assertTrue($this->guard->canManageMembers($agency, $project));
    }

    public function test_agency_cannot_manage_members_on_other_agency_project(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $project = ['id' => '100', 'agency_id' => '2'];

        $this->assertFalse($this->guard->canManageMembers($agency, $project));
    }

    public function test_agency_cannot_manage_members_on_project_without_agency_id(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $project = ['id' => '100'];  // Missing agency_id

        $this->assertFalse($this->guard->canManageMembers($agency, $project));
    }

    public function test_direct_client_cannot_manage_members(): void
    {
        $directClient = ['id' => '3', 'role' => 'direct_client', 'agency_id' => '1'];
        $project = ['id' => '100', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canManageMembers($directClient, $project));
    }

    public function test_end_client_cannot_manage_members(): void
    {
        $endClient = ['id' => '4', 'role' => 'end_client', 'agency_id' => '1'];
        $project = ['id' => '100', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canManageMembers($endClient, $project));
    }

    // ===== getPermissionSummary() Tests =====

    public function test_permission_summary_without_project(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];

        $summary = $this->guard->getPermissionSummary($owner);

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('canCreate', $summary);
        $this->assertTrue($summary['canCreate']);
        $this->assertArrayNotHasKey('canView', $summary);
    }

    public function test_permission_summary_with_project_for_owner(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $project = ['id' => '100', 'agency_id' => '1'];

        $summary = $this->guard->getPermissionSummary($owner, $project);

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('canCreate', $summary);
        $this->assertArrayHasKey('canView', $summary);
        $this->assertArrayHasKey('canEdit', $summary);
        $this->assertArrayHasKey('canDelete', $summary);
        $this->assertArrayHasKey('canManageMembers', $summary);
        $this->assertTrue($summary['canCreate']);
        $this->assertTrue($summary['canView']);
        $this->assertTrue($summary['canEdit']);
        $this->assertTrue($summary['canDelete']);
        $this->assertTrue($summary['canManageMembers']);
    }

    public function test_permission_summary_with_project_for_agency(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $project = ['id' => '100', 'agency_id' => '1'];

        $summary = $this->guard->getPermissionSummary($agency, $project);

        $this->assertTrue($summary['canCreate']);
        $this->assertTrue($summary['canView']);
        $this->assertTrue($summary['canEdit']);
        $this->assertFalse($summary['canDelete']);
        $this->assertTrue($summary['canManageMembers']);
    }

    public function test_permission_summary_for_end_client(): void
    {
        $endClient = ['id' => '4', 'role' => 'end_client', 'agency_id' => '1'];
        $project = ['id' => '100', 'agency_id' => '1'];

        $summary = $this->guard->getPermissionSummary($endClient, $project);

        $this->assertFalse($summary['canCreate']);
        $this->assertFalse($summary['canView']);  // Would need project membership check
        $this->assertFalse($summary['canEdit']);
        $this->assertFalse($summary['canDelete']);
        $this->assertFalse($summary['canManageMembers']);
    }

    // ===== Object Conversion Tests =====

    public function test_can_view_with_object_project(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $project = (object) ['id' => '100', 'agency_id' => '2'];

        $this->assertTrue($this->guard->canView($owner, $project));
    }

    public function test_can_edit_with_object_project(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $project = (object) ['id' => '100', 'agency_id' => '2'];

        $this->assertTrue($this->guard->canEdit($owner, $project));
    }

    public function test_can_manage_members_with_object_project(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $project = (object) ['id' => '100', 'agency_id' => '2'];

        $this->assertTrue($this->guard->canManageMembers($owner, $project));
    }
}
