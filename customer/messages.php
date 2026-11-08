<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/chat.php';
$u = require_role('customer');
if ($_SERVER['REQUEST_METHOD'] === 'POST') { verify_csrf(); chat_send($u, (int)$u['id'], $_POST['text'] ?? ''); redirect('customer/messages.php'); }
chat_mark_read($u, (int)$u['id']);
$qs = trim($_GET['q'] ?? '');
$draft = !empty($_GET['order']) ? 'Hi, about order ' . preg_replace('/[^A-Za-z0-9\-]/', '', $_GET['order']) . ': ' : '';
page_start('Messages', 'messages');
?>
<h1>Messages</h1>
<?= chat_box($u, (int)$u['id'], $qs, $draft, url('customer/messages.php'), true) ?>
<?php page_end(); ?>
