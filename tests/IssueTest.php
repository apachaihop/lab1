<?php

use PHPUnit\Framework\TestCase;

class IssueTest extends TestCase
{
    private $conn;

    protected function setUp(): void
    {
        $this->conn = new mysqli('localhost', 'root', '', 'lab6');
        if ($this->conn->connect_error) {
            throw new Exception("Connection failed: " . $this->conn->connect_error);
        }
    }

    public function testCreateIssue()
    {
        $_SESSION['user_id'] = 1;

        $title = "Test Issue";
        $description = "Test Description";
        $status = "open";

        $stmt = $this->conn->prepare("INSERT INTO Issues (title, description, status, user_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $title, $description, $status, $_SESSION['user_id']);
        $stmt->execute();
        $issue_id = $stmt->insert_id;

        $result = $this->conn->query("SELECT * FROM Issues WHERE issue_id = $issue_id");
        $issue = $result->fetch_assoc();

        $this->assertEquals($title, $issue['title']);
        $this->assertEquals($description, $issue['description']);
        $this->assertEquals($status, $issue['status']);

        // Cleanup
        $this->conn->query("DELETE FROM Issues WHERE issue_id = $issue_id");
    }

    public function testUpdateIssue()
    {
        $_SESSION['user_id'] = 1;

        // Create test issue
        $stmt = $this->conn->prepare("INSERT INTO Issues (title, description, status, user_id) VALUES (?, ?, ?, ?)");
        $title = "Test Issue";
        $desc = "Test Desc";
        $status = "open";
        $userId = $_SESSION['user_id'];
        $stmt->bind_param("sssi", $title, $desc, $status, $userId);
        $stmt->execute();
        $issue_id = $stmt->insert_id;

        // Update issue
        $newTitle = "Updated Issue";
        $newDescription = "Updated Description";
        $newStatus = "closed";
        $issueId = $issue_id;  // Create variables for bind_param

        $updateStmt = $this->conn->prepare("UPDATE Issues SET title = ?, description = ?, status = ? WHERE issue_id = ? AND user_id = ?");
        $updateStmt->bind_param("sssii", $newTitle, $newDescription, $newStatus, $issueId, $userId);
        $updateStmt->execute();

        // Verify update
        $result = $this->conn->query("SELECT * FROM Issues WHERE issue_id = $issue_id");
        $issue = $result->fetch_assoc();

        $this->assertEquals($newTitle, $issue['title']);
        $this->assertEquals($newDescription, $issue['description']);
        $this->assertEquals($newStatus, $issue['status']);

        // Cleanup
        $this->conn->query("DELETE FROM Issues WHERE issue_id = $issue_id");
    }

    protected function tearDown(): void
    {
        $this->conn->close();
    }
}
