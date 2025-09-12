<?php
/**
 * Router script for PHP built-in server
 * Handles routing for DigitalOcean App Platform
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove leading slash
$uri = ltrim($uri, '/');

// Health check
if ($uri === 'health.php') {
    return false; // Serve the actual file
}

// Admin routes
if (str_starts_with($uri, 'admin')) {
    if ($uri === 'admin' || $uri === 'admin/') {
        // Redirect to admin/index.php
        $uri = 'admin/index.php';
    }
    
    $file = __DIR__ . '/' . $uri;
    if (file_exists($file) && !is_dir($file)) {
        return false; // Serve the actual file
    }
}

// API routes
if (str_starts_with($uri, 'api/')) {
    $file = __DIR__ . '/' . $uri;
    if (file_exists($file) && !is_dir($file)) {
        return false; // Serve the actual file
    }
}

// Static files
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2)$/', $uri)) {
    $file = __DIR__ . '/' . $uri;
    if (file_exists($file)) {
        return false; // Serve the actual file
    }
}

// Default routes
switch ($uri) {
    case '':
    case 'index.php':
        include __DIR__ . '/index.php';
        break;
    case 'tournaments.php':
    case 'tournament.php':
        // These files don't exist yet, show coming soon
        include __DIR__ . '/index.php';
        break;
    default:
        http_response_code(404);
        echo '404 Not Found';
        break;
}