<?php
require_once __DIR__ . '/config/database.php';
$u = current_user();
redirect($u ? home_for($u) : 'login.php');
