<?php
// /actions/customer_actions.php
// Handles CRUD operations for customers, using generate_url for redirects.

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
 * Handles adding a new customer.
 * @return array Action message array with 'type', 'text', and 'redirect_to'.
 */
function handle_add_customer_action() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['type' => 'error', 'text' => 'درخواست نامعتبر.', 'redirect_to' => generate_url('customers_list')];
    }
    if (!isset($_SESSION['user_id'])) {
        return ['type' => 'error', 'text' => 'دسترسی غیر مجاز.', 'redirect_to' => 'login.php']; // login.php is standalone
    }

    $db = getDB();
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $customer_id_field = trim($_POST['customer_id'] ?? ''); 
    $join_date_str = trim($_POST['join_date'] ?? '');

    // Prepare redirect URL for errors, preserving some input
    $error_redirect_params = ['name' => $name, 'phone' => $phone, 'email' => $email, 'address' => $address, 'customer_id_field' => $customer_id_field, 'join_date' => $join_date_str, 'notes' => $notes];

    if (empty($name)) {
        $_SESSION['form_data']['customer_form'] = $error_redirect_params;
        return ['type' => 'error', 'text' => 'نام مشتری نمی‌تواند خالی باشد.', 'redirect_to' => generate_url('customer_form', $error_redirect_params)];
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['form_data']['customer_form'] = $error_redirect_params;
        return ['type' => 'error', 'text' => 'فرمت ایمیل نامعتبر است.', 'redirect_to' => generate_url('customer_form', $error_redirect_params)];
    }
    $join_date = !empty($join_date_str) && preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $join_date_str) 
                 ? date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $join_date_str))) 
                 : date('Y-m-d H:i:s');


    $profile_pic_filename = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['profile_pic'];
        if ($file['size'] > MAX_FILE_SIZE) {
            $_SESSION['form_data']['customer_form'] = $error_redirect_params;
            return ['type' => 'error', 'text' => 'اندازه فایل تصویر بیش از حد مجاز است (حداکثر '.(MAX_FILE_SIZE/(1024*1024)).'MB).', 'redirect_to' => generate_url('customer_form', $error_redirect_params)];
        }
        if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) {
            $_SESSION['form_data']['customer_form'] = $error_redirect_params;
            return ['type' => 'error', 'text' => 'نوع فایل تصویر مجاز نیست (فقط JPG, PNG, GIF).', 'redirect_to' => generate_url('customer_form', $error_redirect_params)];
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $profile_pic_filename = 'customer_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $upload_path = UPLOAD_DIR . $profile_pic_filename;

        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            error_log("Failed to move uploaded file for customer profile: " . $file['tmp_name'] . " to " . $upload_path);
            $_SESSION['form_data']['customer_form'] = $error_redirect_params;
            return ['type' => 'error', 'text' => 'خطا در بارگذاری تصویر پروفایل.', 'redirect_to' => generate_url('customer_form', $error_redirect_params)];
        }
    }

    try {
        if (!empty($email)) {
            $stmt_check_email = $db->prepare("SELECT id FROM customers WHERE email = :email");
            $stmt_check_email->bindParam(':email', $email);
            $stmt_check_email->execute();
            if ($stmt_check_email->fetch()) {
                 if ($profile_pic_filename && file_exists(UPLOAD_DIR . $profile_pic_filename)) { unlink(UPLOAD_DIR . $profile_pic_filename); } 
                $_SESSION['form_data']['customer_form'] = $error_redirect_params;
                return ['type' => 'error', 'text' => 'این آدرس ایمیل قبلا ثبت شده است.', 'redirect_to' => generate_url('customer_form', $error_redirect_params)];
            }
        }
        if (!empty($customer_id_field)) {
             $stmt_check_cid = $db->prepare("SELECT id FROM customers WHERE customer_id = :customer_id_field");
             $stmt_check_cid->bindParam(':customer_id_field', $customer_id_field);
             $stmt_check_cid->execute();
             if ($stmt_check_cid->fetch()) {
                  if ($profile_pic_filename && file_exists(UPLOAD_DIR . $profile_pic_filename)) { unlink(UPLOAD_DIR . $profile_pic_filename); }
                $_SESSION['form_data']['customer_form'] = $error_redirect_params;
                 return ['type' => 'error', 'text' => 'این شناسه مشتری قبلا ثبت شده است.', 'redirect_to' => generate_url('customer_form', $error_redirect_params)];
             }
        }

        $stmt = $db->prepare("INSERT INTO customers (name, phone, email, address, profile_pic, notes, customer_id, join_date) 
                             VALUES (:name, :phone, :email, :address, :profile_pic, :notes, :customer_id_field, :join_date)");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':profile_pic', $profile_pic_filename);
        $stmt->bindParam(':notes', $notes);
        $stmt->bindParam(':customer_id_field', $customer_id_field);
        $stmt->bindParam(':join_date', $join_date);
        
        $stmt->execute();
        $new_customer_id = $db->lastInsertId();
        return ['type' => 'success', 'text' => 'مشتری با موفقیت اضافه شد.', 'redirect_to' => generate_url('customer_info', ['id' => $new_customer_id])];

    } catch (PDOException $e) {
        error_log("Add Customer Error: " . $e->getMessage());
        if ($profile_pic_filename && file_exists(UPLOAD_DIR . $profile_pic_filename)) { unlink(UPLOAD_DIR . $profile_pic_filename); }
        $_SESSION['form_data']['customer_form'] = $error_redirect_params;
        return ['type' => 'error', 'text' => 'خطا در افزودن مشتری. '.($e->getCode() == '23000' ? 'ایمیل یا شناسه مشتری تکراری است.' : 'لطفا دوباره تلاش کنید.'), 'redirect_to' => generate_url('customer_form', $error_redirect_params)];
    }
}

