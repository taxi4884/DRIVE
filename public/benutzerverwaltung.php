<?php
require_once '../includes/bootstrap.php'; // DB-Verbindung, $pdo etc.

// Benutzer aktualisieren
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $userId = intval($_POST['update']);
    $name = $_POST['name'][$userId];
    $email = $_POST['email'][$userId];
    $rolle = $_POST['rolle'][$userId];
    $sekundarRolle = isset($_POST['sekundar_rolle'][$userId]) ? implode(',', $_POST['sekundar_rolle'][$userId]) : '';

    // Dynamische Berechtigungsfelder holen
    $stmt = $pdo->prepare("SHOW COLUMNS FROM Benutzer");
    $stmt->execute();
    $spalten = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $berechtigungsWerte = [];
    foreach ($spalten as $spalte) {
        if ($spalte['Type'] === 'tinyint(1)' && !in_array($spalte['Field'], ['BenutzerID'])) {
            $feld = $spalte['Field'];
            $formName = strtolower($feld);
            $berechtigungsWerte[$feld] = isset($_POST[$formName][$userId]) ? 1 : 0;
        }
    }

    // SQL-Query zusammenbauen
    $setSQL = "Name = :name, Email = :email, Rolle = :rolle, SekundarRolle = :sekundarRolle";
    foreach ($berechtigungsWerte as $feld => $wert) {
        $setSQL .= ", `$feld` = :$feld";
    }

    $params = [
        ':name' => $name,
        ':email' => $email,
        ':rolle' => $rolle,
        ':sekundarRolle' => $sekundarRolle,
        ':userId' => $userId
    ];
    foreach ($berechtigungsWerte as $feld => $wert) {
        $params[":$feld"] = $wert;
    }

    try {
        $stmt = $pdo->prepare("UPDATE Benutzer SET $setSQL WHERE BenutzerID = :userId");
        $stmt->execute($params);
        $updateMessage = "Benutzer erfolgreich aktualisiert!";
    } catch (PDOException $e) {
        $updateMessage = "Fehler beim Aktualisieren: " . $e->getMessage();
    }
}

// Benutzer und Berechtigungsfelder abrufen
$stmt = $pdo->prepare("SELECT * FROM Benutzer");
$stmt->execute();
$benutzer = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SHOW COLUMNS FROM Benutzer");
$stmt->execute();
$spalten = $stmt->fetchAll(PDO::FETCH_ASSOC);

$berechtigungsfelder = [];
foreach ($spalten as $spalte) {
    if ($spalte['Type'] === 'tinyint(1)' && !in_array($spalte['Field'], ['BenutzerID'])) {
        $berechtigungsfelder[] = $spalte['Field'];
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Benutzerverwaltung | DRIVE</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 20px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #ccc; transition: .4s;
            border-radius: 20px;
        }
        .slider:before {
            position: absolute; content: "";
            height: 14px; width: 14px; left: 3px; bottom: 3px;
            background-color: white; transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #4CAF50;
        }
        input:checked + .slider:before {
            transform: translateX(20px);
        }
    </style>
</head>
<body>
<?php include 'nav.php'; ?>
<h1>Benutzerverwaltung</h1>

<?php if (isset($updateMessage)): ?>
    <p><?= htmlspecialchars($updateMessage) ?></p>
<?php endif; ?>

<form method="post">
    <table>
        <thead>
            <tr>
                <th>ID</th><th>Name</th><th>Email</th><th>Rolle</th><th>Sekundärrolle</th>
                <?php foreach ($berechtigungsfelder as $feld): ?>
                    <th><?= htmlspecialchars($feld) ?></th>
                <?php endforeach; ?>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($benutzer as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['BenutzerID']) ?></td>
                    <td><input type="text" name="name[<?= $row['BenutzerID'] ?>]" value="<?= htmlspecialchars($row['Name']) ?>"></td>
                    <td><input type="email" name="email[<?= $row['BenutzerID'] ?>]" value="<?= htmlspecialchars($row['Email']) ?>"></td>
                    <td>
                        <select name="rolle[<?= $row['BenutzerID'] ?>]">
                            <option value="Admin" <?= $row['Rolle'] === 'Admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="Mitarbeiter" <?= $row['Rolle'] === 'Mitarbeiter' ? 'selected' : '' ?>>Mitarbeiter</option>
                        </select>
                    </td>
                    <td>
                        <select name="sekundar_rolle[<?= $row['BenutzerID'] ?>][]" multiple>
                            <?php
                            $alleSekundarRollen = ['Abrechnung', 'Werkstatt', 'Admin', 'Zentrale', 'Verwaltung'];
                            $aktiveRollen = explode(',', $row['SekundarRolle']);
                            foreach ($alleSekundarRollen as $rolle) {
                                $selected = in_array($rolle, $aktiveRollen) ? 'selected' : '';
                                echo "<option value=\"".htmlspecialchars($rolle)."\" $selected>".htmlspecialchars($rolle)."</option>";
                            }
                            ?>
                        </select>
                    </td>
                    <?php foreach ($berechtigungsfelder as $feld): ?>
                        <td>
                            <label class="switch">
                                <input type="checkbox" name="<?= strtolower($feld) ?>[<?= $row['BenutzerID'] ?>]" <?= $row[$feld] == 1 ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </td>
                    <?php endforeach; ?>
                    <td><button type="submit" name="update" value="<?= $row['BenutzerID'] ?>">Speichern</button></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</form>

<script>
    document.querySelector('.burger-menu')?.addEventListener('click', () => {
        document.querySelector('.nav-links').classList.toggle('active');
    });
</script>
</body>
</html>
