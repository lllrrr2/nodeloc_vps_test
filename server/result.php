<?php
/**
 * Result Router
 * Handles URLs like /result/YYYY/MM/filename.txt
 * Also provides API access to txt files via ?file= parameter
 */

// Handle API request for file content
if (isset($_GET['file'])) {
    $filePath = $_GET['file'];
    
    // Sanitize file path
    $filePath = str_replace(['..', '\\'], '', $filePath);
    $filePath = ltrim($filePath, '/');
    
    // Validate file path format (YYYY/MM/filename.txt)
    if (!preg_match('#^\d{4}/\d{2}/[a-zA-Z0-9_-]+\.txt$#', $filePath)) {
        http_response_code(400);
        die('Invalid file path format');
    }
    
    // Build full file path
    $fullPath = __DIR__ . '/' . $filePath;
    
    // Check if file exists
    if (!file_exists($fullPath)) {
        http_response_code(404);
        die('File not found: ' . $filePath);
    }
    
    // Return file content
    header('Content-Type: text/plain; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    readfile($fullPath);
    exit;
}

// Get the requested URI
$requestUri = $_SERVER['REQUEST_URI'];

// Remove query string if exists
$path = parse_url($requestUri, PHP_URL_PATH);

// Check if this is a result request
if (preg_match('#^/result/(.+\.txt)$#', $path, $matches)) {
    // Extract the file path (YYYY/MM/filename.txt)
    $filePath = $matches[1];
    
    // Serve bench.html content
    readfile(__DIR__ . '/bench.html');
    exit;
}

// If not a result URL, return 404
http_response_code(404);
echo "404 Not Found";
?>
