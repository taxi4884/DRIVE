<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../includes/bootstrap.php'; // Verbindung und Authentifizierung

// Rolle für diese Route festlegen (einfachste Variante)
$_SESSION['rolle'] = 'Fahrer';

// Session prüfen
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    die('Fehler: Keine gültige Session.');
}

$fahrer_id = $_SESSION['user_id'];

// Persönliche Daten abrufen
try {
    $query = "
        SELECT Vorname, Nachname, Telefonnummer, Email, Strasse, Hausnummer, PLZ, Ort, 
               Fuehrerscheinnummer, FuehrerscheinGueltigkeit, PScheinGueltigkeit
        FROM Fahrer
        WHERE FahrerID = ?
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$fahrer_id]);
    $fahrer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fahrer) {
        die('Keine Daten für diesen Fahrer gefunden.');
    }
} catch (PDOException $e) {
    die('Datenbankfehler: ' . $e->getMessage());
}

// Abwesenheiten (Krankheit und Urlaub) für den Fahrer abrufen
try {
    $abwesenheitenQuery = "
        SELECT abwesenheitsart, grund, status, startdatum, enddatum 
        FROM FahrerAbwesenheiten 
        WHERE FahrerID = ? 
        ORDER BY startdatum DESC
    ";
$stmt = $pdo->prepare($abwesenheitenQuery);
$stmt->execute([$fahrer_id]);
$abwesenheiten = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Datenbankfehler beim Abrufen der Abwesenheiten: ' . $e->getMessage());
}

