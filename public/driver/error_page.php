<?php
if (!defined('DRIVER_ERROR_PAGE_RENDERED')) {
    define('DRIVER_ERROR_PAGE_RENDERED', true);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Es ist ein Fehler aufgetreten</title>
    <link rel="stylesheet" href="css/driver-dashboard.css">
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background-color: #f5f7fa;
            color: #1f2933;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .error-container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12);
            padding: 40px;
            text-align: center;
            max-width: 420px;
            width: 100%;
        }
        .error-container h1 {
            font-size: 1.8rem;
            margin-bottom: 16px;
        }
        .error-container p {
            margin-bottom: 24px;
            line-height: 1.5;
        }
        .error-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .error-actions a,
        .error-actions button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .error-actions a.primary {
            background: #2563eb;
            color: #ffffff;
            box-shadow: 0 12px 20px rgba(37, 99, 235, 0.2);
        }
        .error-actions a.secondary {
            background: #e5e7eb;
            color: #1f2933;
        }
        .error-actions a:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 30px rgba(15, 23, 42, 0.18);
        }
    </style>
</head>
<body>
<div class="error-container">
    <h1>Da ist etwas schiefgelaufen.</h1>
    <p>Wir konnten Ihre Anfrage nicht bearbeiten. Unser Team wurde informiert. Bitte versuchen Sie es später erneut.</p>
    <div class="error-actions">
        <a class="primary" href="dashboard.php">Zurück zum Dashboard</a>
        <a class="secondary" href="login.php">Zur Anmeldung</a>
    </div>
</div>
</body>
</html>
