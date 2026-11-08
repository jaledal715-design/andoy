<?php
require_once __DIR__ . '/../config/database.php';
$u = require_role('admin');
$super = (bool)$u['is_super'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($super) {
        $id = (int)($_POST['id'] ?? 0); $act = $_POST['action'] ?? '';
        $c = one("SELECT * FROM users WHERE id = ? AND role = 'customer'", [$id]);
        if ($c && $act === 'ban') {
            q('UPDATE users SET banned = ? WHERE id = ?', [$c['banned'] ? 0 : 1, $id]);
            q('DELETE FROM remember_tokens WHERE user_id = ?', [$id]);
            flash($c['full_name'] . ($c['banned'] ? ' has been unbanned.' : ' has been banned.'));
        } elseif ($c && $act === 'delete') {
            delete_upload($c['avatar'], 'avatars');
            q('DELETE FROM notifications WHERE user_id = ?', [$id]);
            q('DELETE FROM users WHERE id = ?', [$id]);   // past orders stay in the sales records (user_id becomes NULL)
            flash('Account of ' . $c['full_name'] . ' deleted. Their past orders stay in the sales records.');
        }
    }
    redirect('admin/customers.php');
}
q("UPDATE users SET fresh = 0 WHERE role = 'customer' AND fresh = 1");
$show = $super ? (int)($_GET['show'] ?? 0) : 0;
$qs = trim($_GET['q'] ?? '');
$rows = all("SELECT u.*, COUNT(o.id) AS orders, COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.total END), 0) AS spent
    FROM users u LEFT JOIN orders o ON o.user_id = u.id
    WHERE u.role = 'customer' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?) GROUP BY u.id ORDER BY u.created_at DESC", ["%$qs%", "%$qs%", "%$qs%"]);
page_start('Customers', 'customers');
?>
<h1>Customers</h1>
<?php if ($super): ?><p class="sub">As super admin you can view passwords, ban or unban accounts, and delete accounts.</p><?php endif; ?>
<form class="toolbar" method="get"><div class="f grow"><input type="search" name="q" value="<?= e($qs) ?>" placeholder="Search name, email, or phone" aria-label="Search customers"></div><button class="btn gh">Search</button></form>
<div class="tw"><table>
  <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><?= $super ? '<th>Password</th>' : '' ?><th class="nr">Orders</th><th class="nr">Completed spend</th><?= $super ? '<th></th>' : '' ?></tr></thead><tbody>
<?php foreach ($rows as $c): ?>
  <tr><td><?= avatar($c, 28) ?> <strong><?= e($c['full_name']) ?></strong></td><td><?= e($c['email']) ?></td><td><?= e($c['phone']) ?></td>
    <td><?= $c['banned'] ? '<span class="b cancelled">Banned</span>' : '<span class="b completed">Active</span>' ?></td>
    <?php if ($super): $on = $show === (int)$c['id']; ?>
      <td style="white-space:nowrap"><code><?= $on ? e(dec_pw($c['password_enc']) ?: '(not available)') : '••••••••' ?></code>
        <a class="btn sm gh" href="<?= e(url('admin/customers.php' . ($on ? '' : '?show=' . $c['id']))) ?>" aria-label="<?= $on ? 'Hide' : 'Show' ?> password for <?= e($c['full_name']) ?>"><?= $on ? 'Hide' : 'Show' ?></a></td>
    <?php endif; ?>
    <td class="nr"><?= (int)$c['orders'] ?></td><td class="nr"><?= money($c['spent']) ?></td>
    <?php if ($super): ?><td style="white-space:nowrap">
      <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn sm <?= $c['banned'] ? 'gr' : 'gh' ?>" name="action" value="ban"><?= $c['banned'] ? 'Unban' : 'Ban' ?></button>
        <button class="btn sm dg" name="action" value="delete" data-confirm="Delete the account of <?= e($c['full_name']) ?> permanently? Their past orders stay in the sales records.">Delete</button></form></td><?php endif; ?></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="8" class="mut">No customers found.</td></tr><?php endif; ?>
</tbody></table></div>
<?php page_end(); ?>
