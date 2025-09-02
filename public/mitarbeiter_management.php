<?php
require_once '../includes/bootstrap.php';

// PHP-Fehleranzeige aktivieren
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialisierung
$error = '';
$success = '';

// Mitarbeiter hinzufügen oder bearbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vorname = trim($_POST['vorname']);
    $nachname = trim($_POST['nachname']);
    $telefon = trim($_POST['telefon']);
    $status = $_POST['status'];
    $mitarbeiter_id = $_POST['mitarbeiter_id'] ?? null;

    if (empty($vorname) || empty($nachname)) {
        $error = 'Vorname und Nachname sind erforderlich.';
    } else {
        try {
            if ($mitarbeiter_id) {
                // Mitarbeiter aktualisieren
                $stmt = $pdo->prepare("UPDATE mitarbeiter_zentrale SET vorname = :vorname, nachname = :nachname, telefon = :telefon, status = :status WHERE mitarbeiter_id = :mitarbeiter_id");
                $stmt->execute(['vorname' => $vorname, 'nachname' => $nachname, 'telefon' => $telefon, 'status' => $status, 'mitarbeiter_id' => $mitarbeiter_id]);
                $success = 'Mitarbeiter erfolgreich aktualisiert.';
            } else {
                // Mitarbeiter hinzufügen
                $stmt = $pdo->prepare("INSERT INTO mitarbeiter_zentrale (vorname, nachname, telefon, status) VALUES (:vorname, :nachname, :telefon, :status)");
                $stmt->execute(['vorname' => $vorname, 'nachname' => $nachname, 'telefon' => $telefon, 'status' => $status]);
                $success = 'Mitarbeiter erfolgreich hinzugefügt.';
            }
        } catch (PDOException $e) {
            $error = 'Datenbankfehler: ' . $e->getMessage();
        }
    }
}

// Mitarbeiter zum Bearbeiten abrufen (falls vorhanden)
$editMitarbeiter = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM mitarbeiter_zentrale WHERE mitarbeiter_id = :mitarbeiter_id");
    $stmt->execute(['mitarbeiter_id' => $_GET['edit']]);
    $editMitarbeiter = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Alle Mitarbeiter abrufen
$stmt = $pdo->query("SELECT * FROM mitarbeiter_zentrale ORDER BY nachname ASC");
$mitarbeiterListe = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mitarbeiterverwaltung | DRIVE</title>
    <link rel="stylesheet" href="css/custom.css">
    <style>
		.form-wrapper {
            max-width: 500px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .form-wrapper h2 {
            margin-top: 0;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-actions {
            display: flex;
            justify-content: space-between;
        }
        .form-actions button {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            background-color: #007bff;
            color: white;
            cursor: pointer;
        }
        .form-actions button:hover {
            background-color: #0056b3;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
        .success {
            color: green;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        table th, table td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        table th {
            background-color: #007bff;
            color: white;
        }
        table tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .action-links a {
            margin-right: 10px;
            color: #007bff;
            text-decoration: none;
        }
        .action-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
	<?php include 'nav.php'; ?>
	<main>
		<div class="form-wrapper">
			<h2><?= $editMitarbeiter ? 'Mitarbeiter bearbeiten' : 'Mitarbeiter hinzufügen' ?></h2>
			<?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
			<?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>
			<form method="POST">
				<div class="form-group">
					<label for="vorname">Vorname</label>
					<input type="text" id="vorname" name="vorname" value="<?= htmlspecialchars($editMitarbeiter['vorname'] ?? '') ?>" required>
				</div>
				<div class="form-group">
					<label for="nachname">Nachname</label>
					<input type="text" id="nachname" name="nachname" value="<?= htmlspecialchars($editMitarbeiter['nachname'] ?? '') ?>" required>
				</div>
				<div class="form-group">
					<label for="telefon">Telefon</label>
					<input type="text" id="telefon" name="telefon" value="<?= htmlspecialchars($editMitarbeiter['telefon'] ?? '') ?>">
				</div>
				<div class="form-group">
					<label for="status">Status</label>
					<select id="status" name="status">
						<option value="Aktiv" <?= ($editMitarbeiter['status'] ?? '') === 'Aktiv' ? 'selected' : '' ?>>Aktiv</option>
						<option value="Inaktiv" <?= ($editMitarbeiter['status'] ?? '') === 'Inaktiv' ? 'selected' : '' ?>>Inaktiv</option>
					</select>
				</div>
				<?php if ($editMitarbeiter): ?>
					<input type="hidden" name="mitarbeiter_id" value="<?= $editMitarbeiter['mitarbeiter_id'] ?>">
				<?php endif; ?>
				<div class="form-actions">
					<button type="submit">Speichern</button>
					<a href="mitarbeiter_management.php" class="button">Abbrechen</a>
				</div>
			</form>
		</div>

		<table>
			<thead>
				<tr>
					<th>ID</th>
					<th>Vorname</th>
					<th>Nachname</th>
					<th>Telefon</th>
					<th>Status</th>
					<th>Aktionen</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($mitarbeiterListe as $mitarbeiter): ?>
					<tr>
						<td><?= $mitarbeiter['mitarbeiter_id'] ?></td>
						<td><?= htmlspecialchars($mitarbeiter['vorname']) ?></td>
						<td><?= htmlspecialchars($mitarbeiter['nachname']) ?></td>
						<td><?= htmlspecialchars($mitarbeiter['telefon']) ?></td>
						<td><?= htmlspecialchars($mitarbeiter['status']) ?></td>
						<td class="action-links">
							<a href="?edit=<?= $mitarbeiter['mitarbeiter_id'] ?>">Bearbeiten</a>
							<a href="?delete=<?= $mitarbeiter['mitarbeiter_id'] ?>" onclick="return confirm('Mitarbeiter wirklich löschen?');">Löschen</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</main>
</body>
</html>
