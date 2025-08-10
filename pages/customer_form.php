<?php
// /pages/customer_form.php
// Form for adding or editing a customer

if (!defined('DB_PATH')) { // Should be defined in index.php
    die("Access denied. This page should be accessed via index.php");
}

// db.php and its functions are included via index.php
$db = getDB();
// $app_base_path and other global vars are available from header.php/index.php
global $app_base_path, $id; // $id is the customer's DB ID for editing

$is_edit_mode = false;
$customer = null; 
$page_title = "افزودن مشتری جدید";
$form_action_name = 'add_customer'; // Action name for generate_url
$submit_button_text = "ثبت مشتری";
$submit_button_icon = "plus-circle";

// Default values for form fields
$customer_data = [
    'id' => null,
    'name' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'profile_pic' => null,
    'notes' => '',
    'customer_id' => 'CUST-' . strtoupper(substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 6)), // Auto-generate a suggestion
    'join_date' => date('Y/m/d') 
];

// Pre-fill form from GET if redirected from a failed add attempt that used GET params
// (Though POST-Redirect-GET with session for errors is usually better)
if (!$is_edit_mode && isset($_GET['name'])) $customer_data['name'] = htmlspecialchars($_GET['name']);
if (!$is_edit_mode && isset($_GET['phone'])) $customer_data['phone'] = htmlspecialchars($_GET['phone']);
if (!$is_edit_mode && isset($_GET['email'])) $customer_data['email'] = htmlspecialchars($_GET['email']);
// etc. for other fields if needed

if ($id !== null) { // $id is from index.php, passed if editing
    $is_edit_mode = true;
    $customer_id_to_edit = $id;
    
    $stmt = $db->prepare("SELECT * FROM customers WHERE id = :id");
    $stmt->bindParam(':id', $customer_id_to_edit, PDO::PARAM_INT);
    $stmt->execute();
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($customer) {
        $page_title = "ویرایش اطلاعات: " . htmlspecialchars($customer['name']);
        $form_action_name = 'edit_customer';
        $submit_button_text = "ذخیره تغییرات";
        $submit_button_icon = "save";
        
        $customer_data = $customer; // Overwrite defaults with fetched data
        $customer_data['join_date'] = !empty($customer['join_date']) ? date('Y/m/d', strtotime($customer['join_date'])) : date('Y/m/d');
    } else {
        $_SESSION['action_message'] = ['type' => 'error', 'text' => 'مشتری مورد نظر برای ویرایش یافت نشد.'];
        header('Location: ' . generate_url('customers_list'));
        exit;
    }
}

// For displaying previous input values if validation failed on server (using session)
$form_values = $_SESSION['form_data']['customer_form'] ?? $customer_data;
unset($_SESSION['form_data']['customer_form']); // Clear after use

$profile_pic_url_form = 'https://placehold.co/160x160/E0E7FF/4338CA?text=' . strtoupper(mb_substr($form_values['name'] ?: 'C', 0, 1, 'UTF-8'));
if (!empty($form_values['profile_pic']) && file_exists(UPLOAD_DIR . $form_values['profile_pic'])) {
    $profile_pic_path_form = UPLOAD_DIR_PUBLIC_PATH . htmlspecialchars($form_values['profile_pic']);
    $profile_pic_url_form = $app_base_path . '/' . $profile_pic_path_form;
}

