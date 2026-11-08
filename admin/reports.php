<?php
require_once __DIR__ . '/../config/database.php';
$u = require_role('admin');
$valid = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d) && strtotime($d) !== false;
$from = $valid($_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
$to = $valid($_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
if ($from > $to) [$from, $to] = [$to, $from];

if (isset($_GET['export'])) {   // CSV download of completed sales in the chosen range
    $rows = all("SELECT o.order_number, o.created_at, COALESCE(u.full_name, o.customer_name) AS cust, o.fulfillment, p.method, o.subtotal, o.delivery_fee, o.total
        FROM orders o JOIN payments p ON p.order_id = o.id LEFT JOIN users u ON u.id = o.user_id WHERE o.status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ? ORDER BY o.created_at", [$from, $to]);
    header('Content-Type: text/csv; charset=utf-8'); header("Content-Disposition: attachment; filename=\"sales_{$from}_to_{$to}.csv\"");
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Order', 'Date', 'Customer', 'Type', 'Payment', 'Subtotal', 'Delivery fee', 'Total']);
    foreach ($rows as $r) fputcsv($out, array_map(fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v, array_values($r)));
    exit;
}
$years = array_column(all("SELECT DISTINCT YEAR(created_at) y FROM orders WHERE status = 'completed' ORDER BY y DESC"), 'y');
$cy = (int)date('Y'); if (!in_array($cy, $years)) array_unshift($years, $cy);
$y = in_array((int)($_GET['y'] ?? 0), $years) ? (int)$_GET['y'] : (int)$years[0];
$mo = array_fill(1, 12, ['n' => 0, 't' => 0]);
foreach (all("SELECT MONTH(created_at) m, COUNT(*) n, SUM(total) t FROM orders WHERE status = 'completed' AND YEAR(created_at) = ? GROUP BY MONTH(created_at)", [$y]) as $r) $mo[(int)$r['m']] = ['n' => (int)$r['n'], 't' => (float)$r['t']];
$mx = max(1, max(array_column($mo, 't'))); $ty = array_sum(array_column($mo, 't')); $cnt = array_sum(array_column($mo, 'n'));
$yearly = all("SELECT YEAR(created_at) y, COUNT(*) n, SUM(total) t FROM orders WHERE status = 'completed' GROUP BY YEAR(created_at) ORDER BY y DESC");

$rg = "FROM orders o JOIN payments p ON p.order_id = o.id WHERE o.status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?";
$sum = one("SELECT COUNT(*) n, COALESCE(SUM(o.total),0) t, COALESCE(SUM(o.delivery_fee),0) f $rg", [$from, $to]);
$byM = array_column(all("SELECT p.method k, COUNT(*) n, SUM(o.total) t $rg GROUP BY p.method", [$from, $to]), null, 'k');
$byT = array_column(all("SELECT o.fulfillment k, COUNT(*) n, SUM(o.total) t $rg GROUP BY o.fulfillment", [$from, $to]), null, 'k');
$top = all("SELECT oi.product_name, SUM(oi.quantity) q, SUM(oi.line_total) t FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ? GROUP BY oi.product_name ORDER BY q DESC LIMIT 10", [$from, $to]);
page_start('Sales reports', 'reports');
$mn = fn($i) => date('F', mktime(0, 0, 0, $i, 1));
?>
<h1>Sales reports</h1>
<div class="card">
  <form method="get" class="tabsrow"><h2 style="margin:0 auto 0 0">Complete sales for <?= $y ?></h2><div class="f" style="margin:0"><label for="y">Year</label><select id="y" name="y" data-autosubmit><?php foreach ($years as $v): ?><option <?= (int)$v === $y ? 'selected' : '' ?>><?= (int)$v ?></option><?php endforeach; ?></select></div></form>
  <div class="stats"><div class="stat"><b><?= money($ty) ?></b><span>Sales in <?= $y ?></span></div><div class="stat"><b><?= $cnt ?></b><span>Completed orders</span></div><div class="stat"><b><?= money($cnt ? $ty / $cnt : 0) ?></b><span>Average order</span></div></div>
  <div class="bars" role="img" aria-label="Monthly sales for <?= $y ?>"><?php foreach ($mo as $i => $m): ?><div style="height:<?= max(2, round($m['t'] / $mx * 100)) ?>%" title="<?= e($mn($i)) ?>: <?= e(money($m['t'])) ?>"></div><?php endforeach; ?></div>
  <div class="tw" style="margin-top:14px"><table><thead><tr><th>Month</th><th class="nr">Orders</th><th class="nr">Sales</th></tr></thead><tbody>
    <?php foreach ($mo as $i => $m): ?><tr><td><?= e($mn($i)) ?> <?= $y ?></td><td class="nr"><?= $m['n'] ?></td><td class="nr"><?= money($m['t']) ?></td></tr><?php endforeach; ?>
    <tr><td><strong>Total <?= $y ?></strong></td><td class="nr"><strong><?= $cnt ?></strong></td><td class="nr"><strong><?= money($ty) ?></strong></td></tr></tbody></table></div>
</div>
<h2>Sales by year</h2>
<div class="tw"><table><thead><tr><th>Year</th><th class="nr">Orders</th><th class="nr">Sales</th></tr></thead><tbody>
  <?php foreach ($yearly as $r): ?><tr><td><?= (int)$r['y'] ?></td><td class="nr"><?= (int)$r['n'] ?></td><td class="nr"><?= money($r['t']) ?></td></tr><?php endforeach; if (!$yearly): ?><tr><td colspan="3" class="mut">No completed sales yet.</td></tr><?php endif; ?></tbody></table></div>
<h2 style="margin-top:8px">Custom date range</h2>
<form class="toolbar no-print" method="get"><input type="hidden" name="y" value="<?= $y ?>">
  <div class="f"><label for="f">From</label><input id="f" type="date" name="from" value="<?= e($from) ?>"></div><div class="f"><label for="t">To</label><input id="t" type="date" name="to" value="<?= e($to) ?>"></div>
  <button class="btn">Generate</button><a class="btn gh" href="?from=<?= e($from) ?>&to=<?= e($to) ?>&export=1">Download CSV</a><button type="button" class="btn gh" onclick="window.print()">Print</button></form>
<p class="mut">Completed orders placed from <?= e(date('M j, Y', strtotime($from))) ?> to <?= e(date('M j, Y', strtotime($to))) ?>.</p>
<div class="stats"><div class="stat"><b><?= money($sum['t']) ?></b><span>Total sales</span></div><div class="stat"><b><?= (int)$sum['n'] ?></b><span>Completed orders</span></div><div class="stat"><b><?= money($sum['n'] ? $sum['t'] / $sum['n'] : 0) ?></b><span>Average order</span></div><div class="stat"><b><?= money($sum['f']) ?></b><span>Delivery fees</span></div></div>
<div class="g2">
  <div class="card"><h2>By payment</h2><?php foreach (['gcash' => 'GCash', 'cash' => 'Cash'] as $k => $l): ?><div class="row"><span><?= $l ?> <span class="mut">(<?= (int)($byM[$k]['n'] ?? 0) ?> orders)</span></span><b><?= money($byM[$k]['t'] ?? 0) ?></b></div><?php endforeach; ?></div>
  <div class="card"><h2>By order type</h2><?php foreach (['pickup' => 'Pickup', 'delivery' => 'Delivery'] as $k => $l): ?><div class="row"><span><?= $l ?> <span class="mut">(<?= (int)($byT[$k]['n'] ?? 0) ?> orders)</span></span><b><?= money($byT[$k]['t'] ?? 0) ?></b></div><?php endforeach; ?></div>
</div>
<h2>Best sellers</h2>
<div class="tw"><table><thead><tr><th>Product</th><th class="nr">Qty sold</th><th class="nr">Sales</th></tr></thead><tbody>
  <?php foreach ($top as $r): ?><tr><td><?= e($r['product_name']) ?></td><td class="nr"><?= (int)$r['q'] ?></td><td class="nr"><?= money($r['t']) ?></td></tr><?php endforeach; if (!$top): ?><tr><td colspan="3" class="mut">Nothing sold in this range.</td></tr><?php endif; ?></tbody></table></div>
<?php page_end(); ?>
