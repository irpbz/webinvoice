<?php
// /pages/customer_info.php
// Displays detailed information for a single customer and their invoices.

if (!defined('DB_PATH')) { // Should be defined in index.php
    die("Access denied. This page should be accessed via index.php");
}

// db.php and its functions are included via index.php
$db = getDB();
// $app_base_path, $id (customer's DB ID) are available from index.php/header.php
global $app_base_path, $id; 

$customer_id_to_view = $id; // $id is the customer's database ID from the URL

if ($customer_id_to_view === null || $customer_id_to_view <= 0) {
    $_SESSION['action_message'] = ['type' => 'error', 'text' => 'شناسه مشتری نامعتبر است.'];
    header('Location: ' . generate_url('customers_list'));
    exit;
}

// Fetch customer details
$stmt_customer = $db->prepare("SELECT * FROM customers WHERE id = :id");
$stmt_customer->bindParam(':id', $customer_id_to_view, PDO::PARAM_INT);
$stmt_customer->execute();
$customer = $stmt_customer->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    $_SESSION['action_message'] = ['type' => 'error', 'text' => 'مشتری یافت نشد.'];
    header('Location: ' . generate_url('customers_list'));
    exit;
}

// Fetch customer's invoices (e.g., last 10 or all, with pagination if needed)
$stmt_invoices = $db->prepare("SELECT id, invoice_number, date, final_amount, status, type 
                               FROM invoices 
                               WHERE customer_id = :customer_id 
                               ORDER BY date DESC, created_at DESC 
                               LIMIT 10"); // Limiting for now, pagination could be added
$stmt_invoices->bindParam(':customer_id', $customer_id_to_view, PDO::PARAM_INT);
$stmt_invoices->execute();
$customer_invoices = $stmt_invoices->fetchAll(PDO::FETCH_ASSOC);

$profile_pic_url_info = 'https://placehold.co/128x128/E0E7FF/4338CA?text=' . strtoupper(mb_substr(htmlspecialchars($customer['name']), 0, 1, 'UTF-8'));
if (!empty($customer['profile_pic']) && file_exists(UPLOAD_DIR . $customer['profile_pic'])) {
    $profile_pic_path_info = UPLOAD_DIR_PUBLIC_PATH . htmlspecialchars($customer['profile_pic']);
    $profile_pic_url_info = $app_base_path . '/' . $profile_pic_path_info;
}

// Determine active tab from GET parameter, default to 'invoices'
$active_tab = $_GET['tab'] ?? 'invoices'; 
if (!in_array($active_tab, ['invoices', 'details'])) {
    $active_tab = 'invoices'; // Default to a valid tab
}

?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <h2 class="text-xl lg:text-2xl font-semibold text-slate-800">
            اطلاعات مشتری: <span class="text-sky-700"><?php echo htmlspecialchars($customer['name']); ?></span>
        </h2>
        <a href="<?php echo generate_url('customers_list'); ?>" class="bg-blue-600 flex text-white px-6 py-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
            <i data-lucide="arrow-right" class="icon-sm ml-2"></i> بازگشت به لیست مشتریان
        </a>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-lg">
        <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
            <img src="<?php echo $profile_pic_url_info; ?>" alt="عکس پروفایل <?php echo htmlspecialchars($customer['name']); ?>" class="w-32 h-32 rounded-full object-cover border-4 border-sky-500 shadow-md bg-slate-100">
            <div class="flex-1 text-center md:text-right">
                <h3 class="text-2xl lg:text-3xl font-bold text-slate-800"><?php echo htmlspecialchars($customer['name']); ?></h3>
                <p class="text-slate-600 mt-1.5 text-sm">
                    <i data-lucide="mail" class="icon-sm ml-1.5 text-slate-400 rtl:mr-0 rtl:ml-1.5"></i> <?php echo htmlspecialchars($customer['email'] ?: 'ایمیل ثبت نشده'); ?>
                </p>
                <p class="text-slate-600 text-sm">
                    <i data-lucide="phone" class="icon-sm ml-1.5 text-slate-400 rtl:mr-0 rtl:ml-1.5"></i> <?php echo htmlspecialchars($customer['phone'] ?: 'شماره تماس ثبت نشده'); ?>
                </p>
                <p class="text-slate-600 text-sm">
                    <i data-lucide="map-pin" class="icon-sm ml-1.5 text-slate-400 rtl:mr-0 rtl:ml-1.5"></i> <?php echo htmlspecialchars($customer['address'] ?: 'آدرس ثبت نشده'); ?>
                </p>
                <p class="text-xs text-slate-500 mt-2.5">
                    شناسه مشتری: <span class="font-medium"><?php echo htmlspecialchars($customer['customer_id'] ?: '-'); ?></span> | 
                    تاریخ عضویت: <span class="font-medium"><?php echo htmlspecialchars(date("Y/m/d", strtotime($customer['join_date'] ?: $customer['created_at']))); ?></span>
                </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 mt-4 md:mt-0 self-center md:self-start shrink-0">
                <a href="<?php echo generate_url('customer_form', ['id' => $customer['id']]); ?>" class="bg-yellow-600 flex text-white px-6 py-2 rounded-lg hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2">
                    <i data-lucide="edit-3" class="icon-md ml-2"></i> ویرایش مشتری
                </a>
                <form action="<?php echo generate_url('delete_customer', [], true); ?>" method="POST" class="inline-block w-full sm:w-auto" onsubmit="return confirmDelete(event, 'آیا از حذف مشتری \'<?php echo htmlspecialchars(addslashes($customer['name'])); ?>\' مطمئن هستید؟ این عمل تمامی فاکتورهای مرتبط با این مشتری را نیز تحت تاثیر قرار خواهد داد (مشتری از فاکتورها حذف می‌شود).');">
                    <input type="hidden" name="customer_id" value="<?php echo $customer['id']; ?>">
                    <button type="submit" class="bg-red-600 flex text-white px-6 py-2 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                        <i data-lucide="trash-2" class="icon-md ml-2"></i> حذف مشتری
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg">
        <div class="border-b border-slate-200">
            <nav class="flex -mb-px px-4 sm:px-6 space-x-4 rtl:space-x-reverse" aria-label="Tabs">
                <a href="<?php echo generate_url('customer_info', ['id' => $customer['id'], 'tab' => 'invoices']); ?>" 
                   class="py-3 sm:py-4 px-2 border-b-2 font-medium text-sm whitespace-nowrap flex items-center gap-2 <?php echo ($active_tab === 'invoices') ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'; ?>">
                   <i data-lucide="file-text" class="icon-sm"></i> آخرین فاکتورها <span class="bg-slate-100 text-slate-600 text-xs font-semibold px-1.5 py-0.5 rounded-full"><?php echo count($customer_invoices); ?></span>
                </a>
                <a href="<?php echo generate_url('customer_info', ['id' => $customer['id'], 'tab' => 'details']); ?>" 
                   class="py-3 sm:py-4 px-2 border-b-2 font-medium text-sm whitespace-nowrap flex items-center gap-2 <?php echo ($active_tab === 'details') ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'; ?>">
                   <i data-lucide="info" class="icon-sm"></i> اطلاعات تکمیلی
                </a>
            </nav>
        </div>
        <div class="p-4 sm:p-6">
            <?php if ($active_tab === 'invoices'): ?>
                <div class="space-y-4">
                    <button class="bg-blue-600 flex text-white px-6 py-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                            <a href="<?php echo generate_url('invoice_form', ['customer_id' => $customer['id']]); ?>" class="btn btn-success mb-2 inline-flex items-center">
                        <i data-lucide="plus-circle" class="icon-md ml-2"></i> ایجاد فاکتور برای این مشتری
                    </a>
                    </button>
                    <?php if (empty($customer_invoices)): ?>
                        <div class="text-center py-8 text-slate-500">
                            <i data-lucide="file-search-2" class="w-12 h-12 mx-auto mb-3 text-slate-400"></i>
                            <p>این مشتری هنوز فاکتوری ندارد.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto table-wrapper border border-slate-200 rounded-lg"> <table class="w-full text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-right font-semibold text-slate-600">شماره فاکتور</th>
                                        <th class="px-4 py-3 text-right font-semibold text-slate-600">تاریخ</th>
                                        <th class="px-4 py-3 text-right font-semibold text-slate-600">نوع</th>
                                        <th class="px-4 py-3 text-right font-semibold text-slate-600">مبلغ نهایی</th>
                                        <th class="px-4 py-3 text-right font-semibold text-slate-600">وضعیت</th>
                                        <th class="px-4 py-3 text-right font-semibold text-slate-600">عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($customer_invoices as $invoice): ?>
                                        <tr>
                                            <td class="px-4 py-3 text-slate-700 font-medium">
                                                <a href="<?php echo generate_url('invoice_details', ['id' => $invoice['id']]); ?>" class="hover:text-sky-600">
                                                    <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                                </a>
                                            </td>
                                            <td class="px-4 py-3 text-slate-700 font-medium"><?php echo htmlspecialchars(date("Y/m/d", strtotime($invoice['date']))); ?></td>
                                            <td class="px-4 py-3 text-slate-700 font-medium"><?php echo htmlspecialchars($invoice['type']); ?></td>
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
                                            <td class="px-4 py-3 text-slate-700 font-medium">
                                    <div class="flex items-center justify-center space-x-1 rtl:space-x-reverse">
                                        <a href="<?php echo generate_url('invoice_details', ['id' => $invoice['id']]); ?>" class="btn-see-item p-2 text-blue-500 hover:text-blue-700 hover:bg-blue-100 rounded-md" title="مشاهده">
                                            <i data-lucide="eye" class="icon-md"></i>
                                        </a>
                                        <a href="<?php echo generate_url('invoice_form', ['id' => $invoice['id']]); ?>" class="btn-edit-item p-2 text-yellow-500 hover:text-yellow-700 hover:bg-yellow-100 rounded-md" title="ویرایش">
                                            <i data-lucide="edit-3" class="icon-md ml-2"></i>
                                        </a>
                                        <form action="<?php echo generate_url('delete_invoice', [], true); ?>" method="POST" class="inline-block" onsubmit="return confirmDelete(event, 'آیا از حذف فاکتور \'<?php echo htmlspecialchars(addslashes($invoice['invoice_number'])); ?>\' مطمئن هستید؟');">
                                            <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
                                            <button type="submit" class="btn-delete-item p-2 text-red-500 hover:text-red-700 hover:bg-red-100 rounded-md" title="حذف">
                                                <i data-lucide="trash-2" class="icon-md ml-2"></i>
                                            </button>
                                        </form>
                                    </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($active_tab === 'details'): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5 text-sm">
                    <div class="sm:col-span-2">
                        <p class="form-label">نام کامل:</p>
                        <p class="text-slate-800 font-medium"><?php echo htmlspecialchars($customer['name']); ?></p>
                    </div>
                    <div>
                        <p class="form-label">شماره تماس:</p>
                        <p class="text-slate-800"><?php echo htmlspecialchars($customer['phone'] ?: '-'); ?></p>
                    </div>
                    <div>
                        <p class="form-label">آدرس ایمیل:</p>
                        <p class="text-slate-800"><?php echo htmlspecialchars($customer['email'] ?: '-'); ?></p>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="form-label">آدرس:</p>
                        <p class="text-slate-800 leading-relaxed"><?php echo nl2br(htmlspecialchars($customer['address'] ?: '-')); ?></p>
                    </div>
                     <div>
                        <p class="form-label">شناسه مشتری:</p>
                        <p class="text-slate-800 font-mono"><?php echo htmlspecialchars($customer['customer_id'] ?: '-'); ?></p>
                    </div>
                    <div>
                        <p class="form-label">تاریخ عضویت:</p>
                        <p class="text-slate-800"><?php echo htmlspecialchars(date("Y/m/d H:i", strtotime($customer['join_date'] ?: $customer['created_at']))); ?></p>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="form-label">توضیحات:</p>
                        <p class="text-slate-800 whitespace-pre-wrap bg-slate-50 p-3 rounded-md border border-slate-200"><?php echo nl2br(htmlspecialchars($customer['notes'] ?: 'ثبت نشده')); ?></p>
                    </div>
                     <div class="sm:col-span-2">
                        <p class="form-label">آخرین بروزرسانی اطلاعات:</p>
                        <p class="text-slate-500"><?php echo htmlspecialchars(date("Y/m/d H:i", strtotime($customer['updated_at']))); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