/**
 * Handles editing an existing customer.
 * @return array Action message array.
 */
function handle_edit_customer_action() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['type' => 'error', 'text' => 'درخواست نامعتبر.', 'redirect_to' => generate_url('customers_list')];
    }
    if (!isset($_SESSION['user_id'])) { return ['type' => 'error', 'text' => 'دسترسی غیر مجاز.', 'redirect_to' => 'login.php']; }

    $db = getDB();
    $customer_db_id = filter_input(INPUT_POST, 'customer_db_id', FILTER_VALIDATE_INT);
    if (empty($customer_db_id)) {
        return ['type' => 'error', 'text' => 'شناسه مشتری برای ویرایش نامعتبر است.', 'redirect_to' => generate_url('customers_list')];
    }

    $stmt_old = $db->prepare("SELECT profile_pic, email, customer_id FROM customers WHERE id = :id");
    $stmt_old->bindParam(':id', $customer_db_id, PDO::PARAM_INT);
    $stmt_old->execute();
    $old_customer_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

    if (!$old_customer_data) {
        return ['type' => 'error', 'text' => 'مشتری برای ویرایش یافت نشد.', 'redirect_to' => generate_url('customers_list')];
    }
    $old_profile_pic = $old_customer_data['profile_pic'];
    $old_email = $old_customer_data['email'];
    // $old_customer_id_field = $old_customer_data['customer_id']; // customer_id field is readonly on edit form

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $customer_id_field = trim($_POST['customer_id'] ?? $old_customer_data['customer_id']); // Should be readonly, but get it anyway
    $join_date_str = trim($_POST['join_date'] ?? '');
    
    $error_redirect_params = ['id' => $customer_db_id]; // For redirecting back to edit form

    if (empty($name)) { return ['type' => 'error', 'text' => 'نام مشتری نمی‌تواند خالی باشد.', 'redirect_to' => generate_url('customer_form', $error_redirect_params)]; }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) { return ['type' => 'error', 'text' => 'فرمت ایمیل نامعتبر است.', 'redirect_to' => generate_url('customer_form', $error_redirect_params)]; }
    $join_date = !empty($join_date_str) && preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $join_date_str) 
                 ? date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $join_date_str))) 
                 : date('Y-m-d H:i:s', strtotime($old_customer_data['join_date'] ?? 'now'));


    $profile_pic_filename = $old_profile_pic; 
    $new_pic_uploaded = false;

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['profile_pic'];
        if ($file['size'] > MAX_FILE_SIZE) { return ['type' => 'error', 'text' => 'اندازه فایل تصویر بیش از حد مجاز است.', 'redirect_to' => generate_url('customer_form', $error_redirect_params)]; }
        if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) { return ['type' => 'error', 'text' => 'نوع فایل تصویر مجاز نیست.', 'redirect_to' => generate_url('customer_form', $error_redirect_params)]; }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $new_profile_pic_filename = 'customer_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $upload_path = UPLOAD_DIR . $new_profile_pic_filename;

        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            if ($old_profile_pic && file_exists(UPLOAD_DIR . $old_profile_pic)) {
                @unlink(UPLOAD_DIR . $old_profile_pic);
            }
            $profile_pic_filename = $new_profile_pic_filename; 
            $new_pic_uploaded = true;
        } else {
            error_log("Failed to move uploaded file for customer profile update: " . $file['tmp_name'] . " to " . $upload_path);
            return ['type' => 'error', 'text' => 'خطا در بارگذاری تصویر پروفایل جدید.', 'redirect_to' => generate_url('customer_form', $error_redirect_params)];
        }
    }

    try {
        if (!empty($email) && $email !== $old_email) {
            $stmt_check_email = $db->prepare("SELECT id FROM customers WHERE email = :email AND id != :id");
            $stmt_check_email->execute([':email' => $email, ':id' => $customer_db_id]);
            if ($stmt_check_email->fetch()) {
                if ($new_pic_uploaded && file_exists(UPLOAD_DIR . $profile_pic_filename)) { @unlink(UPLOAD_DIR . $profile_pic_filename); } 
                return ['type' => 'error', 'text' => 'این آدرس ایمیل قبلا توسط مشتری دیگری استفاده شده است.', 'redirect_to' => generate_url('customer_form', $error_redirect_params)];
            }
        }
        // Customer ID field is usually not editable, but if it were, similar check needed.
        // For now, we assume customer_id_field from POST is the same as old one if form field is readonly.

        $stmt = $db->prepare("UPDATE customers SET 
                             name = :name, phone = :phone, email = :email, address = :address, 
                             profile_pic = :profile_pic, notes = :notes, customer_id = :customer_id_field, join_date = :join_date
                             WHERE id = :id");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':profile_pic', $profile_pic_filename);
        $stmt->bindParam(':notes', $notes);
        $stmt->bindParam(':customer_id_field', $customer_id_field); // Using the one from POST (which should be the original if readonly)
        $stmt->bindParam(':join_date', $join_date);
        $stmt->bindParam(':id', $customer_db_id, PDO::PARAM_INT);
        
        $stmt->execute();
        return ['type' => 'success', 'text' => 'اطلاعات مشتری با موفقیت ویرایش شد.', 'redirect_to' => generate_url('customer_info', ['id' => $customer_db_id])];

    } catch (PDOException $e) {
        error_log("Edit Customer Error: " . $e->getMessage());
        if ($new_pic_uploaded && file_exists(UPLOAD_DIR . $profile_pic_filename)) { @unlink(UPLOAD_DIR . $profile_pic_filename); }
        return ['type' => 'error', 'text' => 'خطا در ویرایش اطلاعات مشتری. '.($e->getCode() == '23000' ? 'ایمیل یا شناسه مشتری تکراری است.' : 'لطفا دوباره تلاش کنید.'), 'redirect_to' => generate_url('customer_form', $error_redirect_params)];
    }
}

