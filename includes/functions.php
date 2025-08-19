<?php
/**
 * Common utility functions
 */

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token']) || 
        (isset($_SESSION['csrf_token_time']) && time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_EXPIRE)) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $token) &&
           isset($_SESSION['csrf_token_time']) && 
           time() - $_SESSION['csrf_token_time'] <= CSRF_TOKEN_EXPIRE;
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Format currency in Taka
 */
function formatTaka($amount) {
    return '৳' . number_format($amount, 2);
}

/**
 * Generate unique filename
 */
function generateUniqueFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}

/**
 * Check if file is valid image
 */
function isValidImage($file) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    $fileType = $file['type'];
    $fileName = $file['name'];
    $fileSize = $file['size'];
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    return in_array($fileType, $allowedTypes) && 
           in_array($extension, $allowedExtensions) && 
           $fileSize <= MAX_UPLOAD_SIZE;
}

/**
 * Upload file
 */
function uploadFile($file, $directory = 'uploads/') {
    if (!isValidImage($file)) {
        return ['success' => false, 'message' => 'Invalid file type or size too large (max 5MB)'];
    }
    
    $uploadDir = UPLOAD_PATH . $directory;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileName = generateUniqueFilename($file['name']);
    $uploadPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return ['success' => true, 'filename' => $fileName, 'path' => $directory . $fileName];
    }
    
    return ['success' => false, 'message' => 'Upload failed'];
}

/**
 * Redirect with message
 */
function redirect($url, $message = '', $type = 'info') {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header('Location: ' . $url);
    exit();
}

/**
 * Get flash message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if admin is logged in
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

/**
 * Require login
 */
function requireLogin($redirectUrl = 'user/login.php') {
    if (!isLoggedIn()) {
        redirect($redirectUrl, 'Please login to access this page', 'warning');
    }
}

/**
 * Require admin login
 */
function requireAdminLogin($redirectUrl = 'admin/login.php') {
    if (!isAdminLoggedIn()) {
        redirect($redirectUrl, 'Please login as admin to access this page', 'warning');
    }
}

/**
 * Format date
 */
function formatDate($date, $format = 'M d, Y H:i') {
    return date($format, strtotime($date));
}

/**
 * Time ago function
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M d, Y', strtotime($datetime));
}
?>
