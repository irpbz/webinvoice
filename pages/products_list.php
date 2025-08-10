<?php
// /pages/products_list.php
// Displays the list of products, styled to match the user's updated invoices_list.php

if (!defined('DB_PATH')) { die("Access denied."); }

$db = getDB();
global $app_base_path; 

// --- Search and Filter ---
$search_term = trim($_GET['search'] ?? '');
$filter_status = $_GET['status'] ?? 'all'; 
$filter_category = trim($_GET['category'] ?? '');

$current_query_params = $_GET; 
unset($current_query_params['page']); 
unset($current_query_params['p']);    

$db_params = []; 
$conditions = [];

if (!empty($search_term)) {
    $conditions[] = "(p.name LIKE :search_term OR p.description LIKE :search_term OR p.product_id LIKE :search_term)";
    $db_params[':search_term'] = '%' . $search_term . '%';
}
if ($filter_status !== 'all' && !empty($filter_status)) {
    $conditions[] = "p.status = :status";
    $db_params[':status'] = $filter_status;
}
if (!empty($filter_category)) {
    $conditions[] = "p.category = :category";
    $db_params[':category'] = $filter_category;
}

$where_clause = '';
if (!empty($conditions)) {
    $where_clause = " WHERE " . implode(" AND ", $conditions);
}

// --- Pagination ---
$page_num_pagination = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$items_per_page = 5; // Matching the items per page from user's invoice list for consistency
$offset = ($page_num_pagination - 1) * $items_per_page;

$total_stmt = $db->prepare("SELECT COUNT(p.id) FROM products p" . $where_clause);
$total_stmt->execute($db_params);
$total_products = $total_stmt->fetchColumn();
$total_pages = ceil($total_products / $items_per_page);

if ($page_num_pagination > $total_pages && $total_pages > 0) {
    $redirect_params_pagination = $current_query_params;
    $redirect_params_pagination['p'] = $total_pages;
    header('Location: ' . generate_url('products_list', $redirect_params_pagination));
    exit;
}

$query = "SELECT p.id, p.name, p.category, p.sell_price, p.inventory, p.status, p.image, p.product_id, p.created_at 
          FROM products p
          {$where_clause}
          ORDER BY p.created_at DESC 
          LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
