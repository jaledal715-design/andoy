<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/chat.php';
$u = require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $cid = (int)($_POST['cid'] ?? 0);
    if (one("SELECT id FROM users WHERE id = ? AND role = 'customer'", [$cid])) chat_send($u, $cid, $_POST['text'] ?? '');
    redirect('admin/messages.php?cid=' . $cid);
}
$qs = trim($_GET['q'] ?? '');
$cs = all("SELECT u.*, (SELECT body FROM messages m WHERE m.customer_id = u.id ORDER BY m.id DESC LIMIT 1) AS last_body,
    (SELECT MAX(id) FROM messages m WHERE m.customer_id = u.id) AS last_id,
    (SELECT COUNT(*) FROM messages m WHERE m.customer_id = u.id AND m.from_role = 'customer' AND m.is_read = 0) AS unread
    FROM users u WHERE u.role = 'customer' ORDER BY last_id IS NULL, last_id DESC, u.full_name");
$sel = (int)($_GET['cid'] ?? 0);
if (!$sel || !array_filter($cs, fn($c) => (int)$c['id'] === $sel)) $sel = $cs ? (int)$cs[0]['id'] : 0;
if ($qs !== '') {
    $hits = array_column(all('SELECT DISTINCT customer_id FROM messages WHERE body LIKE ?', ["%$qs%"]), 'customer_id');
    $list = array_values(array_filter($cs, fn($c) => mb_stripos($c['full_name'] . ' ' . $c['email'], $qs) !== false || in_array($c['id'], $hits)));
} else $list = $cs;
if ($sel) chat_mark_read($u, $sel);
$draft = !empty($_GET['order']) ? 'About your order ' . preg_replace('/[^A-Za-z0-9\-]/', '', $_GET['order']) . ': ' : '';
page_start('Messages', 'messages');
?>
<h1>Messages</h1>
<?php if (!$cs): ?><div class="empty">No customers yet. Messages appear here once customers sign up.</div><?php else: ?>
<div class="mg">
  <div class="card" style="padding:8px;margin:0">
    <form method="get" style="margin-bottom:8px"><input type="hidden" name="cid" value="<?= $sel ?>"><input type="search" name="q" value="<?= e($qs) ?>" placeholder="Search names or messages" aria-label="Search messages"></form>
    <?php foreach ($list as $c): ?>
      <a class="nt <?= (int)$c['id'] === $sel ? 'new' : '' ?>" href="<?= e(url('admin/messages.php?cid=' . $c['id'] . ($qs !== '' ? '&q=' . urlencode($qs) : ''))) ?>" style="margin-bottom:4px;text-decoration:none;color:inherit">
        <span><?= avatar($c, 24) ?> <strong><?= hl($c['full_name'], $qs) ?></strong><?php if ($c['unread'] && (int)$c['id'] !== $sel): ?><span class="cnt"><?= (int)$c['unread'] ?></span><?php endif; ?></span>
        <small class="mut"><?= $c['last_body'] ? hl(mb_substr($c['last_body'], 0, 60), $qs) : 'No messages yet' ?></small></a>
    <?php endforeach; if (!$list): ?><div class="empty" style="padding:16px">No conversations match “<?= e($qs) ?>”.</div><?php endif; ?>
  </div>
  <?= $sel ? chat_box($u, $sel, $qs, $draft, url('admin/messages.php'), false) : '' ?>
</div>
<?php endif; ?>
<?php page_end(); ?>
