<?php
// db.php
// Database connection and schema management

require_once __DIR__ . '/config.php'; // Ensures DEFAULT_USE_FRIENDLY_URLS is available

/**
 * Establishes a connection to the SQLite database.
 * @return PDO|null The PDO database connection object or null on failure.
 */
function getDB() {
    static $db = null; 
    if ($db === null) {
        try {
            $db = new PDO('sqlite:' . DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); 
            $db->exec('PRAGMA foreign_keys = ON;'); 
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("اتصال به پایگاه داده برقرار نشد. لطفا تنظیمات و دسترسی‌های فایل زیر را بررسی کنید: " . DB_PATH . "<br>خطا: " . $e->getMessage());
        }
    }
    return $db;
}

/**
 * Initializes the database schema if tables do not exist.
 */
function initializeDBSchema() {
    $db = getDB();
    if (!$db) return;

    try {
        // Customers Table
        $db->exec("CREATE TABLE IF NOT EXISTS customers (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, phone TEXT, email TEXT UNIQUE, address TEXT, profile_pic TEXT, notes TEXT, customer_id TEXT UNIQUE, join_date TEXT, created_at TEXT DEFAULT (datetime('now','localtime')), updated_at TEXT DEFAULT (datetime('now','localtime')) )");
        // Products Table
        $db->exec("CREATE TABLE IF NOT EXISTS products (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, category TEXT, sell_price REAL NOT NULL DEFAULT 0, buy_price REAL DEFAULT 0, inventory INTEGER NOT NULL DEFAULT 0, description TEXT, image TEXT, product_id TEXT UNIQUE, status TEXT DEFAULT 'فعال', created_at TEXT DEFAULT (datetime('now','localtime')), updated_at TEXT DEFAULT (datetime('now','localtime')) )");
        // Invoices Table
        $db->exec("CREATE TABLE IF NOT EXISTS invoices (id INTEGER PRIMARY KEY AUTOINCREMENT, invoice_number TEXT UNIQUE NOT NULL, customer_id INTEGER, date TEXT NOT NULL, due_date TEXT, type TEXT NOT NULL DEFAULT 'فروش', total_amount REAL NOT NULL DEFAULT 0, discount REAL DEFAULT 0, tax_amount REAL DEFAULT 0, final_amount REAL NOT NULL DEFAULT 0, status TEXT DEFAULT 'در انتظار پرداخت', payment_method TEXT, notes TEXT, created_at TEXT DEFAULT (datetime('now','localtime')), updated_at TEXT DEFAULT (datetime('now','localtime')), FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL )");
        // Invoice Items Table
        $db->exec("CREATE TABLE IF NOT EXISTS invoice_items (id INTEGER PRIMARY KEY AUTOINCREMENT, invoice_id INTEGER NOT NULL, product_id INTEGER, product_name TEXT NOT NULL, quantity INTEGER NOT NULL DEFAULT 1, unit_price REAL NOT NULL DEFAULT 0, total_price REAL NOT NULL DEFAULT 0, FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE, FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL )");
        // Settings Table
        $db->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");

        // Default settings including the new friendly URL toggle from config.php
        $defaultSettings = [
            'store_name' => STORE_NAME,
            'store_logo' => '', 
            'store_address' => 'خیابان اصلی، کوچه فرعی، پلاک ۱۰', // Example default
            'store_phone' => '021-12345678',                   // Example default
            'store_email' => 'info@example.com',               // Example default
            'store_postal_code' => '12345-67890',              // Example default
            'store_registration_number' => '123456',           // Example default
            'default_tax_rate' => '0.09', 
            'use_friendly_urls' => DEFAULT_USE_FRIENDLY_URLS // Use constant from config.php
        ];

        $stmt = $db->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (:key, :value)");
        foreach ($defaultSettings as $key => $value) {
            $stmt->execute(['key' => $key, 'value' => $value]);
        }
        
        $tablesWithUpdatedAt = ['customers', 'products', 'invoices'];
        foreach ($tablesWithUpdatedAt as $table) {
            $trigger_name = "update_{$table}_updated_at";
            $check_trigger_stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type='trigger' AND name= :trigger_name;");
            $check_trigger_stmt->bindParam(':trigger_name', $trigger_name, PDO::PARAM_STR);
            $check_trigger_stmt->execute();
            if (!$check_trigger_stmt->fetchColumn()) {
                 $db->exec("CREATE TRIGGER {$trigger_name} AFTER UPDATE ON {$table} FOR EACH ROW BEGIN UPDATE {$table} SET updated_at = datetime('now', 'localtime') WHERE id = OLD.id; END;");
            }
        }

    } catch (PDOException $e) {
        error_log("Database Schema Initialization Error: " . $e->getMessage());
        die(" مقداردهی اولیه اسکیمای پایگاه داده با خطا مواجه شد. خطا: " . $e->getMessage());
    }
}

