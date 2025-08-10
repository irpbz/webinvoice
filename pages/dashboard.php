<?php
// /pages/dashboard.php
// Dashboard page for the Store Accounting Program

if (!defined('DB_PATH')) { // Should be defined in index.php if included correctly
    die("Access denied. This page should be accessed via index.php");
}

// db.php (and thus generate_url() and format_currency_php()) should already be included by index.php
$db = getDB();

// --- Fetch data for dashboard ---

// Recent Invoices (last 3-5, newest first)
$stmt_invoices = $db->query("SELECT i.id, i.invoice_number, i.customer_id, c.name as customer_name, i.final_amount, i.status, i.date 
                             FROM invoices i
                             LEFT JOIN customers c ON i.customer_id = c.id
                             ORDER BY i.date DESC, i.created_at DESC LIMIT 3");
$recent_invoices = $stmt_invoices->fetchAll(PDO::FETCH_ASSOC);

// Recent Customers (last 3-5, newest first)
$stmt_customers = $db->query("SELECT id, name, email, profile_pic, customer_id, join_date, created_at FROM customers ORDER BY created_at DESC LIMIT 3");
$recent_customers = $stmt_customers->fetchAll(PDO::FETCH_ASSOC);

// Recent Products Added (last 3-5, newest first)
$stmt_products_added = $db->query("SELECT id, name, image, inventory, sell_price, product_id FROM products ORDER BY created_at DESC LIMIT 3");
$recent_products_added = $stmt_products_added->fetchAll(PDO::FETCH_ASSOC);

// --- Stats ---
// Total Sales (paid 'فروش' invoices)
$stmt_total_sales = $db->query("SELECT SUM(final_amount) as total FROM invoices WHERE status = 'پرداخت شده' AND type = 'فروش'");
$total_sales_amount_stat = $stmt_total_sales->fetchColumn() ?: 0;

// Pending Invoices ('در انتظار پرداخت' invoices)
$stmt_pending_invoices = $db->query("SELECT COUNT(id) as count FROM invoices WHERE status = 'در انتظار پرداخت'");
$pending_invoices_count_stat = $stmt_pending_invoices->fetchColumn() ?: 0;

// Total Customers
$stmt_total_customers = $db->query("SELECT COUNT(id) as count FROM customers");
$total_customers_count_stat = $stmt_total_customers->fetchColumn() ?: 0;

// Low Stock Products (inventory > 0 AND < 10, for example)
$low_stock_threshold = 10;
$stmt_low_stock = $db->prepare("SELECT COUNT(id) as count FROM products WHERE inventory > 0 AND inventory < :threshold");
$stmt_low_stock->bindParam(':threshold', $low_stock_threshold, PDO::PARAM_INT);
$stmt_low_stock->execute();
$low_stock_products_count_stat = $stmt_low_stock->fetchColumn() ?: 0;

// $app_base_path is available from header.php
global $app_base_path; 

?>

<div class="space-y-6 xl:space-y-8">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="bg-white p-5 rounded-xl shadow-lg border-r-4 border-emerald-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs sm:text-sm font-medium text-slate-500 uppercase">درآمد کل (پرداخت شده)</p>
                    <p class="text-xl sm:text-2xl lg:text-3xl font-bold text-slate-800 mt-1"><?php echo format_currency_php($total_sales_amount_stat); ?></p>
                </div>
                <div class="p-2.5 sm:p-3 rounded-full bg-emerald-100 text-emerald-600">
                    <i data-lucide="dollar-sign" class="w-6 h-6 sm:w-7 sm:h-7"></i>
                </div>
            </div>
        </div>
        <div class="bg-white p-5 rounded-xl shadow-lg border-r-4 border-amber-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs sm:text-sm font-medium text-slate-500 uppercase">فاکتورهای معوق</p>
                    <p class="text-xl sm:text-2xl lg:text-3xl font-bold text-slate-800 mt-1"><?php echo number_format($pending_invoices_count_stat); ?></p>
                </div>
                <div class="p-2.5 sm:p-3 rounded-full bg-amber-100 text-amber-600">
                    <i data-lucide="alert-triangle" class="w-6 h-6 sm:w-7 sm:h-7"></i>
                </div>
            </div>
        </div>
        <div class="bg-white p-5 rounded-xl shadow-lg border-r-4 border-sky-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs sm:text-sm font-medium text-slate-500 uppercase">تعداد مشتریان</p>
                    <p class="text-xl sm:text-2xl lg:text-3xl font-bold text-slate-800 mt-1"><?php echo number_format($total_customers_count_stat); ?></p>
                </div>
                <div class="p-2.5 sm:p-3 rounded-full bg-sky-100 text-sky-600">
                    <i data-lucide="users-2" class="w-6 h-6 sm:w-7 sm:h-7"></i>
                </div>
            </div>
        </div>
        <div class="bg-white p-5 rounded-xl shadow-lg border-r-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs sm:text-sm font-medium text-slate-500 uppercase">محصولات با موجودی کم</p>
                    <p class="text-xl sm:text-2xl lg:text-3xl font-bold text-slate-800 mt-1"><?php echo number_format($low_stock_products_count_stat); ?></p>
                </div>
                <div class="p-2.5 sm:p-3 rounded-full bg-red-100 text-red-600">
                    <i data-lucide="archive" class="w-6 h-6 sm:w-7 sm:h-7"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <div class="xl:col-span-2 bg-white p-5 md:p-6 rounded-xl shadow-lg">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg md:text-xl font-semibold text-slate-700">آخرین فاکتورها</h3>
                <a href="<?php echo generate_url('invoices_list'); ?>" class="text-sm font-medium text-sky-600 hover:text-sky-700 hover:underline">مشاهده همه <i data-lucide="arrow-left" class="icon-sm"></i></a>
            </div>
            <div class="overflow-x-auto table-wrapper border border-slate-200 rounded-lg">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-right font-semibold text-slate-600">شماره فاکتور</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-600">مشتری</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-600">مبلغ نهایی</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-600">وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_invoices)): ?>
                            <tr><td colspan="4" class="text-center py-6 text-slate-500 italic">موردی یافت نشد.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent_invoices as $invoice): ?>
                                <tr>
                                    <td class="px-4 py-3 text-slate-700 font-medium">
                                        <a href="<?php echo generate_url('invoice_details', ['id' => $invoice['id']]); ?>" class="hover:text-sky-600">
                                            <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700 font-medium"><?php echo htmlspecialchars($invoice['customer_name'] ?: 'مشتری حذف شده'); ?></td>
                                    <td class="px-4 py-3 text-slate-700 font-medium"><?php echo format_currency_php($invoice['final_amount']); ?></td>
                                    <td class="px-4 py-3 text-slate-700 font-medium">
                                        <span class="badge <?php
                                            switch ($invoice['status']) {
                                                case 'پرداخت شده': echo 'badge-green'; break;
                                                case 'در انتظار پرداخت': echo 'badge-yellow'; break;
                                                case 'لغو شده': echo 'badge-red'; break;
                                                case 'پیش نویس': echo 'badge-indigo'; break;
                                                default: echo 'badge-gray'; break;
                                            }
                                        ?>"><?php echo htmlspecialchars($invoice['status']); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white p-5 md:p-6 rounded-xl shadow-lg">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg md:text-xl font-semibold text-slate-700">مشتریان جدید</h3>
                <a href="<?php echo generate_url('customers_list'); ?>" class="text-sm font-medium text-sky-600 hover:text-sky-700 hover:underline">مشاهده همه <i data-lucide="arrow-left" class="icon-sm"></i></a>
            </div>
            <div class="space-y-3">
                <?php if (empty($recent_customers)): ?>
                     <p class="text-center py-6 text-slate-500 italic">موردی یافت نشد.</p>
                <?php else: ?>
                    <?php foreach ($recent_customers as $customer): ?>
                        <a href="<?php echo generate_url('customer_info', ['id' => $customer['id']]); ?>" class="flex items-center p-3 -mx-3 rounded-lg hover:bg-slate-50 transition-colors">
                            <?php
                            $profile_pic_path_dash = UPLOAD_DIR_PUBLIC_PATH . ($customer['profile_pic'] ?? '');
                            $profile_pic_url_dash = (!empty($customer['profile_pic']) && file_exists(UPLOAD_DIR . $customer['profile_pic']))
                                ? $app_base_path . '/' . $profile_pic_path_dash
                                : 'https://placehold.co/40x40/E2E8F0/64748B?text=' . strtoupper(mb_substr(htmlspecialchars($customer['name']), 0, 1, 'UTF-8'));
                            ?>
                            <img src="<?php echo $profile_pic_url_dash; ?>" alt="<?php echo htmlspecialchars($customer['name']); ?>" class="w-10 h-10 rounded-full object-cover ml-3 border border-slate-200"/>
                            <div class="flex-1">
                                <p class="font-semibold text-slate-800 text-sm"><?php echo htmlspecialchars($customer['name']); ?></p>
                                <p class="text-xs text-slate-500"><?php echo htmlspecialchars($customer['email'] ?: 'بدون ایمیل'); ?></p>
                            </div>
                            <span class="text-xs text-slate-400"><?php echo htmlspecialchars(date("Y/m/d", strtotime($customer['join_date'] ?: $customer['created_at']))); ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="bg-white p-5 md:p-6 rounded-xl shadow-lg">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg md:text-xl font-semibold text-slate-700">آخرین محصولات اضافه شده</h3>
            <a href="<?php echo generate_url('products_list'); ?>" class="text-sm font-medium text-sky-600 hover:text-sky-700 hover:underline">مشاهده همه <i data-lucide="arrow-left" class="icon-sm"></i></a>
        </div>
        <div class="overflow-x-auto table-wrapper border border-slate-200 rounded-lg">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">نام محصول</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">موجودی</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">قیمت فروش</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_products_added)): ?>
                        <tr><td colspan="3" class="text-center py-6 text-slate-500 italic">موردی یافت نشد.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recent_products_added as $product): ?>
                            <tr>
                                <td class="px-4 flex py-3 text-slate-700 font-medium">
                                    <?php
                                    $product_image_path_dash = UPLOAD_DIR_PUBLIC_PATH . ($product['image'] ?? '');
                                    $product_image_url_dash = (!empty($product['image']) && file_exists(UPLOAD_DIR . $product['image']))
                                        ? $app_base_path . '/' . $product_image_path_dash
                                        : 'https://placehold.co/40x40/E2E8F0/64748B?text=P';
                                    ?>
                                    <img src="<?php echo $product_image_url_dash; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-10 h-10 rounded-md object-cover ml-3 border border-slate-200"/>
                                    <div>
                                        <a href="<?php echo generate_url('product_info', ['id' => $product['id']]); ?>" class="hover:text-sky-600">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </a>
                                        <p class="text-xs text-slate-500"><?php echo htmlspecialchars($product['product_id'] ?: '-'); ?></p>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-700 font-medium">
                                     <span class="badge <?php 
                                        if ($product['inventory'] > 10) echo 'badge-green'; 
                                        elseif ($product['inventory'] > 0) echo 'badge-yellow'; 
                                        else echo 'badge-red'; 
                                    ?>">
                                        <?php echo htmlspecialchars($product['inventory']); ?> عدد
                                    </span>
                                </td>
                                <td><?php echo format_currency_php($product['sell_price']); ?></td>
                                                                            <td class="px-4 py-3 text-slate-700 font-medium">
                                    <div class="flex items-center justify-center space-x-1 rtl:space-x-reverse">
                                        <a href="<?php echo generate_url('product_info', ['id' => $product['id']]); ?>" class="btn-see-item p-2 text-blue-500 hover:text-blue-700 hover:bg-blue-100 rounded-md" title="مشاهده">
                                            <i data-lucide="eye" class="icon-md"></i>
                                        </a>
                                        <a href="<?php echo generate_url('product_form', ['id' => $product['id']]); ?>" class="btn-edit-item p-2 text-yellow-500 hover:text-yellow-700 hover:bg-yellow-100 rounded-md" title="ویرایش">
                                            <i data-lucide="edit-3" class="icon-md"></i>
                                        </a>
                                        <form action="<?php echo generate_url('delete_product', [], true); ?>" method="POST" class="inline-block" onsubmit="return confirmDelete(event, 'آیا از حذف محصول \'<?php echo htmlspecialchars(addslashes($product['name'])); ?>\' مطمئن هستید؟');">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <button type="submit" class="btn-delete-item p-2 text-red-500 hover:text-red-700 hover:bg-red-100 rounded-md" title="حذف">
                                                <i data-lucide="trash-2" class="icon-md"></i>
                                            </button>
                                        </form>
                                    </div>
                                            </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Ensure Lucide icons are re-rendered if this content is loaded dynamically.
    // Since this is a full page load, they should be rendered by the script in footer.php.
    // If you were loading parts of this page via AJAX, you'd call initializeLucideIcons() here.
    // For safety, if this script block is guaranteed to run after lucide.js is loaded:
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>
