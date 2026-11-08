<?php
require_once __DIR__ . '/config/database.php';
remember_clear();
$_SESSION = [];
session_destroy();
session_start();
flash('You have been logged out.');
redirect('login.php');
