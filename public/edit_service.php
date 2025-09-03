<?php
// Verbindung zur Datenbank herstellen
require_once '../includes/bootstrap.php'; 

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Ungültige Anfrage.");
}

$wartungID = intval($_GET['id']);

// Wartungsdaten abrufen
$query = "
    SELECT 
        Wartung.*, 
        Fahrzeuge.Konzessionsnummer, 
        Fahrzeuge.Marke, 
        Fahrzeuge.Modell 
    FROM Wartung
    JOIN Fahrzeuge ON Wartung.FahrzeugID = Fahrzeuge.FahrzeugID
    WHERE Wartung.WartungID = :wartungID
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':wartungID', $wartungID, PDO::PARAM_INT);
    $stmt->execute();
    $wartung = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$wartung) {
        die("Keine Wartung gefunden.");
    }
} catch (PDOException $e) {
    die("Datenbankfehler: " . $e->getMessage());
}

// Update logik nach POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $beschreibung = $_POST['beschreibung'];
    $kosten = $_POST['kosten'];
    $bemerkungen = $_POST['bemerkungen'];

    $updateQuery = "
        UPDATE Wartung
        SET Beschreibung = :beschreibung, 
            Kosten = :kosten, 
            Bemerkungen = :bemerkungen
        WHERE WartungID = :wartungID
    ";

    try {
        $stmt = $pdo->prepare($updateQuery);
        $stmt->bindParam(':beschreibung', $beschreibung, PDO::PARAM_STR);
        $stmt->bindParam(':kosten', $kosten, PDO::PARAM_STR);
        $stmt->bindParam(':bemerkungen', $bemerkungen, PDO::PARAM_STR);
        $stmt->bindParam(':wartungID', $wartungID, PDO::PARAM_INT);
        $stmt->execute();
        header("Location: service.php");
        exit();
    } catch (PDOException $e) {
        die("Fehler beim Aktualisieren: " . $e->getMessage());
    }
}
?>
<?php
$title = 'Wartung bearbeiten';
include __DIR__ . '/../includes/layout.php';
?>


    <main>
        <h1>Wartung bearbeiten</h1>
        <form method="POST">
            <label>Fahrzeug:</label>
            <p><?= htmlspecialchars($wartung['Konzessionsnummer']) ?> - <?= htmlspecialchars($wartung['Marke']) ?> <?= htmlspecialchars($wartung['Modell']) ?></p>
            
            <label>Beschreibung:</label>
            <textarea name="beschreibung"><?= htmlspecialchars($wartung['Beschreibung']) ?></textarea>
            
            <label>Kosten (€):</label>
            <input type="number" step="0.01" name="kosten" value="<?= htmlspecialchars($wartung['Kosten']) ?>">
            
            <label>Bemerkungen:</label>
            <textarea name="bemerkungen"><?= htmlspecialchars($wartung['Bemerkungen']) ?></textarea>
            
            <button type="submit">Speichern</button>
        </form>
    </main>

</body>
</html>
