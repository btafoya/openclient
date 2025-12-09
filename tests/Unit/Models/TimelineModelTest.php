<?php

namespace Tests\Unit\Models;

use App\Models\TimelineModel;
use App\Models\ClientModel;
use App\Models\ContactModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class TimelineModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $timelineModel;
    protected $clientModel;
    protected $contactModel;
    protected $userModel;
    protected $migrate = true;
    protected $refresh = true;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        $this->timelineModel = new TimelineModel();
        $this->clientModel = new ClientModel();
        $this->contactModel = new ContactModel();
        $this->userModel = new UserModel();
    }

    public function testLogEventCreatesTimelineEntry()
    {
        // Arrange
        $userId = $this->createTestUser();
        $clientId = $this->createTestClient();

        // Act
        $timelineId = $this->timelineModel->logEvent(
            userId: $userId,
            entityType: 'client',
            entityId: $clientId,
            eventType: 'created',
            description: 'Created client: Test Client'
        );

        // Assert
        $this->assertIsString($timelineId);
        $entry = $this->timelineModel->find($timelineId);
        $this->assertNotNull($entry);
        $this->assertEquals('client', $entry['entity_type']);
        $this->assertEquals('created', $entry['event_type']);
        $this->assertEquals('Created client: Test Client', $entry['description']);
    }

    public function testLogEventWithMetadata()
    {
        // Arrange
        $userId = $this->createTestUser();
        $clientId = $this->createTestClient();
        $metadata = ['changed_fields' => ['name', 'email']];

        // Act
        $timelineId = $this->timelineModel->logEvent(
            userId: $userId,
            entityType: 'client',
            entityId: $clientId,
            eventType: 'updated',
            description: 'Updated client',
            metadata: $metadata
        );

        // Assert
        $entry = $this->timelineModel->find($timelineId);
        $this->assertNotNull($entry);
        $this->assertEquals($metadata, $entry['metadata']);
    }

    public function testGetByEntityReturnsEntityTimeline()
    {
        // Arrange
        $userId = $this->createTestUser();
        $client1Id = $this->createTestClient();
        $client2Id = $this->createTestClient('Other Client');

        $this->timelineModel->logEvent($userId, 'client', $client1Id, 'created', 'Created client 1');
        $this->timelineModel->logEvent($userId, 'client', $client1Id, 'updated', 'Updated client 1');
        $this->timelineModel->logEvent($userId, 'client', $client2Id, 'created', 'Created client 2');

        // Act
        $timeline = $this->timelineModel->getByEntity('client', $client1Id);

        // Assert
        $this->assertCount(2, $timeline);
        $this->assertEquals('Updated client 1', $timeline[0]['description']); // Most recent first
        $this->assertEquals('Created client 1', $timeline[1]['description']);
    }

    public function testGetByUserReturnsUserTimeline()
    {
        // Arrange
        $user1Id = $this->createTestUser('user1@example.com');
        $user2Id = $this->createTestUser('user2@example.com');
        $clientId = $this->createTestClient();

        $this->timelineModel->logEvent($user1Id, 'client', $clientId, 'created', 'User 1 created client');
        $this->timelineModel->logEvent($user1Id, 'client', $clientId, 'updated', 'User 1 updated client');
        $this->timelineModel->logEvent($user2Id, 'client', $clientId, 'updated', 'User 2 updated client');

        // Act
        $timeline = $this->timelineModel->getByUser($user1Id);

        // Assert
        $this->assertCount(2, $timeline);
        foreach ($timeline as $entry) {
            $this->assertEquals($user1Id, $entry['user_id']);
        }
    }

    public function testGetByEventTypeReturnsFilteredTimeline()
    {
        // Arrange
        $userId = $this->createTestUser();
        $clientId = $this->createTestClient();

        $this->timelineModel->logEvent($userId, 'client', $clientId, 'created', 'Created');
        $this->timelineModel->logEvent($userId, 'client', $clientId, 'updated', 'Updated');
        $this->timelineModel->logEvent($userId, 'client', $clientId, 'updated', 'Updated again');

        // Act
        $timeline = $this->timelineModel->getByEventType('updated');

        // Assert
        $this->assertCount(2, $timeline);
        foreach ($timeline as $entry) {
            $this->assertEquals('updated', $entry['event_type']);
        }
    }

    public function testGetForAgencyWithFilters()
    {
        // Arrange
        $userId = $this->createTestUser();
        $clientId = $this->createTestClient();

        $this->timelineModel->logEvent($userId, 'client', $clientId, 'created', 'Created client');
        $this->timelineModel->logEvent($userId, 'client', $clientId, 'updated', 'Updated client');
        $this->timelineModel->logEvent($userId, 'contact', 'contact-id', 'created', 'Created contact');

        // Act: Filter by entity_type
        $timeline = $this->timelineModel->getForAgency(100, ['entity_type' => 'client']);

        // Assert
        $this->assertCount(2, $timeline);
        foreach ($timeline as $entry) {
            $this->assertEquals('client', $entry['entity_type']);
        }
    }

    public function testGetForAgencyWithSearchFilter()
    {
        // Arrange
        $userId = $this->createTestUser();
        $clientId = $this->createTestClient();

        $this->timelineModel->logEvent($userId, 'client', $clientId, 'created', 'Important client created');
        $this->timelineModel->logEvent($userId, 'client', $clientId, 'updated', 'Random update');

        // Act
        $timeline = $this->timelineModel->getForAgency(100, ['search' => 'Important']);

        // Assert
        $this->assertCount(1, $timeline);
        $this->assertStringContainsString('Important', $timeline[0]['description']);
    }

    public function testGetRecentWithDetailsIncludesEntityInfo()
    {
        // Arrange
        $userId = $this->createTestUser();
        $clientId = $this->createTestClient('Test Client');

        $this->timelineModel->logEvent($userId, 'client', $clientId, 'created', 'Created client');

        // Act
        $timeline = $this->timelineModel->getRecentWithDetails(10);

        // Assert
        $this->assertCount(1, $timeline);
        $this->assertEquals('Test Client', $timeline[0]['entity_name']);
        $this->assertStringContainsString('/clients/', $timeline[0]['entity_url']);
    }

    public function testGetEntityNameReturnsCorrectNames()
    {
        // Arrange
        $clientId = $this->createTestClient('Test Client');
        $contactId = $this->createTestContact($clientId, 'John', 'Doe');

        // Act
        $clientName = $this->timelineModel->getEntityName('client', $clientId);
        $contactName = $this->timelineModel->getEntityName('contact', $contactId);

        // Assert
        $this->assertEquals('Test Client', $clientName);
        $this->assertEquals('John Doe', $contactName);
    }

    public function testGetEntityUrlReturnsCorrectUrls()
    {
        // Arrange
        $clientId = 'test-client-id';
        $contactId = 'test-contact-id';

        // Act
        $clientUrl = $this->timelineModel->getEntityUrl('client', $clientId);
        $contactUrl = $this->timelineModel->getEntityUrl('contact', $contactId);

        // Assert
        $this->assertStringContainsString('/clients/test-client-id', $clientUrl);
        $this->assertStringContainsString('/contacts/test-contact-id', $contactUrl);
    }

    public function testGetStatisticsReturnsEventCounts()
    {
        // Arrange
        $userId = $this->createTestUser();
        $clientId = $this->createTestClient();

        $this->timelineModel->logEvent($userId, 'client', $clientId, 'created', 'Created');
        $this->timelineModel->logEvent($userId, 'client', $clientId, 'updated', 'Updated');
        $this->timelineModel->logEvent($userId, 'client', $clientId, 'updated', 'Updated again');

        // Act
        $stats = $this->timelineModel->getStatistics();

        // Assert
        $this->assertEquals(3, $stats['total_events']);
        $this->assertCount(2, $stats['by_event_type']); // created, updated
        $this->assertCount(1, $stats['by_entity_type']); // client only
    }

    public function testGetStatisticsWithFilters()
    {
        // Arrange
        $userId = $this->createTestUser();
        $clientId = $this->createTestClient();

        $this->timelineModel->logEvent($userId, 'client', $clientId, 'created', 'Created');
        $this->timelineModel->logEvent($userId, 'client', $clientId, 'updated', 'Updated');
        $this->timelineModel->logEvent($userId, 'contact', 'contact-id', 'created', 'Created contact');

        // Act
        $stats = $this->timelineModel->getStatistics(['entity_type' => 'client']);

        // Assert
        $this->assertEquals(2, $stats['total_events']);
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

    private function createTestContact(string $clientId, string $firstName = 'Test', string $lastName = 'Contact'): string
    {
        return $this->contactModel->insert([
            'client_id' => $clientId,
            'agency_id' => 'test-agency-id',
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
    }
}
