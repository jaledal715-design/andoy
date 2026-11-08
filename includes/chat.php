<?php
if (!defined('ROOT_PATH')) exit;
// Chat helpers shared by customer/messages.php, admin/messages.php and chat_poll.php.
function hl(string $t, string $q): string {
    if ($q === '') return e($t);
    $o = ''; $lo = mb_strtolower($t); $ql = mb_strtolower($q); $i = 0; $len = mb_strlen($q);
    while (($k = mb_strpos($lo, $ql, $i)) !== false) { $o .= e(mb_substr($t, $i, $k - $i)) . '<mark>' . e(mb_substr($t, $k, $len)) . '</mark>'; $i = $k + $len; }
    return $o . e(mb_substr($t, $i));
}
function chat_rows(int $cid): array {
    return all('SELECT m.*, s.full_name AS sname, c.full_name AS cname FROM messages m LEFT JOIN users s ON s.id = m.sender_id JOIN users c ON c.id = m.customer_id WHERE m.customer_id = ? ORDER BY m.id', [$cid]);
}
function chat_bubbles(array $viewer, int $cid, string $q = ''): string {
    $admin = $viewer['role'] === 'admin';
    $rows = chat_rows($cid);
    if (!$admin && $q !== '') $rows = array_values(array_filter($rows, fn($m) => mb_stripos($m['body'] . ' ' . ($m['from_role'] === 'customer' ? $m['cname'] : ($m['sname'] ?? 'Shop admin')), $q) !== false));
    if (!$rows) return '<div class="empty">' . ($q !== '' && !$admin ? 'No messages match “' . e($q) . '”.' : 'No messages yet. Say hello!') . '</div>';
    $h = '';
    foreach ($rows as $m) {
        $mine = $m['from_role'] === ($admin ? 'admin' : 'customer');
        $name = $m['from_role'] === 'customer' ? $m['cname'] : ($m['sname'] ?? 'Shop admin');
        $you = $m['sender_id'] && (int)$m['sender_id'] === (int)$viewer['id'];
        $h .= '<div class="bub ' . ($mine ? 'me' : 'ot') . '"><b class="who">' . e($name) . ($you ? ' (you)' : '') . '</b><div>' . hl($m['body'], $q) . '</div><small>' . e(date('M j, Y g:i A', strtotime($m['created_at']))) . '</small></div>';
    }
    return $h;
}
function chat_mark_read(array $viewer, int $cid): void { q('UPDATE messages SET is_read = 1 WHERE customer_id = ? AND from_role = ? AND is_read = 0', [$cid, $viewer['role'] === 'admin' ? 'customer' : 'admin']); }
function chat_send(array $viewer, int $cid, string $text): void {
    $text = trim(mb_substr($text, 0, 1000));
    if ($text === '') return;
    q('INSERT INTO messages (customer_id, sender_id, from_role, body) VALUES (?,?,?,?)', [$cid, $viewer['id'], $viewer['role'] === 'admin' ? 'admin' : 'customer', $text]);
}
function chat_box(array $viewer, int $cid, string $q, string $draft, string $action, bool $withSearch): string {
    $admin = $viewer['role'] === 'admin';
    $c = one('SELECT * FROM users WHERE id = ?', [$cid]);
    $h = '<div class="chat card"><div class="ch-h">' . ($admin ? avatar($c, 32) : '<span class="mk">A</span>') . '<strong>' . e($admin ? $c['full_name'] : setting('shop_name') . ' (shop)') . '</strong></div>';
    if ($withSearch) $h .= '<form class="ch-s" method="get"><input type="search" name="q" value="' . e($q) . '" placeholder="Search this conversation" aria-label="Search messages"></form>';
    $h .= '<div class="ch-b" id="chb" data-src="' . e(url('chat_poll.php?cid=' . $cid . '&q=' . urlencode($q))) . '">' . chat_bubbles($viewer, $cid, $q) . '</div>'
        . '<form class="ch-f" method="post" action="' . e($action) . '">' . csrf_field() . '<input type="hidden" name="cid" value="' . $cid . '"><input name="text" value="' . e($draft) . '" placeholder="Type a message" autocomplete="off" required maxlength="1000" aria-label="Message"><button class="btn">Send</button></form></div>';
    return $h;
}
