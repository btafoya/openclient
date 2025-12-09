<?php

namespace Tests\Unit\Models;

use App\Models\CsvImportModel;
use App\Models\ClientModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class CsvImportModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $csvImportModel;
    protected $clientModel;
    protected $userModel;
    protected $migrate = true;
    protected $refresh = true;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        $this->csvImportModel = new CsvImportModel();
        $this->clientModel = new ClientModel();
        $this->userModel = new UserModel();
    }

    protected function tearDown(): void
    {
        // Clean up test files
        $uploadDir = WRITEPATH . 'uploads/csv/';
        if (is_dir($uploadDir)) {
            $files = glob($uploadDir . '*.csv');
            foreach ($files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
        parent::tearDown();
    }

    public function testCreateImportRecord()
    {
        // Arrange
        $userId = $this->createTestUser();

        // Act
        $importId = $this->csvImportModel->insert([
            'user_id' => $userId,
            'entity_type' => 'clients',
            'filename' => 'test.csv',
            'file_path' => '/tmp/test.csv',
            'file_size' => 1024,
            'status' => 'pending',
        ]);

        // Assert
        $this->assertIsString($importId);
        $import = $this->csvImportModel->find($importId);
        $this->assertNotNull($import);
        $this->assertEquals('clients', $import['entity_type']);
        $this->assertEquals('pending', $import['status']);
    }

    public function testGetEntityFieldMapping()
    {
        // Act
        $clientMapping = $this->csvImportModel->getEntityFieldMapping('clients');
        $contactMapping = $this->csvImportModel->getEntityFieldMapping('contacts');
        $noteMapping = $this->csvImportModel->getEntityFieldMapping('notes');

        // Assert
        $this->assertIsArray($clientMapping);
        $this->assertArrayHasKey('required', $clientMapping);
        $this->assertArrayHasKey('optional', $clientMapping);
        $this->assertContains('name', $clientMapping['required']);

        $this->assertIsArray($contactMapping);
        $this->assertContains('first_name', $contactMapping['required']);
        $this->assertContains('last_name', $contactMapping['required']);

        $this->assertIsArray($noteMapping);
        $this->assertContains('content', $noteMapping['required']);
    }

    public function testValidateHeadersSuccess()
    {
        // Arrange
        $headers = ['name', 'email', 'phone'];

        // Act
        $result = $this->csvImportModel->validateHeaders('clients', $headers);

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateHeadersMissingRequired()
    {
        // Arrange
        $headers = ['email', 'phone']; // Missing 'name'

        // Act
        $result = $this->csvImportModel->validateHeaders('clients', $headers);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('name', $result['errors'][0]);
    }

    public function testValidateRowSuccess()
    {
        // Arrange
        $row = ['name' => 'Test Client', 'email' => 'test@example.com'];

        // Act
        $result = $this->csvImportModel->validateRow('clients', $row, 1);

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateRowMissingRequired()
    {
        // Arrange
        $row = ['email' => 'test@example.com']; // Missing 'name'

        // Act
        $result = $this->csvImportModel->validateRow('clients', $row, 1);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testValidateRowInvalidEmail()
    {
        // Arrange
        $row = ['name' => 'Test Client', 'email' => 'invalid-email'];

        // Act
        $result = $this->csvImportModel->validateRow('clients', $row, 1);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('email', $result['errors'][0]);
    }

    public function testProcessImportSuccess()
    {
        // Arrange
        $userId = $this->createTestUser();
        $csvFile = $this->createTestCsvFile([
            ['name', 'email', 'phone'],
            ['Client 1', 'client1@example.com', '555-1111'],
            ['Client 2', 'client2@example.com', '555-2222'],
        ]);

        $importId = $this->csvImportModel->insert([
            'user_id' => $userId,
            'entity_type' => 'clients',
            'filename' => 'test.csv',
            'file_path' => $csvFile,
            'file_size' => filesize($csvFile),
            'status' => 'pending',
            'import_options' => ['skip_duplicates' => false, 'update_existing' => false],
        ]);

        // Act
        $result = $this->csvImportModel->processImport($importId);

        // Assert
        $this->assertTrue($result);
        $import = $this->csvImportModel->find($importId);
        $this->assertEquals('completed', $import['status']);
        $this->assertEquals(2, $import['processed_rows']);
        $this->assertEquals(0, $import['failed_rows']);
    }

    public function testProcessImportWithValidationErrors()
    {
        // Arrange
        $userId = $this->createTestUser();
        $csvFile = $this->createTestCsvFile([
            ['name', 'email'],
            ['Client 1', 'valid@example.com'],
            ['', 'invalid-email'], // Missing name, invalid email
        ]);

        $importId = $this->csvImportModel->insert([
            'user_id' => $userId,
            'entity_type' => 'clients',
            'filename' => 'test.csv',
            'file_path' => $csvFile,
            'file_size' => filesize($csvFile),
            'status' => 'pending',
            'import_options' => ['skip_duplicates' => false, 'update_existing' => false],
        ]);

        // Act
        $result = $this->csvImportModel->processImport($importId);

        // Assert
        $this->assertTrue($result);
        $import = $this->csvImportModel->find($importId);
        $this->assertEquals('completed', $import['status']);
        $this->assertEquals(1, $import['processed_rows']);
        $this->assertEquals(1, $import['failed_rows']);
        $this->assertNotEmpty($import['validation_errors']);
    }

    public function testProcessImportSkipDuplicates()
    {
        // Arrange
        $userId = $this->createTestUser();

        // Create existing client
        $this->clientModel->insert([
            'agency_id' => 'test-agency-id',
            'name' => 'Existing Client',
            'email' => 'existing@example.com',
        ]);

        $csvFile = $this->createTestCsvFile([
            ['name', 'email'],
            ['Existing Client', 'existing@example.com'], // Duplicate
            ['New Client', 'new@example.com'],
        ]);

        $importId = $this->csvImportModel->insert([
            'user_id' => $userId,
            'entity_type' => 'clients',
            'filename' => 'test.csv',
            'file_path' => $csvFile,
            'file_size' => filesize($csvFile),
            'status' => 'pending',
            'import_options' => ['skip_duplicates' => true, 'update_existing' => false],
        ]);

        // Act
        $result = $this->csvImportModel->processImport($importId);

        // Assert
        $this->assertTrue($result);
        $import = $this->csvImportModel->find($importId);
        $this->assertEquals(1, $import['processed_rows']); // Only new client imported
    }

    public function testGetHistory()
    {
        // Arrange
        $userId = $this->createTestUser();

        $this->csvImportModel->insert([
            'user_id' => $userId,
            'entity_type' => 'clients',
            'filename' => 'import1.csv',
            'file_path' => '/tmp/import1.csv',
            'file_size' => 1024,
            'status' => 'completed',
        ]);

        $this->csvImportModel->insert([
            'user_id' => $userId,
            'entity_type' => 'contacts',
            'filename' => 'import2.csv',
            'file_path' => '/tmp/import2.csv',
            'file_size' => 2048,
            'status' => 'pending',
        ]);

        // Act
        $history = $this->csvImportModel->getHistory();

        // Assert
        $this->assertCount(2, $history);
        $this->assertEquals('import2.csv', $history[0]['filename']); // Most recent first
    }

    public function testGetByStatus()
    {
        // Arrange
        $userId = $this->createTestUser();

        $this->csvImportModel->insert([
            'user_id' => $userId,
            'entity_type' => 'clients',
            'filename' => 'completed.csv',
            'file_path' => '/tmp/completed.csv',
            'file_size' => 1024,
            'status' => 'completed',
        ]);

        $this->csvImportModel->insert([
            'user_id' => $userId,
            'entity_type' => 'clients',
            'filename' => 'pending.csv',
            'file_path' => '/tmp/pending.csv',
            'file_size' => 1024,
            'status' => 'pending',
        ]);

        // Act
        $completed = $this->csvImportModel->getByStatus('completed');
        $pending = $this->csvImportModel->getByStatus('pending');

        // Assert
        $this->assertCount(1, $completed);
        $this->assertEquals('completed.csv', $completed[0]['filename']);
        $this->assertCount(1, $pending);
        $this->assertEquals('pending.csv', $pending[0]['filename']);
    }

    public function testGetByEntityType()
    {
        // Arrange
        $userId = $this->createTestUser();

        $this->csvImportModel->insert([
            'user_id' => $userId,
            'entity_type' => 'clients',
            'filename' => 'clients.csv',
            'file_path' => '/tmp/clients.csv',
            'file_size' => 1024,
            'status' => 'completed',
        ]);

        $this->csvImportModel->insert([
            'user_id' => $userId,
            'entity_type' => 'contacts',
            'filename' => 'contacts.csv',
            'file_path' => '/tmp/contacts.csv',
            'file_size' => 1024,
            'status' => 'completed',
        ]);

        // Act
        $clientImports = $this->csvImportModel->getByEntityType('clients');
        $contactImports = $this->csvImportModel->getByEntityType('contacts');

        // Assert
        $this->assertCount(1, $clientImports);
        $this->assertEquals('clients.csv', $clientImports[0]['filename']);
        $this->assertCount(1, $contactImports);
        $this->assertEquals('contacts.csv', $contactImports[0]['filename']);
    }

    public function testCancelImport()
    {
        // Arrange
        $userId = $this->createTestUser();
        $importId = $this->csvImportModel->insert([
            'user_id' => $userId,
            'entity_type' => 'clients',
            'filename' => 'test.csv',
            'file_path' => '/tmp/test.csv',
            'file_size' => 1024,
            'status' => 'pending',
        ]);

        // Act
        $result = $this->csvImportModel->cancelImport($importId);

        // Assert
        $this->assertTrue($result);
        $import = $this->csvImportModel->find($importId);
        $this->assertEquals('cancelled', $import['status']);
    }

    public function testGetStatistics()
    {
        // Arrange
        $userId = $this->createTestUser();

        $this->csvImportModel->insert([
            'user_id' => $userId,
            'entity_type' => 'clients',
            'filename' => 'import1.csv',
            'file_path' => '/tmp/import1.csv',
            'file_size' => 1024,
            'status' => 'completed',
            'processed_rows' => 100,
            'failed_rows' => 5,
        ]);

        $this->csvImportModel->insert([
            'user_id' => $userId,
            'entity_type' => 'contacts',
            'filename' => 'import2.csv',
            'file_path' => '/tmp/import2.csv',
            'file_size' => 2048,
            'status' => 'completed',
            'processed_rows' => 200,
            'failed_rows' => 10,
        ]);

        // Act
        $stats = $this->csvImportModel->getStatistics();

        // Assert
        $this->assertEquals(2, $stats['total_imports']);
        $this->assertEquals(300, $stats['total_rows_processed']);
        $this->assertEquals(15, $stats['total_rows_failed']);
        $this->assertArrayHasKey('by_status', $stats);
        $this->assertArrayHasKey('by_entity_type', $stats);
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

    private function createTestCsvFile(array $data): string
    {
        $uploadDir = WRITEPATH . 'uploads/csv/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = $uploadDir . 'test_' . uniqid() . '.csv';
        $handle = fopen($filename, 'w');

        foreach ($data as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $filename;
    }
}
