<?php
// login.php
// Admin login page

if (session_status() == PHP_SESSION_NONE) {
    // config.php might not be loaded yet if accessing login.php directly
    // So, define SESSION_NAME if not already defined or load config.
    if (!defined('SESSION_NAME')) {
        require_once __DIR__ . '/config.php'; // To get SESSION_NAME
        require_once __DIR__ . '/db.php'; // For generate_url() and get_setting()
    }
    session_name(SESSION_NAME);
    session_start();
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
        header('Location: ' . generate_url('dashboard'));
    exit;
}

require_once __DIR__ . '/config.php'; // Ensure config is loaded for ADMIN_USERNAME etc.
// db.php is not strictly needed for login page itself, but action handler will need it.

$error_message = '';
$message_type = $_GET['message'] ?? '';

if ($message_type === 'session_expired') {
    $error_message = 'نشست شما منقضی شده است. لطفا دوباره وارد شوید.';
} elseif (isset($_SESSION['login_error'])) {
    $error_message = $_SESSION['login_error'];
    unset($_SESSION['login_error']); // Clear error after displaying
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سیستم حسابداری فروشگاه</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
        }
        .form-input {
            @apply w-full px-4 py-3 rounded-lg bg-gray-50 border border-gray-300 focus:border-blue-500 focus:bg-white focus:ring-0 text-sm placeholder-gray-400 text-gray-700;
        }
        .form-label {
            @apply block text-sm font-medium text-gray-700 mb-1 text-right;
        }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-xl shadow-2xl">
        <div class="text-center">
            <svg class="mx-auto h-12 w-auto text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
            </svg>
            <h2 class="mt-6 text-3xl font-bold text-gray-900">
                ورود به حساب کاربری
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                لطفا اطلاعات خود را برای ورود وارد کنید
            </p>
        </div>

        <?php if ($error_message): ?>
            <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg border border-red-300 text-right" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form class="space-y-6" action="index.php" method="POST">
            <input type="hidden" name="action" value="login_user">
            
            <div>
                <label for="username" class="form-label">نام کاربری:</label>
                <input id="username" name="username" type="text" required class="form-input" placeholder="نام کاربری خود را وارد کنید">
            </div>

            <div>
                <label for="password" class="form-label">رمز عبور:</label>
                <input id="password" name="password" type="password" required class="form-input" placeholder="رمز عبور خود را وارد کنید">
            </div>

            <div>
                <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    ورود
                </button>
            </div>
        </form>
        <p class="mt-8 text-xs text-center text-gray-500">
            &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(STORE_NAME); ?>. تمامی حقوق محفوظ است.
        </p>
    </div>
</body>
</html>
