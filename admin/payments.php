<?php
require_once __DIR__ . '/../config/database.php';
$u = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $oid = (int)($_POST['order_id'] ?? 0); $act = $_POST['action'] ?? '';
    $o = load_orders('o.id = ?', [$oid])[0] ?? null;
    if ($o && $act === 'verify' && ($o['pay_method'] === 'cash' || $o['proof_image'])) {
        q("UPDATE payments SET status = 'verified', admin_note = '', verified_at = NOW() WHERE order_id = ?", [$oid]);
        if ($o['status'] === 'pending' && $o['pay_method'] === 'gcash') q("UPDATE orders SET status = 'confirmed' WHERE id = ?", [$oid]);
        if ($o['user_id']) notify_user((int)$o['user_id'], 'Payment for your order ' . $o['order_number'] . ' was verified.', $oid);
        flash('Payment verified for ' . $o['order_number'] . '.');
    } elseif ($o && $act === 'reject' && $o['pay_method'] === 'gcash' && $o['proof_image']) {
        $note = mb_substr(trim($_POST['admin_note'] ?? ''), 0, 255);
        q("UPDATE payments SET status = 'rejected', admin_note = ?, verified_at = NULL WHERE order_id = ?", [$note, $oid]);
        if ($o['user_id']) notify_user((int)$o['user_id'], 'Payment for your order ' . $o['order_number'] . ' was rejected' . ($note ? ': ' . $note : '') . '. Please upload a new screenshot.', $oid);
        flash('Payment rejected. The customer can upload a new screenshot.');
    }
    redirect('admin/payments.php?id=' . $oid);
}
$id = (int)($_GET['id'] ?? 0);
if ($id) {
    q("UPDATE payments SET seen = 1 WHERE order_id = ? AND proof_image IS NOT NULL", [$id]);
    $o = load_orders('o.id = ?', [$id])[0] ?? null;
    if (!$o) { flash('Payment not found.', 'err'); redirect('admin/payments.php'); }
    page_start('Payment for ' . $o['order_number'], 'payments');
?>
<p><a href="<?= e(url('admin/payments.php')) ?>">← All payments</a></p>
<div class="g2" style="align-items:start">
  <section class="card"><h2><?= e($o['order_number']) ?></h2>
    <p><?= e($o['cname']) ?> · <?= $o['pay_method'] === 'gcash' ? 'GCash' : 'Cash' ?> · <strong><?= money($o['total']) ?></strong></p>
    <p><?= pay_badge($o) ?> <?= status_badge($o['status']) ?></p>
    <?php if ($o['reference_no']): ?><p class="mut">Reference: <?= e($o['reference_no']) ?></p><?php endif; ?>
    <?php if ($o['admin_note']): ?><p class="mut">Note: <?= e($o['admin_note']) ?></p><?php endif; ?>
    <?php if ($o['pay_status'] !== 'verified' && ($o['pay_method'] === 'cash' || $o['proof_image'])): ?>
      <form method="post" style="margin-bottom:12px"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="action" value="verify"><button class="btn gr"><?= $o['pay_method'] === 'cash' ? 'Mark as paid' : 'Verify payment' ?></button></form>
    <?php endif; ?>
    <?php if ($o['pay_method'] === 'gcash' && $o['proof_image'] && $o['pay_status'] === 'pending'): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="action" value="reject">
        <div class="f"><label for="n">Reason for rejecting</label><input id="n" name="admin_note" maxlength="255" placeholder="e.g. Amount does not match the order total"></div>
        <button class="btn dg" data-confirm="Reject this payment?">Reject payment</button></form>
    <?php endif; ?>
  </section>
  <section class="card"><h2>Payment proof</h2>
    <?php if ($o['proof_image']): ?><a href="<?= e(url('uploads/proofs/' . $o['proof_image'])) ?>" target="_blank" rel="noopener"><img style="max-width:100%;max-height:420px;border-radius:12px;border:1px solid var(--line)" src="<?= e(url('uploads/proofs/' . $o['proof_image'])) ?>" alt="GCash payment screenshot"></a>
      <p class="hint">Check that the amount equals <?= money($o['total']) ?> and the reference matches.</p>
    <?php else: ?><p class="mut"><?= $o['pay_method'] === 'cash' ? 'Cash is collected on ' . e($o['fulfillment']) . '.' : 'No screenshot uploaded yet.' ?></p><?php endif; ?>
  </section>
</div>
<?php
} else {
    q("UPDATE payments SET seen = 1 WHERE proof_image IS NOT NULL AND seen = 0");
    $f = $_GET['status'] ?? ''; $m = $_GET['method'] ?? '';
    $where = '1=1'; $args = [];
    if (in_array($f, ['pending', 'verified', 'rejected'], true)) { $where .= ' AND p.status = ?'; $args[] = $f; } else $f = '';
    if (in_array($m, ['gcash', 'cash'], true)) { $where .= ' AND p.method = ?'; $args[] = $m; }
    $rows = load_orders($where, $args, 'LIMIT 300');
    page_start('Payments', 'payments');
?>
<h1>Payments</h1>
<form class="toolbar" method="get">
  <div class="f"><label for="s">Payment status</label><select id="s" name="status" data-autosubmit><option value="">All</option><?php foreach (['pending' => 'Awaiting check', 'verified' => 'Paid', 'rejected' => 'Rejected'] as $k => $l): ?><option value="<?= $k ?>" <?= $f === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <div class="f"><label for="m">Method</label><select id="m" name="method" data-autosubmit><option value="">All</option><option value="gcash" <?= $m === 'gcash' ? 'selected' : '' ?>>GCash</option><option value="cash" <?= $m === 'cash' ? 'selected' : '' ?>>Cash</option></select></div>
</form>
<div class="tw"><table>
  <thead><tr><th>Order</th><th>Customer</th><th>Method</th><th>Payment</th><th>Order status</th><th class="nr">Amount</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><strong><?= e($r['order_number']) ?></strong></td><td><?= e($r['cname']) ?></td><td><?= $r['pay_method'] === 'gcash' ? 'GCash' . ($r['reference_no'] ? ' · ' . e($r['reference_no']) : '') : 'Cash' ?></td>
      <td><?= pay_badge($r) ?></td><td><?= status_badge($r['status']) ?></td><td class="nr"><?= money($r['total']) ?></td>
      <td><a class="btn sm gh" href="<?= e(url('admin/payments.php?id=' . $r['id'])) ?>">Review</a></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="mut">No payments in this view.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php } page_end(); ?>
