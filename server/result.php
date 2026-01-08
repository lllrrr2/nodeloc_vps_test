<?php
/**
 * Result Router
 * Handles URLs like /result/YYYY/MM/filename.txt
 * Returns bench.html content with the file path embedded
 */

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
