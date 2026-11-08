<?php
require_once __DIR__ . '/../config/database.php';
$u = require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $act = $_POST['action'] ?? '';
    if ($act === 'theme' && in_array($_POST['theme'] ?? '', ['auto', 'light', 'dark'], true)) {
        q('UPDATE users SET theme = ? WHERE id = ?', [$_POST['theme'], $u['id']]);
        setcookie('kk_theme', $_POST['theme'], cookie_opts(time() + 365 * 86400));
    } elseif ($act === 'shop') {
        $fee = (float)($_POST['delivery_fee'] ?? 0);
        if (trim($_POST['shop_name'] ?? '') === '') flash('Enter a shop name.', 'err');
        elseif ($fee < 0) flash('Delivery fee cannot be negative.', 'err');
        else { foreach (['shop_name', 'shop_address', 'shop_phone', 'gcash_number', 'gcash_name', 'delivery_note'] as $k) save_setting($k, trim($_POST[$k] ?? '')); save_setting('delivery_fee', number_format($fee, 2, '.', '')); flash('Settings saved.'); }
    } elseif ($act === 'intro') {
        foreach (array_keys(intro_defaults()) as $k) save_setting($k, mb_substr(trim($_POST[$k] ?? ''), 0, $k === 'intro_text' ? 400 : 120));
        if (trim($_POST['intro_title'] ?? '') === '') save_setting('intro_title', intro_defaults()['intro_title']);
        flash('Login page text saved.');
    } elseif ($act === 'intro_reset') { q("DELETE FROM settings WHERE setting_key LIKE 'intro\\_%'"); flash('Login page text restored to the default.'); }
    elseif ($act === 'password') {
        $new = $_POST['new_password'] ?? '';
        if (!password_verify($_POST['current_password'] ?? '', $u['password_hash'])) flash('Current password is incorrect.', 'err');
        elseif (strlen($new) < 8) flash('New password must be at least 8 characters.', 'err');
        else { q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]); flash('Admin password changed.'); }
    }
    redirect('admin/settings.php');
}
page_start('Settings', 'settings');
$t = $u['theme'];
?>
<h1>Settings</h1>
<div class="card" style="max-width:640px"><h2>Appearance</h2><p class="mut" style="margin-top:0">Choose how the system looks for your account.</p>
  <form method="post" class="opts3" role="group" aria-label="Color theme"><?= csrf_field() ?><input type="hidden" name="action" value="theme">
    <?php foreach (['light' => ['Light mode', 'Bright and clean'], 'dark' => ['Dark mode', 'Easier on the eyes at night'], 'auto' => ['Match my device', 'Follows your phone or computer']] as $k => [$l, $d]): ?>
      <button name="theme" value="<?= $k ?>" class="opt" aria-pressed="<?= $t === $k ? 'true' : 'false' ?>"><?= e($l) ?><small><?= e($d) ?></small></button><?php endforeach; ?></form></div>
<form class="card" method="post" style="max-width:640px"><h2>Shop and payment details</h2><?= csrf_field() ?><input type="hidden" name="action" value="shop">
  <div class="f"><label for="a">Shop name</label><input id="a" name="shop_name" value="<?= e(setting('shop_name')) ?>" required></div>
  <div class="f"><label for="b">Pickup address</label><input id="b" name="shop_address" value="<?= e(setting('shop_address')) ?>"></div>
  <div class="f"><label for="c">Shop phone</label><input id="c" name="shop_phone" value="<?= e(setting('shop_phone')) ?>"></div>
  <div class="g2"><div class="f"><label for="d">GCash number</label><input id="d" name="gcash_number" value="<?= e(setting('gcash_number')) ?>"></div><div class="f"><label for="e">GCash account name</label><input id="e" name="gcash_name" value="<?= e(setting('gcash_name')) ?>"></div></div>
  <div class="g2"><div class="f"><label for="f">Delivery fee (₱)</label><input id="f" type="number" step="0.01" min="0" name="delivery_fee" value="<?= e(setting('delivery_fee')) ?>"></div><div class="f"><label for="g">Delivery note</label><input id="g" name="delivery_note" value="<?= e(setting('delivery_note')) ?>"></div></div>
  <button class="btn">Save settings</button></form>
<form class="card" method="post" style="max-width:640px"><h2>Login page introduction</h2><p class="mut" style="margin-top:0">This text appears at the top of the login page. Type {shop} to insert your shop name.</p><?= csrf_field() ?><input type="hidden" name="action" value="intro">
  <div class="f"><label for="i1">Small tagline (above the heading)</label><input id="i1" name="intro_tag" maxlength="120" value="<?= e(intro('intro_tag')) ?>"></div>
  <div class="f"><label for="i2">Heading</label><input id="i2" name="intro_title" maxlength="120" required value="<?= e(intro('intro_title')) ?>"></div>
  <div class="f"><label for="i3">Introduction paragraph</label><textarea id="i3" name="intro_text" maxlength="400"><?= e(intro('intro_text')) ?></textarea></div>
  <?php foreach ([1, 2, 3] as $n): ?><div class="g2"><div class="f"><label>Highlight <?= $n ?> title</label><input name="intro_t<?= $n ?>" maxlength="40" value="<?= e(intro("intro_t$n")) ?>"></div><div class="f"><label>Highlight <?= $n ?> text</label><input name="intro_d<?= $n ?>" maxlength="90" value="<?= e(intro("intro_d$n")) ?>"></div></div><?php endforeach; ?>
  <button class="btn">Save login text</button> <button class="btn gh" name="action" value="intro_reset" data-confirm="Restore the default login text?">Restore default</button>
  <p class="hint">Log out to see how it looks on the login page. Leave a highlight empty to hide it.</p></form>
<form class="card" method="post" style="max-width:640px"><h2>Change admin password</h2><?= csrf_field() ?><input type="hidden" name="action" value="password">
  <div class="f"><label>Current password</label><?= pw_field('current_password', 'current-password') ?></div>
  <div class="f"><label>New password</label><?= pw_field('new_password', 'new-password', true, true) ?></div>
  <button class="btn">Change password</button></form>
<?php page_end(); ?>
