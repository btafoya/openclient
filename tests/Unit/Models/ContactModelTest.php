<?php

namespace Tests\Unit\Models;

use App\Models\ContactModel;
use App\Models\ClientModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class ContactModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $contactModel;
    protected $clientModel;
    protected $migrate = true;
    protected $refresh = true;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        $this->contactModel = new ContactModel();
        $this->clientModel = new ClientModel();
    }

    public function testSearchFindsContactsByName()
    {
        // Arrange: Create test client and contact
        $clientId = $this->clientModel->insert([
            'agency_id' => 'test-agency-id',
            'name' => 'Test Client Corp',
        ]);

        $contactId = $this->contactModel->insert([
            'client_id' => $clientId,
            'agency_id' => 'test-agency-id',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        // Act: Search for contact
        $results = $this->contactModel->search('John');

        // Assert: Contact found in results
        $this->assertNotEmpty($results);
        $this->assertEquals('John', $results[0]['first_name']);
        $this->assertEquals('Doe', $results[0]['last_name']);
    }

    public function testValidationRequiresFirstAndLastName()
    {
        // Arrange: Data without required names
        $data = ['email' => 'test@example.com'];

        // Act: Attempt validation
        $isValid = $this->contactModel->validate($data);

        // Assert: Validation fails
        $this->assertFalse($isValid);
        $this->assertArrayHasKey('first_name', $this->contactModel->errors());
        $this->assertArrayHasKey('last_name', $this->contactModel->errors());
    }

    public function testPrimaryContactLogicUnsetsOtherPrimary()
    {
        // Arrange: Create client and two contacts
        $clientId = $this->clientModel->insert([
            'agency_id' => 'test-agency-id',
            'name' => 'Test Client Corp',
        ]);

        $contact1Id = $this->contactModel->insert([
            'client_id' => $clientId,
            'agency_id' => 'test-agency-id',
            'first_name' => 'First',
            'last_name' => 'Contact',
            'is_primary' => true,
        ]);

        $contact2Id = $this->contactModel->insert([
            'client_id' => $clientId,
            'agency_id' => 'test-agency-id',
            'first_name' => 'Second',
            'last_name' => 'Contact',
            'is_primary' => true,
        ]);

        // Act: Get both contacts
        $contact1 = $this->contactModel->find($contact1Id);
        $contact2 = $this->contactModel->find($contact2Id);

        // Assert: Only second contact is primary
        $this->assertFalse($contact1['is_primary']);
        $this->assertTrue($contact2['is_primary']);
    }

    public function testGetByClientReturnsClientContacts()
    {
        // Arrange: Create two clients with contacts
        $client1Id = $this->clientModel->insert([
            'agency_id' => 'test-agency-id',
            'name' => 'Client One',
        ]);

        $client2Id = $this->clientModel->insert([
            'agency_id' => 'test-agency-id',
            'name' => 'Client Two',
        ]);

        $this->contactModel->insert([
            'client_id' => $client1Id,
            'agency_id' => 'test-agency-id',
            'first_name' => 'Client1',
            'last_name' => 'Contact',
        ]);

        $this->contactModel->insert([
            'client_id' => $client2Id,
            'agency_id' => 'test-agency-id',
            'first_name' => 'Client2',
            'last_name' => 'Contact',
        ]);

        // Act: Get contacts for client 1
        $contacts = $this->contactModel->getByClient($client1Id);

        // Assert: Only client 1's contacts returned
        $this->assertCount(1, $contacts);
        $this->assertEquals('Client1', $contacts[0]['first_name']);
    }

    public function testGetPrimaryContactReturnsCorrectContact()
    {
        // Arrange: Create client with primary and non-primary contacts
        $clientId = $this->clientModel->insert([
            'agency_id' => 'test-agency-id',
            'name' => 'Test Client Corp',
        ]);

        $this->contactModel->insert([
            'client_id' => $clientId,
            'agency_id' => 'test-agency-id',
            'first_name' => 'Non',
            'last_name' => 'Primary',
            'is_primary' => false,
        ]);

        $this->contactModel->insert([
            'client_id' => $clientId,
            'agency_id' => 'test-agency-id',
            'first_name' => 'Primary',
            'last_name' => 'Contact',
            'is_primary' => true,
        ]);

        // Act: Get primary contact
        $primary = $this->contactModel->getPrimaryContact($clientId);

        // Assert: Primary contact returned
        $this->assertNotNull($primary);
        $this->assertEquals('Primary', $primary['first_name']);
        $this->assertTrue($primary['is_primary']);
    }

    public function testGetActiveCountReturnsCorrectCount()
    {
        // Arrange: Create client and active/inactive contacts
        $clientId = $this->clientModel->insert([
            'agency_id' => 'test-agency-id',
            'name' => 'Test Client Corp',
        ]);

        $this->contactModel->insert([
            'client_id' => $clientId,
            'agency_id' => 'test-agency-id',
            'first_name' => 'Active',
            'last_name' => 'Contact',
            'is_active' => true,
        ]);

        $this->contactModel->insert([
            'client_id' => $clientId,
            'agency_id' => 'test-agency-id',
            'first_name' => 'Inactive',
            'last_name' => 'Contact',
            'is_active' => false,
        ]);

        // Act: Get active count
        $count = $this->contactModel->getActiveCount();

        // Assert: Only active contact counted
        $this->assertEquals(1, $count);
    }

    public function testToggleActiveChangesStatus()
    {
        // Arrange: Create contact
        $clientId = $this->clientModel->insert([
            'agency_id' => 'test-agency-id',
            'name' => 'Test Client Corp',
        ]);

        $contactId = $this->contactModel->insert([
            'client_id' => $clientId,
            'agency_id' => 'test-agency-id',
            'first_name' => 'Toggle',
            'last_name' => 'Contact',
            'is_active' => true,
        ]);

        // Act: Toggle status
        $this->contactModel->toggleActive($contactId);
        $contact = $this->contactModel->find($contactId);

        // Assert: Status changed to inactive
        $this->assertFalse($contact['is_active']);
    }

    public function testRestoreRemovesDeletedAt()
    {
        // Arrange: Create and soft-delete contact
        $clientId = $this->clientModel->insert([
            'agency_id' => 'test-agency-id',
            'name' => 'Test Client Corp',
        ]);

        $contactId = $this->contactModel->insert([
            'client_id' => $clientId,
            'agency_id' => 'test-agency-id',
            'first_name' => 'Restore',
            'last_name' => 'Contact',
        ]);

        $this->contactModel->delete($contactId);

        // Act: Restore contact
        $this->contactModel->restore($contactId);
        $contact = $this->contactModel->withDeleted()->find($contactId);

        // Assert: deleted_at is null
        $this->assertNull($contact['deleted_at']);
    }

    public function testGetFullNameCombinesFirstAndLast()
    {
        // Arrange: Contact data
        $contact = [
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];

        // Act: Get full name
        $fullName = $this->contactModel->getFullName($contact);

        // Assert: Names combined correctly
        $this->assertEquals('John Doe', $fullName);
    }

    public function testGetClientContactCountReturnsCorrectCount()
    {
        // Arrange: Create client with multiple contacts
        $clientId = $this->clientModel->insert([
            'agency_id' => 'test-agency-id',
            'name' => 'Test Client Corp',
        ]);

        $this->contactModel->insert([
            'client_id' => $clientId,
            'agency_id' => 'test-agency-id',
            'first_name' => 'Contact',
            'last_name' => 'One',
        ]);

        $this->contactModel->insert([
            'client_id' => $clientId,
            'agency_id' => 'test-agency-id',
            'first_name' => 'Contact',
            'last_name' => 'Two',
        ]);

        $this->contactModel->insert([
            'client_id' => $clientId,
            'agency_id' => 'test-agency-id',
            'first_name' => 'Contact',
            'last_name' => 'Three',
        ]);

        // Act: Get contact count
        $count = $this->contactModel->getClientContactCount($clientId);

        // Assert: Correct count returned
        $this->assertEquals(3, $count);
    }
}
