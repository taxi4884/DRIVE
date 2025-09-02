<?php
require_once '../includes/bootstrap.php';
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'DRIVE'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QY6pDz0YjU+vWXTjhBMWrMUR3pvN8I2c2MvmXrKE+g6uSPRqCMgfHdCqkfNNPJwN" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/custom.css?v=<?= filemtime(__DIR__ . '/css/custom.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-w74AqDTFDzJr3TOScKq3Y0CA8DmFumiQJ5AZt0pOEtp5uWZL0E+nobVHTp6fmx4d" crossorigin="anonymous"></script>
</head>
