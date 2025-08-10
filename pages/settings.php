<?php
// /pages/settings.php
// Page for managing store settings, styled to match unnamed (14).png

if (!defined('DB_PATH')) { // Should be defined in index.php
    die("Access denied. This page should be accessed via index.php");
}

// db.php and its functions are included via index.php
$db = getDB();
// $app_base_path is available from header.php
global $app_base_path; 

// Fetch current settings to pre-fill the form
$settings_data_page = [
    'store_name' => get_setting('store_name', STORE_NAME),
    'store_logo' => get_setting('store_logo', ''), 
    'store_address' => get_setting('store_address', ''),
    'store_phone' => get_setting('store_phone', ''),
    'store_email' => get_setting('store_email', ''),
    'store_postal_code' => get_setting('store_postal_code', ''),
    'store_fax' => get_setting('store_fax', ''), // New field based on unnamed (14).png
    'store_registration_number' => get_setting('store_registration_number', ''),
    'default_tax_rate' => get_setting('default_tax_rate', '0.09'),
    'use_friendly_urls' => (bool)get_setting('use_friendly_urls', '1') 
];

$current_logo_url_settings = 'https://placehold.co/128x128/E0E7FF/4338CA?text=لوگو'; 
if (!empty($settings_data_page['store_logo']) && file_exists(UPLOAD_DIR . $settings_data_page['store_logo'])) {
    $logo_path_settings = UPLOAD_DIR_PUBLIC_PATH . htmlspecialchars($settings_data_page['store_logo']);
    $current_logo_url_settings = $app_base_path . '/' . $logo_path_settings; 
}

// Pre-fill form from session if validation error occurred
$form_values_settings = $_SESSION['form_data']['settings_form'] ?? $settings_data_page;
unset($_SESSION['form_data']['settings_form']);