initializeDBSchema();

/**
 * Fetches a single setting value from the settings table.
 * Uses config constant as ultimate fallback if $default is null.
 */
function get_setting($key, $default = null) {
    $db = getDB();
    // Determine the ultimate fallback: if $default is explicitly provided, use it. Otherwise, check for config constant.
    $ultimate_default = $default;
    if ($default === null) { // Only check config if no specific default was passed to function
        if ($key === 'use_friendly_urls' && defined('DEFAULT_USE_FRIENDLY_URLS')) {
            $ultimate_default = DEFAULT_USE_FRIENDLY_URLS;
        } elseif ($key === 'store_name' && defined('STORE_NAME')) {
            $ultimate_default = STORE_NAME;
        }
        // Add more key checks for other config constants if needed
    }
    
    if (!$db && $ultimate_default !== null) return $ultimate_default;
    if (!$db) return null;

    try {
        $stmt = $db->prepare("SELECT value FROM settings WHERE key = :key");
        $stmt->bindParam(':key', $key);
        $stmt->execute();
        $result = $stmt->fetchColumn();
        return ($result !== false) ? $result : $ultimate_default;
    } catch (PDOException $e) {
        error_log("Error fetching setting {$key}: " . $e->getMessage());
        return $ultimate_default; 
    }
}

function update_setting($key, $value) {
    $db = getDB();
    if (!$db) return false;
    try {
        $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :value)");
        $stmt->bindParam(':key', $key);
        $stmt->bindParam(':value', $value);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error updating setting {$key}: " . $e->getMessage());
        return false;
    }
}

function generate_url($page_or_action_name, $params = [], $is_action_url = false) {
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $base_path = rtrim($script_dir, '/'); 
    if ($base_path === '.' || $base_path === '/') { $base_path = ''; }

    // Use the new DEFAULT_USE_FRIENDLY_URLS from config.php as the fallback for get_setting
    $use_friendly_urls = (bool)get_setting('use_friendly_urls', defined('DEFAULT_USE_FRIENDLY_URLS') ? DEFAULT_USE_FRIENDLY_URLS : '1');

    if ($use_friendly_urls) {
        $url = $base_path; 
        $id = $params['id'] ?? null;
        unset($params['page'], $params['action'], $params['id']); 

        if ($is_action_url) {
            $url .= '/action/' . $page_or_action_name;
        } else { 
            $friendly_paths = [
                'dashboard'         => '/dashboard',
                'customers_list'    => '/customers',
                'customer_form'     => ($id ? '/customer/edit/' . $id : '/customer/add'),
                'customer_info'     => ($id ? '/customer/info/' . $id : '/customers'),
                'products_list'     => '/products',
                'product_form'      => ($id ? '/product/edit/' . $id : '/product/add'),
                'product_info'      => ($id ? '/product/info/' . $id : '/products'),
                'invoices_list'     => '/invoices',
                'invoice_form'      => ($id ? '/invoice/edit/' . $id : '/invoice/add'),
                'invoice_details'   => ($id ? '/invoice/detail/' . $id : '/invoices'),
                'invoice_print'     => ($id ? '/invoice/print/' . $id : '/invoices'), 
                'settings'          => '/settings',
                'reports'           => '/reports',
            ];
            if (isset($friendly_paths[$page_or_action_name])) {
                $url .= $friendly_paths[$page_or_action_name];
            } else {
                $fallback_params = ['page' => $page_or_action_name];
                if ($id) $fallback_params['id'] = $id;
                $fallback_params = array_merge($fallback_params, $params); 
                return $base_path . '/index.php?' . http_build_query($fallback_params);
            }
        }
        if (!$is_action_url && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    } else {
        $query_data = [];
        if ($is_action_url) {
            $query_data['action'] = $page_or_action_name;
        } else {
            $query_data['page'] = $page_or_action_name;
        }
        $query_data = array_merge($query_data, $params); 
        return $base_path . '/index.php?' . http_build_query($query_data);
    }
}

function format_currency_php($amount, $currency_symbol = null) {
    if ($currency_symbol === null) {
        $currency_symbol = defined('DEFAULT_CURRENCY_SYMBOL') ? DEFAULT_CURRENCY_SYMBOL : 'ریال';
    }
    if (!is_numeric($amount)) { $amount = 0; }
    return number_format($amount, 0, '.', ',') . ' ' . $currency_symbol;
}

?>