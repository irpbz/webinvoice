<?php
// /actions/invoice_actions.php
// Handles CRUD operations for invoices and invoice items, using generate_url and session for form data persistence on error.

if (session_status() == PHP_SESSION_NONE) {
    if (!defined('SESSION_NAME')) { require_once __DIR__ . '/../config.php'; }
    session_name(SESSION_NAME);
    session_start();
}
if (!function_exists('getDB') || !function_exists('generate_url') || !function_exists('get_setting')) {
    require_once __DIR__ . '/../config.php'; 
    require_once __DIR__ . '/../db.php'; 
}

/**
 * Generates a unique invoice number (internal helper).
 */
if (!function_exists('generate_invoice_number_internal_v2')) {
    function generate_invoice_number_internal_v2($db) {
        $year = date('Y');
        $stmt_last_num = $db->prepare("SELECT invoice_number FROM invoices WHERE invoice_number LIKE :pattern ORDER BY id DESC LIMIT 1");
        $pattern = "INV-{$year}-%";
        $stmt_last_num->bindParam(':pattern', $pattern);
        $stmt_last_num->execute();
        $last_invoice_number = $stmt_last_num->fetchColumn();
        
        $next_sequence = 1;
        if ($last_invoice_number) {
            $parts = explode('-', $last_invoice_number);
            if (count($parts) === 3 && is_numeric($parts[2]) && $parts[1] == $year) {
                $next_sequence = (int)$parts[2] + 1;
            } else { // Fallback if format changed or different year, restart sequence for this year or use count
                $stmt_count = $db->prepare("SELECT COUNT(id) FROM invoices WHERE strftime('%Y', date) = :year_val");
                $stmt_count->bindParam(':year_val', $year);
                $stmt_count->execute();
                $next_sequence = $stmt_count->fetchColumn() + 1;
            }
        } else { // No invoices for this year yet
            $stmt_count_init = $db->prepare("SELECT COUNT(id) FROM invoices WHERE strftime('%Y', date) = :year_val_init");
            $stmt_count_init->bindParam(':year_val_init', $year);
            $stmt_count_init->execute();
            $next_sequence = $stmt_count_init->fetchColumn() + 1;
        }
        return 'INV-' . $year . '-' . str_pad($next_sequence, 4, '0', STR_PAD_LEFT);
    }
}

/**
 * Calculates invoice totals (internal helper).
 */
if (!function_exists('calculate_invoice_totals_internal_v2')) {
    function calculate_invoice_totals_internal_v2(array $items, $discount = 0, $tax_rate = 0.09) {
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += (float)($item['quantity'] ?? 0) * (float)($item['unit_price'] ?? 0);
        }
        $amount_after_discount = $subtotal - (float)$discount;
        $tax_amount = $amount_after_discount * (float)$tax_rate;
        $final_amount = $amount_after_discount + $tax_amount;
        return ['subtotal' => round($subtotal, 2), 'tax_amount' => round($tax_amount, 2), 'final_amount' => round($final_amount, 2)];
    }
}

/**
 * Handles adding a new invoice.
 */
