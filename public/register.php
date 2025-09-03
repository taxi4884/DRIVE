<?php
require_once '../includes/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Alle Felder sind erforderlich!';
    } else {
        // Passwort hashen
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Benutzer registrieren
        try {
            $stmt = $pdo->prepare("INSERT INTO Benutzer (Name, Email, Passwort) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $hashedPassword]);
            $success = 'Benutzer erfolgreich registriert!';
        } catch (PDOException $e) {
            // Fehler behandeln, z. B. E-Mail bereits registriert
            if ($e->getCode() === '23000') { // Duplicate entry
                $error = 'Diese E-Mail-Adresse ist bereits registriert.';
            } else {
                $error = 'Datenbankfehler: ' . $e->getMessage();
            }
        }
    }
}
?>
<?php
$title = 'Registrierung';
$showNav = false;
include __DIR__ . '/../includes/layout.php';
?>


    <h1>Benutzer registrieren</h1>
    <?php if ($error): ?>
        <p style="color: red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p style="color: green;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>
    <form action="register.php" method="POST">
        <label for="name">Name:</label>
        <input type="text" id="name" name="name" required>
        <br>
        <label for="email">E-Mail:</label>
        <input type="email" id="email" name="email" required>
        <br>
        <label for="password">Passwort:</label>
        <input type="password" id="password" name="password" required>
        <br>
        <button type="submit">Registrieren</button>
    </form>
    <p><a href="index.php">Zum Login</a></p>

</body>
</html>
