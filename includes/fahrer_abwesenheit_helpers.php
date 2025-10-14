<?php

declare(strict_types=1);


if (!function_exists('fetchVacationApproverRecipients')) {
    /**
     * Ermittelt alle Benutzer, die neue Fahrer-Urlaube genehmigen dürfen.
     *
     * Es werden zunächst bekannte Berechtigungs-Flags geprüft. Falls keine der
     * Spalten existiert, erfolgt ein Fallback auf Rollen mit Verwaltungs- oder
     * Zentrale-Bezug.
     *
     * @return array<int, array{BenutzerID: int, Name: string|null}>
     */
    function fetchVacationApproverRecipients(PDO $pdo): array
    {
        try {
            $columnsStmt = $pdo->query('SHOW COLUMNS FROM Benutzer');
            $availableColumns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }

        $availableColumns = array_map(static function ($column) {
            return (string) $column;
        }, $availableColumns ?: []);

        $permissionColumns = array_values(array_intersect(
            [
                'UrlaubGenehmigen',
                'UrlaubGenehmigenFahrer',
                'FahrerUrlaubGenehmigen',
                'AbwesenheitGenehmigen',
                'KrankFahrer',
            ],
            $availableColumns
        ));

        $recipients = [];

        if (!empty($permissionColumns)) {
            $conditions = array_map(static function (string $column): string {
                return sprintf('`%s` = 1', str_replace('`', '``', $column));
            }, $permissionColumns);

            $query = sprintf(
                'SELECT BenutzerID, Name FROM Benutzer WHERE %s',
                implode(' OR ', $conditions)
            );

            try {
                $stmt = $pdo->query($query);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $rows = [];
            }

            foreach ($rows as $row) {
                $id = isset($row['BenutzerID']) ? (int) $row['BenutzerID'] : 0;
                if ($id <= 0) {
                    continue;
                }

                $recipients[$id] = [
                    'BenutzerID' => $id,
                    'Name' => $row['Name'] ?? null,
                ];
            }

            if (!empty($recipients)) {
                return array_values($recipients);
            }
        }

        try {
            $fallbackStmt = $pdo->query('SELECT BenutzerID, Name, Rolle, SekundarRolle FROM Benutzer');
            $rows = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }

        foreach ($rows as $row) {
            $id = isset($row['BenutzerID']) ? (int) $row['BenutzerID'] : 0;
            if ($id <= 0) {
                continue;
            }

            $primaryRole = strtolower(trim((string) ($row['Rolle'] ?? '')));
            $secondaryRoles = array_filter(array_map(
                static function ($role) {
                    return strtolower(trim((string) $role));
                },
                explode(',', (string) ($row['SekundarRolle'] ?? ''))
            ));

            if (
                $primaryRole === 'verwaltung' ||
                $primaryRole === 'zentrale' ||
                in_array('verwaltung', $secondaryRoles, true) ||
                in_array('zentrale', $secondaryRoles, true)
            ) {
                $recipients[$id] = [
                    'BenutzerID' => $id,
                    'Name' => $row['Name'] ?? null,
                ];
            }
        }

        return array_values($recipients);
    }
}

if (!function_exists('formatVacationDate')) {
    function formatVacationDate(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        $timestamp = strtotime($date);

        return $timestamp ? date('d.m.Y', $timestamp) : $date;
    }
}

if (!function_exists('buildVacationOverviewLink')) {
    function buildVacationOverviewLink(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $schemeIsHttps = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        $scheme = $schemeIsHttps ? 'https' : 'http';

        if ($host === '') {
            return 'abwesenheit_fahrer.php';
        }

        return sprintf('%s://%s/abwesenheit_fahrer.php', $scheme, $host);
    }
}

if (!function_exists('fetchDriverName')) {
    function fetchDriverName(PDO $pdo, int $fahrerId): ?string
    {
        try {
            $stmt = $pdo->prepare('SELECT Vorname, Nachname FROM Fahrer WHERE FahrerID = ? LIMIT 1');
            $stmt->execute([$fahrerId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return null;
        }

        if (!$row) {
            return null;
        }

        $vorname = trim((string) ($row['Vorname'] ?? ''));
        $nachname = trim((string) ($row['Nachname'] ?? ''));

        $fullName = trim($vorname . ' ' . $nachname);

        return $fullName !== '' ? $fullName : null;
    }
}

if (!function_exists('createVacationApprovalMessages')) {
    /**
     * Erstellt Nachrichten an alle berechtigten Benutzer über einen neuen Urlaubsantrag.
     *
     * @param int|null $senderId Benutzer-ID des Antragstellers; ohne gültige ID wird keine Nachricht erzeugt.
     */
    function createVacationApprovalMessages(
        PDO $pdo,
        ?int $senderId,
        int $fahrerId,
        string $startdatum,
        string $enddatum,
        ?string $grund,
        ?string $kommentar
    ): void {
        if ($senderId === null || $senderId <= 0) {
            return;
        }

        $recipients = fetchVacationApproverRecipients($pdo);
        if (empty($recipients)) {
            return;
        }

        $fahrerName = fetchDriverName($pdo, $fahrerId) ?? ('Fahrer #' . $fahrerId);

        $startFormatted = formatVacationDate($startdatum);
        $endFormatted = formatVacationDate($enddatum);

        $subject = sprintf('Neuer Urlaubsantrag: %s', $fahrerName);

        $lines = [
            'Es wurde ein neuer Urlaubsantrag eines Fahrers erfasst, der genehmigt werden muss.',
            'Fahrer: ' . $fahrerName,
        ];

        if ($startFormatted !== null || $endFormatted !== null) {
            $period = trim(sprintf('%s – %s', $startFormatted ?? '?', $endFormatted ?? '?'));
            $lines[] = 'Zeitraum: ' . $period;
        }

        if ($grund !== null && $grund !== '') {
            $lines[] = 'Grund: ' . $grund;
        }

        if ($kommentar !== null && $kommentar !== '') {
            $lines[] = 'Kommentar: ' . $kommentar;
        }

        $lines[] = 'Übersicht: ' . buildVacationOverviewLink();

        $body = implode("\n", $lines);

        try {
            $messageStmt = $pdo->prepare(
                'INSERT INTO messages (sender_id, recipient_id, subject, body) VALUES (:sender, :recipient, :subject, :body)'
            );
        } catch (PDOException $e) {
            return;
        }

        $sentTo = [];

        foreach ($recipients as $recipient) {
            $recipientId = isset($recipient['BenutzerID']) ? (int) $recipient['BenutzerID'] : 0;
            if ($recipientId <= 0 || $recipientId === $senderId) {
                continue;
            }

            if (isset($sentTo[$recipientId])) {
                continue;
            }

            try {
                $messageStmt->execute([
                    ':sender' => $senderId,
                    ':recipient' => $recipientId,
                    ':subject' => $subject,
                    ':body' => $body,
                ]);
                $sentTo[$recipientId] = true;
            } catch (PDOException $e) {
                // Bei einem Empfängerfehler die restlichen Empfänger nicht blockieren.
                continue;
            }
        }
    }
}

