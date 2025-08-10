<?php
// /pages/product_form.php
// Form for adding or editing a product

if (!defined('DB_PATH')) { // Should be defined in index.php
    die("Access denied. This page should be accessed via index.php");
}

// db.php and its functions are included via index.php
$db = getDB();
// $app_base_path, $id (product's DB ID for editing) are available from index.php/header.php
global $app_base_path, $id; 

$is_edit_mode = false;
$product = null;
$page_title = "افزودن محصول جدید";
$form_action_name = 'add_product'; // Action name for generate_url
$submit_button_text = "ذخیره محصول";
$submit_button_icon = "plus-circle";

// Default values for form fields
$product_data = [
    'id' => null,
    'name' => '',
    'category' => '',
    'sell_price' => '',
    'buy_price' => '',
    'inventory' => 0,
    'description' => '',
    'image' => null,
    'product_id' => 'PROD-' . strtoupper(substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 6)),
    'status' => 'فعال'
];

if ($id !== null) { // $id is from index.php, passed if editing
    $is_edit_mode = true;
    $product_id_to_edit = $id;
    
    $stmt = $db->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->bindParam(':id', $product_id_to_edit, PDO::PARAM_INT);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        $page_title = "ویرایش محصول: " . htmlspecialchars($product['name']);
        $form_action_name = 'edit_product';
        $submit_button_text = "ذخیره تغییرات";
        $submit_button_icon = "save";
        
        $product_data = $product; 
        $product_data['sell_price'] = $product['sell_price'] ?? '';
        $product_data['buy_price'] = $product['buy_price'] ?? ''; // Keep as is, might be null
        $product_data['inventory'] = $product['inventory'] ?? 0;
    } else {
        $_SESSION['action_message'] = ['type' => 'error', 'text' => 'محصول مورد نظر برای ویرایش یافت نشد.'];
        header('Location: ' . generate_url('products_list'));
        exit;
    }
}

// For displaying previous input values if validation failed on server (using session)
// If 'id_for_edit' is set in session form_data, it means it was an edit attempt that failed.
if (isset($_SESSION['form_data']['product_form'])) {
    if ($is_edit_mode && isset($_SESSION['form_data']['product_form']['id_for_edit']) && $_SESSION['form_data']['product_form']['id_for_edit'] == $id) {
        // Only use session data if it's for the product currently being edited
        $form_values = array_merge($product_data, $_SESSION['form_data']['product_form']);
    } elseif (!$is_edit_mode && !isset($_SESSION['form_data']['product_form']['id_for_edit'])) {
        // Use session data if it's for an add attempt
        $form_values = array_merge($product_data, $_SESSION['form_data']['product_form']);
    } else {
        // Session data is for a different product or mode, use current $product_data
        $form_values = $product_data;
    }
    unset($_SESSION['form_data']['product_form']); // Clear after use
} else {
    $form_values = $product_data;
}


$product_image_url_form = 'https://placehold.co/300x300/E0E7FF/4338CA?text=تصویر+محصول';
if (!empty($form_values['image']) && file_exists(UPLOAD_DIR . $form_values['image'])) {
    $product_image_path_form = UPLOAD_DIR_PUBLIC_PATH . htmlspecialchars($form_values['image']);
    $product_image_url_form = $app_base_path . '/' . $product_image_path_form;
}

