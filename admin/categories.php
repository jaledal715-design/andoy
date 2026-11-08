<?php
require_once __DIR__ . '/../config/database.php';
$u = require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $act = $_POST['action'] ?? '';
    try {
        if ($act === 'save') {
            $id = (int)($_POST['id'] ?? 0); $name = trim($_POST['name'] ?? ''); $desc = trim($_POST['description'] ?? '');
            if ($name === '') throw new RuntimeException('Enter a category name.');
            if ($id) q('UPDATE categories SET name = ?, description = ? WHERE id = ?', [$name, $desc, $id]); else q('INSERT INTO categories (name, description) VALUES (?,?)', [$name, $desc]);
            flash('Category saved.');
        } elseif ($act === 'delete') { q('DELETE FROM categories WHERE id = ?', [(int)$_POST['id']]); flash('Category deleted. Its products are now uncategorized.'); }
    } catch (PDOException $ex) { flash('A category with that name already exists.', 'err'); } catch (RuntimeException $ex) { flash($ex->getMessage(), 'err'); }
    redirect('admin/categories.php');
}
$edit = isset($_GET['edit']) ? one('SELECT * FROM categories WHERE id = ?', [(int)$_GET['edit']]) : null;
$cats = all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS n FROM categories c ORDER BY c.name');
page_start('Categories', 'categories');
?>
<h1>Categories</h1>
<form class="card" method="post"><h2><?= $edit ? 'Edit category' : 'Add category' ?></h2><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <div class="g2"><div class="f"><label for="cn">Name</label><input id="cn" name="name" value="<?= e($edit['name'] ?? '') ?>" required></div><div class="f"><label for="cd">Description</label><input id="cd" name="description" maxlength="255" value="<?= e($edit['description'] ?? '') ?>"></div></div>
  <button class="btn">Save category</button> <?php if ($edit): ?><a class="btn gh" href="<?= e(url('admin/categories.php')) ?>">Cancel</a><?php endif; ?></form>
<div class="tw"><table><thead><tr><th>Name</th><th>Description</th><th class="nr">Products</th><th></th></tr></thead><tbody>
<?php foreach ($cats as $c): ?><tr><td><strong><?= e($c['name']) ?></strong></td><td class="mut"><?= e($c['description']) ?></td><td class="nr"><?= (int)$c['n'] ?></td>
  <td style="white-space:nowrap"><a class="btn sm gh" href="<?= e(url('admin/categories.php?edit=' . $c['id'])) ?>">Edit</a>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn sm dg" data-confirm="Delete this category? Its products will become uncategorized.">Delete</button></form></td></tr>
<?php endforeach; if (!$cats): ?><tr><td colspan="4" class="mut">No categories yet.</td></tr><?php endif; ?>
</tbody></table></div>
<?php page_end(); ?>
