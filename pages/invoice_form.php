<?php
// /pages/invoice_form.php
// Form for creating or editing an invoice, with UI matching "unnamed*" images.

if (!defined('DB_PATH')) { die("Access denied."); }

$db = getDB();
global $app_base_path, $id; // $id is invoice_db_id for editing

$is_edit_mode = false;
$invoice = null;
$invoice_items = [];
$page_title = "ایجاد فاکتور جدید";
$form_action_name = 'add_invoice';
$submit_button_text = "ایجاد و ذخیره فاکتور";
$submit_button_icon = "plus-circle";

// Default values for a new invoice
$invoice_data = [
    'id' => null, 'customer_id' => ($_GET['customer_id'] ?? ''),
    'invoice_number' => 'درحال صدور...', 'date' => date('Y/m/d'), // Persian format for display
    'due_date' => '', 'type' => 'فروش', 'status' => 'پیش نویس',
    'payment_method' => 'نقدی', 'notes' => '', 'discount' => 0,
    'total_amount' => 0, 'tax_amount' => 0, 'final_amount' => 0
];

$stmt_customers = $db->query("SELECT id, name, customer_id FROM customers ORDER BY name ASC");
$customers = $stmt_customers->fetchAll(PDO::FETCH_ASSOC);
$stmt_products = $db->query("SELECT id, name, sell_price, buy_price, inventory, product_id as product_code FROM products WHERE status = 'فعال' ORDER BY name ASC");
$products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);
$products_json = json_encode($products); // For JS access

$store_settings_display_inv_form = [
    'store_name' => get_setting('store_name', STORE_NAME),
    'store_logo' => get_setting('store_logo', ''),
    'store_address' => get_setting('store_address', ''),
    'store_phone' => get_setting('store_phone', ''),
    'store_email' => get_setting('store_email', ''),
    'default_tax_rate' => (float)get_setting('default_tax_rate', 0.09)
];
$store_logo_url_invoice_form = '';
if (!empty($store_settings_display_inv_form['store_logo']) && file_exists(UPLOAD_DIR . $store_settings_display_inv_form['store_logo'])) {
    $store_logo_url_invoice_form = $app_base_path . '/' . UPLOAD_DIR_PUBLIC_PATH . htmlspecialchars($store_settings_display_inv_form['store_logo']);
}

if ($id !== null) { // Editing existing invoice
    $is_edit_mode = true;
    $invoice_id_to_edit = $id;
    
    $stmt_invoice = $db->prepare("SELECT * FROM invoices WHERE id = :id");
    $stmt_invoice->bindParam(':id', $invoice_id_to_edit, PDO::PARAM_INT);
    $stmt_invoice->execute();
    $invoice = $stmt_invoice->fetch(PDO::FETCH_ASSOC);

    if ($invoice) {
        $page_title = "ویرایش فاکتور: " . htmlspecialchars($invoice['invoice_number']);
        $form_action_name = 'edit_invoice';
        $submit_button_text = "ذخیره تغییرات فاکتور";
        $submit_button_icon = "save";
        
        $invoice_data = array_merge($invoice_data, $invoice); 
        $invoice_data['date'] = !empty($invoice['date']) ? date('Y/m/d', strtotime($invoice['date'])) : date('Y/m/d');
        $invoice_data['due_date'] = !empty($invoice['due_date']) ? date('Y/m/d', strtotime($invoice['due_date'])) : '';
        $invoice_data['discount'] = $invoice['discount'] ?? 0;

        $stmt_items = $db->prepare("SELECT ii.*, p.product_id as product_code FROM invoice_items ii LEFT JOIN products p ON ii.product_id = p.id WHERE ii.invoice_id = :invoice_id ORDER BY ii.id ASC");
        $stmt_items->bindParam(':invoice_id', $invoice_id_to_edit, PDO::PARAM_INT);
        $stmt_items->execute();
        $invoice_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $_SESSION['action_message'] = ['type' => 'error', 'text' => 'فاکتور مورد نظر برای ویرایش یافت نشد.'];
        header('Location: ' . generate_url('invoices_list'));
        exit;
    }
}

