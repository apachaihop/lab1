<?php
class FileHandler
{
    public $repoFilesPath;
    private $maxFileSize = 1048576; // 1MB in bytes
    private $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif'];
    private $allowedTextTypes = [
        'text/plain',
        'text/html',
        'text/css',
        'text/javascript',
        'application/json',
        'application/xml',
        'text/x-php',
        'text/x-python',
        'text/x-java-source',
        'text/x-c',
        'text/markdown',
        'text/x-sql'
    ];
    private $allowedPDFTypes = ['application/pdf'];

    public function __construct()
    {
        $this->repoFilesPath = __DIR__ . '/../repository_files/';
        $this->validateStorageDirectory();
    }

    private function validateStorageDirectory()
    {
        if (!file_exists($this->repoFilesPath)) {
            error_log("Repository files directory does not exist: " . $this->repoFilesPath);
            throw new Exception("Storage directory not found. Please contact administrator.");
        }

        if (!is_dir($this->repoFilesPath)) {
            error_log("Repository files path is not a directory: " . $this->repoFilesPath);
            throw new Exception("Invalid storage configuration. Please contact administrator.");
        }

        if (!is_readable($this->repoFilesPath)) {
            error_log("Repository files directory is not readable: " . $this->repoFilesPath);
            throw new Exception("Storage directory is not accessible. Please contact administrator.");
        }

        if (!is_writable($this->repoFilesPath)) {
            error_log("Repository files directory is not writable: " . $this->repoFilesPath);
            throw new Exception("Cannot write to storage directory. Please contact administrator.");
        }
    }

    public function saveAvatar($conn, $userId, $file)
    {
        $this->validateUploadedFile($file);
        $this->validateFileType($file['type'], $this->allowedImageTypes);
        $this->validateFileSize($file['size']);

        try {
            $avatarData = @file_get_contents($file['tmp_name']);
            if ($avatarData === false) {
                error_log("Failed to read uploaded avatar file: " . $file['tmp_name']);
                throw new Exception("Failed to process uploaded image. Please try again.");
            }

            if (!@getimagesizefromstring($avatarData)) {
                error_log("Invalid image data for user $userId");
                throw new Exception("The uploaded file is not a valid image.");
            }

            $stmt = $conn->prepare("UPDATE Users SET avatar_data = ?, avatar_type = ? WHERE user_id = ?");
            if (!$stmt) {
                error_log("Failed to prepare avatar update statement: " . $conn->error);
                throw new Exception("Database error while saving avatar.");
            }

            $stmt->bind_param("ssi", $avatarData, $file['type'], $userId);
            if (!$stmt->execute()) {
                error_log("Failed to execute avatar update: " . $stmt->error);
                throw new Exception("Failed to save avatar to database.");
            }
            $stmt->close();

            return true;
        } catch (Exception $e) {
            error_log("Error in saveAvatar: " . $e->getMessage());
            throw new Exception("Failed to save avatar: " . $e->getMessage());
        }
    }

