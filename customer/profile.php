<?php
require_once __DIR__ . '/../config/database.php';
$u = require_role('customer');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $act = $_POST['action'] ?? '';
    if ($act === 'theme' && in_array($_POST['theme'] ?? '', ['auto', 'light', 'dark'], true)) {
        q('UPDATE users SET theme = ? WHERE id = ?', [$_POST['theme'], $u['id']]);
        setcookie('kk_theme', $_POST['theme'], cookie_opts(time() + 365 * 86400));
    } elseif ($act === 'profile') {
        $name = trim($_POST['full_name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? '')); $phone = trim($_POST['phone'] ?? '');
        try {
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter your name and a valid email.');
            if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) throw new RuntimeException('Enter a valid mobile number.');
            if (one('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $u['id']])) throw new RuntimeException('That email is used by another account.');
            $av = upload_image($_FILES['avatar'] ?? [], 'avatars');
            if ($av) delete_upload($u['avatar'], 'avatars');
            q('UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, avatar = ? WHERE id = ?', [$name, $email, $phone, trim($_POST['address'] ?? ''), $av ?: $u['avatar'], $u['id']]);
            flash('Profile saved.');
        } catch (RuntimeException $ex) { flash($ex->getMessage(), 'err'); }
    } elseif ($act === 'password') {
        $new = $_POST['new_password'] ?? '';
        if (!password_verify($_POST['current_password'] ?? '', $u['password_hash'])) flash('Current password is incorrect.', 'err');
        elseif (strlen($new) < 8) flash('New password must be at least 8 characters.', 'err');
        else { q('UPDATE users SET password_hash = ?, password_enc = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), enc_pw($new), $u['id']]); flash('Password changed.'); }
    }
    redirect('customer/profile.php');
}
page_start('Profile', 'profile');
$t = $u['theme'];
?>
<h1>Profile</h1>
<div class="card" style="max-width:640px"><h2>Appearance</h2><p class="mut" style="margin-top:0">Choose how the system looks for your account.</p>
  <form method="post" class="opts3" role="group" aria-label="Color theme"><?= csrf_field() ?><input type="hidden" name="action" value="theme">
    <?php foreach (['light' => ['Light mode', 'Bright and clean'], 'dark' => ['Dark mode', 'Easier on the eyes at night'], 'auto' => ['Match my device', 'Follows your phone or computer']] as $k => [$l, $d]): ?>
      <button name="theme" value="<?= $k ?>" class="opt" aria-pressed="<?= $t === $k ? 'true' : 'false' ?>"><?= e($l) ?><small><?= e($d) ?></small></button>
    <?php endforeach; ?>
  </form></div>
<div class="g2" style="align-items:start">
  <form class="card" method="post" enctype="multipart/form-data"><h2>Your details</h2><?= csrf_field() ?><input type="hidden" name="action" value="profile">
    <div class="f" style="display:flex;gap:14px;align-items:center"><?= avatar($u, 64) ?><div style="flex:1"><label for="avatar">Profile picture</label><input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp"></div></div>
    <div class="f"><label for="n">Full name</label><input id="n" name="full_name" value="<?= e($u['full_name']) ?>" required></div>
    <div class="f"><label for="e">Email</label><input id="e" type="email" name="email" value="<?= e($u['email']) ?>" required></div>
    <div class="f"><label for="p">Mobile number</label><input id="p" type="tel" name="phone" value="<?= e($u['phone']) ?>" required></div>
    <div class="f"><label for="a">Delivery address</label><textarea id="a" name="address"><?= e($u['address']) ?></textarea></div>
    <button class="btn">Save profile</button></form>
  <form class="card" method="post"><h2>Change password</h2><?= csrf_field() ?><input type="hidden" name="action" value="password">
    <div class="f"><label>Current password</label><?= pw_field('current_password', 'current-password') ?></div>
    <div class="f"><label>New password</label><?= pw_field('new_password', 'new-password', true, true) ?></div>
    <button class="btn">Change password</button></form>
</div>
<?php page_end(); ?>
