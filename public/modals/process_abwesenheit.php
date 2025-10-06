<?php
// process_abwesenheit.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/db.php';
require_once 'send_abwesenheit.php'; // E-Mail-Funktion einbinden

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_abwesenheit'])) {
    $mitarbeiter_id = (int)$_POST['mitarbeiter_id'];
    $typ = 'Krank'; 
    $startdatum = $_POST['startdatum'];
    $enddatum = $_POST['enddatum'];
    $bemerkungen = trim($_POST['bemerkungen'] ?? '');

    if (empty($mitarbeiter_id) || empty($startdatum) || empty($enddatum)) {
        die('Alle Pflichtfelder müssen ausgefüllt werden!');
    }

    try {
        $stmtMitarbeiter = $pdo->prepare("
            SELECT vorname, nachname
            FROM mitarbeiter_zentrale
            WHERE mitarbeiter_id = ?
              AND status = 'Aktiv'
            LIMIT 1
        ");
        $stmtMitarbeiter->execute([$mitarbeiter_id]);
        $mitarbeiter = $stmtMitarbeiter->fetch(PDO::FETCH_ASSOC);

        if (!$mitarbeiter) {
            die('Mitarbeiter mit der angegebenen ID wurde nicht gefunden.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO abwesenheiten_zentrale (mitarbeiter_id, typ, startdatum, enddatum, bemerkungen)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$mitarbeiter_id, $typ, $startdatum, $enddatum, $bemerkungen]);

        $abwesenheitId = $pdo->lastInsertId();

        $stmtZentrale = $pdo->prepare("
			SELECT BenutzerID, Name, Email
			FROM Benutzer
			WHERE AbwesenheitZentrale = 1
        ");
        $stmtZentrale->execute();
        $zentraleBenutzer = $stmtZentrale->fetchAll(PDO::FETCH_ASSOC);

        foreach ($zentraleBenutzer as $zUser) {
            sendeKrankmeldungEmail(
                $zUser['Email'],
                $zUser['Name'],
                [
                    'vorname' => $mitarbeiter['vorname'],
                    'nachname' => $mitarbeiter['nachname'],
                    'typ' => $typ,
                    'startdatum' => $startdatum,
                    'enddatum' => $enddatum,
                    'bemerkungen' => $bemerkungen
                ]
            );
        }

        header('Location: ../zentrale_dashboard.php?success=Krankmeldung erfasst');
        exit;
    } catch (PDOException $e) {
        die('Fehler beim Speichern der Krankmeldung: ' . $e->getMessage());
    }
}
?>