function handle_add_invoice_action() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['type' => 'error', 'text' => 'درخواست نامعتبر.', 'redirect_to' => generate_url('invoices_list')];
    }
    if (!isset($_SESSION['user_id'])) { return ['type' => 'error', 'text' => 'دسترسی غیر مجاز.', 'redirect_to' => 'login.php']; }

    $db = getDB();
    
    $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    $invoice_date_str = trim($_POST['invoice_date'] ?? '');
    $due_date_str = trim($_POST['due_date'] ?? '');
    $invoice_type = trim($_POST['invoice_type'] ?? 'فروش');
    $status = trim($_POST['status'] ?? 'پیش نویس');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $discount_str = $_POST['discount'] ?? '0';
    $items_data = $_POST['items'] ?? [];

    $_SESSION['form_data']['invoice_form'] = $_POST; // Persist form data for repopulation on error

    if (empty($customer_id)) { return ['type' => 'error', 'text' => 'مشتری انتخاب نشده است.', 'redirect_to' => generate_url('invoice_form', ['customer_id' => $customer_id])]; } // Pass customer_id if set
    if (empty($invoice_date_str) || !preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $invoice_date_str)) {
        return ['type' => 'error', 'text' => 'فرمت تاریخ فاکتور نامعتبر است (مثال: 1403/03/01).', 'redirect_to' => generate_url('invoice_form', ['customer_id' => $customer_id])];
    }
    $invoice_date = date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $invoice_date_str))); // Store with time
    $due_date = null;
    if (!empty($due_date_str)) {
        if (!preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $due_date_str)) { return ['type' => 'error', 'text' => 'فرمت تاریخ سررسید نامعتبر است.', 'redirect_to' => generate_url('invoice_form', ['customer_id' => $customer_id])]; }
        $due_date = date('Y-m-d', strtotime(str_replace('/', '-', $due_date_str)));
    }
    $discount = filter_var($discount_str, FILTER_VALIDATE_FLOAT);
    if ($discount === false || $discount < 0) $discount = 0;

    if (empty($items_data) || !is_array($items_data)) { return ['type' => 'error', 'text' => 'حداقل یک قلم کالا باید به فاکتور اضافه شود.', 'redirect_to' => generate_url('invoice_form', ['customer_id' => $customer_id])]; }
    
    $valid_items = [];
    foreach ($items_data as $item_idx => $item) { // Keep index for error reporting if any
        $product_id = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        $quantity = filter_var($item['quantity'] ?? 0, FILTER_VALIDATE_INT);
        $unit_price = filter_var($item['unit_price'] ?? 0, FILTER_VALIDATE_FLOAT);
        $product_name_manual = trim($item['product_name_manual'] ?? '');

        if ($quantity === false || $quantity <= 0) { return ['type' => 'error', 'text' => 'تعداد برای ردیف '.($item_idx+1).' نامعتبر است.', 'redirect_to' => generate_url('invoice_form', ['customer_id' => $customer_id])]; }
        if ($unit_price === false || $unit_price < 0) { return ['type' => 'error', 'text' => 'قیمت واحد برای ردیف '.($item_idx+1).' نامعتبر است.', 'redirect_to' => generate_url('invoice_form', ['customer_id' => $customer_id])]; }
        
        $item_product_name = $product_name_manual;
        if ($product_id) { 
            $stmt_prod = $db->prepare("SELECT name, inventory FROM products WHERE id = :pid");
            $stmt_prod->bindParam(':pid', $product_id, PDO::PARAM_INT); $stmt_prod->execute();
            $product_details = $stmt_prod->fetch(PDO::FETCH_ASSOC);
            if (!$product_details) { return ['type' => 'error', 'text' => 'محصول انتخاب شده در ردیف '.($item_idx+1).' یافت نشد.', 'redirect_to' => generate_url('invoice_form', ['customer_id' => $customer_id])]; }
            $item_product_name = $product_details['name'];
            if ($invoice_type === 'فروش' && $status !== 'پیش نویس' && $status !== 'لغو شده' && $product_details['inventory'] < $quantity) {
                 return ['type' => 'error', 'text' => "موجودی محصول \"{$item_product_name}\" (ردیف ".($item_idx+1).") کافی نیست (موجودی: {$product_details['inventory']}).", 'redirect_to' => generate_url('invoice_form', ['customer_id' => $customer_id])];
            }
        } elseif (empty($item_product_name)) {
             return ['type' => 'error', 'text' => 'نام محصول برای ردیف '.($item_idx+1).' (قلم سفارشی) وارد نشده است.', 'redirect_to' => generate_url('invoice_form', ['customer_id' => $customer_id])];
        }
        $valid_items[] = ['product_id' => $product_id, 'product_name' => $item_product_name, 'quantity' => $quantity, 'unit_price' => $unit_price, 'total_price' => $quantity * $unit_price];
    }
    if (empty($valid_items)) { return ['type' => 'error', 'text' => 'هیچ قلم معتبری برای فاکتور یافت نشد.', 'redirect_to' => generate_url('invoice_form', ['customer_id' => $customer_id])]; }

    $default_tax_rate = (float)get_setting('default_tax_rate', 0.09);
    $totals = calculate_invoice_totals_internal_v2($valid_items, $discount, $default_tax_rate);
    $invoice_number = generate_invoice_number_internal_v2($db);

    try {
        $db->beginTransaction();
        $stmt_invoice = $db->prepare("INSERT INTO invoices (invoice_number, customer_id, date, due_date, type, total_amount, discount, tax_amount, final_amount, status, payment_method, notes) VALUES (:invoice_number, :customer_id, :date, :due_date, :type, :total_amount, :discount, :tax_amount, :final_amount, :status, :payment_method, :notes)");
        $stmt_invoice->execute([':invoice_number'=> $invoice_number, ':customer_id'=> $customer_id, ':date'=> $invoice_date, ':due_date'=> $due_date, ':type'=> $invoice_type, ':total_amount'=> $totals['subtotal'], ':discount'=> $discount, ':tax_amount'=> $totals['tax_amount'], ':final_amount'=> $totals['final_amount'], ':status'=> $status, ':payment_method'=> $payment_method, ':notes'=> $notes]);
        $new_invoice_id = $db->lastInsertId();

        $stmt_item = $db->prepare("INSERT INTO invoice_items (invoice_id, product_id, product_name, quantity, unit_price, total_price) VALUES (:invoice_id, :product_id, :product_name, :quantity, :unit_price, :total_price)");
        foreach ($valid_items as $item) {
            $stmt_item->execute([':invoice_id'=> $new_invoice_id, ':product_id'=> $item['product_id'], ':product_name'=> $item['product_name'], ':quantity'=> $item['quantity'], ':unit_price'=> $item['unit_price'], ':total_price'=> $item['total_price']]);
            if ($item['product_id'] && $status !== 'پیش نویس' && $status !== 'لغو شده') {
                $inventory_change = ($invoice_type === 'فروش') ? -$item['quantity'] : +$item['quantity'];
                $stmt_update_inv = $db->prepare("UPDATE products SET inventory = MAX(0, inventory + :change) WHERE id = :pid");
                $stmt_update_inv->execute([':change' => $inventory_change, ':pid' => $item['product_id']]);
            }
        }
        $db->commit();
        unset($_SESSION['form_data']['invoice_form']); // Clear persisted form data on success
        return ['type' => 'success', 'text' => 'فاکتور با شماره ' . $invoice_number . ' با موفقیت ایجاد شد.', 'redirect_to' => generate_url('invoice_details', ['id' => $new_invoice_id])];
    } catch (PDOException $e) {
        $db->rollBack(); error_log("Add Invoice Error: " . $e->getMessage());
        // $_SESSION['form_data']['invoice_form'] is already set
        return ['type' => 'error', 'text' => 'خطا در ایجاد فاکتور: ' . $e->getMessage(), 'redirect_to' => generate_url('invoice_form', ['customer_id' => $customer_id])];
    }
}

