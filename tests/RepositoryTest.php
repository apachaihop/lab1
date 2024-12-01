<?php

use PHPUnit\Framework\TestCase;

class RepositoryTest extends TestCase
{
    private $conn;

    protected function setUp(): void
    {
        $this->conn = new mysqli('localhost', 'root', '', 'lab6');
        if ($this->conn->connect_error) {
            throw new Exception("Connection failed: " . $this->conn->connect_error);
        }
    }

    public function testCreateRepository()
    {
        // Simulate user session
        $_SESSION['user_id'] = 1;

        // Test data
        $name = "Test Repository";
        $description = "Test Description";
        $language = "PHP";

        // Insert repository
        $stmt = $this->conn->prepare("INSERT INTO Repositories (name, description, language, user_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $name, $description, $language, $_SESSION['user_id']);
        $stmt->execute();
        $repo_id = $stmt->insert_id;

        // Verify repository was created
        $result = $this->conn->query("SELECT * FROM Repositories WHERE repo_id = $repo_id");
        $repo = $result->fetch_assoc();

        $this->assertEquals($name, $repo['name']);
        $this->assertEquals($description, $repo['description']);
        $this->assertEquals($language, $repo['language']);

        // Cleanup
        $this->conn->query("DELETE FROM Repositories WHERE repo_id = $repo_id");
    }

    public function testDeleteRepository()
    {
        // Create test repository
        $_SESSION['user_id'] = 1;
        $stmt = $this->conn->prepare("INSERT INTO Repositories (name, description, language, user_id) VALUES (?, ?, ?, ?)");
        $name = "Test Repo";
        $desc = "Test Desc";
        $lang = "PHP";
        $userId = $_SESSION['user_id'];
        $stmt->bind_param("sssi", $name, $desc, $lang, $userId);
        $stmt->execute();
        $repo_id = $stmt->insert_id;

        // Delete repository
        $deleteStmt = $this->conn->prepare("DELETE FROM Repositories WHERE repo_id = ? AND user_id = ?");
        $repoId = $repo_id;  // Create variables for bind_param
        $userId = $_SESSION['user_id'];
        $deleteStmt->bind_param("ii", $repoId, $userId);
        $deleteStmt->execute();

        // Verify deletion
        $result = $this->conn->query("SELECT * FROM Repositories WHERE repo_id = $repo_id");
        $this->assertEquals(0, $result->num_rows);
    }

    protected function tearDown(): void
    {
        $this->conn->close();
    }
}
