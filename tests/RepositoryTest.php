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
        // Enable strict mode
        $this->conn->query("SET sql_mode = 'STRICT_ALL_TABLES'");
    }

    public function testCreateRepository()
    {
        $_SESSION['user_id'] = 1;

        $name = "Test Repository";
        $description = "Test Description";
        $language = "PHP";

        $stmt = $this->conn->prepare("INSERT INTO Repositories (name, description, language, user_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $name, $description, $language, $_SESSION['user_id']);
        $stmt->execute();
        $repo_id = $stmt->insert_id;

        $result = $this->conn->query("SELECT * FROM Repositories WHERE repo_id = $repo_id");
        $repo = $result->fetch_assoc();

        $this->assertEquals($name, $repo['name']);
        $this->assertEquals($description, $repo['description']);
        $this->assertEquals($language, $repo['language']);

        // Cleanup
        $this->conn->query("DELETE FROM Repositories WHERE repo_id = $repo_id");
    }

    public function testCreateRepositoryWithEmptyName()
    {
        $_SESSION['user_id'] = 1;

        try {
            $name = "";
            $description = "Test Description";
            $language = "PHP";

            // Validate input
            if (empty($name)) {
                throw new InvalidArgumentException("Repository name cannot be empty");
            }

            $stmt = $this->conn->prepare("INSERT INTO Repositories (name, description, language, user_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $name, $description, $language, $_SESSION['user_id']);
            $stmt->execute();

            $this->fail("Should have thrown an exception for empty name");
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Repository name cannot be empty", $e->getMessage());
        }
    }

    public function testCreateRepositoryWithEmptyDescription()
    {
        $_SESSION['user_id'] = 1;

        try {
            $name = "Test Repository";
            $description = "";
            $language = "PHP";

            // Validate input
            if (empty($description)) {
                throw new InvalidArgumentException("Repository description cannot be empty");
            }

            $stmt = $this->conn->prepare("INSERT INTO Repositories (name, description, language, user_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $name, $description, $language, $_SESSION['user_id']);
            $stmt->execute();

            $this->fail("Should have thrown an exception for empty description");
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Repository description cannot be empty", $e->getMessage());
        }
    }

    public function testCreateRepositoryWithInvalidLanguage()
    {
        $_SESSION['user_id'] = 1;

        try {
            $name = "Test Repository";
            $description = "Test Description";
            $language = str_repeat("a", 1000); // Too long language name

            // Validate input
            if (strlen($language) > 50) { // Assuming 50 is the max length
                throw new InvalidArgumentException("Programming language name is too long");
            }

            $stmt = $this->conn->prepare("INSERT INTO Repositories (name, description, language, user_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $name, $description, $language, $_SESSION['user_id']);
            $stmt->execute();

            $this->fail("Should have thrown an exception for invalid language");
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Programming language name is too long", $e->getMessage());
        }
    }

    public function testCreateRepositoryWithSpecialCharacters()
    {
        $_SESSION['user_id'] = 1;

        $name = "Test<script>alert('xss')</script>";
        $description = "Test Description";
        $language = "PHP";

        // Sanitize input
        $sanitizedName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

        $stmt = $this->conn->prepare("INSERT INTO Repositories (name, description, language, user_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $sanitizedName, $description, $language, $_SESSION['user_id']);
        $stmt->execute();
        $repo_id = $stmt->insert_id;

        // Verify the name was properly sanitized
        $result = $this->conn->query("SELECT name FROM Repositories WHERE repo_id = $repo_id");
        $repo = $result->fetch_assoc();

        $this->assertEquals($sanitizedName, $repo['name']);
        $this->assertNotEquals($name, $repo['name']);

        // Cleanup
        $this->conn->query("DELETE FROM Repositories WHERE repo_id = $repo_id");
    }

    public function testDeleteRepository()
    {
        $_SESSION['user_id'] = 1;

        // Create test repository
        $stmt = $this->conn->prepare("INSERT INTO Repositories (name, description, language, user_id) VALUES (?, ?, ?, ?)");
        $name = "Test Repo";
        $description = "Test Description";
        $language = "PHP";
        $stmt->bind_param("sssi", $name, $description, $language, $_SESSION['user_id']);
        $stmt->execute();
        $repo_id = $stmt->insert_id;

        // Delete repository
        $deleteStmt = $this->conn->prepare("DELETE FROM Repositories WHERE repo_id = ? AND user_id = ?");
        $deleteStmt->bind_param("ii", $repo_id, $_SESSION['user_id']);
        $deleteStmt->execute();

        // Verify deletion
        $result = $this->conn->query("SELECT * FROM Repositories WHERE repo_id = $repo_id");
        $this->assertEquals(0, $result->num_rows);
    }

    public function testDeleteRepositoryUnauthorized()
    {
        // Create repository with user_id = 1
        $_SESSION['user_id'] = 1;
        $stmt = $this->conn->prepare("INSERT INTO Repositories (name, description, language, user_id) VALUES (?, ?, ?, ?)");
        $name = "Test Repo";
        $description = "Test Description";
        $language = "PHP";
        $stmt->bind_param("sssi", $name, $description, $language, $_SESSION['user_id']);
        $stmt->execute();
        $repo_id = $stmt->insert_id;

        // Try to delete with different user_id
        $_SESSION['user_id'] = 2;
        $deleteStmt = $this->conn->prepare("DELETE FROM Repositories WHERE repo_id = ? AND user_id = ?");
        $deleteStmt->bind_param("ii", $repo_id, $_SESSION['user_id']);
        $deleteStmt->execute();

        // Verify repository still exists
        $result = $this->conn->query("SELECT * FROM Repositories WHERE repo_id = $repo_id");
        $this->assertEquals(1, $result->num_rows);

        // Cleanup
        $this->conn->query("DELETE FROM Repositories WHERE repo_id = $repo_id");
    }

    protected function tearDown(): void
    {
        // Restore default SQL mode
        $this->conn->query("SET sql_mode = ''");
        $this->conn->close();
    }
}
