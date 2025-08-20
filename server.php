<?php
// Simple PHP development server entry point
// This script handles routing for the eSports Tournament Management System

$requestUri = $_SERVER['REQUEST_URI'];
$requestUri = parse_url($requestUri, PHP_URL_PATH);

// Remove leading slash for easier matching
$path = ltrim($requestUri, '/');

// Define routes
$routes = [
    '' => 'index.php',
    'index.php' => 'index.php',
    // Admin routes
    'admin' => 'admin/index.php',
    'admin/' => 'admin/index.php',
    'admin/index.php' => 'admin/index.php',
    'admin/login.php' => 'admin/login.php',
    'admin/dashboard.php' => 'admin/dashboard.php',
    'admin/tournaments.php' => 'admin/tournaments.php',
    'admin/matches.php' => 'admin/matches.php',
    'admin/users.php' => 'admin/users.php',
    'admin/results.php' => 'admin/results.php',
    'admin/tournament_results.php' => 'admin/tournament_results.php',
    'admin/payments.php' => 'admin/payments.php',
    'admin/withdrawals.php' => 'admin/withdrawals.php',
    'admin/registrations.php' => 'admin/registrations.php',
    'admin/logout.php' => 'admin/logout.php',
    
    // User routes
    'user' => 'user/index.php',
    'user/' => 'user/index.php',
    'user/index.php' => 'user/index.php',
    'user/login.php' => 'user/login.php',
    'user/register.php' => 'user/register.php',
    'user/dashboard.php' => 'user/dashboard.php',
    'user/tournaments.php' => 'user/tournaments.php',
    'user/matches.php' => 'user/matches.php',
    'user/payments.php' => 'user/payments.php',
    'user/withdraw.php' => 'user/withdraw.php',
    'user/upload.php' => 'user/upload.php',
    'user/logout.php' => 'user/logout.php',
    
    // API routes
    'api/dashboard_stats.php' => 'api/dashboard_stats.php',
    'api/get_teams.php' => 'api/get_teams.php',
    'api/payment.php' => 'api/payment.php',
    'api/update_profile.php' => 'api/update_profile.php',
    'api/upload.php' => 'api/upload.php',
];

// Check if the requested path matches a route
if (isset($routes[$path])) {
    $file = $routes[$path];
    if (file_exists($file)) {
        require $file;
        exit;
    }
}

// Check if it's a static file (CSS, JS, images)
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf)$/', $path)) {
    $file = $path;
    if (file_exists($file)) {
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf'
        ];
        
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
        
        header('Content-Type: ' . $mimeType);
        readfile($file);
        exit;
    }
}

// Check if the file exists directly
if (file_exists($path) && !is_dir($path)) {
    require $path;
    exit;
}

// If no route matched, try to serve the file directly or show 404
if (file_exists($path)) {
    if (is_dir($path)) {
        // If it's a directory, look for index.php
        if (file_exists($path . '/index.php')) {
            require $path . '/index.php';
        } else {
            http_response_code(403);
            echo "Directory listing not allowed";
        }
    } else {
        require $path;
    }
} else {
    http_response_code(404);
    echo "Page not found";
}
?>