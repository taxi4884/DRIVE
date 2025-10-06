<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
	
require_once '../../includes/bootstrap.php'; // Datenbankverbindung und Authentifizierung

// Rolle für diese Route festlegen (einfachste Variante)
$_SESSION['rolle'] = 'Fahrer';

if (!isset($_SESSION['user_id'])) {
    die('Fehler: Keine gültige Session. Bitte erneut anmelden.');
}
$fahrer_id = $_SESSION['user_id'];

// Fahrername abrufen
$stmtFahrer = $pdo->prepare("SELECT Vorname, Nachname FROM Fahrer WHERE FahrerID = :id");
$stmtFahrer->execute(['id' => $fahrer_id]);
$fahrer = $stmtFahrer->fetch(PDO::FETCH_ASSOC);
$fahrer_name = $fahrer ? $fahrer['Vorname'] . ' ' . $fahrer['Nachname'] : 'Unbekannter Fahrer';

// Verarbeitung der Inspektionsmeldung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inspektion_melden'])) {
    $fahrzeugID = (int)$_POST['fahrzeug_id'];
    $konzession = $_POST['konzession'] ?? '';
    $kennzeichen = $_POST['kennzeichen'] ?? '';
    $meldung = $_POST['meldung'] ?? '';
    $rest_km = $_POST['rest_km'] ?? '';
    $gesamt_km = $_POST['gesamt_km'] ?? '';

    // PHPMailer einbinden
    $phpmailerPath      = __DIR__ . '/../../phpmailer/';
    require $phpmailerPath . 'Exception.php';
    require $phpmailerPath . 'PHPMailer.php';
    require $phpmailerPath . 'SMTP.php';

    $mail = new PHPMailer(true);

    try {
        $mail->setFrom('no-reply@drive.4884.de', 'DRIVE System');
        $mail->addAddress('verwaltung@taxi4884.de');
        $mail->Subject = "Inspektionsmeldung – Konzession {$konzession}";
        $mail->CharSet = 'UTF-8';
		$mail->Encoding = 'base64';

		$mail->Subject = "Inspektionsmeldung – Konzession {$konzession}";
		$mail->Body = 
		"🚗 Fahrzeugmeldung zur Inspektion\n\n" .
		"🔢 Konzession: {$konzession}\n" .
		"🚘 Kennzeichen: {$kennzeichen}\n" .
		"📝 Anzeigetext: {$meldung}\n" .
		"📉 Restkilometer: {$rest_km} km\n" .
		"📈 Gesamtkilometerstand: {$gesamt_km} km\n\n" .
		"👤 Gemeldet von: {$fahrer_name} (ID: {$fahrer_id})";

        $mail->send();
        echo '<p style="color: green; text-align: center; font-weight: bold">✅ Die Inspektionsmeldung wurde an die Verwaltung gesendet.</p>';
    } catch (Exception $e) {
        echo '<p style="color: red; text-align: center; font-weight: bold">❌ Fehler beim Senden der E-Mail: ' . $mail->ErrorInfo . '</p>';
    }
}

// Daten für Fahrzeuge und zugehörige Wartungen abrufen
$query = "
    SELECT 
        Fahrzeuge.FahrzeugID,
        Fahrzeuge.Konzessionsnummer, 
        Fahrzeuge.Kennzeichen, 
        Fahrzeuge.Marke, 
        Fahrzeuge.Modell,
        Wartung.Wartungsdatum,
        Wartung.Beschreibung,
        Wartung.Werkstatt,
		Wartung.Bemerkungen
    FROM Fahrzeuge
    LEFT JOIN (
        SELECT * FROM Wartung
        WHERE Wartungsdatum IN (
            SELECT MAX(Wartungsdatum)
            FROM Wartung
            GROUP BY FahrzeugID
        )
    ) AS Wartung ON Fahrzeuge.FahrzeugID = Wartung.FahrzeugID
    JOIN FahrerFahrzeug ON FahrerFahrzeug.FahrzeugID = Fahrzeuge.FahrzeugID
    WHERE FahrerFahrzeug.FahrerID = ?
    ORDER BY Fahrzeuge.Konzessionsnummer ASC
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute([$fahrer_id]);
    $fahrzeuge = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Datenbankfehler: " . $e->getMessage());
}

