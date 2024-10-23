<?php
class FileHandler
{
    public $repoFilesPath;

    public function __construct()
    {
        $this->repoFilesPath = __DIR__ . '/../repository_files/';

        if (!file_exists($this->repoFilesPath)) {
            error_log("Repository files directory does not exist: " . $this->repoFilesPath);
            throw new Exception("Repository files directory does not exist. Please create it manually.");
        }

        if (!is_writable($this->repoFilesPath)) {
            error_log("Repository files directory is not writable: " . $this->repoFilesPath);
            throw new Exception("Repository files directory is not writable. Please check permissions.");
        }
    }

    public function saveAvatar($conn, $userId, $file)
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error uploading file");
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception("Invalid file type. Only JPG, PNG and GIF are allowed.");
        }

        $avatarData = file_get_contents($file['tmp_name']);
        $stmt = $conn->prepare("UPDATE Users SET avatar_data = ?, avatar_type = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $avatarData, $file['type'], $userId);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    public function saveRepoFile($conn, $repoId, $file)
    {
        try {
            error_log("Starting saveRepoFile for repoId: " . $repoId);
            error_log("File data: " . print_r($file, true));

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
                ];
                $errorMessage = isset($errorMessages[$file['error']])
                    ? $errorMessages[$file['error']]
                    : 'Unknown upload error';
                error_log("File upload error: " . $errorMessage);
                throw new Exception("Error uploading file: " . $errorMessage);
            }

            $fileName = basename($file['name']);
            $fileDir = $this->repoFilesPath . $repoId . '/';
            error_log("Creating directory: " . $fileDir);

            if (!file_exists($fileDir)) {
                if (!mkdir($fileDir, 0755, true)) {
                    error_log("Failed to create directory: " . $fileDir);
                    throw new Exception("Failed to create directory for repository files");
                }
            }

            $filePath = $fileDir . $fileName;
            error_log("Attempting to move file to: " . $filePath);

            if (!is_uploaded_file($file['tmp_name'])) {
                error_log("Invalid upload attempt - file is not an uploaded file: " . $file['tmp_name']);
                throw new Exception("Invalid file upload attempt");
            }

            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                $moveError = error_get_last();
                error_log("Failed to move uploaded file. PHP Error: " . print_r($moveError, true));
                error_log("Source: " . $file['tmp_name']);
                error_log("Destination: " . $filePath);
                error_log("File permissions: " . substr(sprintf('%o', fileperms($fileDir)), -4));
                throw new Exception("Failed to save file to repository");
            }

            $relativeFilePath = $repoId . '/' . $fileName;
            error_log("File moved successfully, saving to database. RelativePath: " . $relativeFilePath);

            $stmt = $conn->prepare("INSERT INTO RepositoryFiles (repo_id, file_name, file_path) VALUES (?, ?, ?)");
            if (!$stmt) {
                error_log("Database prepare error: " . $conn->error);
                throw new Exception("Database error while preparing statement");
            }

            $stmt->bind_param("iss", $repoId, $fileName, $relativeFilePath);
            if (!$stmt->execute()) {
                error_log("Database execute error: " . $stmt->error);
                throw new Exception("Database error while saving file record");
            }

            $success = $stmt->affected_rows > 0;
            $stmt->close();

            error_log("File saved successfully: " . $fileName);
            return $success;
        } catch (Exception $e) {
            error_log("Exception in saveRepoFile: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    public function getRepoFile($filePath)
    {
        $fullPath = $this->repoFilesPath . $filePath;
        if (!file_exists($fullPath)) {
            throw new Exception("File not found");
        }
        return file_get_contents($fullPath);
    }

    public function deleteRepoFile($conn, $repoId, $fileName)
    {
        $stmt = $conn->prepare("SELECT file_path FROM RepositoryFiles WHERE repo_id = ? AND file_name = ?");
        $stmt->bind_param("is", $repoId, $fileName);
        $stmt->execute();
        $result = $stmt->get_result();
        $file = $result->fetch_assoc();
        $stmt->close();

        if ($file) {
            $fullPath = $this->repoFilesPath . $file['file_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            $stmt = $conn->prepare("DELETE FROM RepositoryFiles WHERE repo_id = ? AND file_name = ?");
            $stmt->bind_param("is", $repoId, $fileName);
            $success = $stmt->execute();
            $stmt->close();

            return $success;
        }
        return false;
    }
}