?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
        <h2 class="text-xl lg:text-2xl font-semibold text-slate-800"><?php echo $page_title; ?></h2>
        <a href="<?php echo generate_url('customers_list'); ?>" class="bg-blue-600 flex text-white px-6 py-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
            <i data-lucide="arrow-right" class="icon-sm ml-2"></i> بازگشت به لیست مشتریان
        </a>
    </div>

    <form action="<?php echo generate_url($form_action_name, [], true); ?>" method="POST" enctype="multipart/form-data" class="bg-white p-6 md:p-8 rounded-xl shadow-lg">
        <?php if ($is_edit_mode && $form_values['id']): ?>
            <input type="hidden" name="customer_db_id" value="<?php echo $form_values['id']; ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
            <div class="lg:col-span-1 space-y-6 order-first lg:order-last">
                <div>
                    <label class="form-label text-center lg:text-right">عکس پروفایل:</label>
                    <div class="flex flex-col items-center p-4 border-2 border-dashed border-slate-300 rounded-lg bg-slate-50">
                        <img id="profilePicPreview" src="<?php echo $profile_pic_url_form; ?>" alt="پیش نمایش پروفایل" class="w-40 h-40 rounded-full object-cover border-4 border-slate-200 shadow-md mb-3 bg-slate-100">
                        <label for="profile_pic_upload" class="btn btn-secondary btn-sm w-full max-w-[200px] text-center cursor-pointer flex items-center justify-center">
                            <i data-lucide="upload-cloud" class="icon-sm ml-2"></i>
                            <span><?php echo $is_edit_mode && $form_values['profile_pic'] ? 'تغییر عکس' : 'انتخاب عکس'; ?></span>
                        </label>
                        <input type="file" name="profile_pic" id="profile_pic_upload" class="hidden" accept="image/jpeg,image/png,image/gif" onchange="previewImage(event, 'profilePicPreview')">
                        <p class="text-xs text-slate-500 mt-2 text-center">فرمت‌های مجاز: JPG, PNG, GIF (حداکثر <?php echo MAX_FILE_SIZE / (1024*1024); ?>MB)</p>
                    </div>
                </div>
                 <div>
                    <label for="notes" class="form-label">توضیحات (اختیاری):</label>
                    <textarea id="notes" name="notes" rows="4" class="form-textarea text-sm" placeholder="هرگونه اطلاعات اضافی در مورد مشتری..."><?php echo htmlspecialchars($form_values['notes']); ?></textarea>
                </div>
            </div>

            <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                <div>
                    <label for="name" class="form-label">نام و نام خانوادگی <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" class="form-input" value="<?php echo htmlspecialchars($form_values['name']); ?>" required placeholder="مثلا: علی رضایی">
                </div>
                <div>
                    <label for="phone" class="form-label">شماره تماس</label>
                    <input type="tel" id="phone" name="phone" class="form-input" value="<?php echo htmlspecialchars($form_values['phone']); ?>" placeholder="مثلا: 09123456789">
                </div>
                <div>
                    <label for="email" class="form-label">آدرس ایمیل</label>
                    <input type="email" id="email" name="email" class="form-input" value="<?php echo htmlspecialchars($form_values['email']); ?>" placeholder="مثلا: user@example.com">
                </div>
                 <div>
                    <label for="customer_id_field" class="form-label">شناسه مشتری <?php echo $is_edit_mode ? '(غیرقابل تغییر)' : ''; ?></label>
                    <input type="text" id="customer_id_field" name="customer_id" class="form-input <?php echo $is_edit_mode ? 'bg-slate-200 cursor-not-allowed' : '';?>" value="<?php echo htmlspecialchars($form_values['customer_id']); ?>" placeholder="مثال: CUST-1001" <?php echo $is_edit_mode ? 'readonly' : ''; ?>>
                     <?php if (!$is_edit_mode): ?>
                        <p class="text-xs text-slate-500 mt-1">در صورت خالی رها کردن، به صورت خودکار پیشنهاد می‌شود.</p>
                    <?php endif; ?>
                </div>
                 <div class="sm:col-span-2">
                    <label for="address" class="form-label">آدرس</label>
                    <textarea id="address" name="address" rows="3" class="form-textarea text-sm" placeholder="مثال: تهران، خیابان ولیعصر، کوچه ..."><?php echo htmlspecialchars($form_values['address']); ?></textarea>
                </div>
                <div>
                    <label for="join_date" class="form-label">تاریخ عضویت</label>
                    <input type="text" id="join_date" name="join_date" class="form-input" value="<?php echo htmlspecialchars($form_values['join_date']); ?>" placeholder="مثال: 1403/03/15">
                     <p class="text-xs text-slate-500 mt-1">فرمت: سال/ماه/روز مثال: 1403/03/15</p>
                </div>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-3 rtl:sm:space-x-reverse pt-6 border-t border-slate-200 mt-8">
            <a href="<?php echo generate_url('customers_list'); ?>" class="bg-red-600 flex text-white px-6 py-2 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">انصراف</a>
            <button type="submit" class="bg-blue-600 flex text-white px-6 py-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                <i data-lucide="<?php echo $submit_button_icon; ?>" class="icon-md ml-2"></i>
                <?php echo $submit_button_text; ?>
            </button>
        </div>
    </form>
</div>