if (isset($_SESSION['form_data']['invoice_form'])) {
    $session_form_data = $_SESSION['form_data']['invoice_form'];
    if ((!$is_edit_mode && !isset($session_form_data['invoice_db_id'])) || 
        ($is_edit_mode && isset($session_form_data['invoice_db_id']) && $session_form_data['invoice_db_id'] == $id)) {
        
        $invoice_data['customer_id'] = $session_form_data['customer_id'] ?? $invoice_data['customer_id'];
        $invoice_data['date'] = $session_form_data['invoice_date'] ?? $invoice_data['date'];
        $invoice_data['due_date'] = $session_form_data['due_date'] ?? $invoice_data['due_date'];
        $invoice_data['type'] = $session_form_data['invoice_type'] ?? $invoice_data['type'];
        $invoice_data['status'] = $session_form_data['status'] ?? $invoice_data['status'];
        $invoice_data['payment_method'] = $session_form_data['payment_method'] ?? $invoice_data['payment_method'];
        $invoice_data['notes'] = $session_form_data['notes'] ?? $invoice_data['notes'];
        $invoice_data['discount'] = $session_form_data['discount'] ?? $invoice_data['discount'];
        
        if (isset($session_form_data['items']) && is_array($session_form_data['items'])) {
            $invoice_items = []; 
            foreach($session_form_data['items'] as $s_item_idx => $s_item) {
                $item_detail = [
                    'product_id' => $s_item['product_id'] ?? '',
                    'product_name_manual' => $s_item['product_name_manual'] ?? '',
                    'quantity' => $s_item['quantity'] ?? 1,
                    'unit_price' => $s_item['unit_price'] ?? 0,
                ];
                // If product_id is set, try to find its code for prefill
                if ($item_detail['product_id']) {
                    foreach($products as $p_opt) {
                        if ($p_opt['id'] == $item_detail['product_id']) {
                            $item_detail['product_code'] = $p_opt['product_code'];
                            break;
                        }
                    }
                }
                $invoice_items[] = $item_detail;
            }
        }
    }
    unset($_SESSION['form_data']['invoice_form']);
}

if (empty($invoice_items)) { 
    $invoice_items[] = ['product_id' => '', 'product_name_manual' => '', 'product_code' => '', 'quantity' => 1, 'unit_price' => 0, 'total_price' => 0];
}