function formatDateTime($datetime) {
    $date = new DateTime($datetime);
    return $date->format('d.m.Y');
}

$title = 'Meine Fahrzeuge';
$extraCss = [
    'css/driver-dashboard.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css'
];
include __DIR__ . '/../../includes/layout.php';
?>
        <style>
		body {
			background: #f6f8fa;
			font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
		}

		main {
			padding: 20px;
		}

		h1 {
			margin-bottom: 20px;
			color: #333;
		}

		.table-responsive {
			width: 100%;
			overflow-x: auto;
		}

		.fahrzeug-tabelle {
			width: 100%;
			background: #fff;
			border-collapse: collapse;
			border-radius: 8px;
			overflow: hidden;
			box-shadow: 0 2px 8px rgba(0,0,0,0.05);
		}

		.fahrzeug-tabelle th,
		.fahrzeug-tabelle td {
			padding: 14px 16px;
			text-align: left;
			border-bottom: 1px solid #eee;
		}

		.fahrzeug-tabelle th {
			background-color: #f5f5f5;
			font-weight: bold;
		}

		.wartung-daten td {
			background-color: #fafafa;
			padding: 20px;
		}

		.wartung-container {
			background: #ffffff;
			border-left: 5px solid #4caf50;
			padding: 15px;
			border-radius: 6px;
			box-shadow: 0 2px 6px rgba(0,0,0,0.05);
			margin-top: 10px;
		}

		.wartung-container h3 {
			margin-top: 0;
			margin-bottom: 10px;
			color: #333;
		}

		.wartung-container ul {
			list-style: none;
			padding: 0;
		}

                .wartung-container ul li {
                        margin-bottom: 8px;
                        padding-left: 10px;
                }

                .inspection-form {
                        margin-top: 15px;
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                        gap: 16px;
                        align-items: end;
                }

                .inspection-form .form-group {
                        display: flex;
                        flex-direction: column;
                        gap: 6px;
                }

                .inspection-form .button-group {
                        display: flex;
                        gap: 12px;
                        flex-wrap: wrap;
                        order: -1;
                }

                .inspection-form button {
                        background: #ff9800;
                        color: white;
                        padding: 10px 20px;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                        transition: background 0.2s ease;
                }

                .inspection-form button:hover {
                        background: #fb8c00;
                }

                p {
                        color: #666;
                }

		/* 👇 Mobile-Ansicht */
                @media (max-width: 768px) {
                        .fahrzeug-tabelle thead {
                                display: none; /* Keine Tabellenüberschrift auf Handy */
                        }

                        .inspection-form {
                                grid-template-columns: 1fr;
                        }

                        .inspection-form .button-group {
                                order: 0;
                                justify-content: flex-start;
                        }

                        .fahrzeug-tabelle,
                        .fahrzeug-tabelle tbody,
                        .fahrzeug-tabelle tr,
                        .fahrzeug-tabelle td {
                                display: block;
				width: 100%;
			}

			.fahrzeug-tabelle tr {
				margin-bottom: 15px;
				background: #fff;
				border-radius: 8px;
				box-shadow: 0 2px 6px rgba(0,0,0,0.05);
				padding: 10px;
			}

			.fahrzeug-tabelle td {
				text-align: left;
				padding-left: 50%;
				position: relative;
			}

			.fahrzeug-tabelle td::before {
				content: attr(data-label);
				position: absolute;
				left: 15px;
				top: 14px;
				font-weight: bold;
				color: #555;
			}

			.wartung-daten td {
				padding: 10px;
			}
		}
        </style>

    <main>
        <h1>🚗 Meine Fahrzeuge</h1>

        <div class="table-responsive">
            <table class="fahrzeug-tabelle">
                <thead>
                    <tr>
                        <th>Konzession</th>
                        <th>Kennzeichen</th>
                        <th>Marke</th>
                        <th>Modell</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $currentFahrzeugID = null; 
                    foreach ($fahrzeuge as $fahrzeug): 
                        if ($fahrzeug['FahrzeugID'] !== $currentFahrzeugID): 
                            $currentFahrzeugID = $fahrzeug['FahrzeugID']; 
                    ?>
                        <tr>
                            <td data-label="Konzession"><?= htmlspecialchars($fahrzeug['Konzessionsnummer']) ?></td>
                            <td data-label="Kennzeichen"><?= htmlspecialchars($fahrzeug['Kennzeichen']) ?></td>
                            <td data-label="Marke"><?= htmlspecialchars($fahrzeug['Marke']) ?></td>
                            <td data-label="Modell"><?= htmlspecialchars($fahrzeug['Modell']) ?></td>
                        </tr>
                        <tr class="wartung-daten">
                            <td colspan="4">
                                <div class="wartung-container">
                                    <h3>🛠️ Nächster Wartungstermin</h3>
                                    <?php if (!empty($fahrzeug['Wartungsdatum'])): ?>
                                        <ul>
                                            <li><strong>📅 Datum:</strong> <?= htmlspecialchars(formatDateTime($fahrzeug['Wartungsdatum'])) ?></li>
                                            <li><strong>📝 Beschreibung:</strong> <?= htmlspecialchars($fahrzeug['Beschreibung']) ?></li>
                                            <li><strong>🏢 Werkstatt:</strong> <?= htmlspecialchars($fahrzeug['Werkstatt']) ?></li>
											<?php if (!empty($fahrzeug['Bemerkungen'])): ?>
												<li><strong>💬 Bemerkung:</strong> <?= htmlspecialchars($fahrzeug['Bemerkungen']) ?></li>
											<?php endif; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p>❗ Keine Wartungsdaten verfügbar.</p>
                                    <?php endif; ?>

                                    <!-- Formular zur Inspektionsmeldung -->
                                    <form method="POST" class="inspection-form">
                                        <input type="hidden" name="fahrzeug_id" value="<?= htmlspecialchars($fahrzeug['FahrzeugID']) ?>">
                                        <input type="hidden" name="konzession" value="<?= htmlspecialchars($fahrzeug['Konzessionsnummer']) ?>">
                                        <input type="hidden" name="kennzeichen" value="<?= htmlspecialchars($fahrzeug['Kennzeichen']) ?>">

                                        <div class="button-group">
                                            <button type="submit" name="inspektion_melden">
                                                🔧 Inspektion melden
                                            </button>
                                        </div>

                                        <div class="form-group">
                                            <label for="meldung-<?= htmlspecialchars($fahrzeug['FahrzeugID']) ?>">Anzeigetext:</label>
                                            <input id="meldung-<?= htmlspecialchars($fahrzeug['FahrzeugID']) ?>" type="text" name="meldung" placeholder="z. B. Inspektion oder Ölservice" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="rest-km-<?= htmlspecialchars($fahrzeug['FahrzeugID']) ?>">Restkilometer:</label>
                                            <input id="rest-km-<?= htmlspecialchars($fahrzeug['FahrzeugID']) ?>" type="number" name="rest_km" placeholder="z. B. 1200" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="gesamt-km-<?= htmlspecialchars($fahrzeug['FahrzeugID']) ?>">Gesamtkilometerstand:</label>
                                            <input id="gesamt-km-<?= htmlspecialchars($fahrzeug['FahrzeugID']) ?>" type="number" name="gesamt_km" placeholder="z. B. 174500" required>
                                        </div>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endif; endforeach; ?>
                </tbody>
            </table>
        </div>

        <p style="text-align: center; margin-top: 30px;">🚀 Mehr Funktionen folgen bald!</p>
    </main>
</body>
</html>
