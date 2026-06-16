<?php
/**
 * Hilfsfunktionen zur Berechnung wiederkehrender Zeiträume für Dashboard und Statistiken.
 */

/**
 * Ermittelt Start, Ende und optionale Label-Beschreibung eines gewünschten Zeitraums.
 *
 * @param string $zeitraum  Der gewünschte Zeitraum (z. B. "monat", "woche", "tag", "quartal", "jahr", "individuell").
 * @param array  $optionen  Zusätzliche Optionen:
 *                          - offset: Ganzzahliger Versatz (für Woche/Monat/Quartal/Jahr)
 *                          - start_date: Startdatum (Y-m-d) für individuelle Zeiträume
 *                          - end_date: Enddatum (Y-m-d) für individuelle Zeiträume
 *
 * @return array{start_date: string, end_date: string, label: string|null}
 *
 * @throws InvalidArgumentException Wenn Parameter ungültig sind.
 */
function berechneZeitraum(string $zeitraum, array $optionen = []): array
{
    $zeitraum = strtolower(trim($zeitraum));
    $offset = isset($optionen['offset']) ? (int) $optionen['offset'] : 0;
    $heute = new DateTimeImmutable('today');
    $label = null;

    switch ($zeitraum) {
        case 'letzte_woche':
            return berechneZeitraum('woche', ['offset' => -1]);

        case 'tag':
            $start = $heute->modify(($offset >= 0 ? '+' : '') . $offset . ' day');
            $end = $start;
            $label = $start->format('d.m.Y');
            break;

        case 'woche':
            $start = $heute->modify('monday this week')->modify(($offset >= 0 ? '+' : '') . $offset . ' week');
            $end = $start->modify('sunday this week');
            $label = $start->format('d.m.Y') . ' - ' . $end->format('d.m.Y');
            break;

        case 'monat':
            $start = $heute->modify('first day of this month')->modify(($offset >= 0 ? '+' : '') . $offset . ' month');
            $end = $start->modify('last day of this month');
            $label = $start->format('d.m.Y') . ' - ' . $end->format('d.m.Y');
            break;

        case 'quartal':
            $aktuellesQuartal = (int) ceil((int) $heute->format('n') / 3);
            $zielQuartal = $aktuellesQuartal + $offset;

            $jahrVersatz = intdiv($zielQuartal - 1, 4);
            $zielQuartal = (($zielQuartal - 1) % 4) + 1;
            $zielJahr = (int) $heute->format('Y') + $jahrVersatz;

            $startMonat = (($zielQuartal - 1) * 3) + 1;
            $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $zielJahr, $startMonat));
            $end = $start->modify('+2 months')->modify('last day of this month');
            $label = $start->format('d.m.Y') . ' - ' . $end->format('d.m.Y');
            break;

        case 'jahr':
            $zielJahr = (int) $heute->format('Y') + $offset;
            $start = new DateTimeImmutable($zielJahr . '-01-01');
            $end = new DateTimeImmutable($zielJahr . '-12-31');
            $label = (string) $zielJahr;
            break;

        case 'individuell':
            $startEingabe = $optionen['start_date'] ?? null;
            $endEingabe = $optionen['end_date'] ?? null;

            if (!$startEingabe || !$endEingabe) {
                throw new InvalidArgumentException('Bitte sowohl Start- als auch Enddatum angeben.');
            }

            $start = DateTimeImmutable::createFromFormat('Y-m-d', $startEingabe) ?: false;
            $end = DateTimeImmutable::createFromFormat('Y-m-d', $endEingabe) ?: false;

            if ($start === false || $start->format('Y-m-d') !== $startEingabe) {
                throw new InvalidArgumentException('Ungültiges Startdatum.');
            }

            if ($end === false || $end->format('Y-m-d') !== $endEingabe) {
                throw new InvalidArgumentException('Ungültiges Enddatum.');
            }

            if ($start > $end) {
                throw new InvalidArgumentException('Das Startdatum darf nicht nach dem Enddatum liegen.');
            }

            $label = $start->format('d.m.Y') . ' - ' . $end->format('d.m.Y');
            break;

        default:
            // Fallback auf aktuellen Monat
            return berechneZeitraum('monat', $optionen);
    }

    return [
        'start_date' => $start->format('Y-m-d'),
        'end_date' => $end->format('Y-m-d'),
        'label' => $label,
    ];
}
