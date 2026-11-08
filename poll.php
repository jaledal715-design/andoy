<?php
require_once __DIR__ . '/config/database.php';
header('Content-Type: application/json');
$u = current_user();
echo json_encode($u ? nav_counts($u) : new stdClass);