?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
        <h2 class="text-xl lg:text-2xl font-semibold text-slate-800"><?php echo $page_title; ?></h2>
        <a href="<?php echo generate_url('invoices_list'); ?>" class="bg-blue-600 flex text-white px-6 py-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
            <i data-lucide="arrow-right" class="icon-sm ml-2"></i> بازگشت به لیست فاکتورها
        </a>
    </div>

    <form id="invoiceForm" action="<?php echo generate_url($form_action_name, [], true); ?>" method="POST" class="bg-white p-6 md:p-8 rounded-xl shadow-lg space-y-8">
        <?php if ($is_edit_mode && $invoice_data['id']): ?>
            <input type="hidden" name="invoice_db_id" value="<?php echo $invoice_data['id']; ?>">
        <?php endif; ?>

        <section class="border-b border-slate-200 pb-6 mb-6">
            <div class="flex flex-col sm:flex-row justify-between items-start gap-4">
                <div class="flex-shrink-0">
                    <?php if ($store_logo_url_invoice_form): ?>
                        <img src="<?php echo $store_logo_url_invoice_form; ?>" alt="لوگو فروشگاه" class="h-12 sm:h-14 mb-2 object-contain">
                    <?php endif; ?>
                    <h3 class="text-lg sm:text-xl font-semibold text-slate-700"><?php echo htmlspecialchars($store_settings_display_inv_form['store_name']); ?></h3>
                    <?php if(!empty($store_settings_display_inv_form['store_address'])): ?><p class="text-xs text-slate-500"><?php echo htmlspecialchars($store_settings_display_inv_form['store_address']); ?></p><?php endif; ?>
                    <?php if(!empty($store_settings_display_inv_form['store_phone'])): ?><p class="text-xs text-slate-500">شماره تماس: <?php echo htmlspecialchars($store_settings_display_inv_form['store_phone']); ?></p><?php endif; ?>
                </div>
                <div class="text-left sm:text-right w-full sm:w-auto mt-2 sm:mt-0">
                    <h3 class="text-xl sm:text-2xl font-bold text-sky-600">
                        <?php echo $is_edit_mode ? 'ویرایش فاکتور' : 'فاکتور جدید'; ?>
                        <span id="invoiceTypeDisplayHeader" class="font-normal text-slate-700">(<?php echo htmlspecialchars($invoice_data['type']); ?>)</span>
                    </h3>
                    <p class="text-slate-600 text-sm">شماره فاکتور: <span class="font-semibold font-mono"><?php echo htmlspecialchars($invoice_data['invoice_number']); ?></span></p>
                    <?php if($is_edit_mode): ?>
                         <p class="text-slate-600 text-sm mt-1">وضعیت فعلی: <span class="font-semibold badge <?php
                                        switch ($invoice_data['status']) {
                                            case 'پرداخت شده': echo 'badge-green'; break;
                                            case 'در انتظار پرداخت': echo 'badge-yellow'; break;
                                            case 'لغو شده': echo 'badge-red'; break;
                                            case 'پیش نویس': echo 'badge-indigo'; break;
                                            default: echo 'badge-gray'; break;
                                        }
                                    ?>"><?php echo htmlspecialchars($invoice_data['status']); ?></span></p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-5">
            <div>
                <label for="customer_id" class="form-label">مشتری <span class="text-red-500">*</span></label>
                <select id="customer_id" name="customer_id" class="form-select" required>
                    <option value="">--- انتخاب مشتری ---</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?php echo $customer['id']; ?>" <?php echo ($invoice_data['customer_id'] == $customer['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($customer['name']) . ($customer['customer_id'] ? ' (' . htmlspecialchars($customer['customer_id']) . ')' : ''); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="invoice_type" class="form-label">نوع فاکتور</label>
                <select id="invoice_type" name="invoice_type" class="form-select">
                    <option value="فروش" <?php echo ($invoice_data['type'] === 'فروش') ? 'selected' : ''; ?>>فروش</option>
                    <option value="خرید" <?php echo ($invoice_data['type'] === 'خرید') ? 'selected' : ''; ?>>خرید</option>
                </select>
            </div>
            <div>
                <label for="invoice_date" class="form-label">تاریخ صدور <span class="text-red-500">*</span></label>
                <input type="text" id="invoice_date" name="invoice_date" class="form-input" value="<?php echo htmlspecialchars($invoice_data['date']); ?>" required placeholder="مثال: 1403/03/01">
            </div>
            <div>
                <label for="due_date" class="form-label">تاریخ سررسید</label>
                <input type="text" id="due_date" name="due_date" class="form-input" value="<?php echo htmlspecialchars($invoice_data['due_date']); ?>" placeholder="مثال: 1403/03/15">
            </div>
            <div>
                <label for="payment_method" class="form-label">روش پرداخت</label>
                <select id="payment_method" name="payment_method" class="form-select">
                    <option value="نقدی" <?php echo ($invoice_data['payment_method'] === 'نقدی') ? 'selected' : ''; ?>>نقدی</option>
                    <option value="کارت به کارت" <?php echo ($invoice_data['payment_method'] === 'کارت به کارت') ? 'selected' : ''; ?>>کارت به کارت</option>
                    <option value="چک" <?php echo ($invoice_data['payment_method'] === 'چک') ? 'selected' : ''; ?>>چک</option>
                    <option value="آنلاین" <?php echo ($invoice_data['payment_method'] === 'آنلاین') ? 'selected' : ''; ?>>آنلاین</option>
                    <option value="اعتباری" <?php echo ($invoice_data['payment_method'] === 'اعتباری') ? 'selected' : ''; ?>>اعتباری</option>
                </select>
            </div>
            <div>
                <label for="status" class="form-label">وضعیت فاکتور</label>
                <select id="status" name="status" class="form-select">
                    <option value="پیش نویس" <?php echo ($invoice_data['status'] === 'پیش نویس') ? 'selected' : ''; ?>>پیش نویس</option>
                    <option value="در انتظار پرداخت" <?php echo ($invoice_data['status'] === 'در انتظار پرداخت') ? 'selected' : ''; ?>>در انتظار پرداخت</option>
                    <option value="پرداخت شده" <?php echo ($invoice_data['status'] === 'پرداخت شده') ? 'selected' : ''; ?>>پرداخت شده</option>
                    <option value="لغو شده" <?php echo ($invoice_data['status'] === 'لغو شده') ? 'selected' : ''; ?>>لغو شده</option>
                </select>
            </div>
        </section>

        <section class="space-y-3 pt-6 border-t border-slate-200 mt-6">
            <h3 class="text-lg font-semibold text-slate-700">اقلام فاکتور</h3>
            <div class="overflow-x-auto border border-slate-200 rounded-lg shadow-sm">
                <table class="w-full min-w-[800px]"> <?php /* Increased min-width for more columns */ ?>
                    <thead class="bg-slate-100">
                        <tr>
                            <th class="px-3 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider w-[30%]">محصول/خدمت</th>
                            <th class="px-3 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider w-[15%]">شناسه کالا</th>
                            <th class="px-3 py-3 text-center text-xs font-semibold text-slate-600 uppercase tracking-wider w-[10%]">تعداد</th>
                            <th class="px-3 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider w-[15%]">قیمت واحد</th>
                            <th class="px-3 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider w-[15%]">مجموع قلم</th>
                            <th class="px-3 py-3 text-center text-xs font-semibold text-slate-600 uppercase tracking-wider w-[10%]">حذف</th>
                        </tr>
                    </thead>
                    <tbody id="invoiceItemsTableBody" class="bg-white divide-y divide-slate-200">
                        <?php foreach ($invoice_items as $index => $item): ?>
                        <tr class="invoice-item-row group hover:bg-slate-50">
                            <td class="px-2 py-2 whitespace-nowrap">
                                <select name="items[<?php echo $index; ?>][product_id]" class="form-select item-product-select text-sm !py-2 h-auto" data-index="<?php echo $index; ?>">
                                    <option value="">--- انتخاب محصول ---</option>
                                    <?php foreach ($products as $product_opt): ?>
                                        <option value="<?php echo $product_opt['id']; ?>" 
                                                data-price-sell="<?php echo $product_opt['sell_price']; ?>"
                                                data-price-buy="<?php echo $product_opt['buy_price'] ?? 0; ?>"
                                                data-name="<?php echo htmlspecialchars($product_opt['name']); ?>"
                                                data-code="<?php echo htmlspecialchars($product_opt['product_code'] ?? ''); ?>"
                                                <?php echo (isset($item['product_id']) && $item['product_id'] == $product_opt['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($product_opt['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="custom">--- قلم سفارشی ---</option>
                                </select>
                                <input type="text" name="items[<?php echo $index; ?>][product_name_manual]" class="form-input item-product-name-manual mt-1 text-sm !py-2 h-auto <?php echo (isset($item['product_id']) && !empty($item['product_id'])) || empty($item['product_name']) ? 'hidden' : ''; ?>" placeholder="نام محصول/خدمت سفارشی" value="<?php echo htmlspecialchars($item['product_name'] ?? ($item['product_name_manual'] ?? '')); ?>">
                            </td>
                             <td class="px-2 py-2 whitespace-nowrap">
                                <input type="text" readonly class="form-input item-product-code text-sm !py-2 h-auto bg-slate-100 cursor-default" value="<?php echo htmlspecialchars($item['product_code'] ?? ''); ?>" placeholder="شناسه">
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap">
                                <input type="number" name="items[<?php echo $index; ?>][quantity]" class="form-input item-quantity text-sm !py-2 h-auto w-20 text-center" value="<?php echo htmlspecialchars($item['quantity'] ?? 1); ?>" min="1" required data-index="<?php echo $index; ?>">
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap">
                                <input type="number" step="any" name="items[<?php echo $index; ?>][unit_price]" class="form-input item-unit-price text-sm !py-2 h-auto w-32" value="<?php echo htmlspecialchars($item['unit_price'] ?? 0); ?>" required data-index="<?php echo $index; ?>" placeholder="قیمت">
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-sm text-slate-700 item-total-price text-left font-medium">
                                <?php echo format_currency_php( (float)($item['quantity'] ?? 1) * (float)($item['unit_price'] ?? 0) ); ?>
                            </td>
                            <td class="px-2 py-2 whitespace-nowrap text-center">
                                <button type="button" class="btn-delete-item p-2 text-red-500 hover:text-red-700 hover:bg-red-100 rounded-md" title="حذف این قلم">
                                    <i data-lucide="trash-2" class="icon-md"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="button" id="btnAddInvoiceItem" class="bg-blue-600 flex text-white px-6 py-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                <i data-lucide="plus" class="icon-sm ml-1.5"></i> افزودن ردیف جدید
            </button>
        </section>

        <section class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-6 border-t border-slate-200 mt-4">
            <div class="md:col-span-2">
                <label for="notes" class="form-label">یادداشت ها / توضیحات فاکتور:</label>
                <textarea id="notes" name="notes" rows="4" class="form-textarea text-sm" placeholder="مثال: شرایط پرداخت، اطلاعات گارانتی، و ..."><?php echo htmlspecialchars($invoice_data['notes']); ?></textarea>
            </div>
            <div class="space-y-2.5 text-sm bg-slate-50 p-4 rounded-lg shadow border border-slate-200">
                <div class="flex justify-between items-center">
                    <span class="text-slate-600">جمع جزء (<?php echo DEFAULT_CURRENCY_SYMBOL; ?>):</span> 
                    <span id="invoiceSubtotal" class="font-semibold text-slate-800"><?php echo format_currency_php(0); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <label for="discount" class="text-slate-600">تخفیف (<?php echo DEFAULT_CURRENCY_SYMBOL; ?>):</label>
                    <input type="number" step="any" id="discount" name="discount" value="<?php echo htmlspecialchars($invoice_data['discount']); ?>" class="form-input text-sm w-28 text-left !py-1.5 h-auto" placeholder="0" min="0">
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-600">مالیات (<?php echo ($store_settings_display_inv_form['default_tax_rate'] * 100); ?>٪) (<?php echo DEFAULT_CURRENCY_SYMBOL; ?>):</span> 
                    <span id="invoiceTaxAmount" class="font-semibold text-slate-800"><?php echo format_currency_php(0); ?></span>
                </div>
                <hr class="my-2 border-slate-200"/>
                <div class="flex justify-between font-bold text-base md:text-lg">
                    <span class="text-slate-700">مبلغ نهایی (<?php echo DEFAULT_CURRENCY_SYMBOL; ?>):</span> 
                    <span id="invoiceFinalAmount" class="text-sky-600"><?php echo format_currency_php(0); ?></span>
                </div>
            </div>
        </section>

        <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-3 rtl:sm:space-x-reverse pt-6 border-t border-slate-200 mt-6">
            <a href="<?php echo $is_edit_mode ? generate_url('invoice_details', ['id' => $invoice_data['id']]) : generate_url('invoices_list'); ?>" class="bg-red-600 flex text-white px-6 py-2 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">لغو</a>
            <button type="submit" class="bg-blue-600 flex text-white px-6 py-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                <i data-lucide="<?php echo $submit_button_icon; ?>" class="icon-md ml-2"></i>
                <?php echo $submit_button_text; ?>
            </button>
        </div>
    </form>
</div>

<script>
    // JavaScript for dynamic invoice items and calculations (from previous version, ensure it's compatible)
    const productsDataInvoice = <?php echo $products_json; ?>;
    const defaultTaxRateInvoice = <?php echo $store_settings_display_inv_form['default_tax_rate']; ?>;
    let invoiceItemIndex = <?php echo count($invoice_items); ?>; 

    document.addEventListener('DOMContentLoaded', function () {
        const invoiceItemsTableBody = document.getElementById('invoiceItemsTableBody');
        const btnAddInvoiceItem = document.getElementById('btnAddInvoiceItem');
        const invoiceTypeSelectForm = document.getElementById('invoice_type');
        const invoiceTypeDisplayHeader = document.getElementById('invoiceTypeDisplayHeader');

        function updateInvoiceTypeDisplayOnForm() {
            if (invoiceTypeDisplayHeader && invoiceTypeSelectForm) {
                invoiceTypeDisplayHeader.textContent = `(${invoiceTypeSelectForm.value})`;
            }
            updateAllItemPricesBasedOnInvoiceTypeForm();
            calculateTotalsInvoiceForm();
        }
        
        if (invoiceTypeSelectForm) {
            invoiceTypeSelectForm.addEventListener('change', updateInvoiceTypeDisplayOnForm);
        }
        
        function updateAllItemPricesBasedOnInvoiceTypeForm() {
            document.querySelectorAll('.invoice-item-row').forEach(row => {
                const productSelect = row.querySelector('.item-product-select');
                if (productSelect && productSelect.value && productSelect.value !== 'custom') {
                    const selectedOption = productSelect.options[productSelect.selectedIndex];
                    const unitPriceInput = row.querySelector('.item-unit-price');
                    const currentInvoiceType = document.getElementById('invoice_type').value;
                    
                    unitPriceInput.value = (currentInvoiceType === 'فروش') ? (selectedOption.dataset.priceSell || 0) : (selectedOption.dataset.priceBuy || 0);
                    updateRowTotalInvoiceForm(productSelect.dataset.index);
                }
            });
        }

        function addInvoiceItemRowJS(itemData = null) {
            const newRow = document.createElement('tr');
            newRow.classList.add('invoice-item-row', 'group', 'hover:bg-slate-50/50');
            const currentIndex = invoiceItemIndex++;

            let productOptionsHTML = '<option value="">--- انتخاب محصول ---</option>';
            productsDataInvoice.forEach(p => {
                productOptionsHTML += `<option value="${p.id}" data-price-sell="${p.sell_price}" data-price-buy="${p.buy_price || 0}" data-name="${escapeHtmlJS(p.name)}" data-code="${escapeHtmlJS(p.product_code || '')}" ${itemData && itemData.product_id == p.id ? 'selected' : ''}>${escapeHtmlJS(p.name)}</option>`;
            });
            productOptionsHTML += '<option value="custom">--- قلم سفارشی ---</option>';

            newRow.innerHTML = `
                <td class="px-2 py-2 whitespace-nowrap">
                    <select name="items[${currentIndex}][product_id]" class="form-select item-product-select text-sm !py-2 h-auto" data-index="${currentIndex}">${productOptionsHTML}</select>
                    <input type="text" name="items[${currentIndex}][product_name_manual]" class="form-input item-product-name-manual mt-1 text-sm !py-2 h-auto ${itemData && !itemData.product_id && (itemData.product_name || itemData.product_name_manual) ? '' : 'hidden'}" placeholder="نام محصول/خدمت سفارشی" value="${escapeHtmlJS(itemData?.product_name_manual || itemData?.product_name || '')}">
                </td>
                <td class="px-2 py-2 whitespace-nowrap">
                    <input type="text" readonly class="form-input item-product-code text-sm !py-2 h-auto bg-slate-100 cursor-default" value="${escapeHtmlJS(itemData?.product_code || '')}" placeholder="شناسه">
                </td>
                <td class="px-2 py-2 whitespace-nowrap">
                    <input type="number" name="items[${currentIndex}][quantity]" class="form-input item-quantity text-sm !py-2 h-auto w-20 text-center" value="${itemData?.quantity || 1}" min="1" required data-index="${currentIndex}">
                </td>
                <td class="px-2 py-2 whitespace-nowrap">
                    <input type="number" step="any" name="items[${currentIndex}][unit_price]" class="form-input item-unit-price text-sm !py-2 h-auto w-32" value="${itemData?.unit_price || 0}" required data-index="${currentIndex}" placeholder="قیمت">
                </td>
                <td class="px-2 py-2 whitespace-nowrap text-sm text-slate-700 item-total-price text-left font-medium">
                    ${formatCurrencyJSInvoice( (itemData?.quantity || 1) * (itemData?.unit_price || 0) )}
                </td>
                <td class="px-2 py-2 whitespace-nowrap text-center">
                    <button type="button" class="btn-delete-item p-2 text-red-500 hover:text-red-700 hover:bg-red-100 rounded-md" title="حذف این قلم"><i data-lucide="trash-2" class="icon-md"></i></button>
                </td>
            `;
            invoiceItemsTableBody.appendChild(newRow);
            if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [newRow.querySelector('.btn-delete-item i')] });
            attachEventListenersToRowInvoiceForm(newRow, currentIndex);
            
            const newProductSelect = newRow.querySelector('.item-product-select');
            if (itemData) {
                if (!itemData.product_id && (itemData.product_name || itemData.product_name_manual) ) { // Custom item
                     newProductSelect.value = 'custom';
                     // Ensure manual input is visible
                     newRow.querySelector('.item-product-name-manual').classList.remove('hidden');

                } else if (itemData.product_id) { // Existing product
                    newProductSelect.value = itemData.product_id; 
                    const event = new Event('change', { bubbles: true });
                    newProductSelect.dispatchEvent(event); // Trigger change to populate code/price
                }
            }
        }

        function attachEventListenersToRowInvoiceForm(rowElement, index) {
            const productSelect = rowElement.querySelector('.item-product-select');
            const productNameManualInput = rowElement.querySelector('.item-product-name-manual');
            const productCodeInput = rowElement.querySelector('.item-product-code');
            const quantityInput = rowElement.querySelector('.item-quantity');
            const unitPriceInput = rowElement.querySelector('.item-unit-price');
            const deleteButton = rowElement.querySelector('.btn-delete-item');

            productSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (this.value === 'custom') {
                    productNameManualInput.classList.remove('hidden');
                    productNameManualInput.value = '';
                    productNameManualInput.focus();
                    unitPriceInput.value = 0;
                    productCodeInput.value = '';
                } else if (this.value && selectedOption) { 
                    productNameManualInput.classList.add('hidden');
                    productNameManualInput.value = ''; // Clear manual name
                    const currentInvoiceType = document.getElementById('invoice_type').value;
                    unitPriceInput.value = (currentInvoiceType === 'فروش') ? (selectedOption.dataset.priceSell || 0) : (selectedOption.dataset.priceBuy || 0);
                    productCodeInput.value = selectedOption.dataset.code || '';
                } else { 
                     productNameManualInput.classList.add('hidden');
                     unitPriceInput.value = 0;
                     productCodeInput.value = '';
                }
                updateRowTotalInvoiceForm(index);
            });

            quantityInput.addEventListener('input', () => updateRowTotalInvoiceForm(index));
            unitPriceInput.addEventListener('input', () => updateRowTotalInvoiceForm(index));
            
            deleteButton.addEventListener('click', function() {
                if (invoiceItemsTableBody.querySelectorAll('tr.invoice-item-row').length > 1) {
                    rowElement.remove();
                    calculateTotalsInvoiceForm();
                } else {
                    // Clear the first row instead of removing
                    const firstRow = invoiceItemsTableBody.querySelector('tr.invoice-item-row');
                    if(firstRow) {
                        firstRow.querySelector('.item-product-select').value = '';
                        firstRow.querySelector('.item-product-name-manual').value = '';
                        firstRow.querySelector('.item-product-name-manual').classList.add('hidden');
                        firstRow.querySelector('.item-product-code').value = '';
                        firstRow.querySelector('.item-quantity').value = 1;
                        firstRow.querySelector('.item-unit-price').value = 0;
                        updateRowTotalInvoiceForm(firstRow.querySelector('.item-product-select').dataset.index);
                    }
                    // alert('حداقل یک قلم باید در فاکتور وجود داشته باشد.'); // Or clear first row
                }
            });
        }
        
        function updateRowTotalInvoiceForm(index) {
            const row = invoiceItemsTableBody.querySelector(`.item-product-select[data-index="${index}"]`)?.closest('tr.invoice-item-row');
            if (!row) return;
            const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
            const unitPrice = parseFloat(row.querySelector('.item-unit-price').value) || 0;
            const total = quantity * unitPrice;
            row.querySelector('.item-total-price').textContent = formatCurrencyJSInvoice(total);
            calculateTotalsInvoiceForm();
        }

        function calculateTotalsInvoiceForm() {
            let subtotal = 0;
            invoiceItemsTableBody.querySelectorAll('tr.invoice-item-row').forEach(row => {
                const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
                const unitPrice = parseFloat(row.querySelector('.item-unit-price').value) || 0;
                subtotal += quantity * unitPrice;
            });

            const discount = parseFloat(document.getElementById('discount').value) || 0;
            const amountAfterDiscount = subtotal - discount;
            const taxAmount = amountAfterDiscount * defaultTaxRateInvoice;
            const finalAmount = amountAfterDiscount + taxAmount;

            document.getElementById('invoiceSubtotal').textContent = formatCurrencyJSInvoice(subtotal);
            document.getElementById('invoiceTaxAmount').textContent = formatCurrencyJSInvoice(taxAmount);
            document.getElementById('invoiceFinalAmount').textContent = formatCurrencyJSInvoice(finalAmount);
        }
        
        function formatCurrencyJSInvoice(amount) {
            return new Intl.NumberFormat('fa-IR', { /*style: 'currency', currency: 'IRR',*/ minimumFractionDigits: 0 }).format(amount) + ' <?php echo DEFAULT_CURRENCY_SYMBOL; ?>';
        }
        function escapeHtmlJS(unsafe) {
            if (unsafe === null || typeof unsafe === 'undefined') return '';
            return unsafe.toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        document.querySelectorAll('.invoice-item-row').forEach((row) => {
            const productSelect = row.querySelector('.item-product-select');
            attachEventListenersToRowInvoiceForm(row, productSelect.dataset.index);
            if (productSelect.value) { 
                const event = new Event('change', { bubbles: true });
                productSelect.dispatchEvent(event);
            }
        });

        if (btnAddInvoiceItem) {
            btnAddInvoiceItem.addEventListener('click', () => addInvoiceItemRowJS());
        }
        const discountInput = document.getElementById('discount');
        if(discountInput) discountInput.addEventListener('input', calculateTotalsInvoiceForm);
        
        calculateTotalsInvoiceForm(); 
        updateInvoiceTypeDisplayOnForm(); 
    });
</script>
