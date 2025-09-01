// urlaub_bearbeiten.php
require_once '../includes/head.php';
requireLogin();

$fahrer_id = $_GET['id'];
$von = $_GET['von'];
$bis = $_GET['bis'];

$stmt = $pdo->prepare("SELECT * FROM FahrerAbwesenheiten WHERE FahrerID = ? AND startdatum = ? AND enddatum = ? AND abwesenheitsart = 'Urlaub'");
$stmt->execute([$fahrer_id, $von, $bis]);
$eintrag = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start = $_POST['startdatum'];
    $ende = $_POST['enddatum'];
    $grund = $_POST['grund'];

    $stmt = $pdo->prepare("UPDATE FahrerAbwesenheiten SET startdatum = ?, enddatum = ?, grund = ? WHERE FahrerID = ? AND startdatum = ? AND enddatum = ? AND abwesenheitsart = 'Urlaub'");
    $stmt->execute([$start, $ende, $grund, $fahrer_id, $von, $bis]);

    header("Location: fahrer_bearbeiten.php?id=" . urlencode($fahrer_id));
    exit;
}
?>
<form method="POST">
    <label>Von: <input type="date" name="startdatum" value="<?= htmlspecialchars($eintrag['startdatum']) ?>"></label><br>
    <label>Bis: <input type="date" name="enddatum" value="<?= htmlspecialchars($eintrag['enddatum']) ?>"></label><br>
    <label>Grund: <input type="text" name="grund" value="<?= htmlspecialchars($eintrag['grund']) ?>"></label><br>
    <button type="submit">Speichern</button>
</form>
