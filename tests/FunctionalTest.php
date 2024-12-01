<?php

use PHPUnit\Framework\TestCase;

class FunctionalTest extends TestCase
{
    private $conn;
    private $test_user_id;
    private $test_repo_id;

    protected function setUp(): void
    {
        $this->conn = new mysqli('localhost', 'root', '', 'lab6');
        if ($this->conn->connect_error) {
            throw new Exception("Connection failed: " . $this->conn->connect_error);
        }

        // Set up test data
        $this->setUpTestUser();
        $this->setUpTestRepository();
    }

    private function setUpTestUser()
    {
        $stmt = $this->conn->prepare("INSERT INTO Users (username, email, password_hash) VALUES (?, ?, ?)");
        $username = "test_user_" . time();
        $email = "test_" . time() . "@example.com";
        $password_hash = password_hash("test123", PASSWORD_DEFAULT);
        $stmt->bind_param("sss", $username, $email, $password_hash);
        $stmt->execute();
        $this->test_user_id = $stmt->insert_id;
        $stmt->close();
    }

    private function setUpTestRepository()
    {
        $stmt = $this->conn->prepare("INSERT INTO Repositories (user_id, name, description, language) VALUES (?, ?, ?, ?)");
        $name = "test_repo_" . time();
        $description = "Test repository";
        $language = "PHP";
        $stmt->bind_param("isss", $this->test_user_id, $name, $description, $language);
        $stmt->execute();
        $this->test_repo_id = $stmt->insert_id;
        $stmt->close();
    }

    public function testRepositoryWorkflow()
    {
        // Test repository creation
        $this->assertGreaterThan(0, $this->test_repo_id);

        // Test repository update
        $newName = "updated_repo_" . time();
        $updateStmt = $this->conn->prepare("UPDATE Repositories SET name = ? WHERE repo_id = ?");
        $updateStmt->bind_param("si", $newName, $this->test_repo_id);
        $updateStmt->execute();
        $this->assertEquals(1, $updateStmt->affected_rows);

        // Test repository retrieval
        $selectStmt = $this->conn->prepare("SELECT * FROM Repositories WHERE repo_id = ?");
        $selectStmt->bind_param("i", $this->test_repo_id);
        $selectStmt->execute();
        $result = $selectStmt->get_result()->fetch_assoc();
        $this->assertEquals($newName, $result['name']);
    }

    public function testIssueWorkflow()
    {
        // Create issue
        $stmt = $this->conn->prepare("INSERT INTO Issues (repo_id, user_id, title, description, status) VALUES (?, ?, ?, ?, ?)");
        $title = "Test Issue";
        $description = "Test Description";
        $status = "open";
        $stmt->bind_param("iisss", $this->test_repo_id, $this->test_user_id, $title, $description, $status);
        $stmt->execute();
        $issue_id = $stmt->insert_id;

        // Test issue update
        $newStatus = "closed";
        $updateStmt = $this->conn->prepare("UPDATE Issues SET status = ? WHERE issue_id = ?");
        $updateStmt->bind_param("si", $newStatus, $issue_id);
        $updateStmt->execute();

        // Verify update
        $selectStmt = $this->conn->prepare("SELECT status FROM Issues WHERE issue_id = ?");
        $selectStmt->bind_param("i", $issue_id);
        $selectStmt->execute();
        $result = $selectStmt->get_result()->fetch_assoc();
        $this->assertEquals($newStatus, $result['status']);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->conn->query("DELETE FROM Issues WHERE user_id = {$this->test_user_id}");
        $this->conn->query("DELETE FROM Repositories WHERE user_id = {$this->test_user_id}");
        $this->conn->query("DELETE FROM Users WHERE user_id = {$this->test_user_id}");
        $this->conn->close();
    }
}
