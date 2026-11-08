<?php
require_once __DIR__ . '/../config/database.php';
$u = require_role('customer');
$qs = trim($_GET['q'] ?? ''); $cat = (int)($_GET['cat'] ?? 0);
$sql = PRODUCT_SELECT . ' WHERE 1=1'; $a = [];
if ($qs !== '') { $sql .= ' AND (p.name LIKE ? OR p.description LIKE ?)'; $a[] = "%$qs%"; $a[] = "%$qs%"; }
if ($cat) { $sql .= ' AND p.category_id = ?'; $a[] = $cat; }
$products = all($sql . ' ORDER BY p.name', $a);
$cats = all('SELECT * FROM categories ORDER BY name');
page_start('Our Products', 'products');
?>
<h1>Our Products</h1>
<p class="sub">Classic Filipino rice cakes, handmade and ready for your table.</p>
<form class="toolbar" method="get">
  <div class="f grow"><input type="search" name="q" value="<?= e($qs) ?>" placeholder="Search kakanin, e.g. biko" aria-label="Search products"></div>
  <?php if ($cat): ?><input type="hidden" name="cat" value="<?= $cat ?>"><?php endif; ?>
  <button class="btn gh">Search</button>
</form>
<div class="chips">
  <a class="chip <?= !$cat ? 'on' : '' ?>" href="<?= e(url('customer/products.php')) ?>" style="text-decoration:none;color:inherit">All</a>
  <?php foreach ($cats as $c): ?><a class="chip <?= $cat === (int)$c['id'] ? 'on' : '' ?>" href="<?= e(url('customer/products.php?cat=' . $c['id'])) ?>" style="text-decoration:none;color:inherit"><?= e($c['name']) ?></a><?php endforeach; ?>
</div>
<?php if (!$products): ?><div class="empty">No kakanin matches your search. Try another word or category.</div><?php else: ?>
<div class="grid">
<?php foreach ($products as $p): ?>
  <article class="pc">
    <?= thumb($p) ?>
    <div class="pb">
      <h3><?= e($p['name']) ?></h3>
      <?= rating_line($p) ?>
      <div class="mut" style="font-size:.85rem;flex:1"><?= e($p['description']) ?></div>
      <div class="row" style="align-items:center"><span class="pr"><?= money($p['price']) ?></span>
        <form method="post" action="<?= e(url('customer/cart.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"><button class="btn sm">Add to cart</button></form>
      </div>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php page_end(); ?>
