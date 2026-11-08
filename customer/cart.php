<?php
require_once __DIR__ . '/../config/database.php';
$u = require_role('customer');
$uid = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? ''; $cartId = get_cart_id($uid);
    if ($action === 'add') {
        $pid = (int)($_POST['product_id'] ?? 0); $qty = max(1, min(99, (int)($_POST['quantity'] ?? 1)));
        if ($name = val('SELECT name FROM products WHERE id = ?', [$pid])) {
            q('INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity = LEAST(99, quantity + VALUES(quantity))', [$cartId, $pid, $qty]);
            flash($name . ' added to your cart.');
        } else flash('That product no longer exists.', 'err');
        redirect('customer/products.php');
    }
    if ($action === 'update') foreach (($_POST['qty'] ?? []) as $id => $n) q('UPDATE cart_items SET quantity = ? WHERE id = ? AND cart_id = ?', [max(1, min(99, (int)$n)), (int)$id, $cartId]);
    if ($action === 'remove') q('DELETE FROM cart_items WHERE id = ? AND cart_id = ?', [(int)($_POST['item_id'] ?? 0), $cartId]);
    redirect('customer/cart.php');
}
$items = cart_items($uid);
$subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
page_start('Your cart', 'cart');
?>
<h1>Your cart</h1>
<?php if (!$items): ?><div class="empty">Your cart is empty. <a href="<?= e(url('customer/products.php')) ?>">Browse the products</a>.</div><?php else: ?>
<div class="cols">
  <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update">
    <?php foreach ($items as $i): ?>
      <div class="line"><?= thumb($i) ?>
        <div><strong><?= e($i['name']) ?></strong><div class="mut"><?= money($i['price']) ?> each</div>
          <button class="linkbtn" form="rm-<?= (int)$i['id'] ?>" data-confirm="Remove <?= e($i['name']) ?> from your cart?" style="background:none;border:0;padding:0;color:var(--p2);cursor:pointer;text-decoration:underline">Remove</button></div>
        <div class="r"><input class="cart-qty" type="number" name="qty[<?= (int)$i['id'] ?>]" value="<?= (int)$i['quantity'] ?>" min="1" max="99" aria-label="Quantity of <?= e($i['name']) ?>"><div class="pr" style="margin-top:6px"><?= money($i['price'] * $i['quantity']) ?></div></div>
      </div>
    <?php endforeach; ?>
    <button class="btn gh sm" style="margin-top:8px">Update quantities</button>
  </form>
  <?php foreach ($items as $i): ?><form id="rm-<?= (int)$i['id'] ?>" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="item_id" value="<?= (int)$i['id'] ?>"></form><?php endforeach; ?>
  <aside class="card"><h2>Summary</h2>
    <div class="row"><span>Subtotal</span><span><?= money($subtotal) ?></span></div>
    <div class="row mut"><span>Delivery fee</span><span>Set at checkout</span></div>
    <a class="btn blk" style="margin-top:12px;text-align:center" href="<?= e(url('customer/checkout.php')) ?>">Go to checkout</a>
  </aside>
</div>
<?php endif; ?>
<?php page_end(); ?>
