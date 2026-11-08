<?php
require_once __DIR__ . '/config/database.php';
$u = current_user();
if (!$u) { flash('Please log in to continue.', 'err'); redirect('login.php'); }
$admin = $u['role'] === 'admin';
$where = $admin ? 'for_admin = 1' : 'user_id = ' . (int)$u['id'];

if (isset($_GET['go'])) {
    $n = one("SELECT * FROM notifications WHERE id = ? AND $where", [(int)$_GET['go']]);
    if ($n) {
        q('UPDATE notifications SET is_read = 1 WHERE id = ?', [$n['id']]);
        if ($n['order_id']) redirect($admin ? 'admin/orders.php' : 'customer/orders.php?id=' . $n['order_id']);
        if ($admin && str_starts_with($n['message'], 'New customer')) redirect('admin/customers.php');
    }
    redirect('notifications.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') { verify_csrf(); q("UPDATE notifications SET is_read = 1 WHERE $where"); redirect('notifications.php'); }

$list = all("SELECT * FROM notifications WHERE $where ORDER BY id DESC LIMIT 100");
page_start('Notifications', 'notifications');
?>
<div class="tabsrow"><h1 style="margin:0 auto 0 0">Notifications</h1>
<?php if (array_filter($list, fn($n) => !$n['is_read'])): ?><form method="post"><?= csrf_field() ?><button class="btn gh sm">Mark all as read</button></form><?php endif; ?></div>
<?php if (!$list): ?><div class="empty">No notifications yet. Order updates will show up here.</div><?php endif; ?>
<?php foreach ($list as $n): ?>
  <a class="nt <?= $n['is_read'] ? '' : 'new' ?>" href="<?= e(url('notifications.php?go=' . $n['id'])) ?>" style="text-decoration:none;color:inherit">
    <span><?= e($n['message']) ?></span><small class="mut"><?= e(date('M j, Y g:i A', strtotime($n['created_at']))) ?></small></a>
<?php endforeach; ?>
<?php page_end(); ?>
