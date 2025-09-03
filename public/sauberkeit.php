<?php
// Verbindung zur Datenbank herstellen
require_once '../includes/bootstrap.php'; // Hier sicherstellen, dass $pdo korrekt definiert ist

// Monat und Jahr aus GET-Parameter oder aktuelles Datum verwenden
$monat = isset($_GET['monat']) ? intval($_GET['monat']) : date('m');
$jahr = isset($_GET['jahr']) ? intval($_GET['jahr']) : date('Y');

// Start- und Enddatum für den gewählten Monat berechnen
$startDatum = sprintf('%04d-%02d-01', $jahr, $monat); // YYYY-MM-DD
$endDatum = date('Y-m-t', strtotime($startDatum));   // Letzter Tag des Monats

// Lokalisierung auf Deutsch einstellen
setlocale(LC_TIME, 'de_DE.UTF-8');
$deutscherMonat = strftime('%B %Y', strtotime($startDatum));

// Fahrzeuge abrufen, die keine Kontrolle im ausgewählten Monat haben
$query = "
    SELECT 
        Fahrzeuge.konzessionsnummer AS konzession, 
        Fahrzeuge.fahrzeugid, 
        Fahrzeuge.kennzeichen
    FROM 
        Fahrzeuge
    WHERE 
        Fahrzeuge.fahrzeugid NOT IN (
            SELECT Fahrzeugkontrollen.fahrzeugid
            FROM Fahrzeugkontrollen
            WHERE Fahrzeugkontrollen.kontrolldatum BETWEEN ? AND ?
        )
    ORDER BY Fahrzeuge.konzessionsnummer
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute([$startDatum, $endDatum]);
    $fehlendeKontrollen = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Datenbankfehler: " . $e->getMessage());
}

// Daten für Fahrzeugkontrollen abrufen
$query = "
    SELECT 
        Fahrzeuge.konzessionsnummer AS konzession, 
        Fahrer.vorname, 
        Fahrer.nachname, 
        Fahrzeugkontrollen.kontrolldatum AS datum, 
        Fahrzeugkontrollen.sauberkeitaussen, 
        Fahrzeugkontrollen.sauberkeitinnen, 
        Fahrzeugkontrollen.reifendruck, 
        Fahrzeugkontrollen.reifenzustand, 
        Fahrzeugkontrollen.bremsenzustand, 
        Fahrzeugkontrollen.kilometerstand, 
        Fahrzeugkontrollen.bemerkung
    FROM 
        Fahrzeugkontrollen
    JOIN Fahrzeuge ON Fahrzeugkontrollen.fahrzeugid = Fahrzeuge.fahrzeugid
    JOIN Fahrer ON Fahrzeugkontrollen.fahrerid = Fahrer.fahrerid
    WHERE 
        Fahrzeugkontrollen.kontrolldatum BETWEEN ? AND ?
    ORDER BY CAST(konzession AS UNSIGNED) ASC
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute([$startDatum, $endDatum]);
    $kontrollen = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Datenbankfehler: " . $e->getMessage());
}

// Funktion zur Auswahl des Icons basierend auf der Bewertung
function getBewertungIcon($note) {
    if ($note == 1) {
        return '<i class="fas fa-check-circle" style="color: green;"></i>';
    } elseif ($note == 2) {
        return '<i class="fas fa-check-circle" style="color: #CCCC00;"></i>';
    } elseif ($note <= 4) {
        return '<i class="fas fa-minus-circle" style="color: orange;"></i>';
    } elseif ($note == 5) {
        return '<i class="fas fa-exclamation-circle" style="color: #CCCC00;"></i>';
    } else { // Note 6
        return '<i class="fas fa-exclamation-triangle" style="color: red;"></i>';
    }
}

// Navigation berechnen
$vorherigerMonat = ($monat == 1) ? 12 : $monat - 1;
$vorherigesJahr = ($monat == 1) ? $jahr - 1 : $jahr;
$nächsterMonat = ($monat == 12) ? 1 : $monat + 1;
$nächstesJahr = ($monat == 12) ? $jahr + 1 : $jahr;
?>
<?php
$title = 'Fahrzeugsauberkeit';
include __DIR__ . '/../includes/layout.php';
?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
	<style>
	.month-navigation {
		  margin: 20px auto;
		  text-align: left;
		}
		.month-navigation a {
		  padding: 10px 15px;
		  font-size: 16px;
		  background-color: #FFD700;
		  color: #000000;
		  text-decoration: none;
		  border-radius: 4px;
		  margin: 0 5px;
		}
		.month-navigation a:hover {
		  background-color: #FFC107;
		}
		.month-navigation span {
		  font-size: 18px;
		  font-weight: bold;
		  margin-left: 10px;
		}
	</style>

    	<main>
    <h1>Fahrzeugkontrollen - Sauberkeit</h1>

    <div class="month-navigation">
        <a href="sauberkeit.php?monat=<?= $vorherigerMonat ?>&jahr=<?= $vorherigesJahr ?>">&lt; Vorheriger Monat</a>
		<span><?= htmlspecialchars($deutscherMonat) ?></span>
        <a href="sauberkeit.php?monat=<?= $nächsterMonat ?>&jahr=<?= $nächstesJahr ?>">Nächster Monat &gt;</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fahrzeug (Konzession)</th>
                <th>Fahrer</th>
                <th>Datum</th>
                <th>Sauberkeit Außen</th>
                <th>Sauberkeit Innen</th>
                <th>Reifendruck</th>
                <th>Reifenzustand</th>
                <th>Bremsenzustand</th>
                <th>Kilometerstand</th>
                <th>Bemerkung</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($kontrollen): ?>
                <?php foreach ($kontrollen as $kontrolle): ?>
                    <tr>
                        <td><?= htmlspecialchars($kontrolle['konzession']) ?></td>
                        <td><?= htmlspecialchars($kontrolle['vorname'] . ' ' . $kontrolle['nachname']) ?></td>
                        <td><?= htmlspecialchars(date('d.m.Y', strtotime($kontrolle['datum']))) ?></td>
                        <td><?= htmlspecialchars($kontrolle['sauberkeitaussen']) ?> <?= getBewertungIcon($kontrolle['sauberkeitaussen']) ?></td>
                        <td><?= htmlspecialchars($kontrolle['sauberkeitinnen']) ?> <?= getBewertungIcon($kontrolle['sauberkeitinnen']) ?></td>
                        <td><?= htmlspecialchars($kontrolle['reifendruck']) ?> bar</td>
                        <td><?= htmlspecialchars($kontrolle['reifenzustand']) ?> <?= getBewertungIcon($kontrolle['reifenzustand']) ?></td>
                        <td><?= htmlspecialchars($kontrolle['bremsenzustand']) ?> <?= getBewertungIcon($kontrolle['bremsenzustand']) ?></td>
                        <td><?= htmlspecialchars($kontrolle['kilometerstand']) ?> km</td>
                        <td><?= nl2br(htmlspecialchars($kontrolle['bemerkung'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($fehlendeKontrollen): ?>
                <?php foreach ($fehlendeKontrollen as $fahrzeug): ?>
                    <tr>
                        <td><?= htmlspecialchars($fahrzeug['konzession'] . ' (' . $fahrzeug['kennzeichen'] . ')') ?></td>
                        <td colspan="9">Keine Kontrolle im gewählten Monat</td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
	</main>

</body>
</html>
