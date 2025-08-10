<?php
// /pages/invoice_details.php
// Displays the full details of a single invoice.

if (!defined('DB_PATH')) { // Should be defined in index.php
    die("Access denied. This page should be accessed via index.php");
}

// db.php and its functions are included via index.php
$db = getDB();
// $app_base_path, $id (invoice_db_id) are available from index.php/header.php
global $app_base_path, $id; 

$invoice_id_to_view = $id; 

if ($invoice_id_to_view === null || $invoice_id_to_view <= 0) {
    $_SESSION['action_message'] = ['type' => 'error', 'text' => 'شناسه فاکتور نامعتبر است.'];
    header('Location: ' . generate_url('invoices_list'));
    exit;
}

// Fetch invoice header, joining with customers for details
$stmt_invoice = $db->prepare("SELECT i.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.address as customer_address, c.customer_id as customer_code
                             FROM invoices i
                             LEFT JOIN customers c ON i.customer_id = c.id
                             WHERE i.id = :id");
$stmt_invoice->bindParam(':id', $invoice_id_to_view, PDO::PARAM_INT);
$stmt_invoice->execute();
$invoice = $stmt_invoice->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    $_SESSION['action_message'] = ['type' => 'error', 'text' => 'فاکتور یافت نشد.'];
    header('Location: ' . generate_url('invoices_list'));
    exit;
}

