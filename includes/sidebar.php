<?php
// /includes/sidebar.php
// Main navigation sidebar for authenticated users.

// This file is included by index.php after session_start() and db.php inclusion.
// So, session variables, get_setting(), and generate_url() should be available.

if (!isset($_SESSION['user_id'])) {
    // Should not happen if index.php's auth check is working, but as a safeguard:
    return; 
}

// $page is set in index.php and indicates the current page key
global $page; // Make it available from index.php
$current_page_key = $page ?? 'dashboard'; 

$store_name_sidebar = get_setting('store_name', STORE_NAME);
$store_logo_filename_sidebar = get_setting('store_logo', '');
$store_logo_url_sidebar = '';

// $app_base_path is available from header.php or can be defined here if needed
global $app_base_path; 
if (!isset($app_base_path)) { // Fallback if header didn't define it (e.g. direct include)
    $app_base_path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if ($app_base_path === '.' || $app_base_path === '/') $app_base_path = '';
}


if (!empty($store_logo_filename_sidebar) && file_exists(UPLOAD_DIR . $store_logo_filename_sidebar)) {
    $store_logo_url_sidebar = $app_base_path . '/' . UPLOAD_DIR_PUBLIC_PATH . htmlspecialchars($store_logo_filename_sidebar);
} else {
    // Fallback text-based logo or generic icon if no image logo
}


$menu_items = [
    // page_key is used with generate_url()
    ['label' => 'داشبورد',       'icon' => 'layout-dashboard', 'page_key' => 'dashboard'], // Changed icon
    ['label' => 'مشتریان',      'icon' => 'users-2',        'page_key' => 'customers_list'],
    ['label' => 'محصولات',     'icon' => 'package',        'page_key' => 'products_list'],
    ['label' => 'فاکتورها',     'icon' => 'file-text',      'page_key' => 'invoices_list'],
    ['label' => 'گزارش ها',      'icon' => 'bar-chart-3',    'page_key' => 'reports'],
    ['label' => 'تنظیمات',     'icon' => 'settings-2',     'page_key' => 'settings'],
];

?>
<aside id="mainSidebar" class="w-64 bg-slate-800 text-slate-200 flex flex-col fixed inset-y-0 right-0 transform translate-x-full md:relative md:translate-x-0 transition-transform duration-300 ease-in-out z-40 md:flex md:z-auto shadow-xl print:hidden">
    <div class="h-16 flex items-center justify-center px-4 border-b border-slate-700 shadow-sm">
        <a href="<?php echo generate_url('dashboard'); ?>" class="flex items-center space-x-3 rtl:space-x-reverse overflow-hidden w-full">
            <?php if ($store_logo_url_sidebar): ?>
                <img src="<?php echo $store_logo_url_sidebar; ?>" alt="لوگو <?php echo htmlspecialchars($store_name_sidebar); ?>" class="h-9 max-h-9 w-auto object-contain rounded-sm flex-shrink-0">
            <?php else: ?>
                 <i data-lucide="landmark" class="h-8 w-8 text-sky-400 flex-shrink-0"></i>
            <?php endif; ?>
            <h1 class="text-lg font-semibold text-white whitespace-nowrap truncate hover:text-sky-300 transition-colors">
                <?php echo htmlspecialchars($store_name_sidebar); ?>
            </h1>
        </a>
    </div>

    <nav class="flex-1 px-3 py-4 space-y-1.5 overflow-y-auto">
        <?php foreach ($menu_items as $item): ?>
            <?php 
                $is_active = ($current_page_key === $item['page_key']);
                if (!$is_active) { // More robust active state checking for parent menu items
                    if ($item['page_key'] === 'customers_list' && in_array($current_page_key, ['customer_form', 'customer_info'])) {
                        $is_active = true;
                    } elseif ($item['page_key'] === 'products_list' && in_array($current_page_key, ['product_form', 'product_info'])) {
                        $is_active = true;
                    } elseif ($item['page_key'] === 'invoices_list' && in_array($current_page_key, ['invoice_form', 'invoice_details', 'invoice_print'])) {
                        $is_active = true;
                    }
                }
                
                $link_classes = "w-full flex items-center space-x-3 rtl:space-x-reverse py-2.5 px-3.5 rounded-lg transition-all duration-150 ease-in-out group ";
                $icon_classes = "w-5 h-5 flex-shrink-0 ";

                if ($is_active) {
                    $link_classes .= 'bg-sky-600 text-white shadow-md font-medium';
                    $icon_classes .= 'text-white';
                } else {
                    $link_classes .= 'text-slate-300 hover:bg-slate-700 hover:text-white';
                    $icon_classes .= 'text-slate-400 group-hover:text-slate-100';
                }
            ?>
            <a href="<?php echo generate_url($item['page_key']); ?>" class="<?php echo $link_classes; ?>">
                <i data-lucide="<?php echo $item['icon']; ?>" class="<?php echo $icon_classes; ?>"></i>
                <span class="text-sm"><?php echo htmlspecialchars($item['label']); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="p-3 border-t border-slate-700 mt-auto">
        <a href="logout.php" <?php // logout.php is a standalone script ?>
           class="w-full flex items-center space-x-3 rtl:space-x-reverse py-2.5 px-3.5 rounded-lg text-slate-300 hover:bg-red-700 hover:text-white transition-colors duration-150 group">
            <i data-lucide="log-out" class="w-5 h-5 text-slate-400 group-hover:text-white"></i>
            <span class="text-sm font-medium">خروج از سیستم</span>
        </a>
    </div>
</aside>