?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
        <h2 class="text-xl lg:text-2xl font-semibold text-slate-800">تنظیمات فروشگاه</h2>
        </div>

    <form action="<?php echo generate_url('save_settings', [], true); ?>" method="POST" enctype="multipart/form-data" class="bg-white p-6 md:p-8 rounded-xl shadow-xl border border-slate-200 space-y-8">
        
        <fieldset class="space-y-6">
            <legend class="text-lg font-semibold text-slate-700 border-b border-slate-300 pb-2 mb-6 w-full">اطلاعات فروشگاه</legend>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-5 items-start">
                <div>
                    <label for="store_name" class="form-label">نام فروشگاه <span class="text-red-500">*</span></label>
                    <input type="text" id="store_name" name="settings[store_name]" class="form-input" value="<?php echo htmlspecialchars($form_values_settings['store_name']); ?>" required>
                </div>
                <div class="space-y-2">
                    <label class="form-label">لوگو فروشگاه:</label>
                    <div class="flex items-center gap-4">
                        <img id="logoPreviewSettings" src="<?php echo $current_logo_url_settings; ?>" alt="لوگو فعلی" class="w-20 h-20 object-contain border bg-slate-50 rounded-md p-1 shadow-sm">
                        <div class="flex-grow">
                            <label for="store_logo_upload" class="btn btn-secondary text-sm w-full sm:w-auto justify-center py-2">
                                <i data-lucide="upload-cloud" class="icon-sm ml-2"></i>
                                <span><?php echo !empty($settings_data_page['store_logo']) ? 'تغییر لوگو' : 'بارگذاری لوگو'; ?></span>
                            </label>
                            <input type="file" name="store_logo_new" id="store_logo_upload" class="hidden" accept="image/jpeg,image/png,image/gif" onchange="previewImage(event, 'logoPreviewSettings')">
                            <p class="text-xs text-slate-500 mt-1.5">فرمت‌های مجاز: JPG, PNG, GIF (حداکثر <?php echo MAX_LOGO_SIZE / (1024*1024); ?>MB)</p>
                        </div>
                    </div>
                     <?php if (!empty($settings_data_page['store_logo'])): ?>
                        <label class="mt-2 flex items-center text-xs text-slate-600">
                            <input type="checkbox" name="remove_store_logo" value="1" class="form-checkbox ml-1.5">
                            حذف لوگوی فعلی
                        </label>
                    <?php endif; ?>
                    <input type="hidden" name="settings[store_logo_current]" value="<?php echo htmlspecialchars($settings_data_page['store_logo']); ?>">
                </div>
            </div>

            <div>
                <label for="store_address" class="form-label">آدرس فروشگاه</label>
                <textarea id="store_address" name="settings[store_address]" rows="3" class="form-textarea"><?php echo htmlspecialchars($form_values_settings['store_address']); ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-5">
                <div>
                    <label for="store_phone" class="form-label">شماره تماس</label>
                    <input type="tel" id="store_phone" name="settings[store_phone]" dir="ltr" class="form-input text-left" value="<?php echo htmlspecialchars($form_values_settings['store_phone']); ?>" placeholder="021-12345678">
                </div>
                <div>
                    <label for="store_postal_code" class="form-label">کد پستی</label>
                    <input type="text" id="store_postal_code" name="settings[store_postal_code]" dir="ltr" class="form-input text-left" value="<?php echo htmlspecialchars($form_values_settings['store_postal_code']); ?>" placeholder="12345-67890">
                </div>
                 <div>
                    <label for="store_email" class="form-label">آدرس ایمیل</label>
                    <input type="email" id="store_email" name="settings[store_email]" dir="ltr" class="form-input text-left" value="<?php echo htmlspecialchars($form_values_settings['store_email']); ?>" placeholder="info@example.com">
                </div>
                <div class="md:col-span-2">
                    <label for="store_registration_number" class="form-label">شماره ثبت / شناسه ملی</label>
                    <input type="text" id="store_registration_number" name="settings[store_registration_number]" dir="ltr" class="form-input text-left" value="<?php echo htmlspecialchars($form_values_settings['store_registration_number']); ?>">
                </div>
            </div>
        </fieldset>

        <fieldset class="space-y-6 pt-6 border-t border-slate-200">
             <legend class="text-lg font-semibold text-slate-700 border-b border-slate-300 pb-2 mb-6 w-full">تنظیمات مالی و عمومی</legend>
             <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-5">
                <div>
                    <label for="default_tax_rate" class="form-label">نرخ پیش‌فرض مالیات (مثال: 0.09 برای 9%)</label>
                    <input type="number" step="0.001" min="0" max="1" id="default_tax_rate" name="settings[default_tax_rate]" dir="ltr" class="form-input text-left" value="<?php echo htmlspecialchars($form_values_settings['default_tax_rate']); ?>">
                </div>
                <div>
                    <label for="use_friendly_urls_toggle" class="form-label">استفاده از آدرس‌های بهینه (Friendly URLs)</label>
                    <div class="mt-2">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="use_friendly_urls_toggle" name="settings[use_friendly_urls]" value="1" class="sr-only peer" <?php echo $form_values_settings['use_friendly_urls'] ? 'checked' : ''; ?>>
                            <div class="relative w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-sky-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-600"></div>
                            <span class="ms-3 text-sm font-medium text-slate-700">فعال بودن آدرس‌های بهینه</span>
                        </label>
                    </div>
                     <p class="text-xs text-slate-500 mt-1.5">در صورت فعال بودن، آدرس‌ها خواناتر نمایش داده می‌شوند. <strong class="text-red-600">ممکن است نیاز به تنظیمات وب سرور (.htaccess) داشته باشد.</strong></p>
                </div>
             </div>
        </fieldset>
        
        <div class="flex justify-end pt-8 mt-6 border-t border-slate-200">
            <button type="submit" class="bg-blue-600 flex text-white px-6 py-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                <i data-lucide="save" class="icon-md ml-2"></i>
                ذخیره تنظیمات
            </button>
            </div>
    </form>
</div>
