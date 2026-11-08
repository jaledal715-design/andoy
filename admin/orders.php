<?php
require_once __DIR__ . '/../config/database.php';
$u = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $o = load_orders('o.id = ?', [(int)($_POST['order_id'] ?? 0)])[0] ?? null;
    $act = $_POST['action'] ?? '';
    if ($o) {
        $live = !in_array($o['cat'], ['completed', 'cancelled'], true);
        if ($act === 'next' && $live && ($n = next_status($o))) { set_order_status($o, $n); flash($o['order_number'] . ': ' . ORDER_STATUSES[$n]); }
        elseif ($act === 'cancel' && $live) { set_order_status($o, 'cancelled'); flash('Order ' . $o['order_number'] . ' cancelled.'); }
        elseif ($act === 'verify' && $o['pay_method'] === 'gcash' && $o['proof_image']) {
            q("UPDATE payments SET status = 'verified', admin_note = '', verified_at = NOW() WHERE order_id = ?", [$o['id']]);
            if ($o['status'] === 'pending') q("UPDATE orders SET status = 'confirmed' WHERE id = ?", [$o['id']]);
            if ($o['user_id']) notify_user((int)$o['user_id'], 'Payment for your order ' . $o['order_number'] . ' was verified.' . ($o['status'] === 'pending' ? ' Your order is confirmed.' : ''), (int)$o['id']);
            flash('Payment verified.');
        } elseif ($act === 'reject' && $o['pay_method'] === 'gcash' && $o['proof_image']) {
            q("UPDATE payments SET status = 'rejected', verified_at = NULL WHERE order_id = ?", [$o['id']]);
            if ($o['user_id']) notify_user((int)$o['user_id'], 'Payment for your order ' . $o['order_number'] . ' was rejected. Please upload a new screenshot.', (int)$o['id']);
            flash('Payment rejected. The customer can upload a new screenshot.');
        }
    }
    redirect('admin/orders.php' . (isset($_GET['tab']) ? '?tab=' . urlencode($_GET['tab']) : ''));
}
q('UPDATE notifications SET seen = 1 WHERE for_admin = 1 AND order_id IS NOT NULL');
$tab = array_key_exists($_GET['tab'] ?? '', TABS) ? $_GET['tab'] : 'all';
$qs = trim($_GET['q'] ?? '');
$where = '1=1'; $args = [];
if ($qs !== '') { $where .= ' AND (o.order_number LIKE ? OR COALESCE(u.full_name, o.customer_name) LIKE ?)'; $args = ["%$qs%", "%$qs%"]; }
$all = load_orders($where, $args, 'LIMIT 300');
$shown = $tab === 'all' ? $all : array_values(array_filter($all, fn($o) => $o['cat'] === $tab));
page_start('Orders', 'orders');
?>
<div class="tabsrow"><h1 style="margin:0 auto 0 0">Orders</h1>
  <form method="get" class="toolbar" style="margin:0"><input type="hidden" name="tab" value="<?= e($tab) ?>"><div class="f"><input type="search" name="q" value="<?= e($qs) ?>" placeholder="Order no. or customer" aria-label="Search orders"></div><button class="btn gh sm">Search</button></form></div>
<?= order_tabs($all, $tab, 'admin/orders.php', true) ?>
<?= order_cards($shown, true) ?>
<?php page_end(); ?>