$title = 'Persönliche Daten';
$extraCss = [
    'css/driver-dashboard.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css'
];
include __DIR__ . '/../../includes/layout.php';
?>
  <script src="../js/modal.js"></script>
        <style>
		body {
			font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
			background: #f6f8fa;
			margin: 0;
			padding: 0;
		}

		main {
			padding: 20px;
		}

		h1, h2 {
			color: #333;
			margin-bottom: 20px;
		}

		.personal-data-container table {
			width: 100%;
			border-collapse: collapse;
			background: #fff;
			border-radius: 8px;
			overflow: hidden;
			box-shadow: 0 2px 8px rgba(0,0,0,0.05);
			margin-bottom: 30px;
		}

		.personal-data-container th,
		.personal-data-container td {
			padding: 12px 15px;
			border-bottom: 1px solid #eee;
		}

		.personal-data-container th {
			background-color: #f5f5f5;
			width: 30%;
			font-weight: bold;
		}

		.abwesenheit ul {
			list-style: none;
			padding: 0;
			margin: 0;
		}

		.abwesenheit ul li {
			background: #ffffff;
			margin-bottom: 12px;
			padding: 15px;
			border-left: 5px solid #4caf50;
			border-radius: 6px;
			box-shadow: 0 2px 6px rgba(0,0,0,0.05);
			transition: transform 0.2s ease;
		}

		.abwesenheit ul li:hover {
			transform: translateX(5px);
		}

		button {
			background-color: #007bff;
			color: white;
			border: none;
			padding: 12px 20px;
			border-radius: 5px;
			font-size: 16px;
			cursor: pointer;
			transition: background 0.3s ease;
		}

		button:hover {
			background-color: #0056b3;
		}

                /* Modal styles are handled globally in public/css/custom.css */

		.close {
			position: absolute;
			top: 15px;
			right: 20px;
			font-size: 24px;
			color: #aaa;
			cursor: pointer;
		}

		.close:hover {
			color: #333;
		}

		form label {
			display: block;
			margin-top: 15px;
			font-weight: 600;
			color: #555;
		}

		form input, form textarea {
			width: 100%;
			padding: 10px;
			margin-top: 6px;
			margin-bottom: 15px;
			border: 1px solid #ccc;
			border-radius: 5px;
		}

		form button {
			background-color: #28a745;
			width: 100%;
		}

		form button:hover {
			background-color: #218838;
		}

		@keyframes fadeIn {
			from { opacity: 0; }
			to { opacity: 1; }
		}

		/* Status-Farben */
		.status-genehmigt { font-weight: bold; color: #28a745; }
		.status-abgelehnt { font-weight: bold; color: #dc3545; }
		.status-beantragt { font-weight: bold; color: #ffc107; }
		
		.icon {
			margin-right: 8px;
			font-size: 22px;
			vertical-align: middle;
		}

		.badge {
			display: inline-block;
			padding: 4px 8px;
			margin-top: 6px;
			font-size: 12px;
			font-weight: bold;
			border-radius: 12px;
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}

		.badge-success {
			background-color: #28a745;
			color: white;
		}

		.badge-danger {
			background-color: #dc3545;
			color: white;
		}

		.badge-warning {
			background-color: #ffc107;
			color: black;
		}

		.badge-secondary {
			background-color: #6c757d;
			color: white;
		}
		.table-responsive {
			width: 100%;
			overflow-x: auto;
			-webkit-overflow-scrolling: touch; /* für sanftes Scrollen auf iOS */
			margin-bottom: 20px;
		}

		.personal-data-table {
			width: 100%;
			min-width: 600px; /* Optional: Mindestbreite, damit die Spalten nicht zu schmal werden */
			border-collapse: collapse;
			background: #fff;
			border-radius: 8px;
			overflow: hidden;
			box-shadow: 0 2px 8px rgba(0,0,0,0.05);
		}

		.personal-data-table th,
		.personal-data-table td {
			padding: 12px 15px;
			border-bottom: 1px solid #eee;
		}

		.personal-data-table th {
			background-color: #f5f5f5;
			width: 30%;
			font-weight: bold;
		}

        </style>
  <main>
    <h1>Persönliche Daten</h1>
	<div class="table-responsive">
	  <table class="personal-data-table">
        <tr>
          <th>Vorname:</th>
          <td><?= htmlspecialchars($fahrer['Vorname']) ?></td>
        </tr>
        <tr>
          <th>Nachname:</th>
          <td><?= htmlspecialchars($fahrer['Nachname']) ?></td>
        </tr>
        <tr>
          <th>Telefonnummer:</th>
          <td><?= htmlspecialchars($fahrer['Telefonnummer']) ?></td>
        </tr>
        <tr>
          <th>Email:</th>
          <td><?= htmlspecialchars($fahrer['Email']) ?></td>
        </tr>
        <tr>
          <th>Straße:</th>
          <td><?= htmlspecialchars($fahrer['Strasse'] . ' ' . $fahrer['Hausnummer']) ?></td>
        </tr>
        <tr>
          <th>PLZ, Ort:</th>
          <td><?= htmlspecialchars($fahrer['PLZ'] . ', ' . $fahrer['Ort']) ?></td>
        </tr>
        <tr>
          <th>Führerscheinnummer:</th>
          <td><?= htmlspecialchars($fahrer['Fuehrerscheinnummer']) ?></td>
        </tr>
        <tr>
          <th>Führerschein gültig bis:</th>
          <td><?= htmlspecialchars(date('d.m.y', strtotime($fahrer['FuehrerscheinGueltigkeit']))) ?></td>
        </tr>
        <tr>
          <th>P-Schein gültig bis:</th>
          <td><?= htmlspecialchars(date('d.m.y', strtotime($fahrer['PScheinGueltigkeit']))) ?></td>
        </tr>
      </table>
    </div>
    
    <button onclick="openModal('urlaubModal')">Urlaub beantragen</button>
    
	<h2>🗓️ Meine Abwesenheiten</h2>
	<div class="abwesenheit">
	  <?php if (!empty($abwesenheiten)): ?>
		<ul>
		  <?php foreach ($abwesenheiten as $eintrag): ?>
			<li>
			  <span class="icon">
				<?php if($eintrag['abwesenheitsart'] === 'Urlaub'): ?>
				  🏖️
				<?php elseif($eintrag['abwesenheitsart'] === 'Krankheit'): ?>
				  🤒
				<?php else: ?>
				  📅
				<?php endif; ?>
			  </span>
			  <strong><?= htmlspecialchars($eintrag['abwesenheitsart']) ?></strong> – 
			  <?= htmlspecialchars($eintrag['grund']) ?><br>
			  <small>von <?= date('d.m.y', strtotime($eintrag['startdatum'])) ?> bis <?= date('d.m.y', strtotime($eintrag['enddatum'])) ?></small>
			  <?php if($eintrag['abwesenheitsart'] === 'Urlaub'): ?>
				<?php 
				  $status = $eintrag['status'] ?? 'nicht gesetzt';
				  if($status === 'genehmigt'): ?>
					<span class="badge badge-success"><?= htmlspecialchars($status) ?></span>
				<?php elseif($status === 'abgelehnt'): ?>
					<span class="badge badge-danger"><?= htmlspecialchars($status) ?></span>
				<?php elseif($status === 'beantragt'): ?>
					<span class="badge badge-warning"><?= htmlspecialchars($status) ?></span>
				<?php else: ?>
					<span class="badge badge-secondary"><?= htmlspecialchars($status) ?></span>
				<?php endif; ?>
			  <?php endif; ?>
			</li>
		  <?php endforeach; ?>
		</ul>
	  <?php else: ?>
		<p>Keine Abwesenheiten gefunden.</p>
	  <?php endif; ?>
	</div>

    <p>Mehr Funktionen folgen später!</p>
  </main>
  
  
  <!-- Modal für Urlaub beantragen -->
  <div id="urlaubModal" class="modal">
    <div class="modal-content">
      <span onclick="closeModal('urlaubModal')" class="close">&times;</span>
      <h2>Urlaub beantragen</h2>
      <form action="process_urlaub_antrag.php" method="POST">
        <label for="startdatum">Startdatum:</label>
        <input type="date" id="startdatum" name="startdatum" required>
        
        <label for="enddatum">Enddatum:</label>
        <input type="date" id="enddatum" name="enddatum" required>
        
        <label for="kommentar">Kommentar:</label>
        <textarea id="kommentar" name="kommentar"></textarea>
        
        <button onclick="openModal('urlaubModal')">➕ Urlaub beantragen</button>
      </form>
    </div>
  </div>
  
  <script>
    // Schließen des Modals durch Klick außerhalb des Inhalts
    window.onclick = function(event) {
      const modal = document.getElementById('urlaubModal');
      if (event.target === modal) {
        closeModal('urlaubModal');
      }
    }
  </script>
</body>
</html>
