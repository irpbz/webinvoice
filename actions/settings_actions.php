<?php
// /actions/settings_actions.php
// Handles saving store settings, using generate_url for redirects.

if (session_status() == PHP_SESSION_NONE) {
    if (!defined('SESSION_NAME')) { require_once __DIR__ . '/../config.php'; }
    session_name(SESSION_NAME);
    session_start();
}

// Ensure config.php and db.php (for update_setting, getDB, generate_url) are loaded
if (!function_exists('getDB') || !function_exists('generate_url') || !function_exists('update_setting')) {
    require_once __DIR__ . '/../config.php'; 
    require_once __DIR__ . '/../db.php'; 
}

/**
 * Handles saving the store settings.
 * @return array Action message array with 'type', 'text', and 'redirect_to'.
 */
function handle_save_settings_action() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['type' => 'error', 'text' => 'درخواست نامعتبر.', 'redirect_to' => generate_url('settings')];
    }
    if (!isset($_SESSION['user_id'])) {
        return ['type' => 'error', 'text' => 'دسترسی غیر مجاز.', 'redirect_to' => 'login.php']; // login.php is standalone
    }

    $db = getDB();
    $posted_settings = $_POST['settings'] ?? [];
    // Default redirect will be to the settings page itself.
    $redirect_params = []; // No extra params needed for settings page redirect usually.
    $redirect_url = generate_url('settings', $redirect_params); 

    // Store POST data in session in case of error for pre-filling (though settings page re-fetches)
    $_SESSION['form_data']['settings_form'] = $posted_settings;


    try {
        $db->beginTransaction();

        // --- Handle Logo Upload/Removal ---
        $current_logo_filename = $posted_settings['store_logo_current'] ?? null;
        $new_logo_filename = $current_logo_filename; // Assume current logo stays unless changed or removed

        // 1. Check if "remove logo" is checked
        if (isset($_POST['remove_store_logo']) && $_POST['remove_store_logo'] == '1') {
            if ($current_logo_filename && file_exists(UPLOAD_DIR . $current_logo_filename)) {
                @unlink(UPLOAD_DIR . $current_logo_filename); // Suppress error if file already gone
            }
            $new_logo_filename = ''; // Set to empty string to remove from DB
        } 
        // 2. Else, check if a new logo is uploaded (only if not removing)
        elseif (isset($_FILES['store_logo_new']) && $_FILES['store_logo_new']['error'] == UPLOAD_ERR_OK) {
            $file = $_FILES['store_logo_new'];
            
            if ($file['size'] > MAX_LOGO_SIZE) { // MAX_LOGO_SIZE from config.php
                $db->rollBack();
                return ['type' => 'error', 'text' => 'اندازه فایل لوگو بیش از حد مجاز است (حداکثر '.(MAX_LOGO_SIZE/(1024*1024)).'MB).', 'redirect_to' => $redirect_url];
            }
            if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) {
                $db->rollBack();
                return ['type' => 'error', 'text' => 'نوع فایل لوگو مجاز نیست (فقط JPG, PNG, GIF).', 'redirect_to' => $redirect_url];
            }

            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            // Ensure extension is valid after checking MIME type
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                 $db->rollBack();
                return ['type' => 'error', 'text' => 'پسوند فایل لوگو نامعتبر است.', 'redirect_to' => $redirect_url];
            }

            $uploaded_filename = 'store_logo_' . time() . '.' . $extension;
            $upload_path = UPLOAD_DIR . $uploaded_filename;

            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // New logo uploaded, delete old one if it exists and is different
                if ($current_logo_filename && $current_logo_filename !== $uploaded_filename && file_exists(UPLOAD_DIR . $current_logo_filename)) {
                    @unlink(UPLOAD_DIR . $current_logo_filename);
                }
                $new_logo_filename = $uploaded_filename; // Set to the new filename
            } else {
                error_log("Failed to move uploaded store logo: " . $file['tmp_name'] . " to " . $upload_path);
                $db->rollBack();
                return ['type' => 'error', 'text' => 'خطا در بارگذاری لوگوی جدید فروشگاه.', 'redirect_to' => $redirect_url];
            }
        }
        // Update the 'store_logo' setting with the final filename (could be new, old, or empty)
        update_setting('store_logo', $new_logo_filename);


        // --- Handle 'use_friendly_urls' setting ---
        // It's a checkbox, so it might not be present in $_POST['settings'] if unchecked
        $use_friendly_urls_value = isset($posted_settings['use_friendly_urls']) && $posted_settings['use_friendly_urls'] == '1' ? '1' : '0';
        update_setting('use_friendly_urls', $use_friendly_urls_value);


        // --- Iterate over other posted settings and update them ---
        $allowed_settings_keys = [ // Define which settings are expected and can be updated
            'store_name', 'store_address', 'store_phone', 'store_email', 
            'store_postal_code', 'store_registration_number', 'default_tax_rate'
        ];

        foreach ($posted_settings as $key => $value) {
            if (!in_array($key, $allowed_settings_keys)) {
                // Skip unexpected settings or keys already handled (like store_logo_current, use_friendly_urls)
                continue; 
            }

            $sanitized_value = trim($value); 

            if ($key === 'default_tax_rate') {
                $sanitized_value_float = filter_var($sanitized_value, FILTER_VALIDATE_FLOAT);
                if ($sanitized_value_float === false || $sanitized_value_float < 0 || $sanitized_value_float > 1) {
                    $db->rollBack(); // Critical setting, rollback if invalid
                    return ['type' => 'error', 'text' => 'مقدار نرخ مالیات نامعتبر است (باید عددی بین 0 و 1 باشد، مثال: 0.09).', 'redirect_to' => $redirect_url];
                }
                $sanitized_value = (string)$sanitized_value_float; // Store as string
            }
            
            if (!update_setting($key, $sanitized_value)) {
                // If a specific update_setting fails, log it. Could choose to rollback.
                error_log("Failed to update setting: " . $key);
                // For now, we don't rollback for individual non-critical setting failures here,
                // but you might want to for certain settings.
            }
        }

        $db->commit();
        unset($_SESSION['form_data']['settings_form']); // Clear form data on success
        return ['type' => 'success', 'text' => 'تنظیمات فروشگاه با موفقیت ذخیره شد.', 'redirect_to' => $redirect_url];

    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Save Settings Error: " . $e->getMessage());
        return ['type' => 'error', 'text' => 'خطا در ذخیره تنظیمات: ' . $e->getMessage(), 'redirect_to' => $redirect_url];
    }
}
?>
