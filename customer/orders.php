<?php
require_once __DIR__ . '/../config/database.php';
$u = require_role('customer');
$uid = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = $_POST['action'] ?? ''; $oid = (int)($_POST['order_id'] ?? 0);
    $o = load_orders('o.id = ? AND o.user_id = ?', [$oid, $uid])[0] ?? null;
    if (!$o) { flash('Order not found.', 'err'); redirect('customer/orders.php'); }

    if ($act === 'cancel' && $o['status'] === 'pending') {
        q("UPDATE orders SET status = 'cancelled' WHERE id = ?", [$oid]);
        notify_admin('Customer ' . $u['full_name'] . ' cancelled order ' . $o['order_number'] . '.', $oid);
        flash('Order cancelled.');
    } elseif ($act === 'again') {
        $cartId = get_cart_id($uid); $n = 0;
        foreach ($o['items'] as $i) if ($i['product_id']) { q('INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity = LEAST(99, quantity + VALUES(quantity))', [$cartId, $i['product_id'], $i['quantity']]); $n++; }
        if ($n) { flash('Items added to your cart.'); redirect('customer/cart.php'); }
        flash('Those products are no longer on the menu.', 'err');
    } elseif ($act === 'proof' && $o['pay_method'] === 'gcash' && $o['pay_status'] !== 'verified' && $o['status'] !== 'cancelled') {
        try {
            $new = upload_image($_FILES['proof'] ?? [], 'proofs');
            if (!$new) throw new RuntimeException('Choose an image to upload.');
            delete_upload($o['proof_image'], 'proofs');
            q("UPDATE payments SET proof_image = ?, reference_no = ?, status = 'pending', admin_note = '', seen = 0 WHERE order_id = ?", [$new, mb_substr(trim($_POST['reference_no'] ?? ''), 0, 60), $oid]);
            notify_admin('New payment proof uploaded for order ' . $o['order_number'] . '.', $oid);
            flash('Payment screenshot uploaded. We will verify it shortly.');
        } catch (RuntimeException $ex) { flash($ex->getMessage(), 'err'); }
    } elseif ($act === 'review' && $o['status'] === 'completed') {
        $pid = (int)($_POST['product_id'] ?? 0); $stars = (int)($_POST['stars'] ?? 0);
        $has = array_filter($o['items'], fn($i) => (int)$i['product_id'] === $pid);
        if (!$has) flash('That product is not in this order.', 'err');
        elseif ($stars < 1 || $stars > 5) flash('Choose 1 to 5 stars first.', 'err');
        else {
            $exists = one('SELECT id FROM reviews WHERE order_id = ? AND product_id = ? AND user_id = ?', [$oid, $pid, $uid]);
            q('INSERT INTO reviews (product_id, user_id, order_id, reviewer_name, stars, comment) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE stars = VALUES(stars), comment = VALUES(comment), created_at = NOW()',
              [$pid, $uid, $oid, $u['full_name'], $stars, mb_substr(trim($_POST['comment'] ?? ''), 0, 300)]);
            if (!$exists) notify_admin($u['full_name'] . ' rated ' . array_values($has)[0]['product_name'] . ' ' . $stars . '/5.', $oid);
            flash('Thanks for your rating!');
        }
    }
    redirect('customer/orders.php?id=' . $oid . ($act === 'review' ? '#rate' : ''));
}

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $o = load_orders('o.id = ? AND o.user_id = ?', [$id, $uid])[0] ?? null;
    if (!$o) { flash('Order not found.', 'err'); redirect('customer/orders.php'); }
    q('UPDATE notifications SET seen = 1 WHERE user_id = ? AND order_id = ?', [$uid, $id]);
    $mine = array_column(all('SELECT * FROM reviews WHERE order_id = ? AND user_id = ?', [$id, $uid]), null, 'product_id');
    $steps = status_steps($o['fulfillment']); $pos = array_search($o['status'], $steps, true);
    page_start('Order ' . $o['order_number'], 'orders');
    $waiting = $o['proof_image'] && $o['pay_status'] === 'pending';
