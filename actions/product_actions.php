<?php
// /actions/product_actions.php
// Handles CRUD operations for products, using generate_url for redirects.

// Ensure session is started and config/db are loaded (usually done by index.php)
if (session_status() == PHP_SESSION_NONE) {
    if (!defined('SESSION_NAME')) { require_once __DIR__ . '/../config.php'; }
    session_name(SESSION_NAME);
    session_start();
}
if (!function_exists('getDB') || !function_exists('generate_url')) { // Ensure helpers are available
    require_once __DIR__ . '/../config.php'; // For UPLOAD_DIR etc.
    require_once __DIR__ . '/../db.php';
}

/**
 * Handles adding a new product.
 * @return array Action message array with 'type', 'text', and 'redirect_to'.
 */
function handle_add_product_action() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['type' => 'error', 'text' => 'درخواست نامعتبر.', 'redirect_to' => generate_url('products_list')];
    }
    if (!isset($_SESSION['user_id'])) {
        return ['type' => 'error', 'text' => 'دسترسی غیر مجاز.', 'redirect_to' => 'login.php'];
    }

    $db = getDB();
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $sell_price_str = $_POST['sell_price'] ?? '0';
    $buy_price_str = $_POST['buy_price'] ?? ''; // Can be empty
    $inventory_str = $_POST['inventory'] ?? '0';
    $description = trim($_POST['description'] ?? '');
    $product_id_field = trim($_POST['product_id'] ?? ''); 
    $status = $_POST['status'] ?? 'فعال';

    // Prepare redirect URL for errors, preserving some input for the form
    $error_redirect_params = ['name' => $name, 'category' => $category, 'sell_price' => $sell_price_str, 'buy_price' => $buy_price_str, 'inventory' => $inventory_str, 'description' => $description, 'product_id' => $product_id_field, 'status' => $status];
    $_SESSION['form_data']['product_form'] = $error_redirect_params; // Store for pre-filling on error

    // --- Validations ---
    if (empty($name)) {
        return ['type' => 'error', 'text' => 'نام محصول نمی‌تواند خالی باشد.', 'redirect_to' => generate_url('product_form', $error_redirect_params)];
    }
    $sell_price = filter_var($sell_price_str, FILTER_VALIDATE_FLOAT);
    if ($sell_price === false || $sell_price < 0) {
        return ['type' => 'error', 'text' => 'قیمت فروش نامعتبر است.', 'redirect_to' => generate_url('product_form', $error_redirect_params)];
    }
    $buy_price = null; // Default to null if empty or invalid
    if ($buy_price_str !== '') {
        $buy_price_temp = filter_var($buy_price_str, FILTER_VALIDATE_FLOAT);
        if ($buy_price_temp !== false && $buy_price_temp >= 0) {
            $buy_price = $buy_price_temp;
        } elseif ($buy_price_temp !== false && $buy_price_temp < 0) { // Only error if it's a negative number
             return ['type' => 'error', 'text' => 'قیمت خرید نمی‌تواند منفی باشد.', 'redirect_to' => generate_url('product_form', $error_redirect_params)];
        }
    }
    $inventory = filter_var($inventory_str, FILTER_VALIDATE_INT);
    if ($inventory === false || $inventory < 0) {
        return ['type' => 'error', 'text' => 'تعداد موجودی نامعتبر است.', 'redirect_to' => generate_url('product_form', $error_redirect_params)];
    }


    // Handle product image upload
    $product_image_filename = null;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['product_image'];
        if ($file['size'] > MAX_FILE_SIZE) { return ['type' => 'error', 'text' => 'اندازه فایل تصویر بیش از حد مجاز است (حداکثر '.(MAX_FILE_SIZE/(1024*1024)).'MB).', 'redirect_to' => generate_url('product_form', $error_redirect_params)]; }
        if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) { return ['type' => 'error', 'text' => 'نوع فایل تصویر مجاز نیست (فقط JPG, PNG, GIF).', 'redirect_to' => generate_url('product_form', $error_redirect_params)]; }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $product_image_filename = 'product_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $upload_path = UPLOAD_DIR . $product_image_filename;

        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            error_log("Failed to move uploaded file for product image: " . $file['tmp_name'] . " to " . $upload_path);
            return ['type' => 'error', 'text' => 'خطا در بارگذاری تصویر محصول.', 'redirect_to' => generate_url('product_form', $error_redirect_params)];
        }
    }

    try {
        if (!empty($product_id_field)) {
             $stmt_check_pid = $db->prepare("SELECT id FROM products WHERE product_id = :product_id_field");
             $stmt_check_pid->bindParam(':product_id_field', $product_id_field);
             $stmt_check_pid->execute();
             if ($stmt_check_pid->fetch()) {
                  if ($product_image_filename && file_exists(UPLOAD_DIR . $product_image_filename)) { @unlink(UPLOAD_DIR . $product_image_filename); }
                 return ['type' => 'error', 'text' => 'این شناسه محصول قبلا ثبت شده است.', 'redirect_to' => generate_url('product_form', $error_redirect_params)];
             }
        }

        $stmt = $db->prepare("INSERT INTO products (name, category, sell_price, buy_price, inventory, description, image, product_id, status) 
                             VALUES (:name, :category, :sell_price, :buy_price, :inventory, :description, :image, :product_id_field, :status)");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':sell_price', $sell_price);
        $stmt->bindParam(':buy_price', $buy_price, ($buy_price === null ? PDO::PARAM_NULL : PDO::PARAM_STR) );
        $stmt->bindParam(':inventory', $inventory, PDO::PARAM_INT);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':image', $product_image_filename);
        $stmt->bindParam(':product_id_field', $product_id_field);
        $stmt->bindParam(':status', $status);
        
        $stmt->execute();
        $new_product_id = $db->lastInsertId();
        unset($_SESSION['form_data']['product_form']); // Clear form data on success
        return ['type' => 'success', 'text' => 'محصول با موفقیت اضافه شد.', 'redirect_to' => generate_url('product_info', ['id' => $new_product_id])];

    } catch (PDOException $e) {
        error_log("Add Product Error: " . $e->getMessage());
        if ($product_image_filename && file_exists(UPLOAD_DIR . $product_image_filename)) { @unlink(UPLOAD_DIR . $product_image_filename); }
        return ['type' => 'error', 'text' => 'خطا در افزودن محصول. '.($e->getCode() == '23000' ? 'شناسه محصول تکراری است.' : 'لطفا دوباره تلاش کنید.'), 'redirect_to' => generate_url('product_form', $error_redirect_params)];
    }
}