    private function validateUploadedFile($file)
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new Exception("Invalid file parameter.");
        }

        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => "The uploaded file exceeds the upload_max_filesize directive.",
            UPLOAD_ERR_FORM_SIZE => "The uploaded file exceeds the MAX_FILE_SIZE directive.",
            UPLOAD_ERR_PARTIAL => "The uploaded file was only partially uploaded.",
            UPLOAD_ERR_NO_FILE => "No file was uploaded.",
            UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
            UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload."
        ];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = isset($uploadErrors[$file['error']])
                ? $uploadErrors[$file['error']]
                : "Unknown upload error";
            error_log("File upload error: " . $errorMessage);
            throw new Exception($errorMessage);
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            error_log("Potential file upload attack: " . $file['tmp_name']);
            throw new Exception("Invalid file upload attempt detected.");
        }
    }

    private function validateFileType($fileType, $allowedTypes)
    {
        if (!in_array($fileType, $allowedTypes)) {
            $allowedExtensions = array_map(function ($type) {
                return strtoupper(substr($type, strpos($type, '/') + 1));
            }, $allowedTypes);
            throw new Exception("Invalid file type. Allowed types: " . implode(', ', $allowedExtensions));
        }
    }

    private function validateFileSize($fileSize)
    {
        if ($fileSize > $this->maxFileSize) {
            $maxSizeMB = $this->maxFileSize / (1024 * 1024);
            throw new Exception("File size exceeds maximum limit of {$maxSizeMB}MB.");
        }
    }

    public function saveRepoFile($conn, $repoId, $file)
    {
        try {
            error_log("Starting saveRepoFile for repoId: " . $repoId);
            error_log("File data: " . print_r($file, true));

            // Validate file size
            $this->validateFileSize($file['size']);

            // Validate file type
            $allowedTypes = array_merge(
                $this->allowedTextTypes,
                $this->allowedImageTypes,
                $this->allowedPDFTypes
            );
            $this->validateFileType($file['type'], $allowedTypes);

            $maxFileSize = 1 * 1024 * 1024;
            if ($file['size'] > $maxFileSize) {
                error_log("File size exceeds limit: " . $file['size'] . " bytes");
                throw new Exception("File size exceeds maximum limit of 1MB");
            }

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

            // Additional PDF validation for PDF files
            if ($file['type'] === 'application/pdf') {
                $tmpName = $file['tmp_name'];

                // Check PDF signature
                $handle = fopen($tmpName, 'rb');
                if (!$handle) {
                    throw new Exception("Cannot read temporary file");
                }

                $header = fread($handle, 4);
                fclose($handle);

                if ($header !== '%PDF') {
                    throw new Exception("Invalid PDF file");
                }
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
            throw new Exception("File not found in repository");
        }

        if (!is_readable($fullPath)) {
            error_log("File not readable: " . $fullPath);
            throw new Exception("Unable to read file. Please contact administrator.");
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            error_log("Failed to read file contents: " . $fullPath);
            throw new Exception("Error reading file contents. Please try again.");
        }

        return $content;
    }

    public function deleteRepoFile($conn, $repoId, $fileName)
    {
        $stmt = $conn->prepare("SELECT file_path FROM RepositoryFiles WHERE repo_id = ? AND file_name = ?");
        if (!$stmt) {
            throw new Exception("Database error while preparing to delete file");
        }

        $stmt->bind_param("is", $repoId, $fileName);
        if (!$stmt->execute()) {
            throw new Exception("Database error while checking file existence");
        }

        $result = $stmt->get_result();
        $file = $result->fetch_assoc();
        $stmt->close();

        if (!$file) {
            throw new Exception("File not found in database");
        }

        $fullPath = $this->repoFilesPath . $file['file_path'];
        if (!file_exists($fullPath)) {
            error_log("Physical file missing: " . $fullPath);
            throw new Exception("File not found in storage");
        }

        if (!is_writable(dirname($fullPath))) {
            error_log("Directory not writable: " . dirname($fullPath));
            throw new Exception("Unable to delete file due to permissions");
        }

        if (!unlink($fullPath)) {
            error_log("Failed to delete file: " . $fullPath);
            throw new Exception("Failed to delete file from storage");
        }

        $stmt = $conn->prepare("DELETE FROM RepositoryFiles WHERE repo_id = ? AND file_name = ?");
        if (!$stmt) {
            throw new Exception("Database error while preparing to remove file record");
        }

        $stmt->bind_param("is", $repoId, $fileName);
        if (!$stmt->execute()) {
            throw new Exception("Database error while removing file record");
        }

        $success = $stmt->affected_rows > 0;
        $stmt->close();

        return $success;
    }

    public function isPDF($filePath)
    {
        $fullPath = $this->repoFilesPath . $filePath;
        if (!file_exists($fullPath)) {
            return false;
        }

        $mimeType = mime_content_type($fullPath);
        return in_array($mimeType, $this->allowedPDFTypes);
    }

    public function isValidPDF($filePath)
    {
        $fullPath = $this->repoFilesPath . $filePath;
        if (!file_exists($fullPath)) {
            return false;
        }

        // Check MIME type
        $mimeType = mime_content_type($fullPath);
        if (!in_array($mimeType, $this->allowedPDFTypes)) {
            return false;
        }

        // Read first few bytes to check PDF signature
        $handle = fopen($fullPath, 'rb');
        if (!$handle) {
            return false;
        }

        $header = fread($handle, 4);
        fclose($handle);

        // Check for PDF signature %PDF
        return $header === '%PDF';
    }
}
