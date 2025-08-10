<?php
// /pages/invoice_print.php
// Generates a printer-friendly version of an invoice.
// This page is intended to be loaded directly, not within the main index.php layout.

// Start session if not already started (e.g., for auth check if direct access is restricted)
if (session_status() == PHP_SESSION_NONE) {
    // We need config for SESSION_NAME and potentially other constants
    // However, avoid including full index.php dependencies if this is a standalone print page.
    // For simplicity, we'll assume config.php can be included directly.
    require_once __DIR__ . '/../config.php'; // For UPLOAD_DIR, STORE_NAME etc.
    session_name(SESSION_NAME);
    session_start();
}

// Authentication check: Ensure user is logged in to print invoices
if (!isset($_SESSION['user_id'])) {
    // Redirect to login or show an error if trying to access print page directly without login
    // For a production app, you might want a more robust way to handle this,
    // possibly passing a one-time token if opened from the main app.
    header('Location: ../login.php?message=auth_required_for_print');
    exit;
}

require_once __DIR__ . '/../db.php'; // For getDB() and get_setting()

$db = getDB();
$invoice_id_to_print = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($invoice_id_to_print <= 0) {
    die("شناسه فاکتور نامعتبر است."); // Simple error for direct access
}

// Fetch invoice header
$stmt_invoice = $db->prepare("SELECT i.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.address as customer_address
                             FROM invoices i
                             LEFT JOIN customers c ON i.customer_id = c.id
                             WHERE i.id = :id");
$stmt_invoice->bindParam(':id', $invoice_id_to_print, PDO::PARAM_INT);
$stmt_invoice->execute();
$invoice = $stmt_invoice->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    die("فاکتور یافت نشد.");
}

// Fetch invoice items
$stmt_items = $db->prepare("SELECT ii.* FROM invoice_items ii WHERE ii.invoice_id = :invoice_id ORDER BY ii.id ASC");
$stmt_items->bindParam(':invoice_id', $invoice_id_to_print, PDO::PARAM_INT);
$stmt_items->execute();
$invoice_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// Store settings for display
$store_settings_print = [
    'store_name' => get_setting('store_name', STORE_NAME),
    'store_logo' => get_setting('store_logo', ''),
    'store_address' => get_setting('store_address', ''),
    'store_phone' => get_setting('store_phone', ''),
    'store_email' => get_setting('store_email', ''),
    'store_postal_code' => get_setting('store_postal_code', ''),
    'store_registration_number' => get_setting('store_registration_number', ''),
];
$store_logo_url_print = '';
if (!empty($store_settings_print['store_logo']) && file_exists(UPLOAD_DIR . $store_settings_print['store_logo'])) {
    // Assuming UPLOAD_DIR is relative to script or an absolute path.
    // For web URL, it needs to be accessible.
    // If UPLOAD_DIR is /var/www/app/uploads, and web root is /var/www/app, then 'uploads/' is correct.
    $base_url = dirname($_SERVER['SCRIPT_NAME']); // Gets directory of index.php if routed
    // A more robust way is to define a base URL in config.php
    $web_uploads_path = (strpos(UPLOAD_DIR, $_SERVER['DOCUMENT_ROOT']) === 0)
                        ? str_replace($_SERVER['DOCUMENT_ROOT'], '', UPLOAD_DIR)
                        : '../uploads/'; // Fallback if UPLOAD_DIR is outside web root or complex path
    $store_logo_url_print = rtrim($web_uploads_path, '/') . '/' . htmlspecialchars($store_settings_print['store_logo']);
}


