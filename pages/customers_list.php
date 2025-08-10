<?php
// /pages/customers_list.php
// Displays the list of customers, styled to match products_list and user's invoice_list.php

if (!defined('DB_PATH')) { // Should be defined in index.php
    die("Access denied. This page should be accessed via index.php");
}

// db.php and its functions are included via index.php
$db = getDB();
// $app_base_path and other global vars are available from header.php/index.php
global $app_base_path; 

// --- Search ---
$search_term = trim($_GET['search'] ?? '');
// Store all current GET parameters for pagination and filter persistence
$current_query_params = $_GET; 
unset($current_query_params['page']); 
unset($current_query_params['p']);    

$db_params = []; 
$conditions = [];

if (!empty($search_term)) {
    $conditions[] = "(c.name LIKE :search_term OR c.email LIKE :search_term OR c.phone LIKE :search_term OR c.customer_id LIKE :search_term)";
    $db_params[':search_term'] = '%' . $search_term . '%';
}

$where_clause = '';
if (!empty($conditions)) {
    $where_clause = " WHERE " . implode(" AND ", $conditions);
}

// --- Pagination ---
$page_num_pagination = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$items_per_page = 5; // Consistent with other list pages
$offset = ($page_num_pagination - 1) * $items_per_page;

// Get total number of customers (for pagination)
$total_stmt = $db->prepare("SELECT COUNT(c.id) FROM customers c" . $where_clause);
$total_stmt->execute($db_params);
$total_customers = $total_stmt->fetchColumn();
$total_pages = ceil($total_customers / $items_per_page);

if ($page_num_pagination > $total_pages && $total_pages > 0) { 
    $redirect_params_pagination = $current_query_params;
    $redirect_params_pagination['p'] = $total_pages;
    header('Location: ' . generate_url('customers_list', $redirect_params_pagination));
    exit;
}

