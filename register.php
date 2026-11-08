<?php
require_once __DIR__ . '/config/database.php';
if (current_user()) redirect('index.php');

$f = ['full_name' => '', 'email' => '', 'phone' => '', 'address' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($f as $k => $_) $f[$k] = trim($_POST[$k] ?? '');
    $f['email'] = strtolower($f['email']);
    $pw = $_POST['password'] ?? '';
    $err = [];
    if ($f['full_name'] === '') $err[] = 'Enter your full name.';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Enter a valid email address.';
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $f['phone'])) $err[] = 'Enter a valid mobile number.';
    if (strlen($pw) < 8) $err[] = 'Password must be at least 8 characters.';
    if (!$err && one('SELECT id FROM users WHERE email = ?', [$f['email']])) $err[] = 'That email is already registered. Try logging in.';
    $avatar = null;
    if (!$err) { try { $avatar = upload_image($_FILES['avatar'] ?? [], 'avatars'); } catch (RuntimeException $ex) { $err[] = $ex->getMessage(); } }
    if (!$err) {
        q("INSERT INTO users (full_name, email, phone, address, password_hash, password_enc, role, fresh, avatar) VALUES (?,?,?,?,?,?, 'customer', 1, ?)",
          [$f['full_name'], $f['email'], $f['phone'], $f['address'], password_hash($pw, PASSWORD_DEFAULT), enc_pw($pw), $avatar]);
        notify_admin('New customer registered: ' . $f['full_name'] . '.');
        redirect('login.php?registered=1&email=' . urlencode($f['email']));   // back to the login page
    }
    foreach ($err as $m) flash($m, 'err');
}
page_start('Create account');
?>
<form class="card auth" method="post" enctype="multipart/form-data" style="max-width:520px">
  <h2>Create your account</h2>
  <?= csrf_field() ?>
  <div class="f"><label for="avatar">Profile picture (optional)</label><input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp"></div>
  <div class="f"><label for="full_name">Full name</label><input id="full_name" name="full_name" value="<?= e($f['full_name']) ?>" required></div>
  <div class="g2">
    <div class="f"><label for="email">Email</label><input id="email" type="email" name="email" value="<?= e($f['email']) ?>" required></div>
    <div class="f"><label for="phone">Mobile number</label><input id="phone" type="tel" name="phone" value="<?= e($f['phone']) ?>" required></div>
  </div>
  <div class="f"><label for="address">Delivery address (optional)</label><textarea id="address" name="address"><?= e($f['address']) ?></textarea></div>
  <div class="f"><label>Password</label><?= pw_field('password', 'new-password', true, true) ?><div class="hint">At least 8 characters.</div></div>
  <button class="btn blk">Create account</button>
  <p class="mut" style="margin:14px 0 0">Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
</form>
<?php page_end(); ?>
