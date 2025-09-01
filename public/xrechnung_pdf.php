<?php
require_once '../includes/head.php';
require_once '../fpdf/fpdf.php'; 

if (!isset($_FILES['xmlFile'])) {
    die('Keine Datei hochgeladen.');
}

$xmlContent = file_get_contents($_FILES['xmlFile']['tmp_name']);
$xml = new DOMDocument();
$xml->loadXML($xmlContent);

// Hilfsfunktion für Namespace
function getElementText($xml, $path) {
    $parts = explode('/', $path);
    $node = $xml->documentElement;
    foreach ($parts as $part) {
        $nodes = $node->getElementsByTagNameNS('*', preg_replace('/^.*:/', '', $part));
        if ($nodes->length == 0) return '';
        $node = $nodes->item(0);
    }
    return $node->textContent;
}

// Werte extrahieren
$invoiceId = getElementText($xml, 'rsm:ExchangedDocument/ram:ID');
$issueDate = getElementText($xml, 'rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString');
$dueDateDesc = getElementText($xml, 'rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:Description');

$grandTotal = getElementText($xml, 'rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:GrandTotalAmount');
$vatAmount = getElementText($xml, 'rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:CalculatedAmount');
$vatRate = getElementText($xml, 'rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax/ram:RateApplicablePercent');

// Positionen auslesen
$positions = [];
foreach ($xml->getElementsByTagNameNS('*', 'IncludedSupplyChainTradeLineItem') as $item) {
    $name = $item->getElementsByTagNameNS('*', 'Name')->item(0)->nodeValue ?? '';
    $qty = $item->getElementsByTagNameNS('*', 'BilledQuantity')->item(0)->nodeValue ?? '';
    $price = $item->getElementsByTagNameNS('*', 'ChargeAmount')->item(0)->nodeValue ?? '';
    $total = $item->getElementsByTagNameNS('*', 'LineTotalAmount')->item(0)->nodeValue ?? '';
    $positions[] = compact('name', 'qty', 'price', 'total');
}

// PDF erzeugen
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, "Rechnung: $invoiceId", 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, "Rechnungsdatum: " . date('d.m.Y', strtotime($issueDate)), 0, 1, 'C');
$pdf->Ln(10);

// Positionen
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(80, 10, 'Bezeichnung', 1);
$pdf->Cell(20, 10, 'Menge', 1);
$pdf->Cell(30, 10, 'Einzelpreis', 1);
$pdf->Cell(30, 10, 'Gesamt', 1);
$pdf->Ln();

$pdf->SetFont('Arial', '', 12);
foreach ($positions as $pos) {
    $pdf->Cell(80, 10, utf8_decode($pos['name']), 1);
    $pdf->Cell(20, 10, $pos['qty'], 1);
    $pdf->Cell(30, 10, $pos['price'], 1);
    $pdf->Cell(30, 10, $pos['total'], 1);
    $pdf->Ln();
}

$pdf->Ln(10);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, "Zwischensumme: " . number_format(($grandTotal - $vatAmount), 2, ',', '.') . " EUR", 0, 1);
$pdf->Cell(0, 10, "MwSt ($vatRate%): " . number_format($vatAmount, 2, ',', '.') . " EUR", 0, 1);
$pdf->Cell(0, 10, "Gesamtbetrag: " . number_format($grandTotal, 2, ',', '.') . " EUR", 0, 1);
$pdf->Ln(5);
$pdf->Cell(0, 10, "Zahlungsziel: $dueDateDesc", 0, 1);

// Ausgabe
$pdf->Output('I', 'Rechnung_' . $invoiceId . '.pdf');
?>
