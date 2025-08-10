<?php
// /actions/user_actions.php
// Handles user-related actions like login, using generate_url for redirects.

// Ensure session is started and config/db are loaded.
// This is typically handled by index.php if actions are routed through it.
if (session_status() == PHP_SESSION_NONE) {
    if (!defined('SESSION_NAME')) { // Ensure config constants are available
        require_once __DIR__ . '/../config.php';
    }
    session_name(SESSION_NAME);
    session_start();
}
// Ensure db.php (for generate_url and get_setting, though not strictly needed here) and config.php are available
if (!function_exists('generate_url')) {
    require_once __DIR__ . '/../config.php'; // For ADMIN_USERNAME, ADMIN_PASSWORD_HASH
    require_once __DIR__ . '/../db.php';     // For generate_url
} elseif (!defined('ADMIN_USERNAME')) { // If db.php was included but not config.php directly by this script
    require_once __DIR__ . '/../config.php';
}


/**
 * Handles the user login action.
 * Verifies credentials against defined constants.
 *
 * @return array An action message array with 'type', 'text', and optionally 'redirect_to'.
 */
function handle_login_user_action() {
    $message = ['type' => 'error', 'text' => 'نام کاربری یا رمز عبور نامعتبر است.']; 
    $redirect_to_login = 'login.php'; // login.php is a direct file, not a page key for generate_url

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $message['text'] = 'نام کاربری و رمز عبور نمی‌توانند خالی باشند.';
            $_SESSION['login_error'] = $message['text'];
            $message['redirect_to'] = $redirect_to_login;
            return $message;
        }

        if ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD_HASH)) {
            $_SESSION['user_id'] = 1; // Example user ID for admin
            $_SESSION['username'] = $username;
            $_SESSION['last_activity'] = time();
            
            unset($_SESSION['login_error']);

            // Check if there was a page to redirect to after login
            $redirect_after_login_url = $_SESSION['redirect_after_login'] ?? generate_url('dashboard');
            unset($_SESSION['redirect_after_login']);

            $message['type'] = 'success';
            $message['text'] = 'ورود با موفقیت انجام شد.';
            $message['redirect_to'] = $redirect_after_login_url; 
        } else {
            $_SESSION['login_error'] = 'نام کاربری یا رمز عبور نامعتبر است.';
            $message['redirect_to'] = $redirect_to_login; 
        }
    } else {
        $message['text'] = 'درخواست نامعتبر.';
        $_SESSION['login_error'] = $message['text'];
        $message['redirect_to'] = $redirect_to_login;
    }
    return $message;
}

?>