// Fetch distinct categories for datalist suggestion
$categories_stmt = $db->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
$distinct_categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
        <h2 class="text-xl lg:text-2xl font-semibold text-slate-800"><?php echo $page_title; ?></h2>
        <a href="<?php echo generate_url('products_list'); ?>" class="bg-blue-600 flex text-white px-6 py-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
            <i data-lucide="arrow-right" class="icon-sm ml-2"></i> بازگشت به لیست محصولات
        </a>
    </div>

    <form action="<?php echo generate_url($form_action_name, [], true); ?>" method="POST" enctype="multipart/form-data" class="bg-white p-6 md:p-8 rounded-xl shadow-lg">
        <?php if ($is_edit_mode && $form_values['id']): ?>
            <input type="hidden" name="product_db_id" value="<?php echo $form_values['id']; ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
            <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                <div class="sm:col-span-2">
                    <label for="name" class="form-label">نام محصول <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" class="form-input" value="<?php echo htmlspecialchars($form_values['name']); ?>" required placeholder="مثال: لپ تاپ گیمینگ حرفه ای">
                </div>
                
                <div>
                    <label for="category" class="form-label">دسته بندی</label>
                    <input type="text" id="category" name="category" list="categories_datalist" class="form-input" value="<?php echo htmlspecialchars($form_values['category']); ?>" placeholder="مثال: الکترونیک">
                    <datalist id="categories_datalist">
                        <?php foreach ($distinct_categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label for="product_id_field" class="form-label">شناسه محصول <?php echo $is_edit_mode ? '(غیرقابل تغییر)' : ''; ?></label>
                    <input type="text" id="product_id_field" name="product_id" class="form-input <?php echo $is_edit_mode ? 'bg-slate-100 cursor-not-allowed' : '';?>" value="<?php echo htmlspecialchars($form_values['product_id']); ?>" placeholder="مثال: PROD-XYZ123" <?php echo $is_edit_mode ? 'readonly' : ''; ?>>
                     <?php if (!$is_edit_mode): ?>
                        <p class="text-xs text-slate-500 mt-1">در صورت خالی رها کردن، به صورت خودکار پیشنهاد می‌شود.</p>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="sell_price" class="form-label">قیمت فروش (<?php echo DEFAULT_CURRENCY_SYMBOL; ?>) <span class="text-red-500">*</span></label>
                    <input type="number" step="any" id="sell_price" name="sell_price" class="form-input" value="<?php echo htmlspecialchars($form_values['sell_price']); ?>" required placeholder="مثال: 25000000" min="0">
                </div>
                <div>
                    <label for="buy_price" class="form-label">قیمت خرید (<?php echo DEFAULT_CURRENCY_SYMBOL; ?>) (اختیاری)</label>
                    <input type="number" step="any" id="buy_price" name="buy_price" class="form-input" value="<?php echo htmlspecialchars($form_values['buy_price'] ?? ''); ?>" placeholder="مثال: 22000000" min="0">
                </div>
                <div>
                    <label for="inventory" class="form-label">تعداد موجودی <span class="text-red-500">*</span></label>
                    <input type="number" id="inventory" name="inventory" class="form-input" value="<?php echo htmlspecialchars($form_values['inventory']); ?>" required placeholder="مثال: 15" min="0">
                </div>
                <div>
                    <label for="status" class="form-label">وضعیت</label>
                    <select id="status" name="status" class="form-select">
                        <option value="فعال" <?php echo ($form_values['status'] === 'فعال') ? 'selected' : ''; ?>>فعال</option>
                        <option value="ناموجود" <?php echo ($form_values['status'] === 'ناموجود') ? 'selected' : ''; ?>>ناموجود</option>
                    </select>
                </div>
                 <div class="sm:col-span-2">
                    <label for="description" class="form-label">توضیحات</label>
                    <textarea id="description" name="description" rows="5" class="form-textarea text-sm" placeholder="توضیحات کامل محصول..."><?php echo htmlspecialchars($form_values['description']); ?></textarea>
                </div>
            </div>
            
            <div class="lg:col-span-1 space-y-3 order-first lg:order-last">
                <label class="form-label text-center lg:text-right">تصویر محصول:</label>
                <div class="flex flex-col items-center p-4 border-2 border-dashed border-slate-300 rounded-lg bg-slate-50">
                    <img id="productImagePreview" src="<?php echo $product_image_url_form; ?>" alt="پیش نمایش محصول" class="w-full max-w-[250px] h-auto max-h-60 object-contain mb-4 rounded-md">
                    <label for="product_image_upload" class="btn btn-secondary btn-sm w-full text-center cursor-pointer flex items-center justify-center">
                        <i data-lucide="upload-cloud" class="icon-sm ml-2"></i>
                        <span><?php echo $is_edit_mode && $form_values['image'] ? 'تغییر تصویر' : 'انتخاب تصویر'; ?></span>
                    </label>
                    <input type="file" name="product_image" id="product_image_upload" class="hidden" accept="image/jpeg,image/png,image/gif" onchange="previewImage(event, 'productImagePreview')">
                    <p class="text-xs text-slate-500 mt-2 text-center">فرمت‌های مجاز: JPG, PNG, GIF (حداکثر <?php echo MAX_FILE_SIZE / (1024*1024); ?>MB)</p>
                </div>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-3 rtl:sm:space-x-reverse pt-6 border-t border-slate-200 mt-8">
            <a href="<?php echo generate_url('products_list'); ?>" class="bg-red-600 flex text-white px-6 py-2 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">انصراف</a>
            <button type="submit" class="bg-blue-600 flex text-white px-6 py-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                <i data-lucide="<?php echo $submit_button_icon; ?>" class="icon-md ml-2"></i>
                <?php echo $submit_button_text; ?>
            </button>
        </div>
    </form>
</div>