// Fetch invoice items
$stmt_items = $db->prepare("SELECT ii.*, p.product_id as product_code_item 
                            FROM invoice_items ii 
                            LEFT JOIN products p ON ii.product_id = p.id
                            WHERE ii.invoice_id = :invoice_id 
                            ORDER BY ii.id ASC");
$stmt_items->bindParam(':invoice_id', $invoice_id_to_view, PDO::PARAM_INT);
$stmt_items->execute();
$invoice_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// Store settings for display
$store_settings_display_details = [
    'store_name' => get_setting('store_name', STORE_NAME),
    'store_logo' => get_setting('store_logo', ''),
    'store_address' => get_setting('store_address', ''),
    'store_phone' => get_setting('store_phone', ''),
    'store_email' => get_setting('store_email', ''),
    'store_postal_code' => get_setting('store_postal_code', ''),
    'store_registration_number' => get_setting('store_registration_number', ''),
];
$store_logo_url_details_page = '';
if (!empty($store_settings_display_details['store_logo']) && file_exists(UPLOAD_DIR . $store_settings_display_details['store_logo'])) {
    $store_logo_url_details_page = $app_base_path . '/' . UPLOAD_DIR_PUBLIC_PATH . htmlspecialchars($store_settings_display_details['store_logo']);
}

?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-center gap-3 print:hidden">
        <h2 class="text-xl lg:text-2xl font-semibold text-slate-800">جزئیات فاکتور: <span class="text-sky-700 font-mono"><?php echo htmlspecialchars($invoice['invoice_number']); ?></span></h2>
        <div class="flex flex-wrap gap-2">
            <a href="<?php echo generate_url('invoices_list'); ?>" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400 ml-2">
             بازگشت به لیست
            </a>
            <a href="<?php echo generate_url('invoice_form', ['id' => $invoice['id']]); ?>" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
            ویرایش فاکتور
            </a>
            <a href="<?php echo generate_url('invoice_print', ['id' => $invoice['id']]); ?>" target="_blank" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block ml-2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg> چاپ فاکتور
            </a>
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg" id="invoice-content-area">
        <header class="flex flex-col sm:flex-row justify-between items-start pb-6 mb-6 border-b border-slate-200 gap-4">
            <div class="flex-shrink-0">
                <?php if ($store_logo_url_details_page): ?>
                    <img src="<?php echo $store_logo_url_details_page; ?>" alt="لوگو <?php echo htmlspecialchars($store_settings_display_details['store_name']); ?>" class="h-14 sm:h-16 mb-2 object-contain">
                <?php endif; ?>
                <h3 class="text-lg sm:text-xl font-bold text-slate-800"><?php echo htmlspecialchars($store_settings_display_details['store_name']); ?></h3>
                <p class="text-xs text-slate-500"><?php echo htmlspecialchars($store_settings_display_details['store_address']); ?></p>
                <p class="text-xs text-slate-500">شماره تماس: <?php echo htmlspecialchars($store_settings_display_details['store_phone']); ?> | ایمیل: <?php echo htmlspecialchars($store_settings_display_details['store_email']); ?></p>
                <?php if($store_settings_display_details['store_registration_number']): ?><p class="text-xs text-slate-500">شماره ثبت: <?php echo htmlspecialchars($store_settings_display_details['store_registration_number']); ?></p><?php endif; ?>
                <?php if($store_settings_display_details['store_postal_code']): ?><p class="text-xs text-slate-500">کد پستی: <?php echo htmlspecialchars($store_settings_display_details['store_postal_code']); ?></p><?php endif; ?>
            </div>
            <div class="text-left sm:text-right w-full sm:w-auto mt-4 sm:mt-0">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-sky-700">فاکتور <?php echo htmlspecialchars($invoice['type']); ?></h1>
                <p class="text-slate-700 mt-1">شماره فاکتور: <span class="font-semibold font-mono"><?php echo htmlspecialchars($invoice['invoice_number']); ?></span></p>
                <p class="text-slate-600 text-sm">تاریخ صدور: <span class="font-semibold"><?php echo htmlspecialchars(date("Y/m/d", strtotime($invoice['date']))); ?></span></p>
                <?php if (!empty($invoice['due_date'])): ?>
                    <p class="text-slate-600 text-sm">تاریخ سررسید: <span class="font-semibold"><?php echo htmlspecialchars(date("Y/m/d", strtotime($invoice['due_date']))); ?></span></p>
                <?php endif; ?>
            </div>
        </header>

        <section class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8 text-sm">
            <div>
                <h3 class="font-semibold text-slate-700 mb-1.5">فاکتور برای:</h3>
                <?php if ($invoice['customer_id'] && $invoice['customer_name']): ?>
                    <p class="text-base font-bold text-slate-800">
                        <a href="<?php echo generate_url('customer_info', ['id' => $invoice['customer_id']]); ?>" class="hover:text-sky-600">
                            <?php echo htmlspecialchars($invoice['customer_name']); ?>
                        </a>
                        <?php if($invoice['customer_code']): ?>
                            <span class="text-xs text-slate-500 font-mono">(<?php echo htmlspecialchars($invoice['customer_code']); ?>)</span>
                        <?php endif; ?>
                    </p>
                    <?php if ($invoice['customer_address']): ?><p class="text-slate-600 mt-0.5"><?php echo nl2br(htmlspecialchars($invoice['customer_address'])); ?></p><?php endif; ?>
                    <?php if ($invoice['customer_phone']): ?><p class="text-slate-600 mt-0.5">تماس: <?php echo htmlspecialchars($invoice['customer_phone']); ?></p><?php endif; ?>
                    <?php if ($invoice['customer_email']): ?><p class="text-slate-600 mt-0.5">ایمیل: <?php echo htmlspecialchars($invoice['customer_email']); ?></p><?php endif; ?>
                <?php else: ?>
                    <p class="text-slate-500 italic">- اطلاعات مشتری در دسترس نیست -</p>
                <?php endif; ?>
            </div>
            <div class="sm:text-right">
                <h3 class="font-semibold text-slate-700 mb-1.5">روش پرداخت:</h3>
                <p class="text-slate-700"><?php echo htmlspecialchars($invoice['payment_method'] ?: '-'); ?></p>
                <h3 class="font-semibold text-slate-700 mt-3 mb-1.5">وضعیت فاکتور:</h3>
                <p class="font-bold text-base <?php
                    switch ($invoice['status']) {
                        case 'پرداخت شده': echo 'text-green-600'; break;
                        case 'در انتظار پرداخت': echo 'text-yellow-600'; break;
                        case 'لغو شده': echo 'text-red-600'; break;
                        case 'پیش نویس': echo 'text-indigo-600'; break;
                        default: echo 'text-slate-600'; break;
                    }
                ?>"><?php echo htmlspecialchars($invoice['status']); ?></p>
            </div>
        </section>

        <section class="mb-8">
            <h3 class="text-lg font-semibold text-slate-700 mb-3">اقلام فاکتور:</h3>
            <div class="overflow-x-auto border border-slate-200 rounded-lg shadow-sm">
                <table class="w-full table">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="w-12">ردیف</th>
                            <th class="w-2/5">شرح کالا / خدمات</th>
                            <th class="text-center">تعداد</th>
                            <th>قیمت واحد</th>
                            <th>مبلغ کل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoice_items)): ?>
                            <tr><td colspan="5" class="text-center py-8 text-slate-500 italic">هیچ قلمی برای این فاکتور ثبت نشده است.</td></tr>
                        <?php else: ?>
                            <?php foreach ($invoice_items as $index => $item): ?>
                                <tr>
                                    <td class="text-center text-slate-600"><?php echo $index + 1; ?></td>
                                    <td class="text-slate-700">
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                        <?php if ($item['product_id']): ?>
                                            <a href="<?php echo generate_url('product_info', ['id' => $item['product_id']]); ?>" class="text-xs text-sky-500 hover:underline ml-1">(<?php echo htmlspecialchars($item['product_code_item'] ?: 'مشاهده'); ?>)</a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center text-slate-600"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                    <td class="text-slate-600"><?php echo format_currency_php($item['unit_price']); ?></td>
                                    <td class="text-slate-700 font-medium"><?php echo format_currency_php($item['total_price']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        
        <section class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-2 text-sm">
                <?php if (!empty($invoice['notes'])): ?>
                    <h4 class="font-semibold text-slate-700 mb-1.5">یادداشت ها و شرایط:</h4>
                    <div class="text-slate-600 bg-slate-50 p-3.5 rounded-md border border-slate-200 whitespace-pre-line leading-relaxed">
                        <?php echo nl2br(htmlspecialchars($invoice['notes'])); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="text-sm space-y-2 bg-slate-100 p-4 rounded-lg shadow-sm border border-slate-200">
                <div class="flex justify-between py-1.5 border-b border-slate-200">
                    <span class="text-slate-600">جمع جزء:</span> 
                    <span class="font-semibold text-slate-800"><?php echo format_currency_php($invoice['total_amount']); ?></span>
                </div>
                <?php if ($invoice['discount'] > 0): ?>
                <div class="flex justify-between py-1.5 border-b border-slate-200 text-red-600">
                    <span class="text-slate-600">تخفیف:</span> 
                    <span class="font-semibold">-<?php echo format_currency_php($invoice['discount']); ?></span>
                </div>
                <?php endif; ?>
                <div class="flex justify-between py-1.5 border-b border-slate-200">
                    <?php 
                        $effective_total_for_tax_details = (float)$invoice['total_amount'] - (float)$invoice['discount'];
                        $tax_percentage_display_details = ($effective_total_for_tax_details > 0 && (float)$invoice['tax_amount'] > 0) 
                            ? round(((float)$invoice['tax_amount'] / $effective_total_for_tax_details) * 100, 0) 
                            : ($effective_total_for_tax_details == 0 && (float)$invoice['tax_amount'] == 0 ? 0 : 'N/A'); // Handle division by zero or no tax
                    ?>
                    <span class="text-slate-600">مالیات (<?php echo $tax_percentage_display_details; ?><?php if(is_numeric($tax_percentage_display_details)) echo '٪'; ?>):</span> 
                    <span class="font-semibold text-slate-800"><?php echo format_currency_php($invoice['tax_amount']); ?></span>
                </div>
                <div class="flex justify-between pt-2.5 font-bold text-lg text-sky-700">
                    <span>مبلغ نهایی قابل پرداخت:</span> 
                    <span><?php echo format_currency_php($invoice['final_amount']); ?></span>
                </div>
            </div>
        </section>
        
        <footer class="text-xs text-slate-500 text-center mt-10 border-t border-slate-200 pt-4">
            <p>با تشکر از همکاری شما!</p>
            <p>در صورت داشتن هرگونه سوال با شماره <?php echo htmlspecialchars($store_settings_display_details['store_phone']); ?> تماس بگیرید. - <?php echo htmlspecialchars($store_settings_display_details['store_name']); ?></p>
        </footer>
    </div>
</div>