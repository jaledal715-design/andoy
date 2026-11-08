<?php
// One-time web installer. Open this page once after uploading the site, fill in the form, and it sets everything up.
// It deletes itself when it finishes. Do not leave it on your server.
declare(strict_types=1);
session_start();
$root = __DIR__;
$local = $root . '/config/local.php';
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function page(string $body): void {
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Install</title>'
        . '<link rel="stylesheet" href="assets/css/style.css"><style>body{padding:20px}.box{max-width:640px;margin:0 auto}.ok{color:#2b7a58;font-weight:700}.bad{color:#b3261e;font-weight:700}</style></head><body><div class="box">' . $body . '</div></body></html>';
}
function run_sql_file(PDO $pdo, string $file): void {
    $t = implode("\n", array_filter(explode("\n", (string)file_get_contents($file)), fn($l) => !str_starts_with(ltrim($l), '--')));
    foreach (preg_split('/;\s*\n/', $t) as $stmt) if (trim($stmt) !== '') $pdo->exec($stmt);
}

if (is_file($local)) {
    http_response_code(403);
    page('<div class="card"><h2>Already installed</h2><p>This website is already set up. For safety, delete <code>install.php</code> from your server.</p><a class="btn" href="login.php">Go to the login page</a></div>');
    exit;
}

