<?php
// /includes/header.php
// Common HTML head, Tailwind CSS, and top navigation bar for authenticated pages.

if (session_status() == PHP_SESSION_NONE) {
    if (!defined('SESSION_NAME')) { require_once __DIR__ . '/../config.php'; }
    if (!function_exists('get_setting') || !function_exists('generate_url')) { require_once __DIR__ . '/../db.php'; }
    session_name(SESSION_NAME);
    session_start();
}

global $page, $id; 

$store_name_from_db = get_setting('store_name', STORE_NAME);
$current_page_key = $page ?? 'dashboard';
$current_id_for_title = $id ?? null;

$page_titles_map = [
    'dashboard'         => 'داشبورد',
    'customers_list'    => 'لیست مشتریان',
    'customer_form'     => ($current_id_for_title ? 'ویرایش مشتری' : 'افزودن مشتری جدید'),
    'customer_info'     => 'اطلاعات مشتری',
    'products_list'     => 'لیست محصولات',
    'product_form'      => ($current_id_for_title ? 'ویرایش محصول' : 'افزودن محصول جدید'),
    'product_info'      => 'اطلاعات محصول',
    'invoices_list'     => 'لیست فاکتورها',
    'invoice_form'      => ($current_id_for_title ? 'ویرایش فاکتور' : 'ایجاد فاکتور جدید'),
    'invoice_details'   => 'جزئیات فاکتور',
    'invoice_print'     => 'چاپ فاکتور',
    'settings'          => 'تنظیمات فروشگاه',
    'reports'           => 'گزارشات',
];
$current_page_display_title = $page_titles_map[$current_page_key] ?? ucfirst(str_replace('_', ' ', $current_page_key));

$app_base_path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($app_base_path === '.' || $app_base_path === '/') {
    $app_base_path = ''; 
}

