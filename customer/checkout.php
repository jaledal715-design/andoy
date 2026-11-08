<?php
require_once __DIR__ . '/../config/database.php';
$u = require_role('customer');
$uid = (int)$u['id'];
$items = cart_items($uid);
if (!$items) { flash('Your cart is empty.', 'err'); redirect('customer/cart.php'); }
$subtotal = round(array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items)), 2);
$feeSetting = (float)setting('delivery_fee', '0');
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $ful = in_array($_POST['fulfillment'] ?? '', ['pickup', 'delivery'], true) ? $_POST['fulfillment'] : null;
    $pay = in_array($_POST['payment_method'] ?? '', ['gcash', 'cash'], true) ? $_POST['payment_method'] : null;
    $phone = trim($_POST['contact_phone'] ?? ''); $addr = trim($_POST['delivery_address'] ?? '');
    $notes = mb_substr(trim($_POST['notes'] ?? ''), 0, 500); $ref = mb_substr(trim($_POST['reference_no'] ?? ''), 0, 60);
    $due = $_POST['due_date'] ?? ''; $slot = $_POST['due_slot'] ?? '';
    $err = [];
    if (!$ful) $err[] = 'Choose pickup or delivery.';
    if (!$pay) $err[] = 'Choose a payment method.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) || $due < $today) $err[] = 'Choose a ' . ($ful === 'delivery' ? 'delivery' : 'pickup') . ' date from today onward.';
    if (!in_array($slot, SLOTS, true)) $slot = SLOTS[0];
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) $err[] = 'Enter a valid contact number.';
    if ($ful === 'delivery' && $addr === '') $err[] = 'Enter your delivery address.';
    $proof = null;
    if (!$err) {
        try {
            $proof = $pay === 'gcash' ? upload_image($_FILES['proof'] ?? [], 'proofs') : null;
            if ($pay === 'gcash' && !$proof) $err[] = 'Pay with GCash first, then upload your payment screenshot to place the order.';
        } catch (RuntimeException $ex) { $err[] = $ex->getMessage(); }
    }
    if (!$err) {
        $fee = $ful === 'delivery' ? $feeSetting : 0.0; $total = $subtotal + $fee;
        try {
            global $pdo; $pdo->beginTransaction();
            $num = new_order_number();
            q('INSERT INTO orders (order_number, user_id, customer_name, fulfillment, delivery_address, contact_phone, notes, due_date, due_slot, subtotal, delivery_fee, total) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
              [$num, $uid, $u['full_name'], $ful, $ful === 'delivery' ? $addr : null, $phone, $notes, $due, $slot, $subtotal, $fee, $total]);
            $oid = last_id();
            foreach ($items as $i) q('INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total) VALUES (?,?,?,?,?,?)', [$oid, $i['product_id'], $i['name'], $i['price'], $i['quantity'], round($i['price'] * $i['quantity'], 2)]);
            q('INSERT INTO payments (order_id, method, amount, reference_no, proof_image) VALUES (?,?,?,?,?)', [$oid, $pay, $total, $pay === 'gcash' ? $ref : '', $proof]);
            q('DELETE FROM cart_items WHERE cart_id = ?', [get_cart_id($uid)]);
            $when = ($ful === 'delivery' ? 'delivery' : 'pickup') . ' on ' . date('D, M j, Y', strtotime($due));
            notify_admin('New order ' . $num . ' from ' . $u['full_name'] . ' · ' . money($total) . ' · ' . ($pay === 'gcash' ? 'GCash' : 'Cash') . ' · ' . $when . ', ' . $slot . '.', $oid);
            notify_user($uid, 'Order ' . $num . ' placed for ' . $when . '. We will confirm it shortly.', $oid);
            $pdo->commit();
            flash("Order $num placed. We'll confirm it shortly.");
            redirect('customer/orders.php?id=' . $oid);
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            delete_upload($proof, 'proofs');
            $err[] = 'Something went wrong while placing your order. Please try again.';
        }
    }
    foreach ($err as $m) flash($m, 'err');
}
$v = fn($k, $d = '') => e($_POST[$k] ?? $d);
$ful = $_POST['fulfillment'] ?? 'pickup'; $pm = $_POST['payment_method'] ?? 'gcash';
$total0 = $subtotal + ($ful === 'delivery' ? $feeSetting : 0);
page_start('Checkout', 'cart');
?>
<h1>Checkout</h1>
<form id="checkout-form" method="post" enctype="multipart/form-data" data-fee="<?= $feeSetting ?>" data-subtotal="<?= $subtotal ?>">
<?= csrf_field() ?>
<div class="cols">
  <div>
    <section class="card">
      <h2>Pickup or delivery</h2>
      <div class="opts">
        <label class="opt"><input type="radio" name="fulfillment" value="pickup" <?= $ful === 'pickup' ? 'checked' : '' ?>>Pickup<small><?= e(setting('shop_address')) ?></small></label>
        <label class="opt"><input type="radio" name="fulfillment" value="delivery" <?= $ful === 'delivery' ? 'checked' : '' ?>>Delivery<small><?= money($feeSetting) ?> delivery fee</small></label>
      </div>
      <div class="g2" style="margin-top:12px">
        <div class="f"><label for="due_date" id="date-label">Pickup date</label><input id="due_date" type="date" name="due_date" required min="<?= $today ?>" value="<?= $v('due_date') ?>"><div class="hint">The day you want your order ready.</div></div>
        <div class="f"><label for="due_slot">Preferred time</label><select id="due_slot" name="due_slot"><?php foreach (SLOTS as $s): ?><option <?= ($_POST['due_slot'] ?? '') === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
      </div>
      <div id="address-box" class="f"<?= $ful === 'delivery' ? '' : ' hidden' ?>><label for="address">Delivery address</label><textarea id="address" name="delivery_address" placeholder="House no., street, barangay, city"><?= $v('delivery_address', $u['address'] ?? '') ?></textarea>
        <?php if (setting('delivery_note')): ?><div class="hint"><?= e(setting('delivery_note')) ?></div><?php endif; ?></div>
      <div class="g2">
        <div class="f"><label for="phone">Contact number</label><input id="phone" type="tel" name="contact_phone" value="<?= $v('contact_phone', $u['phone']) ?>" required></div>
        <div class="f"><label for="notes">Notes (optional)</label><input id="notes" name="notes" maxlength="500" value="<?= $v('notes') ?>"></div>
      </div>
    </section>
    <section class="card">
      <h2>Payment</h2>
      <div class="opts">
        <label class="opt"><input type="radio" name="payment_method" value="gcash" <?= $pm === 'gcash' ? 'checked' : '' ?>>GCash<small>Pay first, then upload the screenshot</small></label>
        <label class="opt"><input type="radio" name="payment_method" value="cash" <?= $pm === 'cash' ? 'checked' : '' ?>>Cash<small id="cash-hint">Pay at the counter when you pick up.</small></label>
      </div>
      <div id="gcash-box" class="pay"<?= $pm === 'gcash' ? '' : ' hidden' ?>>
        <div><strong>Step 1.</strong> Pay <strong id="gcash-amt"><?= money($total0) ?></strong> to</div>
        <div class="n"><?= e(setting('gcash_number')) ?></div><div><?= e(setting('gcash_name')) ?></div>
        <div class="oa" style="padding:10px 0 0;justify-content:flex-start">
          <a class="btn sm" href="gcash://" target="_blank" rel="noopener">Open GCash app</a>
          <button type="button" class="btn sm gh" data-copy="<?= e(preg_replace('/\s+/', '', setting('gcash_number'))) ?>">Copy number</button>
          <button type="button" class="btn sm gh" id="copy-amt" data-copy="<?= number_format($total0, 2, '.', '') ?>">Copy amount</button>
        </div>
        <div class="hint">In GCash, tap Send Money, then enter this number and the exact amount.</div>
        <div class="f" style="margin:14px 0 0"><label for="proof"><strong>Step 2.</strong> Upload your payment screenshot (required)</label><input id="proof" type="file" name="proof" accept="image/jpeg,image/png,image/webp"><div class="hint">You can place your order only after uploading the screenshot of your payment.</div></div>
        <div class="f" style="margin:10px 0 0"><label for="ref">Reference number (optional)</label><input id="ref" name="reference_no" maxlength="60" value="<?= $v('reference_no') ?>"></div>
      </div>
    </section>
  </div>
  <aside class="card">
    <h2>Order summary</h2>
    <?php foreach ($items as $i): ?><div class="row"><span><?= (int)$i['quantity'] ?> × <?= e($i['name']) ?></span><span><?= money($i['price'] * $i['quantity']) ?></span></div><?php endforeach; ?>
    <div class="row" style="border-top:1px solid var(--line);margin-top:8px;padding-top:10px"><span>Subtotal</span><span><?= money($subtotal) ?></span></div>
    <div class="row"><span>Delivery fee</span><span id="sum-fee">₱0.00</span></div>
    <div class="row tot"><span>Total</span><span id="sum-total"><?= money($subtotal) ?></span></div>
    <button class="btn blk" style="margin-top:12px">Place order</button>
    <a class="btn gh blk" style="margin-top:8px;text-align:center" href="<?= e(url('customer/cart.php')) ?>">Back to cart</a>
  </aside>
</div>
</form>
<?php page_end(); ?>
