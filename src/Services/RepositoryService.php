<?php

namespace App\Services;

class RepositoryService
{
    private $conn;

    public function __construct(\mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function createRepository(int $userId, string $name, string $description, string $language): int
    {
        try {
            $this->conn->begin_transaction();

            if (empty($name) || empty($description) || empty($language)) {
                throw new \InvalidArgumentException("All fields are required");
            }

            $stmt = $this->conn->prepare(
                "INSERT INTO Repositories (user_id, name, description, language) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("isss", $userId, $name, $description, $language);
            $stmt->execute();
            $repoId = $stmt->insert_id;

            $this->conn->commit();
            return $repoId;
        } catch (\Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function deleteRepository(int $repoId, int $userId): bool
    {
        try {
            $this->conn->begin_transaction();

            $stmt = $this->conn->prepare(
                "DELETE FROM Repositories WHERE repo_id = ? AND user_id = ?"
            );
            $stmt->bind_param("ii", $repoId, $userId);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                throw new \InvalidArgumentException("Repository not found or unauthorized");
            }

            $this->conn->commit();
            return true;
        } catch (\Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function updateRepository(int $repoId, int $userId, array $data): bool
    {
        try {
            $this->conn->begin_transaction();

            $allowedFields = ['name', 'description', 'language'];
            $updates = [];
            $types = '';
            $values = [];

            foreach ($data as $field => $value) {
                if (in_array($field, $allowedFields) && !empty($value)) {
                    $updates[] = "$field = ?";
                    $types .= 's';
                    $values[] = $value;
                }
            }

            if (empty($updates)) {
                return false;
            }

            $values[] = $repoId;
            $values[] = $userId;
            $types .= 'ii';

            $sql = "UPDATE Repositories SET " . implode(', ', $updates) .
                " WHERE repo_id = ? AND user_id = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param($types, ...$values);
            $stmt->execute();

            $this->conn->commit();
            return $stmt->affected_rows > 0;
        } catch (\Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function getUserRepositories(int $userId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM Repositories WHERE user_id = ? ORDER BY created_at DESC"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_all(MYSQLI_ASSOC);
    }
}