/**
 * Handles editing an existing product.
 * @return array Action message array.
 */
function handle_edit_product_action() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['type' => 'error', 'text' => 'درخواست نامعتبر.', 'redirect_to' => generate_url('products_list')];
    }
    if (!isset($_SESSION['user_id'])) { return ['type' => 'error', 'text' => 'دسترسی غیر مجاز.', 'redirect_to' => 'login.php']; }

    $db = getDB();
    $product_db_id = filter_input(INPUT_POST, 'product_db_id', FILTER_VALIDATE_INT);
    if (empty($product_db_id)) {
        return ['type' => 'error', 'text' => 'شناسه محصول برای ویرایش نامعتبر است.', 'redirect_to' => generate_url('products_list')];
    }
    
    $error_redirect_params = ['id' => $product_db_id]; // For redirecting back to edit form

    $stmt_old = $db->prepare("SELECT image, product_id FROM products WHERE id = :id");
    $stmt_old->bindParam(':id', $product_db_id, PDO::PARAM_INT);
    $stmt_old->execute();
    $old_product_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

    if (!$old_product_data) {
        return ['type' => 'error', 'text' => 'محصول برای ویرایش یافت نشد.', 'redirect_to' => generate_url('products_list')];
    }
    $old_product_image = $old_product_data['image'];
    // $old_product_id_field = $old_product_data['product_id']; // product_id is readonly on edit form

    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $sell_price_str = $_POST['sell_price'] ?? '0';
    $buy_price_str = $_POST['buy_price'] ?? '';
    $inventory_str = $_POST['inventory'] ?? '0';
    $description = trim($_POST['description'] ?? '');
    $product_id_field = trim($_POST['product_id'] ?? $old_product_data['product_id']); // Should be readonly
    $status = $_POST['status'] ?? 'فعال';

    // --- Validations ---
    if (empty($name)) { return ['type' => 'error', 'text' => 'نام محصول نمی‌تواند خالی باشد.', 'redirect_to' => generate_url('product_form', $error_redirect_params)]; }
    $sell_price = filter_var($sell_price_str, FILTER_VALIDATE_FLOAT);
    if ($sell_price === false || $sell_price < 0) { return ['type' => 'error', 'text' => 'قیمت فروش نامعتبر است.', 'redirect_to' => generate_url('product_form', $error_redirect_params)]; }
    $buy_price = null;
    if ($buy_price_str !== '') {
        $buy_price_temp = filter_var($buy_price_str, FILTER_VALIDATE_FLOAT);
        if ($buy_price_temp !== false && $buy_price_temp >= 0) { $buy_price = $buy_price_temp; }
        elseif ($buy_price_temp !== false && $buy_price_temp < 0) { return ['type' => 'error', 'text' => 'قیمت خرید نمی‌تواند منفی باشد.', 'redirect_to' => generate_url('product_form', $error_redirect_params)];}
    }
    $inventory = filter_var($inventory_str, FILTER_VALIDATE_INT);
    if ($inventory === false || $inventory < 0) { return ['type' => 'error', 'text' => 'تعداد موجودی نامعتبر است.', 'redirect_to' => generate_url('product_form', $error_redirect_params)]; }


    $product_image_filename = $old_product_image;
    $new_image_uploaded = false;

    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['product_image'];
        if ($file['size'] > MAX_FILE_SIZE) { return ['type' => 'error', 'text' => 'اندازه فایل تصویر بیش از حد مجاز است.', 'redirect_to' => generate_url('product_form', $error_redirect_params)]; }
        if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) { return ['type' => 'error', 'text' => 'نوع فایل تصویر مجاز نیست.', 'redirect_to' => generate_url('product_form', $error_redirect_params)]; }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $new_product_image_filename = 'product_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $upload_path = UPLOAD_DIR . $new_product_image_filename;

        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            if ($old_product_image && file_exists(UPLOAD_DIR . $old_product_image)) {
                @unlink(UPLOAD_DIR . $old_product_image);
            }
            $product_image_filename = $new_product_image_filename;
            $new_image_uploaded = true;
        } else {
            error_log("Failed to move uploaded file for product image update: " . $file['tmp_name'] . " to " . $upload_path);
            return ['type' => 'error', 'text' => 'خطا در بارگذاری تصویر محصول جدید.', 'redirect_to' => generate_url('product_form', $error_redirect_params)];
        }
    }

    try {
        // product_id_field is readonly, so no need to check for duplicates against others if it hasn't changed.
        // If it were editable, a check similar to add_product_action would be needed:
        // if (!empty($product_id_field) && $product_id_field !== $old_product_id_field) { ... }

        $stmt = $db->prepare("UPDATE products SET 
                             name = :name, category = :category, sell_price = :sell_price, buy_price = :buy_price, 
                             inventory = :inventory, description = :description, image = :image, product_id = :product_id_field, status = :status
                             WHERE id = :id");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':sell_price', $sell_price);
        $stmt->bindParam(':buy_price', $buy_price, ($buy_price === null ? PDO::PARAM_NULL : PDO::PARAM_STR) );
        $stmt->bindParam(':inventory', $inventory, PDO::PARAM_INT);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':image', $product_image_filename);
        $stmt->bindParam(':product_id_field', $product_id_field); // Using the one from POST (original if readonly)
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $product_db_id, PDO::PARAM_INT);
        
        $stmt->execute();
        unset($_SESSION['form_data']['product_form']); // Clear form data on success
        return ['type' => 'success', 'text' => 'اطلاعات محصول با موفقیت ویرایش شد.', 'redirect_to' => generate_url('product_info', ['id' => $product_db_id])];

    } catch (PDOException $e) {
        error_log("Edit Product Error: " . $e->getMessage());
        if ($new_image_uploaded && file_exists(UPLOAD_DIR . $product_image_filename)) { @unlink(UPLOAD_DIR . $product_image_filename); }
        // Store current POST data to re-fill form
        $_SESSION['form_data']['product_form'] = $_POST; 
        $_SESSION['form_data']['product_form']['id_for_edit'] = $product_db_id; // ensure id is passed back for edit form
        return ['type' => 'error', 'text' => 'خطا در ویرایش اطلاعات محصول. '.($e->getCode() == '23000' ? 'شناسه محصول تکراری است.' : 'لطفا دوباره تلاش کنید.'), 'redirect_to' => generate_url('product_form', $error_redirect_params)];
    }
}