// Fetch customers with pagination
$stmt = $db->prepare("SELECT c.id, c.name, c.email, c.phone, c.profile_pic, c.customer_id, c.join_date, c.created_at 
                      FROM customers c 
                      {$where_clause}
                      ORDER BY c.created_at DESC 
                      LIMIT :limit OFFSET :offset");

foreach ($db_params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
        <h2 class="text-xl lg:text-2xl font-semibold text-slate-800">لیست مشتریان</h2>
        <a href="<?php echo generate_url('customer_form'); ?>" class="bg-blue-600 flex text-white px-6 py-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
            <i data-lucide="user-plus" class="icon-md ml-2"></i> افزودن مشتری جدید
        </a>
    </div>

    <div class="bg-white p-4 md:p-5 rounded-xl shadow-lg border border-slate-200">
        <form method="GET" action="<?php echo generate_url('customers_list'); ?>">
            <div class="flex flex-col sm:flex-row gap-3 items-end">
                <div class="relative flex-grow">
                    <label for="search_customer_input" class="form-label">جستجوی مشتریان:</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3.5 pointer-events-none">
                            <i data-lucide="search" class="w-5 h-5 text-slate-400"></i>
                        </span>
                        <input type="text" id="search_customer_input" name="search" class="form-input pr-10" placeholder="نام، ایمیل، تماس، شناسه..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                </div>
                <div class="flex sm:flex-none gap-3 w-full sm:w-auto">
                    <button type="submit" class="btn btn-primary flex-grow sm:flex-grow-0 px-6">
                        <i data-lucide="search" class="icon-sm mr-1.5 sm:hidden"></i>
                        <span class="hidden sm:inline">جستجو</span>
                    </button>
                     <?php if (!empty($search_term)): ?>
                        <a href="<?php echo generate_url('customers_list'); ?>" class="btn btn-secondary flex-grow sm:flex-grow-0 text-center px-6">پاک کردن</a>
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
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">نام مشتری</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">شماره تماس</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">ایمیل</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">تاریخ عضویت</th>
                        <th class="px-4 py-3 text-center font-semibold text-slate-600 w-32">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-10 text-slate-500">
                                <i data-lucide="users-2" class="w-12 h-12 mx-auto mb-3 text-slate-400"></i>
                                <p class="font-medium text-base mb-1">
                                <?php if(!empty($search_term)): ?>
                                    هیچ مشتری با عبارت "<?php echo htmlspecialchars($search_term); ?>" یافت نشد.
                                <?php else: ?>
                                    هنوز هیچ مشتری ثبت نشده است.
                                <?php endif; ?>
                                </p>
                                <?php if(empty($search_term)): ?>
                                <a href="<?php echo generate_url('customer_form'); ?>" class="text-sky-600 hover:underline font-medium">یک مشتری جدید اضافه کنید</a>.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $customer): ?>
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-2 py-3 text-center">
                                    <input type="checkbox" class="form-checkbox rounded border-slate-400" aria-label="انتخاب این ردیف">
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center">
                                        <?php
                                        $profile_pic_url_clist = 'https://placehold.co/40x40/E0E7FF/4338CA?text=' . strtoupper(mb_substr(htmlspecialchars($customer['name']), 0, 1, 'UTF-8'));
                                        if (!empty($customer['profile_pic']) && file_exists(UPLOAD_DIR . $customer['profile_pic'])) {
                                            $profile_pic_path_clist = UPLOAD_DIR_PUBLIC_PATH . htmlspecialchars($customer['profile_pic']);
                                            $profile_pic_url_clist = $app_base_path . '/' . $profile_pic_path_clist;
                                        }
                                        ?>
                                        <img src="<?php echo $profile_pic_url_clist; ?>" alt="<?php echo htmlspecialchars($customer['name']); ?>" class="w-10 h-10 rounded-full object-cover ml-3.5 border-2 border-slate-200 shadow-sm"/>
                                        <div>
                                            <a href="<?php echo generate_url('customer_info', ['id' => $customer['id']]); ?>" class="font-semibold text-slate-700 hover:text-sky-600 block">
                                                <?php echo htmlspecialchars($customer['name']); ?>
                                            </a>
                                            <p class="text-xs text-slate-500">شناسه: <?php echo htmlspecialchars($customer['customer_id'] ?: '-'); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-600"><?php echo htmlspecialchars($customer['phone'] ?: '-'); ?></td>
                                <td class="px-4 py-3 text-slate-600"><?php echo htmlspecialchars($customer['email'] ?: '-'); ?></td>
                                <td class="px-4 py-3 text-slate-600"><?php echo htmlspecialchars(date("Y/m/d", strtotime($customer['join_date'] ?: $customer['created_at']))); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center space-x-1 rtl:space-x-reverse">
                                        <a href="<?php echo generate_url('customer_info', ['id' => $customer['id']]); ?>" class="btn-see-item p-2 text-blue-500 hover:text-blue-700 hover:bg-blue-100 rounded-md" title="مشاهده">
                                            <i data-lucide="eye" class="icon-md"></i>
                                        </a>
                                        <a href="<?php echo generate_url('customer_form', ['id' => $customer['id']]); ?>" class="btn-edit-item p-2 text-yellow-500 hover:text-yellow-700 hover:bg-yellow-100 rounded-md" title="ویرایش">
                                            <i data-lucide="edit-3" class="icon-md ml-2"></i>
                                        </a>
                                        <form action="<?php echo generate_url('delete_customer', [], true); ?>" method="POST" class="inline-block" onsubmit="return confirmDelete(event, 'آیا از حذف مشتری \'<?php echo htmlspecialchars(addslashes($customer['name'])); ?>\' مطمئن هستید؟');">
                                            <input type="hidden" name="customer_id" value="<?php echo $customer['id']; ?>">
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
            نمایش <span class="font-semibold text-slate-800"><?php echo $offset + 1; ?></span>
            تا <span class="font-semibold text-slate-800"><?php echo min($offset + $items_per_page, $total_customers); ?></span>
            از <span class="font-semibold text-slate-800"><?php echo number_format($total_customers); ?></span> مشتری
        </div>
        <div class="flex items-center space-x-2 rtl:space-x-reverse">
            <?php if ($page_num_pagination > 1): ?>
                <a href="<?php echo generate_url('customers_list', array_merge($current_query_params, ['p' => $page_num_pagination - 1])); ?>" class="btn btn-secondary !px-3 !py-1.5 text-xs">
                    <i data-lucide="chevron-right" class="icon-sm ml-1"></i> قبلی
                </a>
            <?php else: ?>
                 <span class="btn btn-secondary !px-3 !py-1.5 text-xs opacity-50 cursor-not-allowed"><i data-lucide="chevron-right" class="icon-sm ml-1"></i> قبلی</span>
            <?php endif; ?>
            
            <span class="text-xs text-slate-500">صفحه <?php echo $page_num_pagination; ?> از <?php echo $total_pages; ?></span>

            <?php if ($page_num_pagination < $total_pages): ?>
                <a href="<?php echo generate_url('customers_list', array_merge($current_query_params, ['p' => $page_num_pagination + 1])); ?>" class="btn btn-secondary !px-3 !py-1.5 text-xs">
                    بعدی <i data-lucide="chevron-left" class="icon-sm mr-1"></i>
                </a>
            <?php else: ?>
                <span class="btn btn-secondary !px-3 !py-1.5 text-xs opacity-50 cursor-not-allowed">بعدی <i data-lucide="chevron-left" class="icon-sm mr-1"></i></span>
            <?php endif; ?>
        </div>
    </nav>
    <?php endif; ?>
</div>
