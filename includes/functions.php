<?php
if (!defined('ROOT_PATH')) exit;
// Shared helpers: escaping, database shortcuts, auth, settings, uploads, orders, ratings, layout.

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($n): string { return '₱' . number_format((float)$n, 2); }
function url(string $p = ''): string { return BASE_URL . '/' . ltrim($p, '/'); }
function redirect(string $p): never { header('Location: ' . (str_starts_with($p, 'http') ? $p : url($p))); exit; }
function flash(string $m, string $t = 'ok'): void { $_SESSION['flash'][] = [$t, $m]; }
function flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !(isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']))) {
        http_response_code(419); exit('Your session expired. Go back, refresh the page, and try again.');
    }
}

/* ---------- Database shortcuts ---------- */
function q(string $sql, array $a = []): PDOStatement { global $pdo; $s = $pdo->prepare($sql); $s->execute($a); return $s; }
function one(string $sql, array $a = []): ?array { $r = q($sql, $a)->fetch(); return $r ?: null; }
function all(string $sql, array $a = []): array { return q($sql, $a)->fetchAll(); }
function val(string $sql, array $a = []) { return q($sql, $a)->fetchColumn(); }
function last_id(): int { global $pdo; return (int)$pdo->lastInsertId(); }

/* ---------- Passwords the super admin may view (encrypted, never plain) ---------- */
function enc_pw(string $pw): string {
    $iv = random_bytes(16);
    return base64_encode($iv . openssl_encrypt($pw, 'aes-256-cbc', hash('sha256', ENC_KEY, true), OPENSSL_RAW_DATA, $iv));
}
function dec_pw(?string $b): string {
    $raw = $b ? base64_decode($b, true) : false;
    if ($raw === false || strlen($raw) < 17) return '';
    $p = openssl_decrypt(substr($raw, 16), 'aes-256-cbc', hash('sha256', ENC_KEY, true), OPENSSL_RAW_DATA, substr($raw, 0, 16));
    return $p === false ? '' : $p;
}

function cookie_opts(int $expires): array { return ['expires' => $expires, 'path' => '/', 'httponly' => true, 'secure' => IS_HTTPS, 'samesite' => 'Lax']; }

/* ---------- Auth ---------- */
function home_for(array $u): string { return $u['role'] === 'admin' ? 'admin/dashboard.php' : 'customer/products.php'; }
function current_user(): ?array {
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['user_id'])) $u = one('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
        else $u = remember_login();
        if ($u && $u['banned']) { $_SESSION = []; $u = null; }
    }
    return $u;
}
function require_role(string $role): array {   // 'customer' | 'admin' | 'super'
    $u = current_user();
    if (!$u) { flash('Please log in to continue.', 'err'); redirect('login.php'); }
    if ($role === 'customer' && $u['role'] !== 'customer') redirect('admin/dashboard.php');
    if ($role !== 'customer' && $u['role'] !== 'admin') redirect('customer/products.php');
    if ($role === 'super' && !$u['is_super']) redirect('admin/dashboard.php');
    return $u;
}
function remember_set(int $uid): void {
    $tok = bin2hex(random_bytes(32));
    q('DELETE FROM remember_tokens WHERE expires_at < NOW()');
    q('INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 7 DAY))', [$uid, hash('sha256', $tok)]);
    setcookie('kk_remember', $uid . ':' . $tok, cookie_opts(time() + 7 * 86400));
}
function remember_login(): ?array {
    if (empty($_COOKIE['kk_remember'])) return null;
    [$id, $tok] = array_pad(explode(':', (string)$_COOKIE['kk_remember'], 2), 2, '');
    if (!one('SELECT id FROM remember_tokens WHERE user_id = ? AND token_hash = ? AND expires_at > NOW()', [(int)$id, hash('sha256', $tok)])) return null;
    $u = one('SELECT * FROM users WHERE id = ?', [(int)$id]);
    if (!$u || $u['banned']) return null;
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$u['id'];
    return $u;
}
function remember_clear(): void {
    if (!empty($_COOKIE['kk_remember'])) {
        [$id, $tok] = array_pad(explode(':', (string)$_COOKIE['kk_remember'], 2), 2, '');
        q('DELETE FROM remember_tokens WHERE user_id = ? AND token_hash = ?', [(int)$id, hash('sha256', $tok)]);
    }
    setcookie('kk_remember', '', cookie_opts(time() - 3600));
}

