<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../includes/db.php'; // Verbindung zur Datenbank

try {
    $heute = date('Y-m-d');
    $aktuellerMonatStart = date('Y-m-01');
    $aktuellerMonatEnde = date('Y-m-t');
    $naechsterMonatStart = date('Y-m-01', strtotime('+1 month'));
    $naechsterMonatEnde = date('Y-m-t', strtotime('+1 month'));

    // 1. Abfrage: Alle Schichten für den heutigen Tag (ohne krank gemeldete Mitarbeiter)
    $stmt = $pdo->prepare("
        SELECT 
            dp.datum,
            m.vorname,
            m.nachname,
            s.name AS schicht_name,
            s.startzeit,
            s.endzeit
        FROM dienstplan dp
        JOIN mitarbeiter_zentrale m ON dp.mitarbeiter_id = m.mitarbeiter_id
        JOIN schichten s ON dp.schicht_id = s.schicht_id
        WHERE dp.datum = :heute
        AND NOT EXISTS (
            SELECT 1 
            FROM abwesenheiten_zentrale a
            WHERE a.mitarbeiter_id = dp.mitarbeiter_id
            AND :heute BETWEEN a.startdatum AND a.enddatum
            AND a.typ = 'Krank'
        )
    ");
    $stmt->execute(['heute' => $heute]);
    $schichtenHeute = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Abfrage: Krank gemeldete Mitarbeiter ohne Schicht am heutigen Tag
    $stmt = $pdo->prepare("
        SELECT 
            a.startdatum,
            a.enddatum,
            m.vorname,
            m.nachname
        FROM abwesenheiten_zentrale a
        JOIN mitarbeiter_zentrale m ON a.mitarbeiter_id = m.mitarbeiter_id
        WHERE :heute BETWEEN a.startdatum AND a.enddatum
        AND a.typ = 'Krank'
        AND NOT EXISTS (
            SELECT 1 
            FROM dienstplan dp
            WHERE dp.mitarbeiter_id = a.mitarbeiter_id AND dp.datum = :heute
        )
    ");
    $stmt->execute(['heute' => $heute]);
    $abwesenheitenKrank = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Abfrage: Schichten für den aktuellen Monat (ohne krank gemeldete Mitarbeiter)
    $stmt = $pdo->prepare("
        SELECT 
            dp.datum,
            m.vorname,
            m.nachname,
            s.name AS schicht_name,
            s.startzeit,
            s.endzeit
        FROM dienstplan dp
        JOIN mitarbeiter_zentrale m ON dp.mitarbeiter_id = m.mitarbeiter_id
        JOIN schichten s ON dp.schicht_id = s.schicht_id
        WHERE dp.datum BETWEEN :monat_start AND :monat_ende
        AND NOT EXISTS (
            SELECT 1 
            FROM abwesenheiten_zentrale a
            WHERE a.mitarbeiter_id = dp.mitarbeiter_id
            AND dp.datum BETWEEN a.startdatum AND a.enddatum
            AND a.typ = 'Krank'
        )
    ");
    $stmt->execute(['monat_start' => $aktuellerMonatStart, 'monat_ende' => $aktuellerMonatEnde]);
    $schichtenAktuellerMonat = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Abfrage: Schichten für den nächsten Monat (ohne krank gemeldete Mitarbeiter)
    $stmt = $pdo->prepare("
        SELECT 
            dp.datum,
            m.vorname,
            m.nachname,
            s.name AS schicht_name,
            s.startzeit,
            s.endzeit
        FROM dienstplan dp
        JOIN mitarbeiter_zentrale m ON dp.mitarbeiter_id = m.mitarbeiter_id
        JOIN schichten s ON dp.schicht_id = s.schicht_id
        WHERE dp.datum BETWEEN :monat_start AND :monat_ende
        AND NOT EXISTS (
            SELECT 1 
            FROM abwesenheiten_zentrale a
            WHERE a.mitarbeiter_id = dp.mitarbeiter_id
            AND dp.datum BETWEEN a.startdatum AND a.enddatum
            AND a.typ = 'Krank'
        )
    ");
    $stmt->execute(['monat_start' => $naechsterMonatStart, 'monat_ende' => $naechsterMonatEnde]);
    $schichtenNaechsterMonat = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Abfrage: Urlaube im aktuellen Monat
    $stmt = $pdo->prepare("
        SELECT 
            a.startdatum,
            a.enddatum,
            m.vorname,
            m.nachname
        FROM abwesenheiten_zentrale a
        JOIN mitarbeiter_zentrale m ON a.mitarbeiter_id = m.mitarbeiter_id
        WHERE a.startdatum BETWEEN :monat_start AND :monat_ende
        AND a.typ = 'Urlaub'
    ");
    $stmt->execute(['monat_start' => $aktuellerMonatStart, 'monat_ende' => $aktuellerMonatEnde]);
    $urlaubeAktuellerMonat = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Abfrage: Urlaube im nächsten Monat
    $stmt = $pdo->prepare("
        SELECT 
            a.startdatum,
            a.enddatum,
            m.vorname,
            m.nachname
        FROM abwesenheiten_zentrale a
        JOIN mitarbeiter_zentrale m ON a.mitarbeiter_id = m.mitarbeiter_id
        WHERE a.startdatum BETWEEN :monat_start AND :monat_ende
        AND a.typ = 'Urlaub'
    ");
    $stmt->execute(['monat_start' => $naechsterMonatStart, 'monat_ende' => $naechsterMonatEnde]);
    $urlaubeNaechsterMonat = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ausgabe der Daten als JSON
    echo json_encode([
        'today' => $schichtenHeute,
        'krank_ohne_schicht' => $abwesenheitenKrank,
        'current_month' => $schichtenAktuellerMonat,
        'next_month' => $schichtenNaechsterMonat,
        'urlaub_current_month' => $urlaubeAktuellerMonat,
        'urlaub_next_month' => $urlaubeNaechsterMonat
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
