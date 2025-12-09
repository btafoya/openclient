<?php

namespace Tests\Unit\Models;

use App\Models\NoteModel;
use App\Models\ClientModel;
use App\Models\ContactModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class NoteModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $noteModel;
    protected $clientModel;
    protected $contactModel;
    protected $userModel;
    protected $migrate = true;
    protected $refresh = true;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        $this->noteModel = new NoteModel();
        $this->clientModel = new ClientModel();
        $this->contactModel = new ContactModel();
        $this->userModel = new UserModel();
    }

    public function testValidationRequiresContent()
    {
        // Arrange: Data without required content
        $data = ['subject' => 'Test Subject'];

        // Act: Attempt validation
        $isValid = $this->noteModel->validate($data);

        // Assert: Validation fails
        $this->assertFalse($isValid);
        $this->assertArrayHasKey('content', $this->noteModel->errors());
    }

    public function testGetByClientReturnsClientNotes()
    {
        // Arrange: Create user, client, and notes
        $userId = $this->createTestUser();
        $client1Id = $this->createTestClient();
        $client2Id = $this->createTestClient('Other Client');

        $this->noteModel->insert([
            'client_id' => $client1Id,
            'user_id' => $userId,
            'content' => 'Note for client 1',
            'agency_id' => 'test-agency-id',
        ]);

        $this->noteModel->insert([
            'client_id' => $client2Id,
            'user_id' => $userId,
            'content' => 'Note for client 2',
            'agency_id' => 'test-agency-id',
        ]);

        // Act: Get notes for client 1
        $notes = $this->noteModel->getByClient($client1Id);

        // Assert: Only client 1's notes returned
        $this->assertCount(1, $notes);
        $this->assertEquals('Note for client 1', $notes[0]['content']);
    }

    public function testGetByContactReturnsContactNotes()
    {
        // Arrange: Create user, client, contact, and notes
        $userId = $this->createTestUser();
        $clientId = $this->createTestClient();
        $contactId = $this->createTestContact($clientId);

        $this->noteModel->insert([
            'contact_id' => $contactId,
            'user_id' => $userId,
            'content' => 'Contact note',
            'agency_id' => 'test-agency-id',
        ]);

        $this->noteModel->insert([
            'client_id' => $clientId,
            'user_id' => $userId,
            'content' => 'Client note',
            'agency_id' => 'test-agency-id',
        ]);

        // Act: Get notes for contact
        $notes = $this->noteModel->getByContact($contactId);

        // Assert: Only contact's notes returned
        $this->assertCount(1, $notes);
        $this->assertEquals('Contact note', $notes[0]['content']);
    }

    public function testSearchFindsNotesByContent()
    {
        // Arrange: Create user, client, and notes
        $userId = $this->createTestUser();
        $clientId = $this->createTestClient();

        $this->noteModel->insert([
            'client_id' => $clientId,
            'user_id' => $userId,
            'content' => 'Important meeting notes',
            'agency_id' => 'test-agency-id',
        ]);

        $this->noteModel->insert([
            'client_id' => $clientId,
            'user_id' => $userId,
            'content' => 'Random thoughts',
            'agency_id' => 'test-agency-id',
        ]);

        // Act: Search for notes
        $results = $this->noteModel->search('meeting');

        // Assert: Found matching note
        $this->assertCount(1, $results);
        $this->assertStringContainsString('meeting', $results[0]['content']);
    }

    public function testTogglePinChangesStatus()
    {
        // Arrange: Create user, client, and note
        $userId = $this->createTestUser();
        $clientId = $this->createTestClient();

        $noteId = $this->noteModel->insert([
            'client_id' => $clientId,
            'user_id' => $userId,
            'content' => 'Test note',
            'is_pinned' => false,
            'agency_id' => 'test-agency-id',
        ]);

        // Act: Toggle pin
        $this->noteModel->togglePin($noteId);
        $note = $this->noteModel->find($noteId);

        // Assert: Status changed to pinned
        $this->assertTrue($note['is_pinned']);
    }

    public function testGetPinnedReturnsOnlyPinnedNotes()
    {
        // Arrange: Create user, client, and notes
        $userId = $this->createTestUser();
        $clientId = $this->createTestClient();

        $this->noteModel->insert([
            'client_id' => $clientId,
            'user_id' => $userId,
            'content' => 'Pinned note',
            'is_pinned' => true,
            'agency_id' => 'test-agency-id',
        ]);

        $this->noteModel->insert([
            'client_id' => $clientId,
            'user_id' => $userId,
            'content' => 'Regular note',
            'is_pinned' => false,
            'agency_id' => 'test-agency-id',
        ]);

        // Act: Get pinned notes
        $pinned = $this->noteModel->getPinned();

        // Assert: Only pinned note returned
        $this->assertCount(1, $pinned);
        $this->assertEquals('Pinned note', $pinned[0]['content']);
    }

    public function testGetEntityTypeReturnsCorrectType()
    {
        // Arrange: Notes with different entity types
        $clientNote = ['client_id' => 'abc-123', 'contact_id' => null, 'project_id' => null];
        $contactNote = ['client_id' => null, 'contact_id' => 'def-456', 'project_id' => null];
        $projectNote = ['client_id' => null, 'contact_id' => null, 'project_id' => 'ghi-789'];

        // Act: Get entity types
        $clientType = $this->noteModel->getEntityType($clientNote);
        $contactType = $this->noteModel->getEntityType($contactNote);
        $projectType = $this->noteModel->getEntityType($projectNote);

        // Assert: Correct types returned
        $this->assertEquals('client', $clientType);
        $this->assertEquals('contact', $contactType);
        $this->assertEquals('project', $projectType);
    }

    public function testRestoreRemovesDeletedAt()
    {
        // Arrange: Create and soft-delete note
        $userId = $this->createTestUser();
        $clientId = $this->createTestClient();

        $noteId = $this->noteModel->insert([
            'client_id' => $clientId,
            'user_id' => $userId,
            'content' => 'Restore note',
            'agency_id' => 'test-agency-id',
        ]);

        $this->noteModel->delete($noteId);

        // Act: Restore note
        $this->noteModel->restore($noteId);
        $note = $this->noteModel->withDeleted()->find($noteId);

        // Assert: deleted_at is null
        $this->assertNull($note['deleted_at']);
    }

    public function testGetByUserReturnsUserNotes()
    {
        // Arrange: Create two users, client, and notes
        $user1Id = $this->createTestUser('user1@example.com');
        $user2Id = $this->createTestUser('user2@example.com');
        $clientId = $this->createTestClient();

        $this->noteModel->insert([
            'client_id' => $clientId,
            'user_id' => $user1Id,
            'content' => 'User 1 note',
            'agency_id' => 'test-agency-id',
        ]);

        $this->noteModel->insert([
            'client_id' => $clientId,
            'user_id' => $user2Id,
            'content' => 'User 2 note',
            'agency_id' => 'test-agency-id',
        ]);

        // Act: Get notes by user 1
        $notes = $this->noteModel->getByUser($user1Id);

        // Assert: Only user 1's notes returned
        $this->assertCount(1, $notes);
        $this->assertEquals('User 1 note', $notes[0]['content']);
    }

    // Helper methods
    private function createTestUser(string $email = 'test@example.com'): string
    {
        return $this->userModel->insert([
            'agency_id' => 'test-agency-id',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'agency',
        ]);
    }

    private function createTestClient(string $name = 'Test Client'): string
    {
        return $this->clientModel->insert([
            'agency_id' => 'test-agency-id',
            'name' => $name,
        ]);
    }

    private function createTestContact(string $clientId): string
    {
        return $this->contactModel->insert([
            'client_id' => $clientId,
            'agency_id' => 'test-agency-id',
            'first_name' => 'Test',
            'last_name' => 'Contact',
        ]);
    }
}
