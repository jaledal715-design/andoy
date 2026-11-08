<?php
require_once __DIR__ . '/../config/database.php';
$u = require_role('admin');
$today = (int)val('SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()');
$pending = (int)val("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
$verify = (int)val("SELECT COUNT(*) FROM payments p JOIN orders o ON o.id = p.order_id WHERE p.method = 'gcash' AND p.status = 'pending' AND p.proof_image IS NOT NULL AND o.status <> 'cancelled'");
$month = (float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE status = 'completed' AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
$custs = (int)val("SELECT COUNT(*) FROM users WHERE role = 'customer'");
$days = [];
for ($i = 13; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i day"))] = 0.0;
foreach (all("SELECT DATE(created_at) d, SUM(total) t FROM orders WHERE status = 'completed' AND created_at >= CURDATE() - INTERVAL 13 DAY GROUP BY DATE(created_at)") as $r) if (isset($days[$r['d']])) $days[$r['d']] = (float)$r['t'];
$max = max(1, max($days));
$recent = load_orders('1=1', [], 'LIMIT 6');
page_start('Dashboard', 'dashboard');
?>
<div class="stats">
  <div class="stat"><b><?= $today ?></b><span>Orders today</span></div>
  <div class="stat"><b><?= $pending ?></b><span>Pending orders</span></div>
  <div class="stat"><b><?= $verify ?></b><span>GCash to verify</span></div>
  <div class="stat"><b><?= money($month) ?></b><span>Completed sales this month</span></div>
  <div class="stat"><b><?= $custs ?></b><span>Customers</span></div>
</div>
<div class="card"><h2>Completed sales, last 14 days</h2>
  <div class="bars" role="img" aria-label="Daily completed sales"><?php foreach ($days as $d => $t): ?><div style="height:<?= max(2, round($t / $max * 100)) ?>%" title="<?= e(date('M j', strtotime($d))) ?>: <?= e(money($t)) ?>"></div><?php endforeach; ?></div></div>
<h2>Recent orders</h2>
<div class="tw"><table>
  <thead><tr><th>Order</th><th>Customer</th><th>Type</th><th>Payment</th><th>Status</th><th class="nr">Total</th></tr></thead>
  <tbody>
  <?php foreach ($recent as $o): ?>
    <tr><td><strong><?= e($o['order_number']) ?></strong><div class="mut" style="font-size:.78rem"><?= e(date('M j, g:i A', strtotime($o['created_at']))) ?></div></td>
      <td><strong><?= e($o['cname']) ?></strong><div class="mut" style="font-size:.78rem"><?= e($o['contact_phone']) ?></div></td>
      <td><?= e(ucfirst($o['fulfillment'])) ?></td><td><?= $o['pay_method'] === 'gcash' ? 'GCash' : 'Cash' ?> <?= pay_badge($o) ?></td>
      <td><?= status_badge($o['status']) ?></td><td class="nr"><?= money($o['total']) ?></td></tr>
  <?php endforeach; if (!$recent): ?><tr><td colspan="6" class="mut">No orders yet.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php page_end(); ?>
