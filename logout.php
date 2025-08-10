<?php
// logout.php
// Handles user logout.

if (session_status() == PHP_SESSION_NONE) {
    if (!defined('SESSION_NAME')) {
        require_once __DIR__ . '/config.php';
    }
    session_name(SESSION_NAME);
    session_start();
}

// Unset all session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to login page with a logout message
header("Location: login.php?message=logged_out");
exit;
?>
