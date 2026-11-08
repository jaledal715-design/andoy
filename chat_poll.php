<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/chat.php';
$u = current_user();
if (!$u) { http_response_code(401); exit; }
$cid = $u['role'] === 'admin' ? (int)($_GET['cid'] ?? 0) : (int)$u['id'];
if (!$cid) exit;
chat_mark_read($u, $cid);
echo chat_bubbles($u, $cid, trim($_GET['q'] ?? ''));
