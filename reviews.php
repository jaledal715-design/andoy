<?php
require_once __DIR__ . '/config/database.php';
$u = current_user();
if (!$u) { flash('Please log in to continue.', 'err'); redirect('login.php'); }
$p = one(PRODUCT_SELECT . ' WHERE p.id = ?', [(int)($_GET['product'] ?? 0)]);
if (!$p) { flash('Product not found.', 'err'); redirect(home_for($u)); }
$rows = all('SELECT * FROM reviews WHERE product_id = ? ORDER BY id DESC', [$p['id']]);
page_start('Ratings for ' . $p['name'], 'products');
?>
<p><a href="<?= e(url($u['role'] === 'admin' ? 'admin/products.php' : 'customer/products.php')) ?>">← Back to products</a></p>
<div class="card" style="max-width:640px">
  <h2><?= e($p['name']) ?></h2>
  <p style="margin:0 0 12px"><span class="stars-v"><?= stars((float)$p['ra']) ?></span> <strong><?= (int)$p['rn'] ? number_format((float)$p['ra'], 1) : '–' ?></strong> <span class="mut">(<?= (int)$p['rn'] ?> rating<?= (int)$p['rn'] === 1 ? '' : 's' ?>)</span></p>
  <?php if (!$rows): ?><div class="empty">No ratings yet.</div><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <div style="border-top:1px solid var(--line);padding:10px 0">
      <div><span class="stars-v"><?= stars((float)$r['stars']) ?></span> <strong><?= e(short_name($r['reviewer_name'])) ?></strong> <span class="mut" style="font-size:.8rem"><?= e(date('M j, Y g:i A', strtotime($r['created_at']))) ?></span></div>
      <?php if ($r['comment'] !== ''): ?><div><?= e($r['comment']) ?></div><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php page_end(); ?>
