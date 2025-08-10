<?php
// /pages/reports.php
// Displays various reports for the Store Accounting Program

if (!defined('DB_PATH')) {
    die("Access denied.");
}

$db = getDB();

// --- Report Data Fetching ---

// 1. Sales Summary (All time for simplicity, can be filtered by date range)
$total_sales_amount = $db->query("SELECT SUM(final_amount) FROM invoices WHERE status = 'پرداخت شده' AND type = 'فروش'")->fetchColumn() ?: 0;
$total_purchase_amount = $db->query("SELECT SUM(final_amount) FROM invoices WHERE status = 'پرداخت شده' AND type = 'خرید'")->fetchColumn() ?: 0;
$total_invoices_count = $db->query("SELECT COUNT(id) FROM invoices")->fetchColumn() ?: 0;
$paid_invoices_count = $db->query("SELECT COUNT(id) FROM invoices WHERE status = 'پرداخت شده'")->fetchColumn() ?: 0;
$pending_invoices_count_report = $db->query("SELECT COUNT(id) FROM invoices WHERE status = 'در انتظار پرداخت'")->fetchColumn() ?: 0;


// 2. Top Selling Products (by quantity sold)
$stmt_top_products = $db->query("
    SELECT p.id, p.name, p.product_id as product_code, SUM(ii.quantity) as total_quantity_sold, SUM(ii.total_price) as total_revenue
    FROM invoice_items ii
    JOIN invoices i ON ii.invoice_id = i.id
    JOIN products p ON ii.product_id = p.id
    WHERE i.type = 'فروش' AND i.status = 'پرداخت شده'
    GROUP BY p.id, p.name, p.product_id
    ORDER BY total_quantity_sold DESC, total_revenue DESC
    LIMIT 10
");
$top_selling_products = $stmt_top_products->fetchAll(PDO::FETCH_ASSOC);

// 3. Top Customers (by total amount spent on paid sales invoices)
$stmt_top_customers = $db->query("
    SELECT c.id, c.name, c.customer_id as customer_code, COUNT(i.id) as total_invoices, SUM(i.final_amount) as total_spent
    FROM customers c
    JOIN invoices i ON c.id = i.customer_id
    WHERE i.type = 'فروش' AND i.status = 'پرداخت شده'
    GROUP BY c.id, c.name, c.customer_id
    ORDER BY total_spent DESC
    LIMIT 10
");
$top_customers = $stmt_top_customers->fetchAll(PDO::FETCH_ASSOC);

// 4. Sales by Month (Example for the last 12 months)
// SQLite date functions can be tricky for grouping by month/year directly in a way that's always portable
// For simplicity, this example might need adjustment or more complex SQL for precise month grouping.
// This is a simplified version that might not be perfectly accurate for month grouping in all SQLite versions.
$sales_by_month_data = [];
try {
    // This query attempts to group by year and month.
    // The date format in the DB is 'YYYY-MM-DD'. strftime('%Y-%m', date) extracts this.
    $stmt_sales_by_month = $db->query("
        SELECT strftime('%Y-%m', date) as sale_month, SUM(final_amount) as monthly_total
        FROM invoices
        WHERE type = 'فروش' AND status = 'پرداخت شده'
        GROUP BY sale_month
        ORDER BY sale_month DESC
        LIMIT 12
    ");
    $sales_by_month_raw = $stmt_sales_by_month->fetchAll(PDO::FETCH_ASSOC);
    // Re-key by month for easier lookup, and reverse for chronological chart display
    foreach (array_reverse($sales_by_month_raw) as $row) {
        if ($row['sale_month']) { // Ensure sale_month is not null
            $sales_by_month_data[$row['sale_month']] = $row['monthly_total'];
        }
    }
} catch (PDOException $e) {
    error_log("Sales by month report error: " . $e->getMessage());
    // $sales_by_month_data will remain empty or partially filled
}


// Helper for currency formatting if not already defined
if (!function_exists('format_currency_php')) {
    function format_currency_php($amount, $currency_symbol = null) {
        if ($currency_symbol === null) { $currency_symbol = defined('DEFAULT_CURRENCY_SYMBOL') ? DEFAULT_CURRENCY_SYMBOL : 'ریال'; }
        return is_numeric($amount) ? number_format($amount, 0, '.', ',') . ' ' . $currency_symbol : '0 ' . $currency_symbol;
    }
}
?>

<div class="space-y-8">
    <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
        <h2 class="text-2xl font-semibold text-gray-800">گزارشات</h2>
        <button onclick="window.print()" class="bg-blue-600 flex text-white px-6 py-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
            <i data-lucide="printer" class="icon-md ml-2"></i> چاپ گزارشات
        </button>
    </div>

    <section class="bg-white p-6 rounded-xl shadow-lg">
        <h3 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">خلاصه مالی</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 text-sm">
            <div class="bg-green-50 p-4 rounded-lg">
                <p class="text-gray-600">مجموع فروش (پرداخت شده):</p>
                <p class="text-xl font-bold text-green-700"><?php echo format_currency_php($total_sales_amount); ?></p>
            </div>
            <div class="bg-red-50 p-4 rounded-lg">
                <p class="text-gray-600">مجموع خرید (پرداخت شده):</p>
                <p class="text-xl font-bold text-red-700"><?php echo format_currency_php($total_purchase_amount); ?></p>
            </div>
            <div class="bg-blue-50 p-4 rounded-lg">
                <p class="text-gray-600">سود ناخالص تخمینی:</p>
                <p class="text-xl font-bold text-blue-700"><?php echo format_currency_php($total_sales_amount - $total_purchase_amount); ?></p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-gray-600">تعداد کل فاکتورها:</p>
                <p class="text-xl font-bold text-gray-800"><?php echo number_format($total_invoices_count); ?></p>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-gray-600">فاکتورهای پرداخت شده:</p>
                <p class="text-xl font-bold text-gray-800"><?php echo number_format($paid_invoices_count); ?></p>
            </div>
            <div class="bg-yellow-50 p-4 rounded-lg">
                <p class="text-gray-600">فاکتورهای در انتظار پرداخت:</p>
                <p class="text-xl font-bold text-yellow-700"><?php echo number_format($pending_invoices_count_report); ?></p>
            </div>
        </div>
    </section>

    <section class="bg-white p-6 rounded-xl shadow-lg">
        <h3 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">فروش ماهانه (۱۲ ماه اخیر)</h3>
        <?php if (!empty($sales_by_month_data)): ?>
            <div class="overflow-x-auto p-2">
                <div class="flex space-x-2 rtl:space-x-reverse items-end h-64 bg-gray-50 p-4 rounded-md">
                    <?php
                        $max_monthly_sale = !empty($sales_by_month_data) ? max($sales_by_month_data) : 1; // Avoid division by zero
                        if ($max_monthly_sale == 0) $max_monthly_sale = 1; // Ensure it's not zero
                    ?>
                    <?php foreach ($sales_by_month_data as $month => $total): ?>
                        <div class="flex flex-col items-center flex-grow text-xs">
                            <div class="w-full sm:w-10 md:w-12 bg-blue-500 hover:bg-blue-600 transition-all" 
                                 style="height: <?php echo round(($total / $max_monthly_sale) * 100); ?>%; min-height: 2px;"
                                 title="<?php echo htmlspecialchars(date("M Y", strtotime($month . "-01"))) . ': ' . format_currency_php($total); ?>">
                            </div>
                            <span class="mt-1 transform rotate-0 sm:-rotate-45 sm:mt-3 whitespace-nowrap"><?php echo htmlspecialchars(date("M Y", strtotime($month . "-01"))); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-xs text-gray-500 mt-2 text-center"></p>
            </div>
        <?php else: ?>
            <p class="text-gray-600">داده ای برای نمایش فروش ماهانه موجود نیست.</p>
        <?php endif; ?>
    </section>

    <section class="bg-white p-6 rounded-xl shadow-lg">
        <h3 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">محصولات پرفروش (بر اساس تعداد)</h3>
        <div class="overflow-x-auto table-wrapper border border-slate-200 rounded-lg">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">#</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">نام محصول</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">شناسه محصول</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">تعداد فروخته شده</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">درآمد کل</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_selling_products)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-gray-500">هنوز محصولی فروخته نشده است.</td></tr>
                    <?php else: ?>
                        <?php foreach ($top_selling_products as $index => $product): ?>
                            <tr>
                                <td class="px-4 py-3 text-slate-700 font-medium"><?php echo $index + 1; ?></td>
                                <td class="px-4 py-3 text-slate-700 font-medium">
                                    <a href="index.php?page=product_info&id=<?php echo $product['id']; ?>" class="hover:text-blue-600">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-slate-700 font-medium"><?php echo htmlspecialchars($product['product_code'] ?: '-'); ?></td>
                                <td class="px-4 py-3 text-slate-700 font-medium"><?php echo number_format($product['total_quantity_sold']); ?></td>
                                <td class="px-4 py-3 text-slate-700 font-medium"><?php echo format_currency_php($product['total_revenue']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="bg-white p-6 rounded-xl shadow-lg">
        <h3 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">مشتریان برتر (بر اساس مجموع خرید)</h3>
        <div class="overflow-x-auto table-wrapper border border-slate-200 rounded-lg">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">#</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">نام مشتری</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">شناسه مشتری</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">تعداد فاکتورها</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">مجموع خرید</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_customers)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-gray-500">هنوز خریدی از مشتریان ثبت نشده است.</td></tr>
                    <?php else: ?>
                        <?php foreach ($top_customers as $index => $customer): ?>
                            <tr>
                                <td class="px-4 py-3 text-slate-700 font-medium"><?php echo $index + 1; ?></td>
                                <td class="px-4 py-3 text-slate-700 font-medium">
                                    <a href="index.php?page=customer_info&id=<?php echo $customer['id']; ?>" class="hover:text-blue-600">
                                        <?php echo htmlspecialchars($customer['name']); ?>
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-slate-700 font-medium"><?php echo htmlspecialchars($customer['customer_code'] ?: '-'); ?></td>
                                <td class="px-4 py-3 text-slate-700 font-medium"><?php echo number_format($customer['total_invoices']); ?></td>
                                <td class="px-4 py-3 text-slate-700 font-medium"><?php echo format_currency_php($customer['total_spent']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 10pt; }
            .bg-white { background-color: #fff !important; box-shadow: none !important; border: 1px solid #ccc !important; }
            .p-6 { padding: 10px !important; }
            h2, h3 { margin-bottom: 0.5rem !important; }
            table { margin-top: 0.5rem !important; }
            .space-y-8 > * + * { margin-top: 1rem !important; } /* Reduce spacing for print */
        }
    </style>
</div>

<script>
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    // For more advanced charts, consider a library like Chart.js or ApexCharts
    // and populate it using PHP-generated JSON data.
</script>
