<?php

$action = $_GET['action'] ?? '';
$target = '/postfach';

if ($action === 'compose') {
    $target = '/postfach/compose';
} elseif ($action === 'store') {
    $target = '/postfach/store';
}

$query = $_GET;
unset($query['action']);
if ($query) {
    $target .= '?' . http_build_query($query);
}

$statusCode = $_SERVER['REQUEST_METHOD'] === 'POST' ? 307 : 302;
header('Location: ' . $target, true, $statusCode);
exit;
