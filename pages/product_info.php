<?php
// /pages/product_info.php
// Displays detailed information for a single product.

if (!defined('DB_PATH')) { // Should be defined in index.php
    die("Access denied. This page should be accessed via index.php");
}

// db.php and its functions are included via index.php
$db = getDB();
// $app_base_path, $id (product's DB ID) are available from index.php/header.php
global $app_base_path, $id; 

$product_id_to_view = $id; // $id is the product's database ID from the URL

if ($product_id_to_view === null || $product_id_to_view <= 0) {
    $_SESSION['action_message'] = ['type' => 'error', 'text' => 'شناسه محصول نامعتبر است.'];
    header('Location: ' . generate_url('products_list'));
    exit;
}

// Fetch product details
$stmt_product = $db->prepare("SELECT * FROM products WHERE id = :id");
$stmt_product->bindParam(':id', $product_id_to_view, PDO::PARAM_INT);
$stmt_product->execute();
$product = $stmt_product->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    $_SESSION['action_message'] = ['type' => 'error', 'text' => 'محصول یافت نشد.'];
    header('Location: ' . generate_url('products_list'));
    exit;
}

$product_image_url_info = 'https://placehold.co/400x400/E0E7FF/4338CA?text=' . strtoupper(mb_substr(htmlspecialchars($product['name']), 0, 1, 'UTF-8'));
if (!empty($product['image']) && file_exists(UPLOAD_DIR . $product['image'])) {
    $product_image_path_info = UPLOAD_DIR_PUBLIC_PATH . htmlspecialchars($product['image']);
    $product_image_url_info = $app_base_path . '/' . $product_image_path_info;
}

?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
        <h2 class="text-xl lg:text-2xl font-semibold text-slate-800">
            اطلاعات محصول: <span class="text-sky-700"><?php echo htmlspecialchars($product['name']); ?></span>
        </h2>
        <a href="<?php echo generate_url('products_list'); ?>" class="bg-blue-600 flex text-white px-6 py-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
            <i data-lucide="arrow-right" class="icon-sm ml-2"></i> بازگشت به لیست محصولات
        </a>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
            <div class="lg:col-span-1 space-y-6">
                <div class="border border-slate-200 rounded-lg p-3 bg-slate-50 shadow-sm">
                    <img src="<?php echo $product_image_url_info; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-auto max-h-[350px] object-contain rounded-md">
                </div>
                <div class="space-y-3">
                    <button class="bg-yellow-600 flex text-white px-6 py-2 rounded-lg hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2">
                     <a href="<?php echo generate_url('product_form', ['id' => $product['id']]); ?>" class="btn btn-warning w-full flex items-center justify-center">
                        <i data-lucide="edit-3" class="icon-md ml-2"></i> ویرایش محصول
                    </a>
                    </button>
                    <form action="<?php echo generate_url('delete_product', [], true); ?>" method="POST" class="w-full" onsubmit="return confirmDelete(event, 'آیا از حذف محصول \'<?php echo htmlspecialchars(addslashes($product['name'])); ?>\' مطمئن هستید؟');">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <button type="submit" class="bg-red-600 flex text-white px-6 py-2 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                            <i data-lucide="trash-2" class="icon-md ml-2"></i> حذف محصول
                        </button>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-5">
                <div class="pb-3 border-b border-slate-200">
                    <h3 class="text-2xl lg:text-3xl font-bold text-slate-800 mb-1"><?php echo htmlspecialchars($product['name']); ?></h3>
                    <p class="text-sm text-slate-500">شناسه محصول: <span class="font-mono"><?php echo htmlspecialchars($product['product_id'] ?: '-'); ?></span></p>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <p class="form-label text-slate-500">دسته بندی:</p>
                        <p class="text-slate-700 font-medium text-base"><?php echo htmlspecialchars($product['category'] ?: '-'); ?></p>
                    </div>
                    <div>
                        <p class="form-label text-slate-500">وضعیت:</p>
                        <span class="badge <?php echo ($product['status'] === 'فعال') ? 'badge-green' : 'badge-red'; ?> text-base py-1 px-3">
                            <?php echo htmlspecialchars($product['status']); ?>
                        </span>
                    </div>
                     <div>
                        <p class="form-label text-slate-500">قیمت فروش:</p>
                        <p class="text-sky-600 font-semibold text-xl"><?php echo format_currency_php($product['sell_price']); ?></p>
                    </div>
                    <div>
                        <p class="form-label text-slate-500">قیمت خرید:</p>
                        <p class="text-slate-700 font-medium text-base"><?php echo $product['buy_price'] !== null ? format_currency_php($product['buy_price']) : '-'; ?></p>
                    </div>
                    <div>
                        <p class="form-label text-slate-500">موجودی انبار:</p>
                        <p class="font-semibold text-base <?php 
                            if ($product['inventory'] > 10) echo 'text-green-600';
                            elseif ($product['inventory'] > 0) echo 'text-yellow-600';
                            else echo 'text-red-600';
                        ?>">
                            <?php echo htmlspecialchars($product['inventory']); ?> عدد
                        </p>
                    </div>
                </div>

                <?php if(!empty($product['description'])): ?>
                <div class="pt-4 border-t border-slate-200 mt-3">
                    <p class="form-label text-slate-500 mb-1">توضیحات محصول:</p>
                    <div class="text-slate-700 leading-relaxed whitespace-pre-line prose prose-sm max-w-none p-3 bg-slate-50 rounded-md border border-slate-200">
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                 <div class="pt-4 border-t border-slate-200 mt-3 text-xs text-slate-500">
                    <p>تاریخ ایجاد: <?php echo htmlspecialchars(date("Y/m/d H:i", strtotime($product['created_at']))); ?></p>
                    <p>آخرین بروزرسانی: <?php echo htmlspecialchars(date("Y/m/d H:i", strtotime($product['updated_at']))); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
