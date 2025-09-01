<?php
require_once '../includes/head.php';

if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

// Nur POST-Anfragen verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Eingaben bereinigen
    $nachricht     = trim($_POST['nachricht'] ?? '');
    $gueltig_bis   = $_POST['gueltig_bis'] ?? '';
    $erstellt_von  = trim($_POST['erstellt_von'] ?? 'Unbekannt');

    // Validierung
    if (empty($nachricht) || empty($gueltig_bis)) {
        die("Bitte alle Pflichtfelder ausfüllen.");
    }

    // Optional: Format prüfen
    $gueltig_bis_dt = date_create_from_format('Y-m-d', $gueltig_bis);
    if (!$gueltig_bis_dt) {
        die("Ungültiges Datumsformat.");
    }

    // In Datenbank einfügen
	$stmt = $pdo->prepare("INSERT INTO fahrer_mitteilungen (nachricht, gueltig_bis, erstellt_von) VALUES (?, ?, ?)");
	$stmt->execute([$nachricht, $gueltig_bis, $erstellt_von]);

    // Weiterleitung mit Erfolg
    header("Location: fahrer.php?msg=mitteilung_erfolgreich");
    exit();
} else {
    // Nicht-POST-Anfragen umleiten
    header("Location: fahrer.php");
    exit();
}
