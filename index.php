<?php
// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection settings
// Ensure you replace 'your_password' with the actual password for your MySQL root user 
$host = "localhost";
$dbname = "video_platform";
$username = "root";
$password = "yomama";

// Create database connection
try {
    $db = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Handle video upload request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['status' => 'error', 'message' => 'Unknown error occurred'];
    
    // Get video title and pseudonym
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $pseudonym = isset($_POST['pseudonym']) ? trim($_POST['pseudonym']) : 'Anonymous';
    
    // Validate inputs
    if (empty($title)) {
        $response = ['status' => 'error', 'message' => 'Video title is required'];
        echo json_encode($response);
        exit;
    }
    
    // Check if video file was uploaded
    if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = isset($_FILES['video']) ? getUploadErrorMessage($_FILES['video']['error']) : 'No video file uploaded';
        $response = ['status' => 'error', 'message' => $errorMessage];
        echo json_encode($response);
        exit;
    }
    
    // Process the uploaded file
    $file = $_FILES['video'];
    $fileName = $file['name'];
    $fileType = $file['type'];
    $fileTmpName = $file['tmp_name'];
    $fileError = $file['error'];
    $fileSize = $file['size'];
    
    // Validate file type
    $allowedExtensions = ['mp4', 'webm', 'ogg', 'mov', 'avi'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        $response = ['status' => 'error', 'message' => 'Invalid file type. Allowed types: ' . implode(', ', $allowedExtensions)];
        echo json_encode($response);
        exit;
    }
    
    // Validate file size (limit to 500MB)
    $maxFileSize = 500 * 1024 * 1024; // 500MB in bytes
    if ($fileSize > $maxFileSize) {
        $response = ['status' => 'error', 'message' => 'File is too large. Maximum file size is 500MB'];
        echo json_encode($response);
        exit;
    }
    
    // Generate unique filename to prevent overwriting
    $newFileName = uniqid('video_') . '_' . time() . '.' . $fileExtension;
    $uploadDir = 'uploads/videos/';
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $uploadPath = $uploadDir . $newFileName;
    
    // Move the uploaded file to the upload directory
    if (move_uploaded_file($fileTmpName, $uploadPath)) {
        // Save video information to database
        try {
            $stmt = $db->prepare("INSERT INTO videos (title, pseudonym, file_name, file_path, file_type, file_size, upload_date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$title, $pseudonym, $newFileName, $uploadPath, $fileType, $fileSize]);
            $videoId = $db->lastInsertId();
            
            $response = [
                'status' => 'success',
                'message' => 'Video uploaded successfully',
                'data' => [
                    'id' => $videoId,
                    'title' => $title,
                    'pseudonym' => $pseudonym,
                    'path' => $uploadPath,
                    'upload_date' => date('Y-m-d H:i:s')
                ]
            ];
        } catch (PDOException $e) {
            // Delete the uploaded file if database insertion fails
            unlink($uploadPath);
            $response = ['status' => 'error', 'message' => 'Failed to save video information: ' . $e->getMessage()];
        }
    } else {
        $response = ['status' => 'error', 'message' => 'Failed to move uploaded file'];
    }
    
    echo json_encode($response);
    exit;
}

// Handle GET request for fetching videos
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $db->prepare("SELECT id, title, pseudonym, file_path, upload_date, likes FROM videos ORDER BY upload_date DESC");
        $stmt->execute();
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $videos]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch videos: ' . $e->getMessage()]);
    }
    exit;
}

// Helper function to get upload error message
function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form';
        case UPLOAD_ERR_PARTIAL:
            return 'The uploaded file was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'A PHP extension stopped the file upload';
        default:
            return 'Unknown upload error';
    }
}

// Create necessary database table (run this once)
/*
CREATE TABLE videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    pseudonym VARCHAR(100) NOT NULL DEFAULT 'Anonymous',
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size BIGINT NOT NULL,
    upload_date DATETIME NOT NULL,
    likes INT DEFAULT 0,
    views INT DEFAULT 0
);
*/
?>