/**
 * Handles deleting a customer.
 * @return array Action message array.
 */
function handle_delete_customer_action() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['type' => 'error', 'text' => 'درخواست نامعتبر.', 'redirect_to' => generate_url('customers_list')];
    }
    if (!isset($_SESSION['user_id'])) { return ['type' => 'error', 'text' => 'دسترسی غیر مجاز.', 'redirect_to' => 'login.php']; }

    $db = getDB();
    $customer_id_to_delete = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);

    if (empty($customer_id_to_delete)) {
        return ['type' => 'error', 'text' => 'شناسه مشتری برای حذف نامعتبر است.', 'redirect_to' => generate_url('customers_list')];
    }

    try {
        $stmt_pic = $db->prepare("SELECT profile_pic FROM customers WHERE id = :id");
        $stmt_pic->bindParam(':id', $customer_id_to_delete, PDO::PARAM_INT);
        $stmt_pic->execute();
        $profile_pic_filename = $stmt_pic->fetchColumn();

        $stmt_delete = $db->prepare("DELETE FROM customers WHERE id = :id");
        $stmt_delete->bindParam(':id', $customer_id_to_delete, PDO::PARAM_INT);
        $deleted = $stmt_delete->execute();

        if ($deleted) {
            if ($profile_pic_filename && file_exists(UPLOAD_DIR . $profile_pic_filename)) {
                @unlink(UPLOAD_DIR . $profile_pic_filename);
            }
            return ['type' => 'success', 'text' => 'مشتری با موفقیت حذف شد.', 'redirect_to' => generate_url('customers_list')];
        } else {
            return ['type' => 'error', 'text' => 'خطا در حذف مشتری. ممکن است مشتری یافت نشود.', 'redirect_to' => generate_url('customers_list')];
        }

    } catch (PDOException $e) {
        error_log("Delete Customer Error: " . $e->getMessage());
        if (strpos($e->getMessage(), 'FOREIGN KEY constraint failed') !== false || $e->getCode() == '23000') {
             return ['type' => 'error', 'text' => 'خطا: این مشتری دارای فاکتورهای مرتبط است و نمی‌توان آن را حذف کرد. ابتدا فاکتورها را مدیریت کنید.', 'redirect_to' => generate_url('customers_list')];
        }
        return ['type' => 'error', 'text' => 'خطا در حذف مشتری: ' . $e->getMessage(), 'redirect_to' => generate_url('customers_list')];
    }
}

?>
