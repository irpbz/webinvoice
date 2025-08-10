<?php
// config.php
// Site-wide configuration settings

// Error Reporting (Development: E_ALL, Production: 0 or E_ERROR)
error_reporting(0);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Tehran'); // Example: Tehran

// Database Configuration
define('DB_PATH', __DIR__ . '/store.sqlite'); // Path to the SQLite database file

// Admin Credentials
// IMPORTANT: Change these default credentials immediately!
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', password_hash('admin', PASSWORD_DEFAULT)); // Hash your desired password

// Uploads Directory
define('UPLOAD_DIR', __DIR__ . '/uploads/'); // Absolute path on server
define('UPLOAD_DIR_PUBLIC_PATH', 'uploads/'); // Relative path for web URLs from app root
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB for general uploads
define('MAX_LOGO_SIZE', 2 * 1024 * 1024); // 2 MB specifically for logo
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Site Settings (can be overridden by database settings later)
define('STORE_NAME', 'عنوان فروشگاه'); // Default store name
define('DEFAULT_CURRENCY_SYMBOL', 'ریال');
define('DEFAULT_USE_FRIENDLY_URLS', '1'); // New constant: '1' for enabled, '0' for disabled by default

// Session Configuration
define('SESSION_NAME', 'StoreAccountingSession_v2'); 
define('SESSION_TIMEOUT_DURATION', 1800); // 30 minutes

// Ensure uploads directory exists and is writable
if (!is_dir(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0775, true)) {
        if (!mkdir(__DIR__ . DIRECTORY_SEPARATOR . trim(UPLOAD_DIR_PUBLIC_PATH, '/'), 0775, true)) {
             die("Failed to create uploads directory. Please create it manually (" . UPLOAD_DIR . ") and ensure it's writable by the web server.");
        }
    }
}
if (!is_writable(UPLOAD_DIR)) {
    @chmod(UPLOAD_DIR, 0775);
    if (!is_writable(UPLOAD_DIR)) {
        die("Uploads directory is not writable. Please check permissions for: " . UPLOAD_DIR);
    }
}

?>