/**
 * Handles editing an existing invoice.
 */
function handle_edit_invoice_action() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['type' => 'error', 'text' => 'درخواست نامعتبر.', 'redirect_to' => generate_url('invoices_list')];
    }
    if (!isset($_SESSION['user_id'])) { return ['type' => 'error', 'text' => 'دسترسی غیر مجاز.', 'redirect_to' => 'login.php']; }

    $db = getDB();
    $invoice_db_id = filter_input(INPUT_POST, 'invoice_db_id', FILTER_VALIDATE_INT);
    if (empty($invoice_db_id)) { return ['type' => 'error', 'text' => 'شناسه فاکتور برای ویرایش نامعتبر است.', 'redirect_to' => generate_url('invoices_list')]; }

    $_SESSION['form_data']['invoice_form'] = $_POST; // Persist form data for repopulation on error
    $error_redirect_params = ['id' => $invoice_db_id];

    $stmt_old_invoice = $db->prepare("SELECT type, status FROM invoices WHERE id = :id");
    $stmt_old_invoice->execute([':id' => $invoice_db_id]);
    $old_invoice_data = $stmt_old_invoice->fetch(PDO::FETCH_ASSOC);
    if (!$old_invoice_data) { return ['type' => 'error', 'text' => 'فاکتور برای ویرایش یافت نشد.', 'redirect_to' => generate_url('invoices_list')]; }
    $old_status = $old_invoice_data['status'];
    $old_type = $old_invoice_data['type'];

    $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    // ... (rest of field collection and validation from add_invoice_action, adapted for edit)
    $invoice_date_str = trim($_POST['invoice_date'] ?? '');
    $due_date_str = trim($_POST['due_date'] ?? '');
    $invoice_type = trim($_POST['invoice_type'] ?? 'فروش'); // New type
    $status = trim($_POST['status'] ?? 'پیش نویس'); // New status
    $payment_method = trim($_POST['payment_method'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $discount_str = $_POST['discount'] ?? '0';
    $items_data = $_POST['items'] ?? [];

    if (empty($customer_id)) { return ['type' => 'error', 'text' => 'مشتری انتخاب نشده است.', 'redirect_to' => generate_url('invoice_form', $error_redirect_params)]; }
    if (empty($invoice_date_str) || !preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $invoice_date_str)) { return ['type' => 'error', 'text' => 'فرمت تاریخ فاکتور نامعتبر است.', 'redirect_to' => generate_url('invoice_form', $error_redirect_params)]; }
    $invoice_date = date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $invoice_date_str))); // Store with time
    $due_date = null;
    if (!empty($due_date_str)) {
        if (!preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $due_date_str)) { return ['type' => 'error', 'text' => 'فرمت تاریخ سررسید نامعتبر است.', 'redirect_to' => generate_url('invoice_form', $error_redirect_params)]; }
        $due_date = date('Y-m-d', strtotime(str_replace('/', '-', $due_date_str)));
    }
    $discount = filter_var($discount_str, FILTER_VALIDATE_FLOAT);
    if ($discount === false || $discount < 0) $discount = 0;

    if (empty($items_data) || !is_array($items_data)) { return ['type' => 'error', 'text' => 'حداقل یک قلم کالا باید به فاکتور اضافه شود.', 'redirect_to' => generate_url('invoice_form', $error_redirect_params)]; }
    $valid_items = []; 
    foreach ($items_data as $item_idx => $item) {
        $product_id = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        $quantity = filter_var($item['quantity'] ?? 0, FILTER_VALIDATE_INT);
        $unit_price = filter_var($item['unit_price'] ?? 0, FILTER_VALIDATE_FLOAT);
        $product_name_manual = trim($item['product_name_manual'] ?? '');

        if ($quantity === false || $quantity <= 0) { return ['type' => 'error', 'text' => 'تعداد برای ردیف '.($item_idx+1).' نامعتبر است.', 'redirect_to' => generate_url('invoice_form', $error_redirect_params)]; }
        if ($unit_price === false || $unit_price < 0) { return ['type' => 'error', 'text' => 'قیمت واحد برای ردیف '.($item_idx+1).' نامعتبر است.', 'redirect_to' => generate_url('invoice_form', $error_redirect_params)]; }
        
        $item_product_name = $product_name_manual;
        if ($product_id) {
            $stmt_prod = $db->prepare("SELECT name FROM products WHERE id = :pid");
            $stmt_prod->bindParam(':pid', $product_id, PDO::PARAM_INT); $stmt_prod->execute();
            $product_details = $stmt_prod->fetch(PDO::FETCH_ASSOC);
            if (!$product_details) { return ['type' => 'error', 'text' => 'محصول انتخاب شده در ردیف '.($item_idx+1).' یافت نشد.', 'redirect_to' => generate_url('invoice_form', $error_redirect_params)]; }
            $item_product_name = $product_details['name'];
        } elseif (empty($item_product_name)) {
             return ['type' => 'error', 'text' => 'نام محصول برای ردیف '.($item_idx+1).' (قلم سفارشی) وارد نشده است.', 'redirect_to' => generate_url('invoice_form', $error_redirect_params)];
        }
        $valid_items[] = ['product_id' => $product_id, 'product_name' => $item_product_name, 'quantity' => $quantity, 'unit_price' => $unit_price, 'total_price' => $quantity * $unit_price];
    }
    if (empty($valid_items)) { return ['type' => 'error', 'text' => 'هیچ قلم معتبری برای فاکتور یافت نشد.', 'redirect_to' => generate_url('invoice_form', $error_redirect_params)]; }

    $default_tax_rate = (float)get_setting('default_tax_rate', 0.09);
    $totals = calculate_invoice_totals_internal_v2($valid_items, $discount, $default_tax_rate);

    try {
        $db->beginTransaction();
        // Revert old inventory changes
        if ($old_status !== 'پیش نویس' && $old_status !== 'لغو شده') {
            $stmt_old_items = $db->prepare("SELECT product_id, quantity FROM invoice_items WHERE invoice_id = :invoice_id");
            $stmt_old_items->execute([':invoice_id' => $invoice_db_id]);
            foreach ($stmt_old_items->fetchAll(PDO::FETCH_ASSOC) as $old_item) {
                if ($old_item['product_id']) {
                    $inv_revert_change = ($old_type === 'فروش') ? +$old_item['quantity'] : -$old_item['quantity'];
                    $stmt_revert_inv = $db->prepare("UPDATE products SET inventory = inventory + :change WHERE id = :pid");
                    $stmt_revert_inv->execute([':change' => $inv_revert_change, ':pid' => $old_item['product_id']]);
                }
            }
        }
        $stmt_delete_items = $db->prepare("DELETE FROM invoice_items WHERE invoice_id = :invoice_id");
        $stmt_delete_items->execute([':invoice_id' => $invoice_db_id]);

        $stmt_invoice = $db->prepare("UPDATE invoices SET customer_id = :customer_id, date = :date, due_date = :due_date, type = :type, total_amount = :total_amount, discount = :discount, tax_amount = :tax_amount, final_amount = :final_amount, status = :status, payment_method = :payment_method, notes = :notes WHERE id = :id");
        $stmt_invoice->execute([':customer_id'=> $customer_id, ':date'=> $invoice_date, ':due_date'=> $due_date, ':type'=> $invoice_type, ':total_amount'=> $totals['subtotal'], ':discount'=> $discount, ':tax_amount'=> $totals['tax_amount'], ':final_amount'=> $totals['final_amount'], ':status'=> $status, ':payment_method'=> $payment_method, ':notes'=> $notes, ':id'=> $invoice_db_id]);

        $stmt_item = $db->prepare("INSERT INTO invoice_items (invoice_id, product_id, product_name, quantity, unit_price, total_price) VALUES (:invoice_id, :product_id, :product_name, :quantity, :unit_price, :total_price)");
        foreach ($valid_items as $item) {
            $stmt_item->execute([':invoice_id'=> $invoice_db_id, ':product_id'=> $item['product_id'], ':product_name'=> $item['product_name'], ':quantity'=> $item['quantity'], ':unit_price'=> $item['unit_price'], ':total_price'=> $item['total_price']]);
            if ($item['product_id'] && $status !== 'پیش نویس' && $status !== 'لغو شده') {
                $inventory_change = ($invoice_type === 'فروش') ? -$item['quantity'] : +$item['quantity'];
                $stmt_update_inv = $db->prepare("UPDATE products SET inventory = MAX(0, inventory + :change) WHERE id = :pid");
                $stmt_update_inv->execute([':change' => $inventory_change, ':pid' => $item['product_id']]);
            }
        }
        $db->commit();
        unset($_SESSION['form_data']['invoice_form']);
        return ['type' => 'success', 'text' => 'فاکتور با موفقیت ویرایش شد.', 'redirect_to' => generate_url('invoice_details', ['id' => $invoice_db_id])];
    } catch (PDOException $e) {
        $db->rollBack(); error_log("Edit Invoice Error: " . $e->getMessage());
        // $_SESSION['form_data']['invoice_form'] is already set
        return ['type' => 'error', 'text' => 'خطا در ویرایش فاکتور: ' . $e->getMessage(), 'redirect_to' => generate_url('invoice_form', $error_redirect_params)];
    }
}

