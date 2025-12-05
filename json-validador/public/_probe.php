<?php
require __DIR__ . '/../app/bootstrap.php';
header('Content-Type: text/plain; charset=UTF-8');
echo "OK\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "SID: " . session_id() . "\n";
echo "Host: " . ($_SERVER['HTTP_HOST'] ?? '') . "\n";
echo "HTTPS: " . (!empty($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : '-') . "\n";
