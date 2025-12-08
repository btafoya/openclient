<?php

namespace Tests\Unit\Domain\Authorization;

use App\Domain\Invoices\Authorization\InvoiceGuard;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for InvoiceGuard
 *
 * Tests fine-grained authorization logic for invoice operations.
 * Mock database interactions to isolate business logic testing.
 */
class InvoiceGuardTest extends TestCase
{
    private InvoiceGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new InvoiceGuard();
    }

    // ===== canView() Tests =====

    public function test_owner_can_view_any_invoice(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $invoice = ['id' => '100', 'agency_id' => '2', 'client_id' => '50'];

        $this->assertTrue($this->guard->canView($owner, $invoice));
    }

    public function test_end_client_cannot_view_any_invoice(): void
    {
        $endClient = ['id' => '5', 'role' => 'end_client', 'agency_id' => '1'];
        $invoice = ['id' => '100', 'agency_id' => '1', 'client_id' => '50'];

        $this->assertFalse($this->guard->canView($endClient, $invoice));
    }

    public function test_agency_can_view_own_agency_invoice(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $invoice = ['id' => '100', 'agency_id' => '1', 'client_id' => '50'];

        $this->assertTrue($this->guard->canView($agency, $invoice));
    }

    public function test_agency_cannot_view_other_agency_invoice(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $invoice = ['id' => '100', 'agency_id' => '2', 'client_id' => '50'];

        $this->assertFalse($this->guard->canView($agency, $invoice));
    }

    public function test_agency_cannot_view_invoice_without_agency_id(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $invoice = ['id' => '100', 'client_id' => '50'];  // Missing agency_id

        $this->assertFalse($this->guard->canView($agency, $invoice));
    }

    public function test_direct_client_cannot_view_invoice_without_client_id(): void
    {
        $directClient = ['id' => '3', 'role' => 'direct_client', 'agency_id' => '1'];
        $invoice = ['id' => '100', 'agency_id' => '1'];  // Missing client_id

        $this->assertFalse($this->guard->canView($directClient, $invoice));
    }

    public function test_unknown_role_cannot_view_invoice(): void
    {
        $unknownUser = ['id' => '10', 'role' => 'unknown_role', 'agency_id' => '1'];
        $invoice = ['id' => '100', 'agency_id' => '1', 'client_id' => '50'];

        $this->assertFalse($this->guard->canView($unknownUser, $invoice));
    }

    // ===== canCreate() Tests =====

    public function test_owner_can_create_invoices(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];

        $this->assertTrue($this->guard->canCreate($owner));
    }

    public function test_agency_can_create_invoices(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];

        $this->assertTrue($this->guard->canCreate($agency));
    }

    public function test_direct_client_cannot_create_invoices(): void
    {
        $directClient = ['id' => '3', 'role' => 'direct_client', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canCreate($directClient));
    }

    public function test_end_client_cannot_create_invoices(): void
    {
        $endClient = ['id' => '4', 'role' => 'end_client', 'agency_id' => '1'];

        $this->assertFalse($this->guard->canCreate($endClient));
    }

    // ===== canEdit() Tests =====

    public function test_owner_can_edit_any_invoice(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $invoice = ['id' => '100', 'agency_id' => '2', 'client_id' => '50'];

        $this->assertTrue($this->guard->canEdit($owner, $invoice));
    }

    public function test_agency_can_edit_own_agency_invoice(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $invoice = ['id' => '100', 'agency_id' => '1', 'client_id' => '50'];

        $this->assertTrue($this->guard->canEdit($agency, $invoice));
    }

    public function test_agency_cannot_edit_other_agency_invoice(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $invoice = ['id' => '100', 'agency_id' => '2', 'client_id' => '50'];

        $this->assertFalse($this->guard->canEdit($agency, $invoice));
    }

    public function test_direct_client_cannot_edit_invoices(): void
    {
        $directClient = ['id' => '3', 'role' => 'direct_client', 'agency_id' => '1'];
        $invoice = ['id' => '100', 'agency_id' => '1', 'client_id' => '50'];

        $this->assertFalse($this->guard->canEdit($directClient, $invoice));
    }

    public function test_end_client_cannot_edit_invoices(): void
    {
        $endClient = ['id' => '4', 'role' => 'end_client', 'agency_id' => '1'];
        $invoice = ['id' => '100', 'agency_id' => '1', 'client_id' => '50'];

        $this->assertFalse($this->guard->canEdit($endClient, $invoice));
    }

    // ===== canDelete() Tests =====

    public function test_owner_can_delete_any_invoice(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $invoice = ['id' => '100', 'agency_id' => '2', 'client_id' => '50'];

        $this->assertTrue($this->guard->canDelete($owner, $invoice));
    }

    public function test_agency_cannot_delete_invoices(): void
    {
        $agency = ['id' => '2', 'role' => 'agency', 'agency_id' => '1'];
        $invoice = ['id' => '100', 'agency_id' => '1', 'client_id' => '50'];

        $this->assertFalse($this->guard->canDelete($agency, $invoice));
    }

    public function test_direct_client_cannot_delete_invoices(): void
    {
        $directClient = ['id' => '3', 'role' => 'direct_client', 'agency_id' => '1'];
        $invoice = ['id' => '100', 'agency_id' => '1', 'client_id' => '50'];

        $this->assertFalse($this->guard->canDelete($directClient, $invoice));
    }

    public function test_end_client_cannot_delete_invoices(): void
    {
        $endClient = ['id' => '4', 'role' => 'end_client', 'agency_id' => '1'];
        $invoice = ['id' => '100', 'agency_id' => '1', 'client_id' => '50'];

        $this->assertFalse($this->guard->canDelete($endClient, $invoice));
    }

    // ===== getPermissionSummary() Tests =====

    public function test_permission_summary_without_invoice(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];

        $summary = $this->guard->getPermissionSummary($owner);

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('canCreate', $summary);
        $this->assertTrue($summary['canCreate']);

        // When no invoice provided, resource-specific permissions are null
        $this->assertArrayHasKey('canView', $summary);
        $this->assertNull($summary['canView']);
        $this->assertNull($summary['canEdit']);
        $this->assertNull($summary['canDelete']);
    }

    public function test_permission_summary_with_invoice(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $invoice = ['id' => '100', 'agency_id' => '1', 'client_id' => '50'];

        $summary = $this->guard->getPermissionSummary($owner, $invoice);

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('canCreate', $summary);
        $this->assertArrayHasKey('canView', $summary);
        $this->assertArrayHasKey('canEdit', $summary);
        $this->assertArrayHasKey('canDelete', $summary);
        $this->assertTrue($summary['canCreate']);
        $this->assertTrue($summary['canView']);
        $this->assertTrue($summary['canEdit']);
        $this->assertTrue($summary['canDelete']);
    }

    public function test_permission_summary_for_end_client(): void
    {
        $endClient = ['id' => '4', 'role' => 'end_client', 'agency_id' => '1'];
        $invoice = ['id' => '100', 'agency_id' => '1', 'client_id' => '50'];

        $summary = $this->guard->getPermissionSummary($endClient, $invoice);

        $this->assertFalse($summary['canCreate']);
        $this->assertFalse($summary['canView']);
        $this->assertFalse($summary['canEdit']);
        $this->assertFalse($summary['canDelete']);
    }

    // ===== Object Conversion Tests =====

    public function test_can_view_with_object_invoice(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $invoice = (object) ['id' => '100', 'agency_id' => '2', 'client_id' => '50'];

        $this->assertTrue($this->guard->canView($owner, $invoice));
    }

    public function test_can_edit_with_object_invoice(): void
    {
        $owner = ['id' => '1', 'role' => 'owner', 'agency_id' => '1'];
        $invoice = (object) ['id' => '100', 'agency_id' => '2', 'client_id' => '50'];

        $this->assertTrue($this->guard->canEdit($owner, $invoice));
    }
}
