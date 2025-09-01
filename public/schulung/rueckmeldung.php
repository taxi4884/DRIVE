<?php
// publig/schulung/rueckmeldung.php
require_once '../../includes/db.php';

if (isset($_GET['id']) && isset($_GET['status'])) {
    $id = (int)$_GET['id'];
    $status = (int)$_GET['status'];

    try {
        $terminAbfrage = $pdo->prepare("SELECT schulungstermin FROM schulungsteilnehmer WHERE id = :id");
        $terminAbfrage->execute([':id' => $id]);
        $schulungstermin = $terminAbfrage->fetchColumn();

        if ($schulungstermin) {
            $cutoff = new DateTime($schulungstermin);
            $cutoff->modify('-1 day')->setTime(14, 30);
            $jetzt = new DateTime();

            if ($jetzt > $cutoff) {
                $message = "Deine Rückmeldung kam leider etwas spät. Aus organisatorischen Gründen können wir sie jetzt nicht mehr berücksichtigen.";
                $anzeigeStatus = -1;
            } else {
                $query = "UPDATE schulungsteilnehmer SET rueckmeldung_status = :status WHERE id = :id";
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    ':status' => $status,
                    ':id' => $id
                ]);

                if ($stmt->rowCount() > 0) {
                    if ($status === 0) {
                        $terminDatum = new DateTime($schulungstermin);
                        $heute = new DateTime();
                        $tageDiff = $heute->diff($terminDatum)->days;

                        if ($terminDatum > $heute && $tageDiff >= 5) {
                            require_once __DIR__ . '/../schulungsverwaltung.php';
                            checkUndVersendeEinladungen($schulungstermin, 1);
                        }

                        $message = "Schade, dass Sie nicht teilnehmen können. Ihre Absage wurde registriert.";
                        $anzeigeStatus = 0;
                    } elseif ($status === 1) {
                        $message = "Vielen Dank! Ihre Teilnahme wurde erfolgreich registriert.";
                        $anzeigeStatus = 1;
                    } else {
                        $message = "Ungültige Auswahl.";
                        $anzeigeStatus = -2;
                    }
                } else {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM schulungsteilnehmer WHERE id = :id");
                    $checkStmt->execute([':id' => $id]);
                    $exists = $checkStmt->fetchColumn();

                    if ($exists) {
                        $message = "Deine Rückmeldung war bereits gespeichert. Danke für dein Engagement!";
                        $anzeigeStatus = $status; // Anzeige je nachdem, was schon gespeichert war
                    } else {
                        $message = "Keine Änderungen vorgenommen. Überprüfe bitte den Link oder kontaktiere uns.";
                        $anzeigeStatus = -9;
                    }
                }
            }
        } else {
            $message = "Kein Schulungstermin gefunden.";
            $anzeigeStatus = -9;
        }

    } catch (PDOException $e) {
        $message = "Es ist ein Fehler aufgetreten: " . $e->getMessage();
        $anzeigeStatus = -9;
    }
} else {
    $message = "Ungültige Anfrage. Bitte stellen Sie sicher, dass Sie den richtigen Link verwenden.";
    $anzeigeStatus = -9;
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rückmeldung | 4884 - Ihr Funktaxi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
        }

        .container {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 400px;
        }

        .container h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .container p {
            font-size: 16px;
            margin-bottom: 20px;
        }

        .container a {
            text-decoration: none;
            color: #007bff;
        }

        .container a:hover {
            text-decoration: underline;
        }

        .icon {
            font-size: 50px;
            margin-bottom: 20px;
        }

        .success {
            color: green;
        }

        .error {
            color: red;
        }
    </style>
</head>
<body>
<div class="container">
    <?php if ($anzeigeStatus === 1): ?>
        <i class="icon fas fa-check-circle success"></i>
    <?php elseif ($anzeigeStatus === 0): ?>
        <i class="icon fas fa-times-circle error"></i>
    <?php elseif ($anzeigeStatus === -1): ?>
        <i class="icon fas fa-exclamation-triangle" style="color: orange;"></i>
    <?php else: ?>
        <i class="icon fas fa-exclamation-circle error"></i>
    <?php endif; ?>

    <h1>Rückmeldung</h1>
    <p><?php echo htmlspecialchars($message); ?></p>
</div>
</body>
</html>