/**
 * Handles deleting an invoice.
 */
function handle_delete_invoice_action() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['type' => 'error', 'text' => 'درخواست نامعتبر.', 'redirect_to' => generate_url('invoices_list')];
    }
    if (!isset($_SESSION['user_id'])) { return ['type' => 'error', 'text' => 'دسترسی غیر مجاز.', 'redirect_to' => 'login.php']; }

    $db = getDB();
    $invoice_id_to_delete = filter_input(INPUT_POST, 'invoice_id', FILTER_VALIDATE_INT);
    if (empty($invoice_id_to_delete)) { return ['type' => 'error', 'text' => 'شناسه فاکتور برای حذف نامعتبر است.', 'redirect_to' => generate_url('invoices_list')]; }

    try {
        $db->beginTransaction();
        $stmt_invoice_info = $db->prepare("SELECT type, status FROM invoices WHERE id = :id");
        $stmt_invoice_info->execute([':id' => $invoice_id_to_delete]);
        $invoice_info = $stmt_invoice_info->fetch(PDO::FETCH_ASSOC);

        if ($invoice_info && $invoice_info['status'] !== 'پیش نویس' && $invoice_info['status'] !== 'لغو شده') {
            $stmt_items = $db->prepare("SELECT product_id, quantity FROM invoice_items WHERE invoice_id = :invoice_id");
            $stmt_items->execute([':invoice_id' => $invoice_id_to_delete]);
            foreach ($stmt_items->fetchAll(PDO::FETCH_ASSOC) as $item) {
                if ($item['product_id']) {
                    $inv_revert_change = ($invoice_info['type'] === 'فروش') ? +$item['quantity'] : -$item['quantity'];
                    $stmt_revert_inv = $db->prepare("UPDATE products SET inventory = inventory + :change WHERE id = :pid");
                    $stmt_revert_inv->execute([':change' => $inv_revert_change, ':pid' => $item['product_id']]);
                }
            }
        }
        $stmt_delete = $db->prepare("DELETE FROM invoices WHERE id = :id");
        $deleted = $stmt_delete->execute([':id' => $invoice_id_to_delete]);

        if ($deleted) {
            $db->commit();
            return ['type' => 'success', 'text' => 'فاکتور و اقلام مرتبط با آن با موفقیت حذف شدند.', 'redirect_to' => generate_url('invoices_list')];
        } else {
            $db->rollBack();
            return ['type' => 'error', 'text' => 'خطا در حذف فاکتور. ممکن است فاکتور یافت نشود.', 'redirect_to' => generate_url('invoices_list')];
        }
    } catch (PDOException $e) {
        $db->rollBack(); error_log("Delete Invoice Error: " . $e->getMessage());
        return ['type' => 'error', 'text' => 'خطا در حذف فاکتور: ' . $e->getMessage(), 'redirect_to' => generate_url('invoices_list')];
    }
}

?>