foreach ($db_params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categories_stmt = $db->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
$distinct_categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
        <h2 class="text-xl lg:text-2xl font-semibold text-slate-800">لیست محصولات</h2>
        <a href="<?php echo generate_url('product_form'); ?>" class="bg-blue-600 flex text-white px-6 py-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
            <i data-lucide="plus-circle" class="icon-md ml-2"></i> افزودن محصول جدید
        </a>
    </div>

    <div class="bg-white p-4 md:p-5 rounded-xl shadow-lg border border-slate-200">
        <form method="GET" action="<?php echo generate_url('products_list'); ?>">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-5 gap-7 items-end">
                <div>
                    <label for="search_product_input_main" class="form-label">جستجو:</label>
                    <div class="relative">
                         <span class="absolute inset-y-0 right-0 flex items-center pr-3.5 pointer-events-none">
                            <i data-lucide="search" class="w-5 h-5 text-slate-400"></i>
                        </span>
                        <input type="text" id="search_product_input_main" name="search" class="form-input pr-10" placeholder="نام، شناسه، توضیحات..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                </div>
                 <div>
                    <label for="filter_category_input" class="form-label">دسته بندی:</label>
                    <select id="filter_category_input" name="category" class="form-select">
                        <option value="">همه دسته‌بندی‌ها</option>
                        <?php foreach ($distinct_categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($filter_category === $cat) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filter_status_input" class="form-label">وضعیت:</label>
                    <select id="filter_status_input" name="status" class="form-select">
                        <option value="all" <?php echo ($filter_status === 'all') ? 'selected' : ''; ?>>همه وضعیت‌ها</option>
                        <option value="فعال" <?php echo ($filter_status === 'فعال') ? 'selected' : ''; ?>>فعال</option>
                        <option value="ناموجود" <?php echo ($filter_status === 'ناموجود') ? 'selected' : ''; ?>>ناموجود</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="btn btn-primary w-full sm:w-auto px-6 py-2.5">
                        <i data-lucide="filter" class="icon-sm ml-1.5"></i> اعمال فیلتر
                    </button>
                </div>
            </div>
            <?php if (!empty($search_term) || $filter_status !== 'all' || !empty($filter_category)): ?>
            <div class="mt-4 pt-4 border-t border-slate-200">
                <a href="<?php echo generate_url('products_list'); ?>" class="btn btn-secondary w-full sm:w-auto text-center px-6 py-2.5">پاک کردن فیلترها</a>
            </div>
            <?php endif; ?>
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
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">نام محصول</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">دسته بندی</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">قیمت فروش</th>
                        <th class="px-4 py-3 text-center font-semibold text-slate-600">موجودی</th>
                        <th class="px-4 py-3 text-center font-semibold text-slate-600">وضعیت</th>
                        <th class="px-4 py-3 text-center font-semibold text-slate-600 w-32">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-10 text-slate-500">
                                <i data-lucide="package-x" class="w-12 h-12 mx-auto mb-3 text-slate-400"></i>
                                <p class="font-medium text-base mb-1">
                                <?php if(!empty($search_term) || $filter_status !== 'all' || !empty($filter_category)): ?>
                                    هیچ محصولی با معیارهای اعمال شده یافت نشد.
                                <?php else: ?>
                                    هنوز هیچ محصولی ثبت نشده است.
                                <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-2 py-3 text-center">
                                    <input type="checkbox" class="form-checkbox rounded border-slate-400" aria-label="انتخاب این ردیف">
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center">
                                        <?php
                                        $product_image_url_list_prod = 'https://placehold.co/40x40/E0E7FF/4338CA?text=P';
                                        if (!empty($product['image']) && file_exists(UPLOAD_DIR . $product['image'])) {
                                            $product_image_path_list_prod = UPLOAD_DIR_PUBLIC_PATH . htmlspecialchars($product['image']);
                                            $product_image_url_list_prod = $app_base_path . '/' . $product_image_path_list_prod;
                                        }
                                        ?>
                                        <img src="<?php echo $product_image_url_list_prod; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-10 h-10 rounded-md object-cover ml-3.5 border border-slate-200 shadow-sm"/>
                                        <div>
                                            <a href="<?php echo generate_url('product_info', ['id' => $product['id']]); ?>" class="font-semibold text-slate-700 hover:text-sky-600 block">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </a>
                                            <p class="text-xs text-slate-500">شناسه: <?php echo htmlspecialchars($product['product_id'] ?: '-'); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-600"><?php echo htmlspecialchars($product['category'] ?: '-'); ?></td>
                                <td class="px-4 py-3 text-slate-700 font-medium"><?php echo format_currency_php($product['sell_price']); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge <?php 
                                        if ($product['inventory'] > 10) echo 'bg-green-100 text-green-700 border border-green-200'; 
                                        elseif ($product['inventory'] > 0) echo 'bg-amber-100 text-amber-700 border border-amber-200'; 
                                        else echo 'bg-red-100 text-red-700 border border-red-200'; 
                                    ?>">
                                        <?php echo htmlspecialchars($product['inventory']); ?> عدد
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge <?php echo ($product['status'] === 'فعال') ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
                                        <?php echo htmlspecialchars($product['status']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
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

    <?php if ($total_pages > 1): ?>
    <nav class="mt-5 flex flex-col sm:flex-row justify-between items-center space-y-3 sm:space-y-0 text-sm text-slate-600" aria-label="Pagination">
        <div class="font-medium">
            نمایش <span class="font-semibold text-slate-800"><?php echo $offset + 1; ?></span>
            تا <span class="font-semibold text-slate-800"><?php echo min($offset + $items_per_page, $total_products); ?></span>
            از <span class="font-semibold text-slate-800"><?php echo number_format($total_products); ?></span> محصول
        </div>
        <div class="flex items-center space-x-2 rtl:space-x-reverse">
            <?php if ($page_num_pagination > 1): ?>
                <a href="<?php echo generate_url('products_list', array_merge($current_query_params, ['p' => $page_num_pagination - 1])); ?>" class="btn btn-secondary !px-3 !py-1.5 text-xs">
                    <i data-lucide="chevron-right" class="icon-sm ml-1"></i> قبلی
                </a>
            <?php else: ?>
                 <span class="btn btn-secondary !px-3 !py-1.5 text-xs opacity-50 cursor-not-allowed"><i data-lucide="chevron-right" class="icon-sm ml-1"></i> قبلی</span>
            <?php endif; ?>
            
            <span class="text-xs text-slate-500">صفحه <?php echo $page_num_pagination; ?> از <?php echo $total_pages; ?></span>

            <?php if ($page_num_pagination < $total_pages): ?>
                <a href="<?php echo generate_url('products_list', array_merge($current_query_params, ['p' => $page_num_pagination + 1])); ?>" class="btn btn-secondary !px-3 !py-1.5 text-xs">
                    بعدی <i data-lucide="chevron-left" class="icon-sm mr-1"></i>
                </a>
            <?php else: ?>
                <span class="btn btn-secondary !px-3 !py-1.5 text-xs opacity-50 cursor-not-allowed">بعدی <i data-lucide="chevron-left" class="icon-sm mr-1"></i></span>
            <?php endif; ?>
        </div>
    </nav>
    <?php endif; ?>
</div>
