<?php

namespace Tests\Services;

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use App\Services\RepositoryService;

class RepositoryServiceTest extends TestCase
{
    private $conn;
    private $service;
    private $testUserId;
    private $testRepoId;

    protected function setUp(): void
    {
        $this->conn = new \mysqli('localhost', 'root', '', 'lab6');
        $this->conn->query("SET sql_mode = 'STRICT_ALL_TABLES'");
        $this->service = new RepositoryService($this->conn);

        // Create test user
        $stmt = $this->conn->prepare(
            "INSERT INTO Users (username, email, password_hash) VALUES (?, ?, ?)"
        );
        $username = "test_user_" . time();
        $email = "test_" . time() . "@example.com";
        $password_hash = password_hash("test123", PASSWORD_DEFAULT);
        $stmt->bind_param("sss", $username, $email, $password_hash);
        $stmt->execute();
        $this->testUserId = $stmt->insert_id;
    }

    public function testCreateRepository()
    {
        $name = "Test Repo";
        $description = "Test Description";
        $language = "PHP";

        $repoId = $this->service->createRepository(
            $this->testUserId,
            $name,
            $description,
            $language
        );

        $this->assertGreaterThan(0, $repoId);

        // Verify repository was created
        $stmt = $this->conn->prepare(
            "SELECT * FROM Repositories WHERE repo_id = ?"
        );
        $stmt->bind_param("i", $repoId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        $this->assertEquals($name, $result['name']);
        $this->assertEquals($description, $result['description']);
        $this->assertEquals($language, $result['language']);

        $this->testRepoId = $repoId;
    }

    public function testCreateRepositoryWithEmptyName()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("All fields are required");

        $this->service->createRepository(
            $this->testUserId,
            "",
            "Test Description",
            "PHP"
        );
    }

    public function testCreateRepositoryWithInvalidUserId()
    {
        $this->expectException(\Exception::class);

        $this->service->createRepository(
            -1,
            "Test Repo",
            "Test Description",
            "PHP"
        );
    }

    public function testUpdateRepository()
    {
        // First create a repository
        $repoId = $this->service->createRepository(
            $this->testUserId,
            "Original Name",
            "Original Description",
            "PHP"
        );

        // Update the repository
        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'language' => 'Python'
        ];

        $result = $this->service->updateRepository($repoId, $this->testUserId, $updateData);
        $this->assertTrue($result);

        // Verify the update
        $stmt = $this->conn->prepare("SELECT * FROM Repositories WHERE repo_id = ?");
        $stmt->bind_param("i", $repoId);
        $stmt->execute();
        $updatedRepo = $stmt->get_result()->fetch_assoc();

        $this->assertEquals($updateData['name'], $updatedRepo['name']);
        $this->assertEquals($updateData['description'], $updatedRepo['description']);
        $this->assertEquals($updateData['language'], $updatedRepo['language']);
    }

    public function testUpdateRepositoryUnauthorized()
    {
        // First create a repository
        $repoId = $this->service->createRepository(
            $this->testUserId,
            "Test Repo",
            "Test Description",
            "PHP"
        );

        // Try to update with wrong user ID
        $wrongUserId = $this->testUserId + 1;
        $result = $this->service->updateRepository(
            $repoId,
            $wrongUserId,
            ['name' => 'Unauthorized Update']
        );

        $this->assertFalse($result);
    }

    public function testDeleteRepository()
    {
        // First create a repository
        $repoId = $this->service->createRepository(
            $this->testUserId,
            "Test Repo",
            "Test Description",
            "PHP"
        );

        // Delete the repository
        $result = $this->service->deleteRepository($repoId, $this->testUserId);
        $this->assertTrue($result);

        // Verify deletion
        $stmt = $this->conn->prepare("SELECT * FROM Repositories WHERE repo_id = ?");
        $stmt->bind_param("i", $repoId);
        $stmt->execute();
        $result = $stmt->get_result();

        $this->assertEquals(0, $result->num_rows);
    }

    public function testGetUserRepositories()
    {
        // Create multiple repositories
        $repos = [
            ['name' => 'Repo 1', 'description' => 'Desc 1', 'language' => 'PHP'],
            ['name' => 'Repo 2', 'description' => 'Desc 2', 'language' => 'Python'],
            ['name' => 'Repo 3', 'description' => 'Desc 3', 'language' => 'JavaScript']
        ];

        foreach ($repos as $repo) {
            $this->service->createRepository(
                $this->testUserId,
                $repo['name'],
                $repo['description'],
                $repo['language']
            );
        }

        // Get user repositories
        $userRepos = $this->service->getUserRepositories($this->testUserId);

        // Verify the number of repositories
        $this->assertCount(count($repos), $userRepos);

        // Verify repository data
        foreach ($userRepos as $index => $userRepo) {
            $this->assertEquals($repos[$index]['name'], $userRepo['name']);
            $this->assertEquals($repos[$index]['description'], $userRepo['description']);
            $this->assertEquals($repos[$index]['language'], $userRepo['language']);
        }
    }

    public function testRepositoryNameSanitization()
    {
        $maliciousName = "<script>alert('XSS')</script>";
        $expectedSanitized = htmlspecialchars($maliciousName, ENT_QUOTES, 'UTF-8');

        $repoId = $this->service->createRepository(
            $this->testUserId,
            $maliciousName,
            "Test Description",
            "PHP"
        );

        $stmt = $this->conn->prepare("SELECT name FROM Repositories WHERE repo_id = ?");
        $stmt->bind_param("i", $repoId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        $this->assertNotEquals($maliciousName, $result['name']);
        $this->assertEquals($expectedSanitized, $result['name']);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        if ($this->testUserId) {
            $this->conn->query("DELETE FROM Repositories WHERE user_id = {$this->testUserId}");
            $this->conn->query("DELETE FROM Users WHERE user_id = {$this->testUserId}");
        }
        $this->conn->close();
    }
}
