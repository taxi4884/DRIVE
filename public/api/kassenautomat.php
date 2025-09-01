<?php
header("Content-Type: application/xml; charset=UTF-8");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 📌 Datenbankverbindung einbinden
require_once "../../includes/db.php";

// 📌 XML-Daten aus der HTTP-POST-Anfrage lesen
$input = file_get_contents("php://input");

if (empty($input)) {
    echo "<error>Leere Anfrage</error>";
    exit;
}

// 📌 XML-Daten parsen
try {
    $xml = new SimpleXMLElement($input);
} catch (Exception $e) {
    echo "<error>Ungültiges XML</error>";
    exit;
}

// 📌 Werte aus XML extrahieren
$messageType = strtoupper((string) $xml->header->messagetype);
$device = (string) $xml->header->device;
$driverNumber = (string) $xml->body->driver;  // 🚨 Ändere von FahrerID auf Fahrernummer
$shiftId = (string) $xml->body->shiftId;
$amount = (float) $xml->body->amount; // Betrag

// 📌 Debugging: Empfangene Daten ausgeben
error_log("📩 Empfangene Daten: MessageType=$messageType, Device=$device, Driver=$driverNumber, ShiftID=$shiftId, Amount=$amount");

// 📌 Antwort-XML erstellen
$responseXml = new SimpleXMLElement("<tamicts></tamicts>");
$header = $responseXml->addChild("header");
$header->addChild("messagetype", "ACK"); // Standardantwort
$body = $responseXml->addChild("body");

// 📌 Anfragetypen auswerten
switch ($messageType) {
    case "LGN":  // Login-Anfrage
        $body->addChild("status", "OK");
        $body->addChild("message", "Login erfolgreich");
        break;

    case "STA":  // Schichtabrechnung speichern
        if (!empty($shiftId) && $amount > 0) {
            $stmt = $pdo->prepare("UPDATE Umsatz SET TatsaechlichAbgerechnet = ? WHERE SchichtID = ?");
            if ($stmt->execute([$amount, $shiftId])) {
                $body->addChild("status", "Success");
                $body->addChild("message", "Umsatz als abgerechnet markiert");
            } else {
                $body->addChild("status", "Error");
                $body->addChild("message", "Fehler beim Aktualisieren der Abrechnung");
            }
        } else {
            $body->addChild("status", "Error");
            $body->addChild("message", "Ungültige Daten");
        }
        break;

    case "GET":  // Umsatz für eine Fahrernummer abrufen
        if (!empty($driverNumber)) {
            // 🚨 Wandle Fahrernummer in FahrerID um
            $stmt = $pdo->prepare("SELECT FahrerID FROM Fahrer WHERE Fahrernummer = ?");
            $stmt->execute([$driverNumber]);
            $driverRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($driverRow) {
                $fahrerID = $driverRow['FahrerID']; // 🚀 Die tatsächliche FahrerID aus der Datenbank

                // 🚨 Hole Umsätze für diesen Fahrer
                $stmt = $pdo->prepare("
                    SELECT UmsatzID, SchichtID, Datum, TaxameterUmsatz, Kartenzahlung, Rechnungsfahrten 
                    FROM Umsatz 
                    WHERE FahrerID = ? AND TatsaechlichAbgerechnet IS NULL
                ");
                $stmt->execute([$fahrerID]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($results) > 0) {
                    foreach ($results as $row) {
                        $shift = $body->addChild("shift");
                        $shift->addChild("UmsatzID", $row['UmsatzID']);
                        $shift->addChild("SchichtID", $row['SchichtID']);
                        $shift->addChild("Datum", $row['Datum']);
                        $shift->addChild("TaxameterUmsatz", $row['TaxameterUmsatz']);
                        $shift->addChild("Kartenzahlung", $row['Kartenzahlung']);
                        $shift->addChild("Rechnungsfahrten", $row['Rechnungsfahrten']);
                    }
                } else {
                    $body->addChild("status", "NoData");
                    $body->addChild("message", "Kein offener Umsatz für Fahrer $driverNumber gefunden");
                }
            } else {
                $body->addChild("status", "Error");
                $body->addChild("message", "Fahrernummer $driverNumber nicht gefunden");
            }
        } else {
            $body->addChild("status", "Error");
            $body->addChild("message", "Keine gültige Fahrernummer übermittelt");
        }
        break;

    default:
        $body->addChild("status", "Error");
        $body->addChild("message", "Unbekannter Request-Typ");
}

// 📌 Antwort als XML zurückgeben
echo $responseXml->asXML();
?>
