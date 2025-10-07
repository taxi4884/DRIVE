<?php

class UmsatzRepository
{
    private const MUTABLE_FIELDS = [
        'TaxameterUmsatz',
        'OhneTaxameter',
        'Kartenzahlung',
        'Rechnungsfahrten',
        'Krankenfahrten',
        'Gutscheine',
        'Alita',
        'TankenWaschen',
        'SonstigeAusgaben',
        'Notiz',
    ];

    private const DEFAULT_VALUES = [
        'Notiz' => null,
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public function getByDriverAndDate(int $fahrerId, string $datum): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM Umsatz WHERE FahrerID = ? AND Datum = ?'
        );
        $stmt->execute([$fahrerId, $datum]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }

    public function getByDriverAndRange(int $fahrerId, string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT Datum, TaxameterUmsatz, OhneTaxameter, Kartenzahlung, Rechnungsfahrten, Krankenfahrten, ' .
            'Gutscheine, Alita, TankenWaschen, SonstigeAusgaben, Notiz, Abgerechnet ' .
            'FROM Umsatz WHERE FahrerID = ? AND Datum BETWEEN ? AND ? ORDER BY Datum ASC'
        );
        $stmt->execute([$fahrerId, $startDate, $endDate]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(int $fahrerId, array $data): void
    {
        $placeholders = implode(', ', self::MUTABLE_FIELDS);
        $valuesPlaceholders = implode(', ', array_fill(0, count(self::MUTABLE_FIELDS), '?'));

        $stmt = $this->pdo->prepare(
            sprintf(
                'INSERT INTO Umsatz (FahrerID, Datum, %s) VALUES (?, ?, %s)',
                $placeholders,
                $valuesPlaceholders
            )
        );

        $params = [$fahrerId, $data['Datum']];
        foreach (self::MUTABLE_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $params[] = $data[$field];
            } else {
                $params[] = self::DEFAULT_VALUES[$field] ?? 0;
            }
        }

        $stmt->execute($params);
    }

    public function update(int $fahrerId, string $datum, array $data): void
    {
        $fields = [];
        $params = [];

        foreach (self::MUTABLE_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = sprintf('%s = ?', $field);
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return;
        }

        $params[] = $fahrerId;
        $params[] = $datum;

        $sql = sprintf(
            'UPDATE Umsatz SET %s WHERE FahrerID = ? AND Datum = ?',
            implode(', ', $fields)
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function delete(int $fahrerId, string $datum): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM Umsatz WHERE FahrerID = ? AND Datum = ?'
        );

        return $stmt->execute([$fahrerId, $datum]);
    }

    public function findOpenShiftDates(int $fahrerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT DATE(sfa.anmeldung) AS offenes_datum
             FROM sync_fahreranmeldung sfa
             JOIN Fahrer f
               ON sfa.fahrer = f.Fahrernummer OR sfa.fahrer = f.fms_alias
             WHERE f.FahrerID = :fahrer_id
               AND DATE(sfa.anmeldung) NOT IN (
                   SELECT DATE(Datum) FROM Umsatz WHERE FahrerID = :fahrer_id
               )
             ORDER BY offenes_datum DESC'
        );

        $stmt->execute(['fahrer_id' => $fahrerId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function calculateBargeld(array $entry): float
    {
        $income = (float) ($entry['TaxameterUmsatz'] ?? 0) + (float) ($entry['OhneTaxameter'] ?? 0);
        $expenses = (float) ($entry['Kartenzahlung'] ?? 0)
            + (float) ($entry['Rechnungsfahrten'] ?? 0)
            + (float) ($entry['Krankenfahrten'] ?? 0)
            + (float) ($entry['Gutscheine'] ?? 0)
            + (float) ($entry['Alita'] ?? 0)
            + (float) ($entry['TankenWaschen'] ?? 0)
            + (float) ($entry['SonstigeAusgaben'] ?? 0);

        return $income - $expenses;
    }

    public static function calculateTotals(array $entries): array
    {
        $umsatz = 0.0;
        $bargeld = 0.0;

        foreach ($entries as $entry) {
            $umsatz += (float) ($entry['TaxameterUmsatz'] ?? 0) + (float) ($entry['OhneTaxameter'] ?? 0);
            $bargeld += self::calculateBargeld($entry);
        }

        return [
            'umsatz' => $umsatz,
            'bargeld' => $bargeld,
        ];
    }

    public static function aggregateByDate(array $entries): array
    {
        $totals = [];

        foreach ($entries as $entry) {
            $dateKey = substr($entry['Datum'], 0, 10);
            $totals[$dateKey] = ($totals[$dateKey] ?? 0)
                + (float) ($entry['TaxameterUmsatz'] ?? 0)
                + (float) ($entry['OhneTaxameter'] ?? 0);
        }

        ksort($totals);

        $result = [];
        foreach ($totals as $date => $sum) {
            $result[] = [
                'Datum' => $date,
                'GesamtUmsatz' => $sum,
            ];
        }

        return $result;
    }

    public static function aggregateByType(array $entries): array
    {
        $totals = [
            'Barzahlung' => 0.0,
            'Kartenzahlung' => 0.0,
            'Rechnungsfahrten' => 0.0,
            'Krankenfahrten' => 0.0,
            'Gutscheine' => 0.0,
            'Alita' => 0.0,
        ];

        foreach ($entries as $entry) {
            $income = (float) ($entry['TaxameterUmsatz'] ?? 0) + (float) ($entry['OhneTaxameter'] ?? 0);
            $nonCash = (float) ($entry['Kartenzahlung'] ?? 0)
                + (float) ($entry['Rechnungsfahrten'] ?? 0)
                + (float) ($entry['Krankenfahrten'] ?? 0)
                + (float) ($entry['Gutscheine'] ?? 0)
                + (float) ($entry['Alita'] ?? 0);

            $totals['Barzahlung'] += $income - $nonCash;
            $totals['Kartenzahlung'] += (float) ($entry['Kartenzahlung'] ?? 0);
            $totals['Rechnungsfahrten'] += (float) ($entry['Rechnungsfahrten'] ?? 0);
            $totals['Krankenfahrten'] += (float) ($entry['Krankenfahrten'] ?? 0);
            $totals['Gutscheine'] += (float) ($entry['Gutscheine'] ?? 0);
            $totals['Alita'] += (float) ($entry['Alita'] ?? 0);
        }

        return $totals;
    }

    public static function aggregateExpenses(array $entries): array
    {
        $totals = [
            'Tanken und Waschen' => 0.0,
            'Sonstige Ausgaben' => 0.0,
        ];

        foreach ($entries as $entry) {
            $totals['Tanken und Waschen'] += (float) ($entry['TankenWaschen'] ?? 0);
            $totals['Sonstige Ausgaben'] += (float) ($entry['SonstigeAusgaben'] ?? 0);
        }

        return $totals;
    }
}
