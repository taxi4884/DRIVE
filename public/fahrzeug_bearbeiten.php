<?php
require_once '../includes/bootstrap.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Fahrzeug-ID fehlt!');
}

$id = (int)$_GET['id'];

// Fahrzeugdaten abrufen
$stmt = $pdo->prepare("SELECT * FROM Fahrzeuge WHERE FahrzeugID = ?");
$stmt->execute([$id]);
$fahrzeug = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fahrzeug) {
    die('Fahrzeug nicht gefunden!');
}

// Alle Fahrer abrufen
$alleFahrerStmt = $pdo->query("SELECT FahrerID, CONCAT(Vorname, ' ', Nachname) AS Name FROM Fahrer");
$alleFahrer = $alleFahrerStmt->fetchAll(PDO::FETCH_ASSOC);

// Zugeordnete Fahrer abrufen
$zugeordneteFahrerStmt = $pdo->prepare("
    SELECT f.FahrerID, CONCAT(f.Vorname, ' ', f.Nachname) AS Name, ff.Schicht, ff.Zuweisungsdatum
    FROM FahrerFahrzeug ff
    JOIN Fahrer f ON ff.FahrerID = f.FahrerID
    WHERE ff.FahrzeugID = ?
");
$zugeordneteFahrerStmt->execute([$id]);
$zugeordneteFahrer = $zugeordneteFahrerStmt->fetchAll(PDO::FETCH_ASSOC);

// Fahrzeugdaten aktualisieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_vehicle'])) {
    $marke = trim($_POST['marke']);
    $modell = trim($_POST['modell']);
    $konzession = trim($_POST['konzession']);
    $kennzeichen = trim($_POST['kennzeichen']);
    $typ = $_POST['typ'];
    $hu = $_POST['hu'];
    $eichung = $_POST['eichung'];

    if (empty($marke) || empty($modell) || empty($konzession) || empty($kennzeichen)) {
        die('Alle Felder müssen ausgefüllt werden!');
    }

    $stmt = $pdo->prepare("
        UPDATE Fahrzeuge 
        SET Marke = ?, Modell = ?, Konzessionsnummer = ?, Kennzeichen = ?, Typ = ?, HU = ?, Eichung = ? 
        WHERE FahrzeugID = ?
    ");
    $stmt->execute([$marke, $modell, $konzession, $kennzeichen, $typ, $hu, $eichung, $id]);

    header("Location: fahrzeug_bearbeiten.php?id=$id");
    exit;
}

// Fahrerzuordnung hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_driver'])) {
    $fahrerID = (int)$_POST['fahrer_id'];
    $schicht = $_POST['schicht'];

    if ($fahrerID && $schicht) {
        $stmt = $pdo->prepare("INSERT INTO FahrerFahrzeug (FahrzeugID, FahrerID, Schicht) VALUES (?, ?, ?)");
        $stmt->execute([$id, $fahrerID, $schicht]);

        $historieStmt = $pdo->prepare("INSERT INTO FahrerFahrzeugHistorie (FahrzeugID, FahrerID, Schicht, Aktion) VALUES (?, ?, ?, 'Zugewiesen')");
        $historieStmt->execute([$id, $fahrerID, $schicht]);
    }

    header("Location: fahrzeug_bearbeiten.php?id=$id");
    exit;
}

// Fahrerzuordnung entfernen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_driver'])) {
    $fahrerID = (int)$_POST['fahrer_id'];

    $stmt = $pdo->prepare("DELETE FROM FahrerFahrzeug WHERE FahrzeugID = ? AND FahrerID = ?");
    $stmt->execute([$id, $fahrerID]);

    $historieStmt = $pdo->prepare("INSERT INTO FahrerFahrzeugHistorie (FahrzeugID, FahrerID, Schicht, Aktion) VALUES (?, ?, NULL, 'Entfernt')");
    $historieStmt->execute([$id, $fahrerID]);

    header("Location: fahrzeug_bearbeiten.php?id=$id");
    exit;
}

