<?php
// index.php
// Main application router and page loader

// CRITICAL: Load configuration first
require_once __DIR__ . '/config.php';
// Load database and helper functions (including generate_url)
require_once __DIR__ . '/db.php'; 

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_name(SESSION_NAME); 
    session_start();
}

// --- Variable Initialization ---
$page = 'dashboard'; // Default page
$action = null;
$id = null; // Will store the numeric ID for edit/info pages
$request_params = $_GET; // Store all GET parameters for potential use in generate_url

// --- URL Parsing & Routing ---
// .htaccess rewrites friendly URLs to index.php with 'page' and 'id' or 'action' query parameters.
// Example: /customers -> index.php?page=customers_list
// Example: /customer/edit/123 -> index.php?page=customer_form&id=123
// Example: /action/save_settings -> index.php?action=save_settings

if (isset($request_params['page'])) {
    $page = preg_replace('/[^a-zA-Z0-9_]/', '', $request_params['page']); 
    unset($request_params['page']); // Remove from general params after processing
}
if (isset($request_params['id'])) {
    $id = filter_var($request_params['id'], FILTER_VALIDATE_INT); 
    if ($id === false || $id <= 0) $id = null; // Ensure positive integer or null
    unset($request_params['id']); 
}
// Action can be in GET (from .htaccess for /action/...) or POST (from forms)
if (isset($_GET['action'])) {
    $action = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['action']); 
} elseif (isset($_POST['action'])) { 
    $action = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['action']);
}
// Remove 'action' from $request_params if it was a GET parameter to avoid re-processing
if (isset($request_params['action'])) {
    unset($request_params['action']);
}


// --- Action Handling ---
$action_message_display = null; // For displaying messages on the page

if ($action) {
    $action_file = null;
    // Determine action file based on action name prefix or specific names
    if (strpos($action, 'customer') !== false) $action_file = __DIR__ . '/actions/customer_actions.php';
    elseif (strpos($action, 'product') !== false) $action_file = __DIR__ . '/actions/product_actions.php';
    elseif (strpos($action, 'invoice') !== false) $action_file = __DIR__ . '/actions/invoice_actions.php';
    elseif (strpos($action, 'settings') !== false) $action_file = __DIR__ . '/actions/settings_actions.php'; // Catches 'save_settings'
    elseif (in_array($action, ['login_user'])) $action_file = __DIR__ . '/actions/user_actions.php';

    if ($action_file && file_exists($action_file)) {
        require_once $action_file;
        $action_function_name = 'handle_' . $action . '_action'; 

        if (function_exists($action_function_name)) {
            $handler_result = $action_function_name(); 
            
            if (!empty($handler_result['text'])) {
                $_SESSION['action_message'] = ['type' => $handler_result['type'], 'text' => $handler_result['text']];
            }
            if (isset($handler_result['redirect_to'])) { // Action handler specified redirect
                header('Location: ' . $handler_result['redirect_to']);
                exit;
            }
        } else {
            error_log("Action function not found: " . $action_function_name . " in file " . $action_file);
            $_SESSION['action_message'] = ['type' => 'error', 'text' => 'عملیات درخواستی نامعتبر است (تابع اجرا کننده یافت نشد).'];
        }
    } else {
        error_log("Action file not found for action: " . $action . " (expected " . ($action_file ?? 'N/A') . ")");
        $_SESSION['action_message'] = ['type' => 'error', 'text' => 'عملیات درخواستی نامعتبر است (فایل اجرا کننده یافت نشد).'];
    }

    // POST-Redirect-GET pattern:
    // If it was a POST request and the action handler didn't explicitly redirect,
    // redirect to the current page (or a sensible default) using GET to clear POST data.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($handler_result['redirect_to'])) {
        $redirect_page = $page; // Current page
        $redirect_params = $request_params; // Remaining GET params
        if ($id) $redirect_params['id'] = $id; // Add back id if it was part of the original URL

        header('Location: ' . generate_url($redirect_page, $redirect_params));
        exit;
    }
}

// Retrieve and clear action message from session for display
if (isset($_SESSION['action_message'])) {
    $action_message_display = $_SESSION['action_message'];
    unset($_SESSION['action_message']);
}


// --- Page Authentication and Loading ---
$auth_required_pages = [
    'dashboard',
    'customers_list', 'customer_form', 'customer_info',
    'products_list', 'product_form', 'product_info',
    'invoices_list', 'invoice_form', 'invoice_details', 'invoice_print',
    'settings',
    'reports'
];

