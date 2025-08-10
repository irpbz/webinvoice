<?php
// /pages/invoices_list.php
// Displays the list of invoices, with fixed filter button layout and button styling.

if (!defined('DB_PATH')) { die("Access denied."); }

$db = getDB();
global $app_base_path; 

// --- Search and Filter ---
$search_term = trim($_GET['search'] ?? '');
$filter_type = $_GET['type'] ?? 'all'; 
$filter_status = $_GET['status'] ?? 'all'; 
$filter_customer_id = isset($_GET['customer_id']) && $_GET['customer_id'] !== '' ? (int)$_GET['customer_id'] : null;

$current_query_params = $_GET; 
unset($current_query_params['page']); 
unset($current_query_params['p']);    

$db_params = []; 
$conditions = [];

if (!empty($search_term)) {
    $conditions[] = "(i.invoice_number LIKE :search_term OR c.name LIKE :search_term OR c.email LIKE :search_term)";
    $db_params[':search_term'] = '%' . $search_term . '%';
}
if ($filter_type !== 'all' && !empty($filter_type)) {
    $conditions[] = "i.type = :type";
    $db_params[':type'] = $filter_type;
}
if ($filter_status !== 'all' && !empty($filter_status)) {
    $conditions[] = "i.status = :status";
    $db_params[':status'] = $filter_status;
}
if ($filter_customer_id !== null) {
    $conditions[] = "i.customer_id = :customer_id";
    $db_params[':customer_id'] = $filter_customer_id;
}

$where_clause = '';
if (!empty($conditions)) {
    $where_clause = " WHERE " . implode(" AND ", $conditions);
}

// --- Pagination ---
$page_num_pagination = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$items_per_page = 5; 
$offset = ($page_num_pagination - 1) * $items_per_page;

$total_query = "SELECT COUNT(i.id) FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id" . $where_clause;
$total_stmt = $db->prepare($total_query);
$total_stmt->execute($db_params);
$total_invoices = $total_stmt->fetchColumn();
$total_pages = ceil($total_invoices / $items_per_page);

if ($page_num_pagination > $total_pages && $total_pages > 0) {
    $redirect_params_pagination = $current_query_params;
    $redirect_params_pagination['p'] = $total_pages;
    header('Location: ' . generate_url('invoices_list', $redirect_params_pagination));
    exit;
}

$query = "SELECT i.id, i.invoice_number, i.customer_id, c.name as customer_name, c.email as customer_email, i.date, i.type, i.final_amount, i.status
          FROM invoices i
          LEFT JOIN customers c ON i.customer_id = c.id
          {$where_clause}
          ORDER BY i.date DESC, i.created_at DESC
          LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
