<?php
require_once '../../includes/db.php';
require_once __DIR__ . '/mailer_public.php';

$teilnehmerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$meldung = null;
$meldungTyp = 'info';
$teilnehmer = null;
$termine = [];
$maxTeilnehmer = 8;
$gewaehlterTermin = null;

if ($teilnehmerId > 0) {
    $stmt = $pdo->prepare('SELECT id, vorname, email, stufe, schulungstermin_id FROM schulungsteilnehmer WHERE id = :id');
    $stmt->execute([':id' => $teilnehmerId]);
    $teilnehmer = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$teilnehmer) {
    $meldung = 'Teilnehmer nicht gefunden. Bitte prüfen Sie den Link.';
    $meldungTyp = 'error';
} else {
    $stufe = (int) $teilnehmer['stufe'];

    $termineStmt = $pdo->prepare('SELECT id, termin FROM schulungstermine WHERE stufe = :stufe ORDER BY termin ASC');
    $termineStmt->execute([':stufe' => $stufe]);
    $termine = $termineStmt->fetchAll(PDO::FETCH_ASSOC);
    $terminCounts = [];
    $terminCountStmt = $pdo->query('SELECT schulungstermin_id AS termin_id, COUNT(*) AS teilnehmer FROM schulungsteilnehmer WHERE schulungstermin_id IS NOT NULL GROUP BY schulungstermin_id');
    foreach ($terminCountStmt->fetchAll(PDO::FETCH_ASSOC) as $countRow) {
        $terminCounts[(int) $countRow['termin_id']] = (int) $countRow['teilnehmer'];
    }

    if (!empty($teilnehmer['schulungstermin_id'])) {
        $gewaehlterTerminStmt = $pdo->prepare('SELECT termin FROM schulungstermine WHERE id = :id');
        $gewaehlterTerminStmt->execute([':id' => (int) $teilnehmer['schulungstermin_id']]);
        $gewaehlterTermin = $gewaehlterTerminStmt->fetchColumn();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['termin_id'])) {
        $terminId = (int) $_POST['termin_id'];
        $terminCheck = $pdo->prepare('SELECT id, termin FROM schulungstermine WHERE id = :id AND stufe = :stufe');
        $terminCheck->execute([':id' => $terminId, ':stufe' => $stufe]);
        $termin = $terminCheck->fetch(PDO::FETCH_ASSOC);

        if ($termin) {
            $aktuelleTerminId = (int) $teilnehmer['schulungstermin_id'];
            $limitCheck = $pdo->prepare('SELECT COUNT(*) FROM schulungsteilnehmer WHERE schulungstermin_id = :termin_id');
            $limitCheck->execute([':termin_id' => $terminId]);
            $teilnehmerAnzahl = (int) $limitCheck->fetchColumn();

            if ($teilnehmerAnzahl >= $maxTeilnehmer && $terminId !== $aktuelleTerminId) {
                $meldung = 'Der ausgewählte Termin ist bereits ausgebucht.';
                $meldungTyp = 'error';
            } else {
            $pdo->beginTransaction();
            try {
                $update = $pdo->prepare('UPDATE schulungsteilnehmer SET schulungstermin_id = :termin_id, rueckmeldung_status = 1 WHERE id = :id');
                $update->execute([':termin_id' => $terminId, ':id' => $teilnehmerId]);

                $anmeldung = $pdo->prepare('
                    INSERT INTO schulung_termin_anmeldungen (teilnehmer_id, termin_id, angemeldet_am)
                    VALUES (:teilnehmer_id, :termin_id, NOW())
                    ON DUPLICATE KEY UPDATE termin_id = VALUES(termin_id), angemeldet_am = NOW()
                ');
                $anmeldung->execute([':teilnehmer_id' => $teilnehmerId, ':termin_id' => $terminId]);

                $pdo->commit();

                $terminDatum = $termin['termin'];
                $terminDe = (new DateTime($terminDatum))->format('d.m.Y H:i');
                $mailOk = sendTerminBestaetigung(
                    $teilnehmer['vorname'],
                    $teilnehmer['email'],
                    $terminDatum,
                    $stufe,
                    $teilnehmerId
                );

                if ($mailOk) {
                    $meldung = 'Vielen Dank! Dein Termin am ' . $terminDe . ' wurde bestätigt.';
                    $meldungTyp = 'success';
                } else {
                    $meldung = 'Der Termin wurde gespeichert, die Bestätigung konnte jedoch nicht per Mail gesendet werden.';
                    $meldungTyp = 'warning';
                }

                $teilnehmer['schulungstermin_id'] = $terminId;
                $gewaehlterTermin = $terminDatum;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $meldung = 'Beim Speichern ist ein Fehler aufgetreten. Bitte versuche es erneut.';
                $meldungTyp = 'error';
            }
            }
        } else {
            $meldung = 'Der ausgewählte Termin ist nicht gültig.';
            $meldungTyp = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termin auswählen | 4884 - Ihr Funktaxi</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .card {
            background: #fff;
            padding: 24px;
            border-radius: 10px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
            width: 420px;
        }

        h1 {
            margin-top: 0;
            font-size: 22px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .alert-success {
            background: #e6f7e9;
            color: #217a39;
        }

        .alert-error {
            background: #fdecea;
            color: #b42318;
        }

        .alert-warning {
            background: #fff4e5;
            color: #8a5300;
        }

        .alert-info {
            background: #e7f3ff;
            color: #084298;
        }

        .termin-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .termin-option:last-child {
            border-bottom: none;
        }

        button {
            margin-top: 16px;
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 6px;
            background: #0d6efd;
            color: #fff;
            font-weight: bold;
            cursor: pointer;
        }

        button:disabled {
            background: #9fbaf5;
            cursor: not-allowed;
        }

        .muted {
            color: #6c757d;
            font-size: 13px;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>Termin auswählen</h1>

    <?php if ($meldung): ?>
        <div class="alert alert-<?php echo htmlspecialchars($meldungTyp); ?>">
            <?php echo htmlspecialchars($meldung); ?>
        </div>
    <?php endif; ?>

    <?php if ($teilnehmer): ?>
        <?php if ($gewaehlterTermin): ?>
            <?php $gewaehlterTerminDe = (new DateTime($gewaehlterTermin))->format('d.m.Y H:i'); ?>
            <p>Hallo <?php echo htmlspecialchars($teilnehmer['vorname']); ?>, dein Termin für Stufe <?php echo (int) $teilnehmer['stufe']; ?> ist bestätigt.</p>
            <div class="alert alert-info">Dein bestätigter Termin: <?php echo htmlspecialchars($gewaehlterTerminDe); ?></div>
            <p class="muted">Du erhältst eine Bestätigung per E-Mail. Falls du Änderungen brauchst, melde dich bitte beim Team.</p>
        <?php else: ?>
            <p>Hallo <?php echo htmlspecialchars($teilnehmer['vorname']); ?>, bitte wähle deinen Termin für Stufe <?php echo (int) $teilnehmer['stufe']; ?>.</p>

            <?php if (empty($termine)): ?>
                <div class="alert alert-info">Aktuell sind keine Termine verfügbar. Bitte probiere es später erneut.</div>
            <?php else: ?>
                <form method="POST">
                    <?php foreach ($termine as $termin): ?>
                        <?php
                            $terminDatum = (new DateTime($termin['termin']))->format('d.m.Y H:i');
                            $checked = (int) $teilnehmer['schulungstermin_id'] === (int) $termin['id'];
                            $belegt = $terminCounts[(int) $termin['id']] ?? 0;
                            $ausgebucht = $belegt >= $maxTeilnehmer;
                        ?>
                        <label class="termin-option">
                            <input type="radio" name="termin_id" value="<?php echo (int) $termin['id']; ?>" <?php echo $checked ? 'checked' : ''; ?> <?php echo $ausgebucht ? 'disabled' : ''; ?> required>
                            <span><?php echo htmlspecialchars($terminDatum); ?></span>
                            <?php if ($ausgebucht): ?>
                                <span class="muted">(ausgebucht)</span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>

                    <button type="submit">Termin bestätigen</button>
                    <p class="muted">Mit der Bestätigung erhältst du eine Terminbestätigung per E-Mail.</p>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>