/* ---------- Settings & login introduction text ---------- */
function settings_all(): array { static $c = null; if ($c === null) $c = array_column(all('SELECT setting_key, setting_value FROM settings'), 'setting_value', 'setting_key'); return $c; }
function setting(string $k, string $d = ''): string { return settings_all()[$k] ?? $d; }
function save_setting(string $k, string $v): void { q('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$k, $v]); }
function intro_defaults(): array {
    return ['intro_tag' => 'Locally handmade native delicacies', 'intro_title' => 'Welcome to {shop}',
        'intro_text' => 'Classic Filipino rice cakes, handmade and ready for your table. Order online, pay by GCash or cash, and pick up or have it delivered.',
        'intro_t1' => 'Handmade fresh', 'intro_d1' => 'Native delicacies made with care.',
        'intro_t2' => 'Pickup or delivery', 'intro_d2' => 'Choose the date that works for you.',
        'intro_t3' => 'GCash or cash', 'intro_d3' => 'Pay your way and track every step.'];
}
function intro(string $k): string { $a = settings_all(); return array_key_exists($k, $a) ? $a[$k] : intro_defaults()[$k]; }
function intro_html(): string {
    $r = fn($t) => e(str_replace('{shop}', setting('shop_name'), $t));
    $h = '<div class="hero">' . (intro('intro_tag') !== '' ? '<div class="tag">' . $r(intro('intro_tag')) . '</div>' : '') . '<h1>' . $r(intro('intro_title')) . '</h1>' . (intro('intro_text') !== '' ? '<p>' . $r(intro('intro_text')) . '</p>' : '') . '</div>';
    $f = '';
    foreach ([1, 2, 3] as $n) if (intro("intro_t$n") !== '' || intro("intro_d$n") !== '') $f .= '<div><b>' . $r(intro("intro_t$n")) . '</b>' . $r(intro("intro_d$n")) . '</div>';
    return $h . ($f ? '<div class="feat">' . $f . '</div>' : '');
}

/* ---------- Uploads (images only) ---------- */
function upload_image(array $file, string $folder): ?string {
    if (empty($file['name']) || ($file['error'] ?? 0) === UPLOAD_ERR_NO_FILE) return null;
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) throw new RuntimeException('Image is too large. Please choose a smaller photo or screenshot.');
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('The image could not be uploaded. Please try again.');
    if ($file['size'] > 3 * 1024 * 1024) throw new RuntimeException('Image is too large. Maximum size is 3 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) throw new RuntimeException('Use a JPG, PNG, or WEBP image.');
    $dir = ROOT_PATH . '/uploads/' . $folder;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Could not save the image.');
    return $name;
}
function delete_upload(?string $name, string $folder): void {
    if ($name && preg_match('/^[a-f0-9]+\.(jpg|png|webp)$/', $name)) @unlink(ROOT_PATH . "/uploads/$folder/$name");
}
function thumb(array $p, string $class = 'th'): string {
    if (!empty($p['image'])) return '<img class="' . $class . '" src="' . e(url('uploads/products/' . $p['image'])) . '" alt="' . e($p['name']) . '" loading="lazy">';
    $h = (crc32($p['name']) % 55) + 10;
    return '<div class="' . $class . ' ph" style="--h:' . $h . '"><span>' . e(mb_strtoupper(mb_substr($p['name'], 0, 1))) . '</span></div>';
}
function avatar(array $u, int $s = 28): string {
    if (!empty($u['avatar'])) return '<img class="av" src="' . e(url('uploads/avatars/' . $u['avatar'])) . '" alt="" style="width:' . $s . 'px;height:' . $s . 'px">';
    return '<span class="av" style="width:' . $s . 'px;height:' . $s . 'px;font-size:' . round($s * .45) . 'px">' . e(mb_strtoupper(mb_substr($u['full_name'] ?? '?', 0, 1))) . '</span>';
}
function pw_field(string $name = 'password', string $ac = 'current-password', bool $req = true, bool $min8 = false): string {
    $eye = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
    return '<div class="pw"><input name="' . e($name) . '" type="password" ' . ($min8 ? 'minlength="8" ' : '') . ($req ? 'required ' : '') . 'autocomplete="' . e($ac) . '"><button type="button" class="eye" data-eye aria-label="Show password" aria-pressed="false">' . $eye . '</button></div>';
}

/* ---------- Notifications ---------- */
function notify_admin(string $msg, int $orderId = 0): void { q('INSERT INTO notifications (for_admin, user_id, order_id, message) VALUES (1, NULL, ?, ?)', [$orderId ?: null, mb_substr($msg, 0, 500)]); }
function notify_user(int $uid, string $msg, int $orderId = 0): void { q('INSERT INTO notifications (for_admin, user_id, order_id, message) VALUES (0, ?, ?, ?)', [$uid, $orderId ?: null, mb_substr($msg, 0, 500)]); }

/* ---------- Orders ---------- */
const ORDER_STATUSES = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'preparing' => 'Preparing', 'ready' => 'Ready for pickup', 'out_for_delivery' => 'Out for delivery', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
const STATUS_MSG = ['confirmed' => 'was confirmed', 'preparing' => 'is being prepared', 'ready' => 'is ready for pickup', 'out_for_delivery' => 'is out for delivery', 'completed' => 'was completed. Thank you!', 'cancelled' => 'was cancelled'];
const NEXT_LABEL = ['confirmed' => 'Confirm order', 'preparing' => 'Start preparing', 'ready' => 'Mark ready for pickup', 'out_for_delivery' => 'Mark out for delivery', 'completed' => 'Mark completed'];
const SLOTS = ['Morning (8 AM – 12 PM)', 'Afternoon (12 – 5 PM)', 'Evening (5 – 8 PM)'];
const TABS = ['all' => 'All', 'topay' => 'To pay', 'toship' => 'To ship', 'shipped' => 'To receive', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
function status_steps(string $f): array { return $f === 'delivery' ? ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'completed'] : ['pending', 'confirmed', 'preparing', 'ready', 'completed']; }
function next_status(array $o): ?string { $s = status_steps($o['fulfillment']); $i = array_search($o['status'], $s, true); return ($i !== false && $i < count($s) - 1) ? $s[$i + 1] : null; }
function order_cat(array $o): string {
    if ($o['status'] === 'cancelled') return 'cancelled';
    if ($o['status'] === 'completed') return 'completed';
    if (in_array($o['status'], ['ready', 'out_for_delivery'], true)) return 'shipped';
    if ($o['pay_method'] === 'gcash' && $o['pay_status'] !== 'verified') return 'topay';
    return 'toship';
}
function order_stx(array $o): string {
    $c = $o['cat'];
    if ($c === 'topay') return $o['pay_status'] === 'rejected' ? 'Payment rejected' : ($o['proof_image'] ? 'Verifying payment' : 'To pay');
    if ($c === 'shipped') return $o['fulfillment'] === 'delivery' ? 'Out for delivery' : 'Ready for pickup';
    if ($c === 'toship') return 'To ship · ' . ORDER_STATUSES[$o['status']];
    return ORDER_STATUSES[$o['status']];
}
function status_badge(string $s): string { return '<span class="b ' . e($s) . '">' . e(ORDER_STATUSES[$s] ?? $s) . '</span>'; }
function pay_badge(array $o): string {
    $s = $o['pay_status'];
    return '<span class="b pay_' . e($s) . '">' . e(['pending' => 'Awaiting payment check', 'verified' => 'Paid', 'rejected' => 'Rejected'][$s] ?? $s) . '</span>';
}
function new_order_number(): string { return 'AC-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2))); }
function set_order_status(array $o, string $new): void {
    q('UPDATE orders SET status = ? WHERE id = ?', [$new, $o['id']]);
    if ($new === 'completed') q("UPDATE payments SET status = 'verified', verified_at = NOW() WHERE order_id = ? AND method = 'cash'", [$o['id']]);
    if ($o['user_id']) notify_user((int)$o['user_id'], 'Your order ' . $o['order_number'] . ' ' . STATUS_MSG[$new] . '.', (int)$o['id']);
}
function load_orders(string $where = '1=1', array $args = [], string $limit = ''): array {
    $orders = all("SELECT o.*, COALESCE(u.full_name, o.customer_name) AS cname, u.email AS cemail, u.avatar AS cavatar, u.id AS cid,
        p.method AS pay_method, p.status AS pay_status, p.proof_image, p.reference_no, p.admin_note
        FROM orders o LEFT JOIN users u ON u.id = o.user_id JOIN payments p ON p.order_id = o.id WHERE $where ORDER BY o.id DESC $limit", $args);
    if (!$orders) return [];
    $in = implode(',', array_map('intval', array_column($orders, 'id')));
    $by = [];
    foreach (all("SELECT oi.*, pr.image FROM order_items oi LEFT JOIN products pr ON pr.id = oi.product_id WHERE oi.order_id IN ($in)") as $i) $by[$i['order_id']][] = $i;
    foreach ($orders as &$o) { $o['items'] = $by[$o['id']] ?? []; $o['cat'] = order_cat($o); }
    return $orders;
}
function order_tabs(array $orders, string $tab, string $base, bool $admin): string {
    $h = '<div class="tb" role="tablist">';
    foreach (TABS as $k => $label) {
        $n = $k === 'all' ? 0 : count(array_filter($orders, fn($o) => $o['cat'] === $k));
        $h .= '<a role="tab" class="' . ($tab === $k ? 'on' : '') . '" href="' . e(url($base . '?tab=' . $k)) . '">' . e($admin && $k === 'topay' ? 'To verify' : $label) . ($n ? ' <i class="cnt" style="background:var(--o)">' . $n . '</i>' : '') . '</a>';
    }
    return $h . '</div>';
}
function order_cards(array $orders, bool $admin): string {
    if (!$orders) return '<div class="empty">No orders in this tab yet.</div>';
    $h = '';
    foreach ($orders as $o) {
        $c = $o['cat']; $live = $c !== 'completed' && $c !== 'cancelled'; $n = next_status($o);
        $h .= '<article class="oc"><div class="oh">' . ($admin ? avatar(['avatar' => $o['cavatar'], 'full_name' => $o['cname']], 26) : '<span class="mk" style="width:24px;height:24px;font-size:.8rem">A</span>')
            . '<strong>' . e($admin ? $o['cname'] : setting('shop_name')) . '</strong><span class="mut" style="margin-right:auto;font-size:.8rem">' . e($o['order_number']) . ' · ' . e(date('M j, Y g:i A', strtotime($o['created_at']))) . '</span><span class="os ' . e($c) . '">' . e(order_stx($o)) . '</span></div>';
        $qty = 0;
        foreach ($o['items'] as $i) {
            $qty += (int)$i['quantity'];
            $h .= '<div class="oi">' . thumb(['name' => $i['product_name'], 'image' => $i['image']]) . '<div style="flex:1"><div>' . e($i['product_name']) . '</div><div class="mut" style="font-size:.85rem">' . ($o['fulfillment'] === 'delivery' ? 'Delivery' : 'Pickup') . ' · ' . e(date('D, M j, Y', strtotime($o['due_date']))) . ($o['due_slot'] ? ' · ' . e($o['due_slot']) : '') . '</div></div><div class="r"><div>' . money($i['unit_price']) . '</div><div class="mut">x' . (int)$i['quantity'] . '</div></div></div>';
        }
        $h .= '<div class="of"><div class="mut">' . $qty . ' items · ' . ($o['pay_method'] === 'gcash' ? 'GCash' : 'Cash') . ' ' . pay_badge($o) . '</div><div>Order total: <span class="pr">' . money($o['total']) . '</span></div></div>';
        if ($admin) {
            $h .= '<div class="od"><div>Customer: <strong>' . e($o['cname']) . '</strong> · ' . e($o['cemail'] ?? 'account deleted') . ' · ' . e($o['contact_phone']) . '</div><div>Payment: <strong>' . ($o['pay_method'] === 'gcash' ? 'GCash' : 'Cash') . '</strong> ' . pay_badge($o) . ' · ' . money($o['total'])
                . ($o['pay_method'] === 'gcash' && !$o['proof_image'] ? ' · <em>No screenshot uploaded yet</em>' : '') . ($o['reference_no'] ? ' · Ref ' . e($o['reference_no']) : '') . '</div>'
                . ($o['fulfillment'] === 'delivery' ? 'Deliver' : 'Pickup') . ' on ' . e(date('D, M j, Y', strtotime($o['due_date']))) . ($o['due_slot'] ? ' (' . e($o['due_slot']) . ')' : '') . ' · '
                . ($o['fulfillment'] === 'delivery' ? 'Deliver to: ' . e($o['delivery_address']) : 'Pickup at ' . e(setting('shop_address'))) . ($o['notes'] ? ' · Notes: ' . e($o['notes']) : '') . '</div>';
            $h .= '<div class="oa" style="padding-top:12px">';
            if ($o['cid']) $h .= '<a class="btn sm gh" href="' . e(url('admin/messages.php?cid=' . $o['cid'] . '&order=' . urlencode($o['order_number']))) . '">Message</a>';
            if ($o['proof_image']) $h .= '<a class="btn sm gh" href="' . e(url('admin/payments.php?id=' . $o['id'])) . '">View proof</a>';
            $h .= '<form method="post" action="' . e(url('admin/orders.php')) . '" class="inline">' . csrf_field() . '<input type="hidden" name="order_id" value="' . (int)$o['id'] . '">';
            if ($o['pay_method'] === 'gcash' && $o['pay_status'] === 'pending' && $o['proof_image'] && $o['status'] !== 'cancelled')
                $h .= '<button class="btn sm dg" name="action" value="reject">Reject payment</button> <button class="btn sm gr" name="action" value="verify">Verify payment</button> ';
            if ($live) $h .= '<button class="btn sm gh" name="action" value="cancel" data-confirm="Cancel order ' . e($o['order_number']) . '?">Cancel</button> ';
            if ($live && $n) $h .= '<button class="btn sm" name="action" value="next">' . e(NEXT_LABEL[$n]) . '</button>';
            $h .= '</form></div></article>';
        } else {
            $h .= '<div class="oa" style="padding-top:12px"><a class="btn sm gh" href="' . e(url('customer/messages.php?order=' . urlencode($o['order_number']))) . '">Message shop</a>';
            if ($o['status'] === 'pending') $h .= '<form method="post" action="' . e(url('customer/orders.php')) . '" class="inline">' . csrf_field() . '<input type="hidden" name="action" value="cancel"><input type="hidden" name="order_id" value="' . (int)$o['id'] . '"><button class="btn sm gh" data-confirm="Cancel this order?">Cancel order</button></form>';
            $h .= '<a class="btn sm gh" href="' . e(url('customer/orders.php?id=' . $o['id'])) . '">' . ($c === 'shipped' || $c === 'toship' ? 'Track order' : 'View details') . '</a>';
            if ($c === 'topay') $h .= '<a class="btn sm" href="' . e(url('customer/orders.php?id=' . $o['id'])) . '">' . ($o['pay_status'] === 'rejected' ? 'Upload new proof' : ($o['proof_image'] ? 'Check payment' : 'Pay with GCash')) . '</a>';
            if ($c === 'completed') $h .= '<a class="btn sm" href="' . e(url('customer/orders.php?id=' . $o['id'])) . '#rate">Rate items</a>';
            if ($c === 'completed' || $c === 'cancelled') $h .= '<form method="post" action="' . e(url('customer/orders.php')) . '" class="inline">' . csrf_field() . '<input type="hidden" name="action" value="again"><input type="hidden" name="order_id" value="' . (int)$o['id'] . '"><button class="btn sm">Buy again</button></form>';
            $h .= '</div></article>';
        }
    }
    return $h;
}

/* ---------- Cart ---------- */
function get_cart_id(int $uid): int {
    $id = val('SELECT id FROM carts WHERE user_id = ?', [$uid]);
    if ($id) return (int)$id;
    q('INSERT INTO carts (user_id) VALUES (?)', [$uid]);
    return last_id();
}
function cart_items(int $uid): array {
    return all('SELECT ci.id, ci.quantity, p.id AS product_id, p.name, p.price, p.image FROM cart_items ci JOIN carts c ON c.id = ci.cart_id JOIN products p ON p.id = ci.product_id WHERE c.user_id = ? ORDER BY ci.id', [$uid]);
}
function cart_count(int $uid): int { return (int)val('SELECT COALESCE(SUM(ci.quantity),0) FROM cart_items ci JOIN carts c ON c.id = ci.cart_id WHERE c.user_id = ?', [$uid]); }

/* ---------- Ratings ---------- */
const PRODUCT_SELECT = 'SELECT p.*, c.name AS category, COALESCE(r.n,0) AS rn, COALESCE(r.a,0) AS ra FROM products p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN (SELECT product_id, COUNT(*) n, AVG(stars) a FROM reviews GROUP BY product_id) r ON r.product_id = p.id';
function stars(float $a): string { $k = (int)round($a); return str_repeat('★', $k) . str_repeat('☆', 5 - $k); }
function rating_line(array $p): string {
    if (!(int)$p['rn']) return '<span class="mut" style="font-size:.85rem">No ratings yet</span>';
    $a = number_format((float)$p['ra'], 1);
    return '<a class="rt" href="' . e(url('reviews.php?product=' . $p['id'])) . '" aria-label="Rated ' . $a . ' out of 5 from ' . (int)$p['rn'] . ' ratings. See reviews"><span class="stars-v" aria-hidden="true">' . stars((float)$p['ra']) . '</span> ' . $a . ' (' . (int)$p['rn'] . ')</a>';
}
function short_name(string $n): string { $p = preg_split('/\s+/', trim($n)); return count($p) > 1 ? $p[0] . ' ' . mb_substr(end($p), 0, 1) . '.' : $p[0]; }

/* ---------- Layout ---------- */
function nav_counts(array $u): array {
    if ($u['role'] === 'customer') {
        return ['bell' => (int)val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$u['id']]),
            'orders' => (int)val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND order_id IS NOT NULL AND seen = 0', [$u['id']]),
            'messages' => (int)val("SELECT COUNT(*) FROM messages WHERE customer_id = ? AND from_role = 'admin' AND is_read = 0", [$u['id']])];
    }
    return ['bell' => (int)val('SELECT COUNT(*) FROM notifications WHERE for_admin = 1 AND is_read = 0'),
        'orders' => (int)val('SELECT COUNT(*) FROM notifications WHERE for_admin = 1 AND order_id IS NOT NULL AND seen = 0'),
        'payments' => (int)val("SELECT COUNT(*) FROM payments p JOIN orders o ON o.id = p.order_id WHERE p.proof_image IS NOT NULL AND p.status = 'pending' AND p.seen = 0 AND o.status <> 'cancelled'"),
        'customers' => (int)val("SELECT COUNT(*) FROM users WHERE role = 'customer' AND fresh = 1"),
        'messages' => (int)val("SELECT COUNT(*) FROM messages WHERE from_role = 'customer' AND is_read = 0")];
}
function page_start(string $title, string $active = ''): void {
    $u = current_user(); $shop = setting('shop_name', 'Shop'); $admin = $u && $u['role'] === 'admin';
    $theme = $u ? $u['theme'] : ($_COOKIE['kk_theme'] ?? 'auto');
    $attr = in_array($theme, ['light', 'dark'], true) ? ' data-theme="' . $theme . '"' : '';
    $c = $u ? nav_counts($u) : [];
    if ($u && $u['role'] === 'customer') $items = [['products', 'Products', 'customer/products.php'], ['cart', 'Cart' . (cart_count((int)$u['id']) ? ' (' . cart_count((int)$u['id']) . ')' : ''), 'customer/cart.php'], ['orders', 'My orders', 'customer/orders.php', 'orders'], ['messages', 'Messages', 'customer/messages.php', 'messages'], ['profile', 'Profile', 'customer/profile.php']];
    elseif ($admin) {
        $items = [['dashboard', 'Dashboard', 'admin/dashboard.php'], ['orders', 'Orders', 'admin/orders.php', 'orders'], ['payments', 'Payments', 'admin/payments.php', 'payments'], ['products', 'Products', 'admin/products.php'], ['categories', 'Categories', 'admin/categories.php'], ['customers', 'Customers', 'admin/customers.php', 'customers'], ['messages', 'Messages', 'admin/messages.php', 'messages'], ['reports', 'Reports', 'admin/reports.php'], ['settings', 'Settings', 'admin/settings.php']];
        if ($u['is_super']) { $items[] = ['sales', 'Complete sales', 'admin/sales.php']; $items[] = ['admins', 'Admins', 'admin/admins.php']; }
    } else $items = [];
    ?><!doctype html>
<html lang="en"<?= $attr ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($shop) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<?php if (!$admin): ?><div class="announce">Handmade native delicacies · Order online for pickup or delivery</div><?php endif; ?>
<header><div class="wrap bar">
  <a class="brand" href="<?= e(url($u ? home_for($u) : 'login.php')) ?>" style="text-decoration:none"><span class="mk">A</span><?= e($shop) ?><?= $admin ? ($u['is_super'] ? ' · Super Admin' : ' · Admin') : '' ?></a>
  <nav>
    <?php foreach ($items as $it): ?>
      <a href="<?= e(url($it[2])) ?>" class="<?= $active === $it[0] ? 'on' : '' ?>"><?= e($it[1]) ?><?php if (!empty($it[3])): ?><span class="dot" data-dot="<?= e($it[3]) ?>"<?= !empty($c[$it[3]]) ? '' : ' hidden' ?>></span><span class="sr">, new</span><?php endif; ?></a>
    <?php endforeach; ?>
    <?php if ($u): ?>
      <a href="<?= e(url('notifications.php')) ?>" class="<?= $active === 'notifications' ? 'on' : '' ?>" aria-label="Notifications">🔔<span class="cnt" data-dot="bell"<?= !empty($c['bell']) ? '' : ' hidden' ?>><?= (int)($c['bell'] ?? 0) ?></span></a>
      <?php if (!$admin): ?><a href="<?= e(url('customer/profile.php')) ?>" aria-label="Profile"><?= avatar($u, 28) ?></a><?php endif; ?>
      <a href="<?= e(url('logout.php')) ?>">Log out</a>
    <?php endif; ?>
  </nav>
</div></header>
<main class="wrap page">
<?php foreach (flashes() as [$type, $msg]): ?><div class="flash flash-<?= e($type) ?>" role="status"><?= e($msg) ?></div><?php endforeach; ?>
<?php }
function page_end(): void {
    $u = current_user(); $admin = $u && $u['role'] === 'admin';
    echo '</main>';
    if (!$admin) { ?>
<footer class="foot"><div class="wrap"><div class="fcols">
  <div><h3><?= e(setting('shop_name')) ?></h3><p>Locally handmade native delicacies.</p></div>
  <div><h3>Visit us</h3><p><?= e(setting('shop_address')) ?></p></div>
  <div><h3>Good to know</h3><p>Store in a cool, dry place. Keep away from direct sunlight. Best enjoyed fresh.</p></div>
</div><div class="copy">©<?= date('Y') ?> <?= e(setting('shop_name')) ?></div></div></footer>
<?php } ?>
<script src="<?= e(url('assets/js/app.js')) ?>" data-poll="<?= $u ? e(url('poll.php')) : '' ?>"></script>
</body>
</html>
<?php }
