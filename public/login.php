<?php
require_once '../includes/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']); // Für Benutzer: Email, Für Fahrer: Fahrernummer
    $password = trim($_POST['password']); // Für Benutzer: Passwort, Für Fahrer: Code

    // Benutzer-Login prüfen
    $stmt = $pdo->prepare("SELECT * FROM Benutzer WHERE Email = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['Passwort'])) {
        // Benutzer erfolgreich eingeloggt
        $_SESSION['user_role'] = 'benutzer';
        $_SESSION['user_id'] = $user['BenutzerID'];
        header("Location: dashboard.php");
        exit();
    }

    // Fahrer-Login prüfen
    $stmt = $pdo->prepare("SELECT * FROM Fahrer WHERE Fahrernummer = ? AND Code = ?");
    $stmt->execute([$username, $password]); // Klartext-Abfrage für Fahrer
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($driver) {
        // Fahrer erfolgreich eingeloggt
        $_SESSION['user_role'] = 'fahrer';
        $_SESSION['user_id'] = $driver['FahrerID'];
        header("Location: ../driver/dashboard.php");
        exit();
    }

    // Wenn weder Benutzer noch Fahrer erfolgreich waren
    $error = 'Ungültige Anmeldedaten!';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | DRIVE</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="wrapper">
        <header>
            <img src="images/4884-logo.png" alt="Ihr Leipzig Taxi 4884" class="logo">
        </header>
        <main>
            <h1>Login</h1>
            <?php if (isset($error)): ?>
                <p style="color: red;"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <form action="/login.php" method="POST">
                <label for="username">Benutzername <small>(Email oder Fahrernummer)</small>:</label>
                <input type="text" id="username" name="username" required>
                <br>
                <label for="password">Passwort:</label>
                <input type="password" id="password" name="password" required>
                <br>
                <button type="submit">Login</button>
            </form>
        </main>
    </div>
</body>
</html>