if (!function_exists('format_currency_print_php')) { // Avoid conflict if already defined
    function format_currency_print_php($amount, $currency_symbol = null) {
        if ($currency_symbol === null) { $currency_symbol = defined('DEFAULT_CURRENCY_SYMBOL') ? DEFAULT_CURRENCY_SYMBOL : 'ریال'; }
        return is_numeric($amount) ? number_format($amount, 0, '.', ',') . ' ' . $currency_symbol : '0 ' . $currency_symbol;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>چاپ فاکتور شماره: <?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script> <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: #fff; /* White background for print */
            color: #000;
            -webkit-print-color-adjust: exact !important; /* Chrome, Safari */
            color-adjust: exact !important; /* Standard */
        }
        .print-container {
            max-width: 800px; /* A4-ish width */
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #eee; /* Optional border for screen view */
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt; /* Smaller font for print */
        }
        th, td {
            border: 1px solid #ccc;
            padding: 6px 8px;
            text-align: right;
        }
        th {
            background-color: #f8f8f8;
            font-weight: bold;
        }
        .no-print {
            display: none !important; /* Hide elements not for printing */
        }
        .totals-table td {
            border: none;
        }
        .header-section img {
            max-height: 70px; /* Control logo size */
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
                font-size: 10pt;
            }
            .print-container {
                max-width: 100%;
                margin: 0;
                padding: 10mm; /* Typical print margin */
                border: none;
                box-shadow: none;
            }
            .no-print-on-page { /* Class for buttons on the print page itself */
                display: none !important;
            }
            /* Ensure backgrounds print if any are set with Tailwind that don't by default */
            .bg-gray-100 { background-color: #f7fafc !important; }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="text-center mb-6 no-print-on-page">
            <button onclick="window.print()" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block ml-2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                چاپ فاکتور
            </button>
            <button onclick="window.close()" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400 ml-2">
                بستن
            </button>
        </div>

        <header class="header-section flex flex-row justify-between items-start pb-4 mb-4 border-b border-gray-300 gap-4">
            <div class="flex-shrink-0">
                <?php if ($store_logo_url_print): ?>
                    <img src="<?php echo $store_logo_url_print; ?>" alt="لوگو فروشگاه">
                <?php endif; ?>
                <h2 class="text-xl font-bold mt-2"><?php echo htmlspecialchars($store_settings_print['store_name']); ?></h2>
                <p class="text-xs"><?php echo htmlspecialchars($store_settings_print['store_address']); ?></p>
                <p class="text-xs">شماره تماس: <?php echo htmlspecialchars($store_settings_print['store_phone']); ?> | ایمیل: <?php echo htmlspecialchars($store_settings_print['store_email']); ?></p>
                <?php if($store_settings_print['store_registration_number']): ?> <p class="text-xs">شماره ثبت: <?php echo htmlspecialchars($store_settings_print['store_registration_number']); ?></p><?php endif; ?>
                <?php if($store_settings_print['store_postal_code']): ?> <p class="text-xs">کد پستی: <?php echo htmlspecialchars($store_settings_print['store_postal_code']); ?></p><?php endif; ?>
            </div>
            <div class="text-left mt-0">
                <h1 class="text-2xl font-extrabold text-black">فاکتور <?php echo htmlspecialchars($invoice['type']); ?></h1>
                <p class="mt-1">شماره فاکتور: <span class="font-semibold"><?php echo htmlspecialchars($invoice['invoice_number']); ?></span></p>
                <p class="text-sm">تاریخ صدور: <span class="font-semibold"><?php echo htmlspecialchars(date("Y/m/d", strtotime($invoice['date']))); ?></span></p>
                <?php if (!empty($invoice['due_date'])): ?>
                    <p class="text-sm">تاریخ سررسید: <span class="font-semibold"><?php echo htmlspecialchars(date("Y/m/d", strtotime($invoice['due_date']))); ?></span></p>
                <?php endif; ?>
            </div>
        </header>

        <section class="grid grid-cols-2 gap-4 mb-6 text-sm">
            <div>
                <h3 class="font-bold mb-1">فاکتور برای:</h3>
                <?php if ($invoice['customer_id'] && $invoice['customer_name']): ?>
                    <p class="font-semibold"><?php echo htmlspecialchars($invoice['customer_name']); ?></p>
                    <?php if ($invoice['customer_address']): ?><p><?php echo nl2br(htmlspecialchars($invoice['customer_address'])); ?></p><?php endif; ?>
                    <?php if ($invoice['customer_phone']): ?><p>تماس: <?php echo htmlspecialchars($invoice['customer_phone']); ?></p><?php endif; ?>
                    <?php if ($invoice['customer_email']): ?><p>ایمیل: <?php echo htmlspecialchars($invoice['customer_email']); ?></p><?php endif; ?>
                <?php else: ?>
                    <p>- اطلاعات مشتری در دسترس نیست -</p>
                <?php endif; ?>
            </div>
            <div class="text-left">
                <h3 class="font-bold mb-1">روش پرداخت:</h3>
                <p><?php echo htmlspecialchars($invoice['payment_method'] ?: '-'); ?></p>
                <h3 class="font-bold mt-2 mb-1">وضعیت فاکتور:</h3>
                <p class="font-semibold"><?php echo htmlspecialchars($invoice['status']); ?></p>
            </div>
        </section>

        <section class="mb-6">
            <table>
                <thead>
                    <tr>
                        <th>ردیف</th>
                        <th style="width: 40%;">شرح کالا / خدمات</th>
                        <th class="text-center">تعداد</th>
                        <th>قیمت واحد</th>
                        <th>مبلغ کل</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoice_items)): ?>
                        <tr><td colspan="5" class="text-center py-4">هیچ قلمی برای این فاکتور ثبت نشده است.</td></tr>
                    <?php else: ?>
                        <?php foreach ($invoice_items as $index => $item): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td><?php echo format_currency_print_php($item['unit_price']); ?></td>
                                <td><?php echo format_currency_print_php($item['total_price']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
        
        <section class="grid grid-cols-3 gap-4">
            <div class="col-span-2 text-xs">
                <?php if (!empty($invoice['notes'])): ?>
                    <h4 class="font-bold mb-1">یادداشت ها و شرایط:</h4>
                    <p class="whitespace-pre-line border p-2 rounded-md bg-gray-50"><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                <?php endif; ?>
            </div>
            <div class="text-sm space-y-1 totals-table">
                 <table>
                    <tr><td>جمع جزء:</td> <td class="font-semibold text-left"><?php echo format_currency_print_php($invoice['total_amount']); ?></td></tr>
                    <?php if ($invoice['discount'] > 0): ?>
                    <tr><td class="text-red-600">تخفیف:</td> <td class="font-semibold text-left text-red-600">-<?php echo format_currency_print_php($invoice['discount']); ?></td></tr>
                    <?php endif; ?>
                    <tr>
                        <?php 
                            $effective_total_for_tax_print = $invoice['total_amount'] - $invoice['discount'];
                            $tax_percentage_display_print = ($effective_total_for_tax_print > 0) ? round(($invoice['tax_amount'] / $effective_total_for_tax_print) * 100, 0) : 0;
                        ?>
                        <td>مالیات (<?php echo $tax_percentage_display_print; ?>٪):</td> 
                        <td class="font-semibold text-left"><?php echo format_currency_print_php($invoice['tax_amount']); ?></td>
                    </tr>
                    <tr class="border-t-2 border-black">
                        <td class="font-bold pt-1 text-base">مبلغ نهایی:</td> 
                        <td class="font-bold pt-1 text-left text-base"><?php echo format_currency_print_php($invoice['final_amount']); ?></td>
                    </tr>
                </table>
            </div>
        </section>
        
        <footer class="text-xs text-gray-600 text-center mt-8 border-t border-gray-300 pt-3">
            <p>با تشکر از همکاری شما!</p>
            <p>در صورت داشتن هرگونه سوال با شماره <?php echo htmlspecialchars($store_settings_print['store_phone']); ?> تماس بگیرید. - <?php echo htmlspecialchars($store_settings_print['store_name']); ?></p>
        </footer>
    </div>
     <script>
        // Optional: Auto-trigger print dialog when page loads
        // window.onload = function() {
        //     window.print();
        // }
    </script>
</body>
</html>