?>
<p><a href="<?= e(url('customer/orders.php')) ?>">← All orders</a></p>
<div class="cols">
  <div>
    <section class="card">
      <div class="row"><h2><?= e($o['order_number']) ?></h2><?= status_badge($o['status']) ?></div>
      <?php if ($o['status'] === 'cancelled'): ?><p class="mut">This order was cancelled.</p><?php else: ?>
        <ol class="track"><?php foreach ($steps as $k => $st): ?><li class="<?= $pos !== false && $k <= $pos ? 'd' : '' ?>"><?= e(ORDER_STATUSES[$st]) ?></li><?php endforeach; ?></ol>
      <?php endif; ?>
    </section>
    <section class="card"><h2>Items</h2>
      <?php foreach ($o['items'] as $i): ?><div class="row"><span><?= (int)$i['quantity'] ?> × <?= e($i['product_name']) ?></span><span><?= money($i['line_total']) ?></span></div><?php endforeach; ?>
      <div class="row" style="border-top:1px solid var(--line);margin-top:8px;padding-top:10px"><span>Subtotal</span><span><?= money($o['subtotal']) ?></span></div>
      <div class="row"><span>Delivery fee</span><span><?= money($o['delivery_fee']) ?></span></div>
      <div class="row tot"><span>Total</span><span><?= money($o['total']) ?></span></div>
    </section>
    <?php if ($o['status'] === 'completed'): ?>
    <section class="card" id="rate"><h2>Rate your items</h2><p class="mut" style="margin-top:0">Tell us how it was. Your rating helps other customers.</p>
      <?php foreach ($o['items'] as $i): if (!$i['product_id']) { echo '<div class="row" style="border-top:1px solid var(--line)"><span>' . e($i['product_name']) . '</span><span class="mut">No longer on the menu</span></div>'; continue; } $rv = $mine[$i['product_id']] ?? null; ?>
        <form method="post" style="border-top:1px solid var(--line);padding:12px 0"><?= csrf_field() ?><input type="hidden" name="action" value="review"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="product_id" value="<?= (int)$i['product_id'] ?>">
          <div><strong><?= e($i['product_name']) ?></strong></div>
          <div class="rate" role="radiogroup" aria-label="Stars for <?= e($i['product_name']) ?>">
            <?php foreach ([5, 4, 3, 2, 1] as $s): $rid = "r$s-{$o['id']}-{$i['product_id']}"; ?><input type="radio" name="stars" value="<?= $s ?>" id="<?= $rid ?>" <?= $rv && (int)$rv['stars'] === $s ? 'checked' : '' ?>><label for="<?= $rid ?>" title="<?= $s ?> star<?= $s > 1 ? 's' : '' ?>"><span class="sr"><?= $s ?> star<?= $s > 1 ? 's' : '' ?></span>★</label><?php endforeach; ?>
          </div>
          <div class="f" style="margin:6px 0 8px"><input name="comment" maxlength="300" placeholder="Write a comment (optional)" value="<?= e($rv['comment'] ?? '') ?>"></div>
          <button class="btn sm"><?= $rv ? 'Update rating' : 'Submit rating' ?></button>
        </form>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>
  </div>
  <aside>
    <section class="card"><h2><?= $o['fulfillment'] === 'delivery' ? 'Delivery' : 'Pickup' ?></h2>
      <p style="margin:0"><?= $o['fulfillment'] === 'delivery' ? nl2br(e($o['delivery_address'])) : e(setting('shop_address')) ?></p>
      <p style="margin:8px 0 0"><?= $o['fulfillment'] === 'delivery' ? 'Deliver on' : 'Pick up on' ?>: <strong><?= e(date('D, M j, Y', strtotime($o['due_date']))) ?></strong><?= $o['due_slot'] ? ' · ' . e($o['due_slot']) : '' ?></p>
      <p class="mut" style="margin:6px 0 0">Contact: <?= e($o['contact_phone']) ?></p>
      <?php if ($o['notes']): ?><p class="mut" style="margin:4px 0 0">Notes: <?= e($o['notes']) ?></p><?php endif; ?>
    </section>
    <section class="card"><h2>Payment</h2>
      <p style="margin:0 0 8px"><?= $o['pay_method'] === 'gcash' ? 'GCash' : 'Cash' ?> <?= pay_badge($o) ?></p>
      <?php if ($o['pay_method'] === 'cash'): ?><p class="mut" style="margin:0"><?= $o['fulfillment'] === 'delivery' ? 'Pay the rider on delivery.' : 'Pay at the counter on pickup.' ?></p>
      <?php elseif ($o['pay_status'] !== 'verified' && $o['status'] !== 'cancelled'): ?>
        <?php if ($o['pay_status'] === 'rejected'): ?><p class="flash flash-err">Payment rejected<?= $o['admin_note'] ? ': ' . e($o['admin_note']) : '' ?>. Please pay again and upload a new screenshot.</p><?php endif; ?>
        <?php if ($waiting): ?><p class="mut" style="margin:0 0 10px">Payment screenshot received. The shop will check and verify it shortly.</p><?php else: ?>
        <div class="pay"><div>Send <strong><?= money($o['total']) ?></strong> to</div><div class="n"><?= e(setting('gcash_number')) ?></div><div><?= e(setting('gcash_name')) ?></div>
          <div class="oa" style="padding:10px 0 0;justify-content:flex-start"><a class="btn sm" href="gcash://" target="_blank" rel="noopener">Open GCash app</a>
            <button type="button" class="btn sm gh" data-copy="<?= e(preg_replace('/\s+/', '', setting('gcash_number'))) ?>">Copy number</button>
            <button type="button" class="btn sm gh" data-copy="<?= number_format((float)$o['total'], 2, '.', '') ?>">Copy amount</button></div></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="action" value="proof"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
          <div class="f"><label for="proof"><?= $o['proof_image'] ? 'Replace your payment screenshot' : 'Upload your payment screenshot' ?></label><input id="proof" type="file" name="proof" accept="image/jpeg,image/png,image/webp" required></div>
          <div class="f"><label for="ref">Reference number</label><input id="ref" name="reference_no" value="<?= e($o['reference_no']) ?>"></div>
          <button class="btn sm">Upload</button></form>
      <?php endif; ?>
    </section>
    <a class="btn gh blk" style="text-align:center" href="<?= e(url('customer/messages.php?order=' . urlencode($o['order_number']))) ?>">Message the shop about this order</a>
  </aside>
</div>
<?php
} else {
    q('UPDATE notifications SET seen = 1 WHERE user_id = ? AND order_id IS NOT NULL', [$uid]);
    $tab = array_key_exists($_GET['tab'] ?? '', TABS) ? $_GET['tab'] : 'all';
    $all = load_orders('o.user_id = ?', [$uid]);
    $shown = $tab === 'all' ? $all : array_values(array_filter($all, fn($o) => $o['cat'] === $tab));
    page_start('My orders', 'orders');
?>
<h1>My orders</h1>
<?= order_tabs($all, $tab, 'customer/orders.php', false) ?>
<?= order_cards($shown, false) ?>
<?php }
page_end(); ?>
