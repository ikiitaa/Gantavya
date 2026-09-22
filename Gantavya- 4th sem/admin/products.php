<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
$adminUser = require_admin();
$pageTitle = 'Products';
$types = ['vehicle', 'trek', 'intl'];

if (request_is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    $productId = trim((string) ($_POST['product_id'] ?? ''));

    if ($action === 'toggle') {
        $stmt = $pdo->prepare('UPDATE products SET is_active = IF(is_active = 1, 0, 1), updated_at = NOW() WHERE id = ?');
        $stmt->execute([$productId]);
        flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'Product availability updated.' : 'Product not found.');
        redirect('admin/products.php', 303);
    }

    $type = in_array($_POST['type'] ?? '', $types, true) ? $_POST['type'] : '';
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $itinerary = trim((string) ($_POST['itinerary'] ?? ''));
    $basePrice = filter_var($_POST['base_price'] ?? null, FILTER_VALIDATE_FLOAT);
    $maxPax = filter_var($_POST['max_pax'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 200]]);
    $existingImage = trim((string) ($_POST['existing_image'] ?? ''));
    $errors = [];
    if ($type === '') $errors[] = 'Select a service type.';
    if (strlen($title) < 2 || strlen($title) > 150) $errors[] = 'Enter a valid title.';
    if (strlen($description) < 10 || strlen($description) > 3000) $errors[] = 'Description must contain 10 to 3,000 characters.';
    if ($basePrice === false || $basePrice < 10) $errors[] = 'Base price must be at least NPR 10.';
    if (!$maxPax) $errors[] = 'Maximum travelers must be between 1 and 200.';

    $imagePath = $existingImage;
    if (!empty($_FILES['image']['name'])) {
        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload failed.';
        } elseif ((int) $_FILES['image']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Image must be smaller than 5 MB.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['image']['tmp_name']);
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($extensions[$mime])) {
                $errors[] = 'Upload a JPG, PNG, or WebP image.';
            } else {
                $filename = bin2hex(random_bytes(10)) . '.' . $extensions[$mime];
                $target = dirname(__DIR__) . '/images/uploads/' . $filename;
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                    $errors[] = 'The image could not be saved. Check folder permissions.';
                } else {
                    $imagePath = 'images/uploads/' . $filename;
                }
            }
        }
    }
    if ($imagePath === '') $errors[] = 'Upload a product image.';

    if ($errors) {
        flash('error', implode(' ', $errors));
    } else {
        try {
            if ($productId === '') {
                $prefix = $type === 'vehicle' ? 'v' : ($type === 'trek' ? 't' : 'i');
                $idStmt = $pdo->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(id, 2) AS UNSIGNED)), 0) + 1 FROM products WHERE type = ?");
                $idStmt->execute([$type]);
                $productId = $prefix . (int) $idStmt->fetchColumn();
                $stmt = $pdo->prepare('INSERT INTO products (id, type, title, description, itinerary, base_price, image, max_pax, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)');
                $stmt->execute([$productId, $type, $title, $description, $itinerary, $basePrice, $imagePath, $maxPax]);
                flash('success', 'Product created.');
            } else {
                $stmt = $pdo->prepare('UPDATE products SET type = ?, title = ?, description = ?, itinerary = ?, base_price = ?, image = ?, max_pax = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$type, $title, $description, $itinerary, $basePrice, $imagePath, $maxPax, $productId]);
                flash('success', 'Product updated.');
            }
        } catch (Throwable $exception) {
            error_log('Product save failed: ' . $exception->getMessage());
            flash('error', 'Product could not be saved.');
        }
    }
    redirect('admin/products.php' . ($productId !== '' ? '?edit=' . rawurlencode($productId) : ''), 303);
}

$editId = trim((string) ($_GET['edit'] ?? ''));
$editProduct = null;
if ($editId !== '') {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$editId]);
    $editProduct = $stmt->fetch() ?: null;
}
$products = $pdo->query('SELECT * FROM products ORDER BY type, id')->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<section class="admin-panel">
    <div class="panel-heading"><div><p>Catalogue</p><h2><?= $editProduct ? 'Edit ' . e($editProduct['title']) : 'Add a service' ?></h2></div><?php if ($editProduct): ?><a href="products.php">Add new instead</a><?php endif; ?></div>
    <form method="post" enctype="multipart/form-data" class="product-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="product_id" value="<?= e($editProduct['id'] ?? '') ?>"><input type="hidden" name="existing_image" value="<?= e($editProduct['image'] ?? '') ?>">
        <label>Type<select name="type" required><?php foreach ($types as $type): ?><option value="<?= e($type) ?>" <?= ($editProduct['type'] ?? '') === $type ? 'selected' : '' ?>><?= e($type === 'intl' ? 'International tour' : ucfirst($type)) ?></option><?php endforeach; ?></select></label>
        <label>Title<input type="text" name="title" maxlength="150" value="<?= e($editProduct['title'] ?? '') ?>" required></label>
        <label>Base price (NPR)<input type="number" name="base_price" min="10" step="0.01" value="<?= e($editProduct['base_price'] ?? '') ?>" required></label>
        <label>Maximum travelers<input type="number" name="max_pax" min="1" max="200" value="<?= e($editProduct['max_pax'] ?? 1) ?>" required></label>
        <label class="wide">Description<textarea name="description" rows="3" maxlength="3000" required><?= e($editProduct['description'] ?? '') ?></textarea></label>
        <label class="wide">
    Day-wise Itinerary

    <textarea
        name="itinerary"
        rows="12"
    ><?= e($editProduct['itinerary'] ?? '') ?></textarea>

</label>
        <label class="wide">Product image <?= $editProduct ? '<small>(leave blank to keep current image)</small>' : '' ?><input type="file" name="image" accept="image/jpeg,image/png,image/webp" <?= $editProduct ? '' : 'required' ?>></label>
        <button class="btn btn-primary" type="submit"><?= $editProduct ? 'Save changes' : 'Create product' ?></button>
    </form>
</section>
<section class="admin-panel">
    <div class="panel-heading"><div><p>Available inventory</p><h2><?= count($products) ?> services</h2></div></div>
    <div class="product-admin-grid">
    <?php foreach ($products as $product): ?>
        <article class="product-admin-card <?= (int) $product['is_active'] === 1 ? '' : 'inactive' ?>">
            <img src="../<?= e($product['image']) ?>" alt=""><div><span class="<?= e(status_class((int) $product['is_active'] === 1 ? 'active' : 'inactive')) ?>"><?= (int) $product['is_active'] === 1 ? 'Active' : 'Inactive' ?></span><h3><?= e($product['title']) ?></h3><p><?= e(ucfirst($product['type'])) ?> · <?= e(format_money($product['base_price'])) ?> · max <?= (int) $product['max_pax'] ?></p><div class="button-row"><a class="btn btn-light" href="products.php?edit=<?= e($product['id']) ?>">Edit</a><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="product_id" value="<?= e($product['id']) ?>"><button class="btn btn-light" type="submit"><?= (int) $product['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button></form></div></div>
        </article>
    <?php endforeach; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
