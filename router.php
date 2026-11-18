<?php
// ==============================================================================
// Meridian Ice Cream Contest - Local Development Server Router
// Mimics Hostinger LiteSpeed / Apache clean URLs without .html
// Usage: php -S localhost:8000 router.php
// ==============================================================================

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

// If static asset exists (images, svg, css, js), serve directly
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

// Redirect direct .html requests to clean URL
if (preg_match('/\.html$/i', $uri)) {
    $cleanUri = preg_replace('/\.html$/i', '', $uri);
    if ($cleanUri === '/index') {
        $cleanUri = '/';
    }
    header("Location: $cleanUri", true, 301);
    exit;
}

// Serve clean URLs matching .html files
if (file_exists($file . '.html')) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile($file . '.html');
    exit;
}

// Root URL serves index.html
if ($uri === '/' || $uri === '') {
    header('Content-Type: text/html; charset=UTF-8');
    readfile(__DIR__ . '/index.html');
    exit;
}

return false;