if (in_array($page, $auth_required_pages)) {
    if (!isset($_SESSION['user_id'])) {
        // Store intended URL before redirecting to login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php'); // login.php is standalone
        exit;
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT_DURATION) {
        session_unset();
        session_destroy();
        header('Location: login.php?message=session_expired');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

$page_file = __DIR__ . '/pages/' . $page . '.php';

// --- Render Page ---
// The $page, $id, and $request_params variables are available to header.php and the included $page_file.
require_once __DIR__ . '/includes/header.php'; 

echo '<div class="flex h-screen bg-slate-100 font-vazirmatn print:bg-white" dir="rtl">'; // Changed bg-gray-100 to bg-slate-100 for a cooler tone
// Sidebar is not shown for invoice_print page (handled within sidebar.php or here)
if (isset($_SESSION['user_id']) && $page !== 'invoice_print') { 
    require_once __DIR__ . '/includes/sidebar.php'; 
}

echo '<div class="flex-1 flex flex-col overflow-hidden print:overflow-visible">';

// Main content area styling adjustment for print
$main_container_class = "flex-1 overflow-x-hidden overflow-y-auto bg-slate-100 print:bg-white print:overflow-visible";
if ($page === 'invoice_print') {
    $main_container_class = "flex-1 bg-white"; // No padding for print page, let print CSS handle margins
} else {
    $main_container_class .= " p-4 md:p-6"; // Standard padding for other pages
}
echo '<main class="' . $main_container_class . '">';

// Display action message if any
if ($action_message_display && !empty($action_message_display['text'])) {
    $message_type_class = $action_message_display['type'] === 'success' 
        ? 'bg-green-50 border-green-500 text-green-700' 
        : 'bg-red-50 border-red-500 text-red-700';
    $icon_lucide = $action_message_display['type'] === 'success' ? 'check-circle-2' : 'alert-circle';
    $icon_color = $action_message_display['type'] === 'success' ? 'text-green-500' : 'text-red-500';
    $title_text = $action_message_display['type'] === 'success' ? 'موفقیت!' : 'خطا!';

    echo '<div class="mb-5 border-r-4 p-4 rounded-md shadow-md ' . $message_type_class . '" role="alert">';
    echo '  <div class="flex">';
    echo '    <div class="flex-shrink-0 pt-0.5">';
    echo '      <i data-lucide="' . $icon_lucide . '" class="w-5 h-5 ' . $icon_color . '"></i>';
    echo '    </div>';
    echo '    <div class="mr-3 flex-1 md:flex md:justify-between">';
    echo '      <div>';
    echo '          <p class="text-sm font-bold">' . $title_text . '</p>';
    echo '          <p class="text-sm">' . htmlspecialchars($action_message_display['text']) . '</p>';
    echo '      </div>';
    // Optional: Add a close button for the alert
    // echo '      <button type="button" class="mt-2 md:mt-0 md:ml-auto text-sm font-medium text-gray-500 hover:text-gray-700" onclick="this.parentElement.parentElement.parentElement.style.display=\'none\'">بستن</button>';
    echo '    </div>';
    echo '  </div>';
    echo '</div>';
}

if (file_exists($page_file)) {
    // The $id and $request_params variables are available to the included page file.
    require_once $page_file;
} else {
    if ($page !== 'login' && $page !== 'logout') { // login.php and logout.php are standalone
        http_response_code(404);
        echo '<div class="text-center py-12 px-4">';
        echo '  <i data-lucide="compass" class="w-20 h-20 mx-auto mb-6 text-sky-500"></i>';
        echo '  <h1 class="text-4xl font-bold text-slate-700">خطای 404</h1>';
        echo '  <p class="text-slate-600 mt-3 text-lg">صفحه مورد نظر (<span class="font-mono text-base text-red-600">' . htmlspecialchars($page) . '</span>) یافت نشد.</p>';
        echo '  <p class="text-slate-500 mt-2">ممکن است آدرس را اشتباه وارد کرده باشید یا صفحه حذف شده باشد.</p>';
        if (isset($_SESSION['user_id'])) {
            echo '<a href="' . generate_url('dashboard') . '" class="mt-8 inline-flex items-center btn btn-primary px-6 py-3 text-base">
                      <i data-lucide="arrow-right" class="icon-md ml-2"></i> بازگشت به داشبورد
                  </a>';
        } else {
            echo '<a href="login.php" class="mt-8 inline-flex items-center btn btn-primary px-6 py-3 text-base">
                      <i data-lucide="log-in" class="icon-md ml-2"></i> رفتن به صفحه ورود
                  </a>';
        }
        echo '</div>';
    }
}

echo '</main>';
echo '</div>'; 
echo '</div>'; 

require_once __DIR__ . '/includes/footer.php';

?>
