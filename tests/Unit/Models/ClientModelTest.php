<?php

namespace Tests\Unit\Models;

use App\Models\ClientModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class ClientModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $model;
    protected $migrate = true;
    protected $refresh = true;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new ClientModel();
    }

    public function testSearchFindsClientsByName()
    {
        // Arrange: Create test client
        $clientId = $this->model->insert([
            'agency_id' => 'test-agency-id',
            'name' => 'Test Client Corp',
            'email' => 'test@example.com',
        ]);

        // Act: Search for client
        $results = $this->model->search('Test Client');

        // Assert: Client found in results
        $this->assertNotEmpty($results);
        $this->assertEquals('Test Client Corp', $results[0]['name']);
    }

    public function testValidationRequiresName()
    {
        // Arrange: Data without required name
        $data = ['email' => 'test@example.com'];

        // Act: Attempt validation
        $isValid = $this->model->validate($data);

        // Assert: Validation fails
        $this->assertFalse($isValid);
        $this->assertArrayHasKey('name', $this->model->errors());
    }

    public function testGetActiveCountReturnsCorrectCount()
    {
        // Arrange: Create active and inactive clients
        $this->model->insert([
            'agency_id' => 'test-agency-id',
            'name' => 'Active Client',
            'is_active' => true,
        ]);

        $this->model->insert([
            'agency_id' => 'test-agency-id',
            'name' => 'Inactive Client',
            'is_active' => false,
        ]);

        // Act: Get active count
        $count = $this->model->getActiveCount();

        // Assert: Only active client counted
        $this->assertEquals(1, $count);
    }

    public function testToggleActiveChangesStatus()
    {
        // Arrange: Create client
        $clientId = $this->model->insert([
            'agency_id' => 'test-agency-id',
            'name' => 'Toggle Client',
            'is_active' => true,
        ]);

        // Act: Toggle status
        $this->model->toggleActive($clientId);
        $client = $this->model->find($clientId);

        // Assert: Status changed to inactive
        $this->assertFalse($client['is_active']);
    }

    public function testRestoreRemovesDeletedAt()
    {
        // Arrange: Create and soft-delete client
        $clientId = $this->model->insert([
            'agency_id' => 'test-agency-id',
            'name' => 'Restore Client',
        ]);

        $this->model->delete($clientId);

        // Act: Restore client
        $this->model->restore($clientId);
        $client = $this->model->withDeleted()->find($clientId);

        // Assert: deleted_at is null
        $this->assertNull($client['deleted_at']);
    }
}
