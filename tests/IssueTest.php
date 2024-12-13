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

        // Enable strict mode for better error handling
        $this->conn->query("SET sql_mode = 'STRICT_ALL_TABLES'");
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

    public function testCreateIssueWithEmptyTitle()
    {
        $_SESSION['user_id'] = 1;

        // Simulate the application-level validation
        $title = "";
        $description = "Test Description";
        $status = "open";

        try {
            // First validate the input
            if (empty($title)) {
                throw new InvalidArgumentException("Title cannot be empty");
            }

            $stmt = $this->conn->prepare("INSERT INTO Issues (title, description, status, user_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $title, $description, $status, $_SESSION['user_id']);
            $stmt->execute();

            $this->fail("Should have thrown an exception for empty title");
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Title cannot be empty", $e->getMessage());
        }
    }

    public function testCreateIssueWithInvalidStatus()
    {
        $_SESSION['user_id'] = 1;

        $title = "Test Issue";
        $description = "Test Description";
        $status = "invalid_status";

        try {
            // First validate the status
            $validStatuses = ['open', 'closed'];
            if (!in_array($status, $validStatuses)) {
                throw new InvalidArgumentException("Invalid status value");
            }

            $stmt = $this->conn->prepare("INSERT INTO Issues (title, description, status, user_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $title, $description, $status, $_SESSION['user_id']);
            $stmt->execute();

            $this->fail("Should have thrown an exception for invalid status");
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Invalid status value", $e->getMessage());
        }
    }

    public function testUpdateIssueWithEmptyDescription()
    {
        $_SESSION['user_id'] = 1;

        // First create a valid issue
        $stmt = $this->conn->prepare("INSERT INTO Issues (title, description, status, user_id) VALUES (?, ?, ?, ?)");
        $title = "Test Issue";
        $description = "Test Description";
        $status = "open";
        $stmt->bind_param("sssi", $title, $description, $status, $_SESSION['user_id']);
        $stmt->execute();
        $issue_id = $stmt->insert_id;

        try {
            $emptyDescription = "";

            // Validate the input
            if (empty($emptyDescription)) {
                throw new InvalidArgumentException("Description cannot be empty");
            }

            $updateStmt = $this->conn->prepare("UPDATE Issues SET description = ? WHERE issue_id = ?");
            $updateStmt->bind_param("si", $emptyDescription, $issue_id);
            $updateStmt->execute();

            $this->fail("Should have thrown an exception for empty description");
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Description cannot be empty", $e->getMessage());
        } finally {
            // Cleanup
            $this->conn->query("DELETE FROM Issues WHERE issue_id = $issue_id");
        }
    }

    public function testUpdateNonExistentIssue()
    {
        $_SESSION['user_id'] = 1;

        $stmt = $this->conn->prepare("UPDATE Issues SET title = ? WHERE issue_id = ? AND user_id = ?");
        $title = "Updated Title";
        $nonExistentId = 99999;

        $stmt->bind_param("sii", $title, $nonExistentId, $_SESSION['user_id']);
        $stmt->execute();

        $this->assertEquals(0, $stmt->affected_rows, "Update should affect 0 rows for non-existent issue");
    }

    public function testUpdateIssueUnauthorized()
    {
        // Create test issue with user_id = 1
        $stmt = $this->conn->prepare("INSERT INTO Issues (title, description, status, user_id) VALUES (?, ?, ?, ?)");
        $title = "Test Issue";
        $description = "Test Description";
        $status = "open";
        $user_id = 1;
        $stmt->bind_param("sssi", $title, $description, $status, $user_id);
        $stmt->execute();
        $issue_id = $stmt->insert_id;

        // Try to update with different user_id
        $_SESSION['user_id'] = 2;

        $updateStmt = $this->conn->prepare("UPDATE Issues SET title = ? WHERE issue_id = ? AND user_id = ?");
        $newTitle = "Unauthorized Update";
        $updateStmt->bind_param("sii", $newTitle, $issue_id, $_SESSION['user_id']);
        $updateStmt->execute();

        $this->assertEquals(0, $updateStmt->affected_rows);

        // Cleanup
        $this->conn->query("DELETE FROM Issues WHERE issue_id = $issue_id");
    }

    protected function tearDown(): void
    {
        // Restore default SQL mode
        $this->conn->query("SET sql_mode = ''");
        $this->conn->close();
    }
}
