<?php
require_once __DIR__ . '/../config/database.php';
$u = require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $act = $_POST['action'] ?? '';
    try {
        if ($act === 'save') {
            $id = (int)($_POST['id'] ?? 0); $name = trim($_POST['name'] ?? ''); $price = (float)($_POST['price'] ?? 0);
            if ($name === '') throw new RuntimeException('Enter a product name.');
            if ($price <= 0) throw new RuntimeException('Enter a price greater than zero.');
            $cat = (int)($_POST['category_id'] ?? 0) ?: null; $desc = trim($_POST['description'] ?? '');
            $img = upload_image($_FILES['image'] ?? [], 'products');
            if ($id) {
                $old = val('SELECT image FROM products WHERE id = ?', [$id]);
                $keep = $img ?: (isset($_POST['remove_image']) ? null : $old);
                q('UPDATE products SET name = ?, description = ?, category_id = ?, price = ?, image = ? WHERE id = ?', [$name, $desc, $cat, $price, $keep, $id]);
                if ($keep !== $old) delete_upload($old ?: null, 'products');
            } else q('INSERT INTO products (name, description, category_id, price, image) VALUES (?,?,?,?,?)', [$name, $desc, $cat, $price, $img]);
            flash('Product saved.');
        } elseif ($act === 'delete') {
            $id = (int)$_POST['id']; delete_upload(val('SELECT image FROM products WHERE id = ?', [$id]) ?: null, 'products');
            q('DELETE FROM products WHERE id = ?', [$id]); flash('Product deleted. Past orders keep their item names and prices.');
        }
    } catch (RuntimeException $ex) { flash($ex->getMessage(), 'err'); }
    redirect('admin/products.php');
}
$qs = trim($_GET['q'] ?? '');
$edit = isset($_GET['edit']) ? one('SELECT * FROM products WHERE id = ?', [(int)$_GET['edit']]) : null;
$adding = isset($_GET['new']);
$products = all(PRODUCT_SELECT . ' WHERE p.name LIKE ? ORDER BY p.name', ["%$qs%"]);
$cats = all('SELECT id, name FROM categories ORDER BY name');
page_start('Products', 'products');
?>
<div class="tabsrow"><h1 style="margin:0 auto 0 0">Products</h1>
  <form method="get" class="toolbar" style="margin:0"><div class="f"><input type="search" name="q" value="<?= e($qs) ?>" placeholder="Search products" aria-label="Search products"></div><button class="btn gh sm">Search</button></form>
  <a class="btn" href="<?= e(url('admin/products.php?new=1')) ?>">Add product</a></div>
<?php if ($edit || $adding): $p = $edit ?: ['id' => 0, 'name' => '', 'price' => '', 'category_id' => 0, 'description' => '', 'image' => null]; ?>
<form class="card" method="post" enctype="multipart/form-data"><h2><?= $p['id'] ? 'Edit product' : 'Add product' ?></h2><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
  <div class="g2"><div class="f"><label for="pn">Name</label><input id="pn" name="name" value="<?= e($p['name']) ?>" required maxlength="120"></div><div class="f"><label for="pp">Price (₱)</label><input id="pp" type="number" step="0.01" min="0.01" name="price" value="<?= e($p['price']) ?>" required></div></div>
  <div class="g2"><div class="f"><label for="pc">Category</label><select id="pc" name="category_id"><option value="0">Uncategorized</option><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$p['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="f"><label for="pi">Photo</label><input id="pi" type="file" name="image" accept="image/jpeg,image/png,image/webp"><div class="hint">Leave empty to keep the current photo.</div>
      <?php if ($p['image']): ?><label style="font-weight:400;margin-top:6px"><input type="checkbox" name="remove_image" value="1" style="width:auto"> Remove current photo</label><?php endif; ?></div></div>
  <div class="f"><label for="pd">Description</label><textarea id="pd" name="description"><?= e($p['description']) ?></textarea></div>
  <button class="btn">Save product</button> <a class="btn gh" href="<?= e(url('admin/products.php')) ?>">Cancel</a></form>
<?php endif; ?>
<div class="tw"><table>
  <thead><tr><th></th><th>Product</th><th>Category</th><th>Rating</th><th class="nr">Price</th><th></th></tr></thead><tbody>
<?php foreach ($products as $p): ?>
  <tr><td style="width:60px"><div style="width:44px;height:44px;border-radius:9px;overflow:hidden"><?= thumb($p) ?></div></td>
    <td><strong><?= e($p['name']) ?></strong><div class="mut" style="font-size:.85rem"><?= e(mb_strimwidth((string)$p['description'], 0, 70, '…')) ?></div></td>
    <td><?= e($p['category'] ?? 'Uncategorized') ?></td><td><?= rating_line($p) ?></td><td class="nr"><?= money($p['price']) ?></td>
    <td style="white-space:nowrap"><a class="btn sm gh" href="<?= e(url('admin/products.php?edit=' . $p['id'])) ?>">Edit</a>
      <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn sm dg" data-confirm="Delete <?= e($p['name']) ?>? This cannot be undone.">Delete</button></form></td></tr>
<?php endforeach; if (!$products): ?><tr><td colspan="6" class="mut">No products found.</td></tr><?php endif; ?>
</tbody></table></div>
<?php page_end(); ?>