/**
 * Handles deleting a product.
 * @return array Action message array.
 */
function handle_delete_product_action() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['type' => 'error', 'text' => 'درخواست نامعتبر.', 'redirect_to' => generate_url('products_list')];
    }
    if (!isset($_SESSION['user_id'])) { return ['type' => 'error', 'text' => 'دسترسی غیر مجاز.', 'redirect_to' => 'login.php']; }

    $db = getDB();
    $product_id_to_delete = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

    if (empty($product_id_to_delete)) {
        return ['type' => 'error', 'text' => 'شناسه محصول برای حذف نامعتبر است.', 'redirect_to' => generate_url('products_list')];
    }

    try {
        // Check if product is used in any non-draft/non-cancelled invoice items
        // This is a more robust check than relying solely on ON DELETE SET NULL if you want to prevent deletion entirely.
        $stmt_check_invoices = $db->prepare("
            SELECT COUNT(ii.id) 
            FROM invoice_items ii
            JOIN invoices i ON ii.invoice_id = i.id
            WHERE ii.product_id = :product_id AND i.status NOT IN ('پیش نویس', 'لغو شده')
        ");
        $stmt_check_invoices->bindParam(':product_id', $product_id_to_delete, PDO::PARAM_INT);
        $stmt_check_invoices->execute();
        if ($stmt_check_invoices->fetchColumn() > 0) {
            return ['type' => 'error', 'text' => 'خطا: این محصول در فاکتورهای فعال یا پرداخت شده استفاده شده است و نمی‌توان آن را حذف کرد. ابتدا فاکتورها را مدیریت کنید یا وضعیت محصول را به "ناموجود" تغییر دهید.', 'redirect_to' => generate_url('products_list')];
        }


        $stmt_img = $db->prepare("SELECT image FROM products WHERE id = :id");
        $stmt_img->bindParam(':id', $product_id_to_delete, PDO::PARAM_INT);
        $stmt_img->execute();
        $product_image_filename = $stmt_img->fetchColumn();

        $stmt_delete = $db->prepare("DELETE FROM products WHERE id = :id");
        $stmt_delete->bindParam(':id', $product_id_to_delete, PDO::PARAM_INT);
        $deleted = $stmt_delete->execute();

        if ($deleted) {
            if ($product_image_filename && file_exists(UPLOAD_DIR . $product_image_filename)) {
                @unlink(UPLOAD_DIR . $product_image_filename);
            }
            // Also delete from invoice_items where product_id was set (ON DELETE SET NULL will handle FK, but this cleans up if desired)
            // However, with ON DELETE SET NULL, the product_id in invoice_items becomes NULL. If you want to remove the items themselves,
            // that's a different business logic. For now, we rely on the FK constraint.
            return ['type' => 'success', 'text' => 'محصول با موفقیت حذف شد.', 'redirect_to' => generate_url('products_list')];
        } else {
            return ['type' => 'error', 'text' => 'خطا در حذف محصول. ممکن است محصول یافت نشود.', 'redirect_to' => generate_url('products_list')];
        }

    } catch (PDOException $e) {
        error_log("Delete Product Error: " . $e->getMessage());
        // The explicit check above should catch most FK issues, but this is a fallback.
        if (strpos(strtolower($e->getMessage()), 'foreign key constraint failed') !== false || $e->getCode() == '23000') {
             return ['type' => 'error', 'text' => 'خطا: این محصول در فاکتورهای ثبت شده استفاده شده است و نمی‌توان آن را مستقیما حذف کرد.', 'redirect_to' => generate_url('products_list')];
        }
        return ['type' => 'error', 'text' => 'خطا در حذف محصول: ' . $e->getMessage(), 'redirect_to' => generate_url('products_list')];
    }
}

?>
