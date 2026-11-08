<?php
require_once __DIR__ . '/../config/database.php';
$u = require_role('super');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $act = $_POST['action'] ?? '';
    if ($act === 'create') {
        $name = trim($_POST['full_name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? '')); $pw = $_POST['password'] ?? '';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) flash('Enter a name and a valid email.', 'err');
        elseif (strlen($pw) < 8) flash('Password must be at least 8 characters.', 'err');
        elseif (one('SELECT id FROM users WHERE email = ?', [$email])) flash('That email is already used by another account.', 'err');
        else { q("INSERT INTO users (full_name, email, phone, password_hash, password_enc, role) VALUES (?,?,?,?,?, 'admin')", [$name, $email, trim($_POST['phone'] ?? ''), password_hash($pw, PASSWORD_DEFAULT), enc_pw($pw)]); flash('Admin account created for ' . $name . '.'); }
    } else {
        $a = one("SELECT * FROM users WHERE id = ? AND role = 'admin' AND is_super = 0", [(int)($_POST['id'] ?? 0)]);
        if ($a && $act === 'reset') {
            $pw = $_POST['password'] ?? '';
            if (strlen($pw) < 8) flash('Password must be at least 8 characters.', 'err');
            else { q('UPDATE users SET password_hash = ?, password_enc = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), enc_pw($pw), $a['id']]); q('DELETE FROM remember_tokens WHERE user_id = ?', [$a['id']]); flash('Password updated for ' . $a['full_name'] . '.'); }
        } elseif ($a && $act === 'remove') { q('DELETE FROM users WHERE id = ?', [$a['id']]); flash('Admin account removed.'); }
    }
    redirect('admin/admins.php');
}
$admins = all("SELECT * FROM users WHERE role = 'admin' ORDER BY is_super DESC, full_name");
$show = (int)($_GET['show'] ?? 0);
page_start('Admin accounts', 'admins');
?>
<h1>Admin accounts</h1>
<p class="sub">Only the super admin can view admin passwords, create, reset or remove admin accounts. Passwords are stored encrypted for this feature.</p>
<div class="cols">
  <div class="tw"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Password</th><th></th></tr></thead><tbody>
  <?php foreach ($admins as $a): ?>
    <tr><td><?= avatar($a, 26) ?> <strong><?= e($a['full_name']) ?></strong></td><td><?= e($a['email']) ?></td>
      <td><?= $a['is_super'] ? '<span class="b confirmed">Super admin</span>' : '<span class="b preparing">Admin</span>' ?></td>
      <td style="white-space:nowrap"><?php if ($a['is_super']): ?><span class="mut">—</span><?php else: $on = $show === (int)$a['id']; ?>
        <code><?= $on ? e(dec_pw($a['password_enc']) ?: '(not available; reset password to enable)') : '••••••••' ?></code>
        <a class="btn sm gh" href="<?= e(url('admin/admins.php' . ($on ? '' : '?show=' . (int)$a['id']))) ?>" aria-label="<?= $on ? 'Hide' : 'Show' ?> password for <?= e($a['full_name']) ?>"><?= $on ? 'Hide' : 'Show' ?></a>
      <?php endif; ?></td>
      <td><?php if ((int)$a['id'] === (int)$u['id']): ?><span class="mut">You</span><?php elseif (!$a['is_super']): ?>
        <form method="post" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <input type="password" name="password" minlength="8" placeholder="New password" aria-label="New password for <?= e($a['full_name']) ?>" autocomplete="new-password" style="width:140px;padding:6px 10px">
          <button class="btn sm gh" name="action" value="reset">Save password</button><button class="btn sm dg" name="action" value="remove" data-confirm="Remove admin account <?= e($a['full_name']) ?>?">Remove</button></form><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <form class="card" method="post"><h2>Create admin account</h2><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="f"><label for="n">Full name</label><input id="n" name="full_name" required></div>
    <div class="f"><label for="e">Email</label><input id="e" type="email" name="email" required></div>
    <div class="f"><label for="p">Mobile number (optional)</label><input id="p" type="tel" name="phone"></div>
    <div class="f"><label>Password</label><?= pw_field('password', 'new-password', true, true) ?><div class="hint">At least 8 characters. Share it with the new admin privately.</div></div>
    <button class="btn blk">Create admin</button></form>
</div>
<?php page_end(); ?>