foreach ($db_params as $key => $value) {
    $param_type = ($key === ':customer_id') ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($key, $value, $param_type);
}
$stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$customers_stmt = $db->query("SELECT id, name, customer_id FROM customers ORDER BY name ASC");
$all_customers = $customers_stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
        <h2 class="text-xl lg:text-2xl font-semibold text-slate-800">مدیریت صورتحساب ها</h2>
        <a href="<?php echo generate_url('invoice_form'); ?>" class="bg-blue-600 flex text-white px-6 py-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
            <i data-lucide="plus-circle" class="icon-md ml-2"></i> ایجاد صورتحساب
        </a>
    </div>

    <div class="bg-white p-4 md:p-5 rounded-xl shadow-lg border border-slate-200">
        <form method="GET" action="<?php echo generate_url('invoices_list'); ?>">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-6 gap-7 items-end">
                <div> 
                    <label for="search_invoice_input_main" class="form-label">جستجو:</label>
                    <div class="relative">
                         <span class="absolute inset-y-0 right-0 flex items-center pr-3.5 pointer-events-none">
                            <i data-lucide="search" class="w-5 h-5 text-slate-400"></i>
                        </span>
                        <input type="text" id="search_invoice_input_main" name="search" class="form-input pr-10" placeholder="شماره فاکتور، مشتری..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                </div>
                 <div>
                    <label for="filter_customer_id_input" class="form-label">مشتری:</label>
                    <select id="filter_customer_id_input" name="customer_id" class="form-select">
                        <option value="">همه مشتریان</option>
                        <?php foreach ($all_customers as $cust): ?>
                            <option value="<?php echo $cust['id']; ?>" <?php echo ($filter_customer_id == $cust['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cust['name']) . ($cust['customer_id'] ? ' (' . htmlspecialchars($cust['customer_id']) . ')' : ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filter_type_input" class="form-label">نوع فاکتور:</label>
                    <select id="filter_type_input" name="type" class="form-select">
                        <option value="all" <?php echo ($filter_type === 'all') ? 'selected' : ''; ?>>همه انواع</option>
                        <option value="فروش" <?php echo ($filter_type === 'فروش') ? 'selected' : ''; ?>>فروش</option>
                        <option value="خرید" <?php echo ($filter_type === 'خرید') ? 'selected' : ''; ?>>خرید</option>
                    </select>
                </div>
                <div>
                    <label for="filter_status_input" class="form-label">وضعیت فاکتور:</label>
                    <select id="filter_status_input" name="status" class="form-select">
                        <option value="all" <?php echo ($filter_status === 'all') ? 'selected' : ''; ?>>همه وضعیت ها</option>
                        <option value="پرداخت شده" <?php echo ($filter_status === 'پرداخت شده') ? 'selected' : ''; ?>>پرداخت شده</option>
                        <option value="در انتظار پرداخت" <?php echo ($filter_status === 'در انتظار پرداخت') ? 'selected' : ''; ?>>در انتظار پرداخت</option>
                        <option value="لغو شده" <?php echo ($filter_status === 'لغو شده') ? 'selected' : ''; ?>>لغو شده</option>
                        <option value="پیش نویس" <?php echo ($filter_status === 'پیش نویس') ? 'selected' : ''; ?>>پیش نویس</option>
                    </select>
                </div>
                <div>
                <button type="submit" class="btn btn-primary w-full sm:w-auto px-6 py-2.5">
                    <i data-lucide="filter" class="icon-sm ml-1.5"></i> اعمال فیلتر
                </button>
                </div>
            <div class="flex flex-col sm:flex-row gap-3 items-center justify-start mt-4 pt-4">
                 <?php if (!empty($search_term) || $filter_type !== 'all' || $filter_status !== 'all' || $filter_customer_id !== null): ?>
                    <a href="<?php echo generate_url('invoices_list'); ?>" class="btn btn-secondary w-full sm:w-auto text-center px-6 py-2.5">پاک کردن فیلترها</a>
                <?php endif; ?>
            </div>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden border border-slate-200">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-2 py-3 w-10 text-center">
                            <input type="checkbox" class="form-checkbox rounded border-slate-400" aria-label="انتخاب همه">
                        </th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">مشتری</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">مبلغ (<?php echo DEFAULT_CURRENCY_SYMBOL; ?>)</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">تاریخ صدور</th>
                        <th class="px-4 py-3 text-center font-semibold text-slate-600">وضعیت</th>
                        <th class="px-4 py-3 text-center font-semibold text-slate-600 w-32">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-10 text-slate-500">
                                <i data-lucide="file-x-2" class="w-12 h-12 mx-auto mb-3 text-slate-400"></i>
                                <p class="font-medium text-base mb-1">
                                <?php if(!empty($search_term) || $filter_status !== 'all' || $filter_type !== 'all' || $filter_customer_id !== null): ?>
                                    هیچ صورتحسابی با معیارهای اعمال شده یافت نشد.
                                <?php else: ?>
                                    هنوز هیچ صورتحسابی ثبت نشده است.
                                <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-2 py-3 text-center">
                                    <input type="checkbox" class="form-checkbox rounded border-slate-400" aria-label="انتخاب این ردیف">
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($invoice['customer_id'] && $invoice['customer_name']): ?>
                                        <div class="flex flex-col">
                                            <a href="<?php echo generate_url('customer_info', ['id' => $invoice['customer_id']]); ?>" class="font-semibold text-slate-700 hover:text-sky-600 block">
                                                <?php echo htmlspecialchars($invoice['customer_name']); ?>
                                            </a>
                                            <?php if ($invoice['customer_email']): ?>
                                                <span class="text-xs text-slate-500"><?php echo htmlspecialchars($invoice['customer_email']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-slate-700 font-medium"><?php echo format_currency_php($invoice['final_amount']); ?></td>
                                <td class="px-4 py-3 text-slate-600"><?php echo htmlspecialchars(date("Y/m/d", strtotime($invoice['date']))); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge <?php
                                        switch ($invoice['status']) {
                                            case 'پرداخت شده': echo 'bg-green-100 text-green-700 border border-green-200'; break;
                                            case 'در انتظار پرداخت': echo 'bg-amber-100 text-amber-700 border border-amber-200'; break;
                                            case 'لغو شده': echo 'bg-red-100 text-red-700 border border-red-200'; break;
                                            case 'پیش نویس': echo 'bg-sky-100 text-sky-700 border border-sky-200'; break;
                                            default: echo 'bg-slate-100 text-slate-700 border border-slate-200'; break;
                                        }
                                    ?>">
                                        <?php echo htmlspecialchars($invoice['status']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
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
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav class="mt-5 flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0 text-sm text-slate-600" aria-label="Pagination">
        <div class="font-medium">
            نمایش <span class="font-semibold text-slate-800"><?php echo $offset + 1; ?></span> تا <span class="font-semibold text-slate-800"><?php echo min($offset + $items_per_page, $total_invoices); ?></span> از <span class="font-semibold text-slate-800"><?php echo number_format($total_invoices); ?></span> صورتحساب
        </div>
        <div class="flex items-center space-x-2 rtl:space-x-reverse">
            <?php if ($page_num_pagination > 1): ?>
                <a href="<?php echo generate_url('invoices_list', array_merge($current_query_params, ['p' => $page_num_pagination - 1])); ?>" class="btn btn-secondary !px-3 !py-1.5 text-xs">
                    <i data-lucide="chevron-right" class="icon-sm ml-1"></i> قبلی
                </a>
            <?php else: ?>
                 <span class="btn btn-secondary !px-3 !py-1.5 text-xs opacity-50 cursor-not-allowed"><i data-lucide="chevron-right" class="icon-sm ml-1"></i> قبلی</span>
            <?php endif; ?>
            
            <span class="text-xs text-slate-500">صفحه <?php echo $page_num_pagination; ?> از <?php echo $total_pages; ?></span>

            <?php if ($page_num_pagination < $total_pages): ?>
                <a href="<?php echo generate_url('invoices_list', array_merge($current_query_params, ['p' => $page_num_pagination + 1])); ?>" class="btn btn-secondary !px-3 !py-1.5 text-xs">
                    بعدی <i data-lucide="chevron-left" class="icon-sm mr-1"></i>
                </a>
            <?php else: ?>
                <span class="btn btn-secondary !px-3 !py-1.5 text-xs opacity-50 cursor-not-allowed">بعدی <i data-lucide="chevron-left" class="icon-sm mr-1"></i></span>
            <?php endif; ?>
        </div>
    </nav>
    <?php endif; ?>
</div>