// Historie abrufen
$historieStmt = $pdo->prepare("
    SELECT h.FahrerID, CONCAT(f.Vorname, ' ', f.Nachname) AS Name, h.Schicht, h.Aktion, h.Datum
    FROM FahrerFahrzeugHistorie h
    LEFT JOIN Fahrer f ON h.FahrerID = f.FahrerID
    WHERE h.FahrzeugID = ?
    ORDER BY h.Datum DESC
");
$historieStmt->execute([$id]);
$historie = $historieStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php
$title = 'Fahrzeug bearbeiten';
include __DIR__ . '/../includes/layout.php';
?>


        <main>
        <h1>Fahrzeug bearbeiten</h1>
        <section>
            <h2>Fahrzeugdaten</h2>
            <form action="fahrzeug_bearbeiten.php?id=<?= htmlspecialchars($id) ?>" method="POST" class="form-grid">
                <label for="marke">Marke:</label>
                <input type="text" id="marke" name="marke" value="<?= htmlspecialchars($fahrzeug['Marke']) ?>" required>

                <label for="modell">Modell:</label>
                <input type="text" id="modell" name="modell" value="<?= htmlspecialchars($fahrzeug['Modell']) ?>" required>

                <label for="konzession">Konzessionsnummer:</label>
                <input type="text" id="konzession" name="konzession" value="<?= htmlspecialchars($fahrzeug['Konzessionsnummer']) ?>" required>

                <label for="kennzeichen">Kennzeichen:</label>
                <input type="text" id="kennzeichen" name="kennzeichen" value="<?= htmlspecialchars($fahrzeug['Kennzeichen']) ?>" required>

                <label for="typ">Typ:</label>
                <select id="typ" name="typ" required>
                    <option value="Taxi" <?= $fahrzeug['Typ'] === 'Taxi' ? 'selected' : '' ?>>Taxi</option>
                    <option value="Mietwagen" <?= $fahrzeug['Typ'] === 'Mietwagen' ? 'selected' : '' ?>>Mietwagen</option>
                </select>

                <label for="hu">HU:</label>
                <input type="date" id="hu" name="hu" value="<?= htmlspecialchars($fahrzeug['HU']) ?>" required>

                <label for="eichung">Eichung:</label>
                <input type="date" id="eichung" name="eichung" value="<?= htmlspecialchars($fahrzeug['Eichung']) ?>" required>

                <button type="submit" name="update_vehicle" class="btn">Speichern</button>
            </form>
        </section>
        <section>
            <h2>Fahrerzuordnung</h2>
            <h3>Zugeordnete Fahrer</h3>
            <?php if (!empty($zugeordneteFahrer)): ?>
                <ul>
                    <?php foreach ($zugeordneteFahrer as $fahrer): ?>
                        <li>
                            <?= htmlspecialchars($fahrer['Name']) ?> 
                            (<?= htmlspecialchars($fahrer['Schicht']) ?>fahrer, zugewiesen am <?= htmlspecialchars($fahrer['Zuweisungsdatum']) ?>)
                            <form action="fahrzeug_bearbeiten.php?id=<?= htmlspecialchars($id) ?>" method="POST" style="display:inline;">
                                <input type="hidden" name="fahrer_id" value="<?= htmlspecialchars($fahrer['FahrerID']) ?>">
                                <button type="submit" name="remove_driver" class="btn-sm">Entfernen</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>Keine Fahrer zugeordnet.</p>
            <?php endif; ?>
            <h3>Neuen Fahrer zuweisen</h3>
            <form action="fahrzeug_bearbeiten.php?id=<?= htmlspecialchars($id) ?>" method="POST" class="form-grid">
                <label for="fahrer_id">Fahrer:</label>
                <select id="fahrer_id" name="fahrer_id" required>
                    <?php foreach ($alleFahrer as $fahrer): ?>
                        <option value="<?= htmlspecialchars($fahrer['FahrerID']) ?>">
                            <?= htmlspecialchars($fahrer['Name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="schicht">Schicht:</label>
                <select id="schicht" name="schicht" required>
                    <option value="Tag">Tag</option>
                    <option value="Nacht">Nacht</option>
                </select>

                <button type="submit" name="add_driver" class="btn">Fahrer zuweisen</button>
            </form>
        </section>
        <section>
            <h3>Historie</h3>
            <table class="styled-table">
                <thead>
                    <tr>
                        <th>Fahrer</th>
                        <th>Schicht</th>
                        <th>Aktion</th>
                        <th>Datum</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historie as $eintrag): ?>
                        <tr>
                            <td><?= htmlspecialchars($eintrag['Name'] ?? 'Unbekannter Fahrer') ?></td>
                            <td><?= htmlspecialchars($eintrag['Schicht'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($eintrag['Aktion']) ?></td>
                            <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($eintrag['Datum']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>

</body>
</html>
