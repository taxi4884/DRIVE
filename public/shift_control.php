<?php
require_once '../includes/bootstrap.php';

// PHP-Fehleranzeige aktivieren
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialisierung
$error = '';
$success = '';

// Schicht hinzufügen oder bearbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schicht_id = $_POST['schicht_id'] ?? null;
    $name = trim($_POST['name']);
    $startzeit = $_POST['startzeit'];
    $endzeit = $_POST['endzeit'];
    $pause = $_POST['pause'];
    $zuschlag = $_POST['zuschlag'];

    if (empty($name) || empty($startzeit) || empty($endzeit)) {
        $error = 'Name, Startzeit und Endzeit sind erforderlich.';
    } else {
        try {
            if ($schicht_id) {
                // Schicht aktualisieren
                $stmt = $pdo->prepare("UPDATE schichten SET name = :name, startzeit = :startzeit, endzeit = :endzeit, pause = :pause, zuschlag = :zuschlag WHERE schicht_id = :schicht_id");
                $stmt->execute(['name' => $name, 'startzeit' => $startzeit, 'endzeit' => $endzeit, 'pause' => $pause, 'zuschlag' => $zuschlag, 'schicht_id' => $schicht_id]);
                $success = 'Schicht erfolgreich aktualisiert.';
            } else {
                // Schicht hinzufügen
                $stmt = $pdo->prepare("INSERT INTO schichten (name, startzeit, endzeit, pause, zuschlag) VALUES (:name, :startzeit, :endzeit, :pause, :zuschlag)");
                $stmt->execute(['name' => $name, 'startzeit' => $startzeit, 'endzeit' => $endzeit, 'pause' => $pause, 'zuschlag' => $zuschlag]);
                $success = 'Schicht erfolgreich hinzugefügt.';
            }
        } catch (PDOException $e) {
            $error = 'Datenbankfehler: ' . $e->getMessage();
        }
    }
}

// Schicht zum Bearbeiten abrufen (falls vorhanden)
$editSchicht = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM schichten WHERE schicht_id = :schicht_id");
    $stmt->execute(['schicht_id' => $_GET['edit']]);
    $editSchicht = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Alle Schichten abrufen
$stmt = $pdo->query("SELECT * FROM schichten ORDER BY startzeit ASC");
$schichtenListe = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schichtenverwaltung | DRIVE</title>
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
        .form-group input {
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
			<h2><?= $editSchicht ? 'Schicht bearbeiten' : 'Schicht hinzufügen' ?></h2>
			<?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
			<?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>
			<form method="POST">
				<div class="form-group">
					<label for="name">Schichtname</label>
					<input type="text" id="name" name="name" value="<?= htmlspecialchars($editSchicht['name'] ?? '') ?>" required>
				</div>
				<div class="form-group">
					<label for="startzeit">Startzeit</label>
					<input type="time" id="startzeit" name="startzeit" value="<?= htmlspecialchars($editSchicht['startzeit'] ?? '') ?>" required>
				</div>
				<div class="form-group">
					<label for="endzeit">Endzeit</label>
					<input type="time" id="endzeit" name="endzeit" value="<?= htmlspecialchars($editSchicht['endzeit'] ?? '') ?>" required>
				</div>
				<div class="form-group">
					<label for="pause">Pause (Minuten)</label>
					<input type="number" id="pause" name="pause" value="<?= htmlspecialchars($editSchicht['pause'] ?? 0) ?>" min="0">
				</div>
				<div class="form-group">
					<label for="zuschlag">Zuschlag (%)</label>
					<input type="number" id="zuschlag" name="zuschlag" value="<?= htmlspecialchars($editSchicht['zuschlag'] ?? 0) ?>" step="0.01" min="0">
				</div>
				<?php if ($editSchicht): ?>
					<input type="hidden" name="schicht_id" value="<?= $editSchicht['schicht_id'] ?>">
				<?php endif; ?>
				<div class="form-actions">
					<button type="submit">Speichern</button>
					<a href="schichten_administration.php" class="button">Abbrechen</a>
				</div>
			</form>
		</div>

		<table>
			<thead>
				<tr>
					<th>ID</th>
					<th>Schichtname</th>
					<th>Startzeit</th>
					<th>Endzeit</th>
					<th>Pause</th>
					<th>Zuschlag</th>
					<th>Aktionen</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($schichtenListe as $schicht): ?>
					<tr>
						<td><?= $schicht['schicht_id'] ?></td>
						<td><?= htmlspecialchars($schicht['name']) ?></td>
						<td><?= htmlspecialchars($schicht['startzeit']) ?></td>
						<td><?= htmlspecialchars($schicht['endzeit']) ?></td>
						<td><?= htmlspecialchars($schicht['pause']) ?> Minuten</td>
						<td><?= htmlspecialchars($schicht['zuschlag']) ?>%</td>
						<td class="action-links">
							<a href="?edit=<?= $schicht['schicht_id'] ?>">Bearbeiten</a>
							<a href="?delete=<?= $schicht['schicht_id'] ?>" onclick="return confirm('Schicht wirklich löschen?');">Löschen</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</main>
</body>
</html>