// Determine if the current page is a form page for specific styling
$is_form_page = in_array($current_page_key, ['customer_form', 'product_form', 'invoice_form', 'settings']);
$main_content_bg_color = $is_form_page ? 'bg-white' : 'bg-slate-50'; // White for forms, light gray for lists/dashboard

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($current_page_display_title) . ' - ' . htmlspecialchars($store_name_from_db); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!--<link rel="stylesheet" href="<?php echo $app_base_path; ?>/assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); // Cache busting ?>">-->
    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: <?php echo $main_content_bg_color; ?>; /* Dynamic background */
            color: #334155; /* Tailwind text-slate-700 */
            scroll-behavior: smooth;
        }
        /* Global styling inspired by "unnamed*" images */
        .form-input, .form-select, .form-textarea {
            @apply w-full px-4 py-2.5 rounded-lg bg-white border border-slate-300 text-slate-700
                   placeholder-slate-400 text-sm font-normal
                   focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500
                   transition duration-150 ease-in-out shadow-sm hover:border-slate-400;
        }
        .form-input[type="file"] { /* Keep specific file input styling if needed */
            @apply p-0 file:mr-3 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100;
        }
        .form-input[readonly], .form-input[disabled],
        .form-select[disabled], .form-textarea[disabled] {
            @apply bg-slate-100 cursor-not-allowed text-slate-500 border-slate-200;
        }
        .form-label {
            @apply block text-sm font-medium text-slate-700 mb-1.5 text-right;
        }
        .form-checkbox, .form-radio { /* Checkbox/Radio styling */
            @apply rounded border-slate-400 text-sky-600 shadow-sm focus:border-sky-400 focus:ring focus:ring-sky-300 focus:ring-opacity-40 h-4 w-4;
        }

        .btn { /* Base button style from "unnamed*" */
            @apply inline-flex items-center justify-center px-6 py-2.5 border border-transparent 
                   text-sm font-medium rounded-lg shadow-md hover:shadow-lg
                   focus:outline-none focus:ring-2 focus:ring-offset-2 
                   transition-all duration-150 ease-in-out transform active:scale-[0.98];
        }
        .btn-primary { /* Blue button, e.g., "ثبت مشتری" */
            @apply bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500;
        }
        .btn-secondary { /* Lighter, often for cancel or less prominent actions */
            @apply bg-slate-100 text-slate-700 border-slate-300 hover:bg-slate-200 focus:ring-slate-400 shadow-sm;
        }
        .btn-danger { /* Red button */
            @apply bg-red-600 text-white hover:bg-red-700 focus:ring-red-500;
        }
        .btn-success { /* Green button */
            @apply bg-emerald-500 text-white hover:bg-emerald-600 focus:ring-emerald-400;
        }
        .btn-warning { /* Yellow/Orange button */
             @apply bg-amber-500 text-white hover:bg-amber-600 focus:ring-amber-400;
        }
        .btn-icon { /* For icon-only buttons in tables/lists */
            @apply p-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-md;
        }
        .btn-icon-sm { @apply p-1.5; }

        .table-wrapper { /* Card containing the table */
            @apply bg-white rounded-xl shadow-lg overflow-hidden;
        }
        .table { /* The table itself */
            @apply w-full text-sm text-right;
        }
        .table th { /* Table header cells */
            @apply px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase bg-slate-50/70 border-b-2 border-slate-200 tracking-wider;
        }
        .table td { /* Table data cells */
            @apply px-5 py-3.5 whitespace-nowrap text-slate-700 border-b border-slate-100;
        }
        .table tbody tr:last-child td { @apply border-b-0; }
        .table tbody tr:hover { @apply bg-slate-50/70 transition-colors duration-100; }
        
        .badge { @apply px-3 py-1 text-xs font-bold rounded-full inline-block leading-tight; } /* Bolder badges */
        .badge-green { @apply bg-green-100 text-green-700; }
        .badge-yellow { @apply bg-yellow-100 text-yellow-700; }
        .badge-red { @apply bg-red-100 text-red-700; }
        .badge-blue { @apply bg-blue-100 text-blue-700; }
        .badge-indigo { @apply bg-indigo-100 text-indigo-700; }
        .badge-gray { @apply bg-slate-100 text-slate-700; }

        .icon-sm { @apply w-4 h-4 inline; }
        .icon-md { @apply w-5 h-5 inline; }
        .icon-lg { @apply w-6 h-6 inline; }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f0f4f8; border-radius: 10px; } /* Lighter track */
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; } /* slate-300 */
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; } /* slate-400 */

        @media print {
            body { font-size: 10pt; background-color: #fff !important; color: #000 !important; }
            .print\:hidden { display: none !important; }
            .print\:bg-white { background-color: #fff !important; }
            .print\:shadow-none { box-shadow: none !important; }
            .print\:border-none { border: none !important; }
            .print\:p-0 { padding: 0 !important; }
            .print\:text-black { color: #000 !important; }
            .print\:overflow-visible { overflow: visible !important; }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body class="text-slate-800"> <?php /* Body bg is set dynamically in <style> based on page type */ ?>
    <?php if (isset($_SESSION['user_id']) && ($page ?? '') !== 'invoice_print'): ?>
    <header class="bg-white h-16 flex items-center justify-between px-4 sm:px-6 sticky top-0 z-30 border-b border-slate-200 print:hidden">
        <div class="flex items-center">
            <button id="mobileMenuButton" class="md:hidden mr-2 text-slate-500 hover:text-slate-700 focus:outline-none p-2 rounded-md hover:bg-slate-100" aria-label="باز کردن منو">
                <i data-lucide="menu" class="w-6 h-6"></i>
            </button>
            <h1 class="hidden md:block text-lg font-semibold text-slate-700">
                <?php echo htmlspecialchars($current_page_display_title); ?>
            </h1>
        </div>
        <div class="flex items-center space-x-2 rtl:space-x-reverse">
            <button class="btn-icon text-slate-500 hover:text-slate-700 relative" aria-label="اطلاعیه‌ها">
                <i data-lucide="bell" class="w-5 h-5"></i>
            </button>
            
            <div class="relative">
                <button id="userMenuButton" class="flex items-center space-x-2 rtl:space-x-reverse p-1 pr-1.5 rounded-full hover:bg-slate-100 focus:outline-none" aria-expanded="false" aria-haspopup="true">
                    <span class="hidden sm:inline text-sm font-medium text-slate-600"><?php echo htmlspecialchars($_SESSION['username'] ?? 'کاربر'); ?></span>
                    <span class="inline-flex items-center justify-center h-9 w-9 rounded-full bg-sky-500 text-white text-xs font-semibold shadow-sm border-2 border-white">
                        <?php echo strtoupper(mb_substr($_SESSION['username'] ?? 'U', 0, 1, 'UTF-8')); ?>
                    </span>
                </button>
                
                <div id="userMenuDropdown" 
                     class="hidden absolute left-0 mt-2 w-56 origin-top-left bg-white rounded-lg shadow-xl z-20 ring-1 ring-slate-200 focus:outline-none py-1.5" 
                     role="menu" aria-orientation="vertical" aria-labelledby="userMenuButton" tabindex="-1">
                    <div class="px-4 py-2.5 border-b border-slate-100">
                        <p class="text-sm font-semibold text-slate-700 truncate">سلام، <?php echo htmlspecialchars($_SESSION['username'] ?? 'کاربر ادمین'); ?>!</p>
                    </div>
                    <div class="py-1">
                        <a href="<?php echo generate_url('settings'); ?>" class="flex items-center px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 hover:text-sky-600 group" role="menuitem" tabindex="-1">
                            <i data-lucide="settings-2" class="icon-sm ml-2.5 text-slate-400 group-hover:text-sky-500"></i>تنظیمات فروشگاه
                        </a>
                    </div>
                    <div class="border-t border-slate-100 py-1">
                        <a href="logout.php" class="flex items-center px-4 py-2 text-sm text-slate-600 hover:bg-red-50 hover:text-red-600 group" role="menuitem" tabindex="-1">
                            <i data-lucide="log-out" class="icon-sm ml-2.5 text-slate-400 group-hover:text-red-500"></i>خروج از سیستم
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <?php endif; ?>
