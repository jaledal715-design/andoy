<?php
require_once __DIR__ . '/../config/database.php';
$u = require_role('super');
$cs = "FROM orders o JOIN payments p ON p.order_id = o.id WHERE o.status = 'completed'";
$t = one("SELECT COUNT(*) n, COALESCE(SUM(o.total),0) s, COALESCE(SUM(o.delivery_fee),0) f $cs");
$prog = one("SELECT COUNT(*) n, COALESCE(SUM(total),0) s FROM orders WHERE status NOT IN ('completed','cancelled')");
$canc = (int)val("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'");
$byM = array_column(all("SELECT p.method k, COUNT(*) n, SUM(o.total) s $cs GROUP BY p.method"), null, 'k');
$byT = array_column(all("SELECT o.fulfillment k, COUNT(*) n, SUM(o.total) s $cs GROUP BY o.fulfillment"), null, 'k');
$months = all("SELECT DATE_FORMAT(o.created_at, '%Y-%m') k, COUNT(*) n, SUM(o.total) s $cs GROUP BY k ORDER BY k DESC");
$top = all("SELECT oi.product_name, SUM(oi.quantity) q, SUM(oi.line_total) s FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.status = 'completed' GROUP BY oi.product_name ORDER BY q DESC LIMIT 10");
$custs = all("SELECT COALESCE(u.full_name, o.customer_name) n, COUNT(*) c, SUM(o.total) s FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.status = 'completed' GROUP BY COALESCE(u.full_name, o.customer_name) ORDER BY s DESC LIMIT 5");
$list = load_orders("o.status = 'completed'", [], 'LIMIT 200');
page_start('Complete sales', 'sales');
?>
<h1>Complete sales</h1><p class="sub">All-time sales across every order. Only completed orders count as sales.</p>
<div class="stats">
  <div class="stat"><b><?= money($t['s']) ?></b><span>Total sales</span></div><div class="stat"><b><?= (int)$t['n'] ?></b><span>Completed orders</span></div>
  <div class="stat"><b><?= money($t['n'] ? $t['s'] / $t['n'] : 0) ?></b><span>Average order</span></div><div class="stat"><b><?= money($t['f']) ?></b><span>Delivery fees</span></div>
  <div class="stat"><b><?= money($prog['s']) ?></b><span>In progress (<?= (int)$prog['n'] ?> orders)</span></div><div class="stat"><b><?= $canc ?></b><span>Cancelled orders</span></div>
</div>
<div class="g2">
  <div class="card"><h2>By payment method</h2><?php foreach (['gcash' => 'GCash', 'cash' => 'Cash'] as $k => $l): ?><div class="row"><span><?= $l ?> <span class="mut">(<?= (int)($byM[$k]['n'] ?? 0) ?> orders)</span></span><b><?= money($byM[$k]['s'] ?? 0) ?></b></div><?php endforeach; ?></div>
  <div class="card"><h2>By order type</h2><?php foreach (['pickup' => 'Pickup', 'delivery' => 'Delivery'] as $k => $l): ?><div class="row"><span><?= $l ?> <span class="mut">(<?= (int)($byT[$k]['n'] ?? 0) ?> orders)</span></span><b><?= money($byT[$k]['s'] ?? 0) ?></b></div><?php endforeach; ?></div>
</div>
<h2>Sales by month</h2>
<div class="tw"><table><thead><tr><th>Month</th><th class="nr">Orders</th><th class="nr">Sales</th></tr></thead><tbody>
<?php foreach ($months as $m): ?><tr><td><?= e(date('F Y', strtotime($m['k'] . '-01'))) ?></td><td class="nr"><?= (int)$m['n'] ?></td><td class="nr"><?= money($m['s']) ?></td></tr><?php endforeach; if (!$months): ?><tr><td colspan="3" class="mut">No completed sales yet.</td></tr><?php endif; ?></tbody></table></div>
<div class="g2" style="align-items:start">
  <div><h2>Best sellers</h2><div class="tw"><table><thead><tr><th>Product</th><th class="nr">Qty</th><th class="nr">Sales</th></tr></thead><tbody>
    <?php foreach ($top as $r): ?><tr><td><?= e($r['product_name']) ?></td><td class="nr"><?= (int)$r['q'] ?></td><td class="nr"><?= money($r['s']) ?></td></tr><?php endforeach; if (!$top): ?><tr><td colspan="3" class="mut">No completed sales yet.</td></tr><?php endif; ?></tbody></table></div></div>
  <div><h2>Top customers</h2><div class="tw"><table><thead><tr><th>Customer</th><th class="nr">Orders</th><th class="nr">Spent</th></tr></thead><tbody>
    <?php foreach ($custs as $r): ?><tr><td><?= e($r['n']) ?></td><td class="nr"><?= (int)$r['c'] ?></td><td class="nr"><?= money($r['s']) ?></td></tr><?php endforeach; if (!$custs): ?><tr><td colspan="3" class="mut">No completed sales yet.</td></tr><?php endif; ?></tbody></table></div></div>
</div>
<h2>All completed sales</h2>
<div class="tw"><table><thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>Type</th><th>Payment</th><th class="nr">Total</th></tr></thead><tbody>
<?php foreach ($list as $o): ?><tr><td><strong><?= e($o['order_number']) ?></strong></td><td><?= e(date('M j, Y g:i A', strtotime($o['created_at']))) ?></td><td><?= e($o['cname']) ?></td><td><?= e($o['fulfillment']) ?></td><td><?= $o['pay_method'] === 'gcash' ? 'GCash' : 'Cash' ?></td><td class="nr"><?= money($o['total']) ?></td></tr>
<?php endforeach; if (!$list): ?><tr><td colspan="6" class="mut">No completed sales yet.</td></tr><?php endif; ?></tbody></table></div>
<?php page_end(); ?>