$checks = [
    'PHP 8.0 or newer (this server has ' . PHP_VERSION . ')' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'MySQL support (pdo_mysql)' => extension_loaded('pdo_mysql'),
    'Text support (mbstring)' => extension_loaded('mbstring'),
    'Encryption support (openssl)' => extension_loaded('openssl'),
    'Image checking (fileinfo)' => extension_loaded('fileinfo'),
    'Folder config/ can be written' => is_writable($root . '/config'),
    'Upload folders can be written' => is_writable($root . '/uploads/products') && is_writable($root . '/uploads/proofs') && is_writable($root . '/uploads/avatars'),
];
$ready = !in_array(false, $checks, true);
$f = ['db_host' => 'localhost', 'db_name' => '', 'db_user' => '', 'shop' => 'Andoy Cakes & Pastries Corner', 'admin_name' => 'Shop Administrator', 'admin_email' => '', 'super_name' => 'Super Administrator', 'super_email' => ''];
$errors = [];
if (empty($_SESSION['ic'])) $_SESSION['ic'] = bin2hex(random_bytes(16));

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['ic'], (string)($_POST['ic'] ?? ''))) { $errors[] = 'Your session expired. Please try again.'; }
    foreach ($f as $k => $_) $f[$k] = trim((string)($_POST[$k] ?? ''));
    $f['admin_email'] = strtolower($f['admin_email']); $f['super_email'] = strtolower($f['super_email']);
    $dbPass = (string)($_POST['db_pass'] ?? ''); $apw = (string)($_POST['admin_pw'] ?? ''); $spw = (string)($_POST['super_pw'] ?? '');
    if ($f['db_name'] === '' || $f['db_user'] === '') $errors[] = 'Enter the database name and the database user.';
    if ($f['shop'] === '') $errors[] = 'Enter your shop name.';
    foreach (['admin' => 'admin', 'super' => 'super admin'] as $k => $label) {
        if ($f[$k . '_name'] === '') $errors[] = "Enter the $label name.";
        if (!filter_var($f[$k . '_email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Enter a valid $label email.";
    }
    if ($f['admin_email'] !== '' && $f['admin_email'] === $f['super_email']) $errors[] = 'The admin and super admin need different emails.';
    if (strlen($apw) < 8 || strlen($spw) < 8) $errors[] = 'Both passwords need at least 8 characters.';
    if (!$errors) {
        try {
            $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
            try {
                $pdo = new PDO("mysql:host={$f['db_host']};dbname={$f['db_name']};charset=utf8mb4", $f['db_user'], $dbPass, $opts);
            } catch (PDOException $first) {   // database may not exist yet: try to create it
                $pdo = new PDO("mysql:host={$f['db_host']};charset=utf8mb4", $f['db_user'], $dbPass, $opts);
                $name = str_replace('`', '', $f['db_name']);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `$name`");
            }
            run_sql_file($pdo, $root . '/install/schema.sql');
            if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) throw new RuntimeException('This database already contains an installation. Use an empty database.');
            if ((int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() === 0) run_sql_file($pdo, $root . '/install/seed.sql');
            $ins = $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash, role, is_super) VALUES (?, ?, '', ?, 'admin', ?)");
            $ins->execute([$f['admin_name'], $f['admin_email'], password_hash($apw, PASSWORD_DEFAULT), 0]);
            $ins->execute([$f['super_name'], $f['super_email'], password_hash($spw, PASSWORD_DEFAULT), 1]);
            $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'shop_name'")->execute([$f['shop']]);
            $cfg = "<?php\n// Created by install.php. Keep this file private.\n"
                . 'define(\'DB_HOST\', ' . var_export($f['db_host'], true) . ");\n"
                . 'define(\'DB_NAME\', ' . var_export($f['db_name'], true) . ");\n"
                . 'define(\'DB_USER\', ' . var_export($f['db_user'], true) . ");\n"
                . 'define(\'DB_PASS\', ' . var_export($dbPass, true) . ");\n"
                . 'define(\'ENC_KEY\', ' . var_export(bin2hex(random_bytes(32)), true) . ");\n";
            if (file_put_contents($local, $cfg, LOCK_EX) === false) throw new RuntimeException('The database was set up but config/local.php could not be written. Make the config folder writable and run the installer again.');
            @chmod($local, 0640);
            unset($_SESSION['ic']);
            $deleted = @unlink(__FILE__);
            page('<div class="card"><h2>Installation complete</h2><p class="ok">Your ordering system is ready.</p><ul>'
                . '<li>Log in at the login page with the admin or super admin email and the password you just chose.</li>'
                . '<li>Open <strong>Admin &rarr; Settings</strong> and enter your real GCash number, name, address and delivery fee.</li>'
                . '<li>' . ($deleted ? '<span class="ok">install.php was deleted automatically.</span>' : '<span class="bad">Please delete install.php from your server now (it could not delete itself).</span>') . '</li>'
                . '<li>If you uploaded database.sql, delete it from the server too.</li></ul><a class="btn" href="login.php">Go to the login page</a></div>');
            exit;
        } catch (Throwable $ex) {
            $errors[] = $ex instanceof PDOException ? 'Could not set up the database: ' . $ex->getMessage() : $ex->getMessage();
        }
    }
}

$body = '<h1>Set up your ordering system</h1><p class="mut">This takes about a minute. You need an empty MySQL database (create one in your hosting control panel first).</p>'
    . '<div class="card"><h2>1. Server check</h2>';
foreach ($checks as $label => $pass) $body .= '<div class="row"><span>' . h($label) . '</span><span class="' . ($pass ? 'ok' : 'bad') . '">' . ($pass ? 'OK' : 'Needs fixing') . '</span></div>';
$body .= '</div>';
if (!$ready) {
    $body .= '<div class="card"><p class="bad">Please fix the items marked "Needs fixing" (ask your host, or make the folders writable), then reload this page.</p></div>';
} else {
    if ($errors) $body .= '<div class="flash flash-err" role="alert">' . implode('<br>', array_map('h', $errors)) . '</div>';
    $in = fn($label, $name, $val, $type = 'text', $hint = '') => '<div class="f"><label for="' . $name . '">' . $label . '</label><input id="' . $name . '" name="' . $name . '" type="' . $type . '" value="' . h($val) . '" required' . ($type === 'password' ? ' minlength="8" autocomplete="new-password"' : '') . '>' . ($hint ? '<div class="hint">' . $hint . '</div>' : '') . '</div>';
    $body .= '<form method="post" autocomplete="off"><input type="hidden" name="ic" value="' . h($_SESSION['ic']) . '">'
        . '<div class="card"><h2>2. Database</h2>' . $in('Database host', 'db_host', $f['db_host'], 'text', 'Usually "localhost".')
        . '<div class="g2">' . $in('Database name', 'db_name', $f['db_name']) . $in('Database user', 'db_user', $f['db_user']) . '</div>'
        . '<div class="f"><label for="db_pass">Database password</label><input id="db_pass" name="db_pass" type="password" autocomplete="new-password"><div class="hint">Leave empty if your database has no password (common on localhost).</div></div></div>'
        . '<div class="card"><h2>3. Your shop</h2>' . $in('Shop name', 'shop', $f['shop']) . '</div>'
        . '<div class="card"><h2>4. Admin account</h2><p class="mut" style="margin-top:0">Runs the shop day to day.</p>' . $in('Full name', 'admin_name', $f['admin_name']) . $in('Email', 'admin_email', $f['admin_email'], 'email') . $in('Password', 'admin_pw', '', 'password', 'At least 8 characters.') . '</div>'
        . '<div class="card"><h2>5. Super admin account</h2><p class="mut" style="margin-top:0">The owner account: creates admins, sees all sales, manages customer accounts.</p>' . $in('Full name', 'super_name', $f['super_name']) . $in('Email', 'super_email', $f['super_email'], 'email') . $in('Password', 'super_pw', '', 'password', 'At least 8 characters. Use a different password from the admin.') . '</div>'
        . '<button class="btn blk">Install now</button></form>';
}
page($body);
