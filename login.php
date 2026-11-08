<?php
require_once __DIR__ . '/config/database.php';
if ($u = current_user()) redirect(home_for($u));

$email = strtolower(trim($_GET['email'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $ip = substr($_SERVER['REMOTE_ADDR'] ?? '0', 0, 45); $blocked = false;
    try { $blocked = (int)val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)', [$ip]) >= 8; } catch (PDOException $ex) {}
    $user = $blocked ? null : one('SELECT * FROM users WHERE email = ?', [$email]);
    if ($blocked) {
        flash('Too many failed attempts. Please wait 15 minutes and try again.', 'err');
    } elseif ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
        if ($user['banned']) {
            flash('This account has been banned. Please contact the shop.', 'err');
        } else {
            try { q('DELETE FROM login_attempts WHERE ip = ?', [$ip]); } catch (PDOException $ex) {}
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            if (!empty($_POST['remember'])) remember_set((int)$user['id']);
            redirect(home_for($user));
        }
    } else {
        try { q('INSERT INTO login_attempts (ip, email) VALUES (?, ?)', [$ip, substr($email, 0, 160)]); q('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)'); } catch (PDOException $ex) {}
        flash('Email or password is incorrect.', 'err');
    }
}
page_start('Log in');
echo intro_html();
?>
<form class="card auth" method="post" style="margin-top:0">
  <h2>Log in</h2>
  <?php if (!empty($_GET['registered'])): ?><p class="okmsg" role="status">Account created. Please log in with your email and password.</p><?php endif; ?>
  <?= csrf_field() ?>
  <div class="f"><label for="email">Email</label><input id="email" type="email" name="email" value="<?= e($email) ?>" required autocomplete="email"></div>
  <div class="f"><label>Password</label><?= pw_field('password', 'current-password') ?></div>
  <label class="chk"><input type="checkbox" name="remember" value="1"> Remember me for 7 days</label>
  <button class="btn blk">Log in</button>
  <p class="mut" style="margin:14px 0 0">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
</form>
<?php page_end(); ?>
