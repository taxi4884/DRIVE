<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetRequest($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handlePostRequest($pdo);
        exit;
    }

    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Methode nicht erlaubt.'
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ein unerwarteter Fehler ist aufgetreten.'
    ]);
}

function handleGetRequest(PDO $pdo): void
{
    $mitarbeiterId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$mitarbeiterId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Eine gültige Mitarbeiter-ID ist erforderlich.'
        ]);
        return;
    }

    $stmt = $pdo->prepare('SELECT * FROM mitarbeiter_zentrale WHERE mitarbeiter_id = :mitarbeiter_id LIMIT 1');
    $stmt->execute(['mitarbeiter_id' => $mitarbeiterId]);
    $mitarbeiter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mitarbeiter) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Der Mitarbeiter wurde nicht gefunden.'
        ]);
        return;
    }

    $schema = array_values(getMitarbeiterSchema($pdo));

    echo json_encode([
        'success' => true,
        'data' => $mitarbeiter,
        'schema' => $schema
    ]);
}

function handlePostRequest(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    if (!isset($input['mitarbeiter_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Die Mitarbeiter-ID fehlt.'
        ]);
        return;
    }

    $mitarbeiterId = filter_var($input['mitarbeiter_id'], FILTER_VALIDATE_INT);
    if (!$mitarbeiterId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Die Mitarbeiter-ID ist ungültig.'
        ]);
        return;
    }

    $schema = getMitarbeiterSchema($pdo);
    $allowedColumns = array_keys($schema);
    $allowedColumns = array_diff($allowedColumns, ['mitarbeiter_id']);

    $fieldsToUpdate = [];
    $params = ['mitarbeiter_id' => $mitarbeiterId];

    foreach ($input as $column => $value) {
        if ($column === 'mitarbeiter_id' || !in_array($column, $allowedColumns, true)) {
            continue;
        }

        $columnMeta = $schema[$column];
        $value = normalizeValue($value, $columnMeta);

        if ($column === 'status' && !in_array($value, ['Aktiv', 'Inaktiv'], true)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Der Status muss entweder "Aktiv" oder "Inaktiv" sein.'
            ]);
            return;
        }

        if (in_array($column, ['vorname', 'nachname'], true) && (!is_string($value) || trim($value) === '')) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Vorname und Nachname dürfen nicht leer sein.'
            ]);
            return;
        }

        $fieldsToUpdate[] = sprintf('`%s` = :%s', $column, $column);
        $params[$column] = $value;
    }

    if (empty($fieldsToUpdate)) {
        echo json_encode([
            'success' => true,
            'message' => 'Es wurden keine Änderungen erkannt.'
        ]);
        return;
    }

    $sql = sprintf(
        'UPDATE mitarbeiter_zentrale SET %s WHERE mitarbeiter_id = :mitarbeiter_id',
        implode(', ', $fieldsToUpdate)
    );

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'message' => 'Der Mitarbeiter wurde erfolgreich aktualisiert.'
    ]);
}

function getMitarbeiterSchema(PDO $pdo): array
{
    static $schemaCache = null;
    if ($schemaCache !== null) {
        return $schemaCache;
    }

    $stmt = $pdo->query('DESCRIBE mitarbeiter_zentrale');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $schemaCache = [];
    foreach ($columns as $column) {
        $schemaCache[$column['Field']] = $column;
    }

    return $schemaCache;
}

function normalizeValue($value, array $columnMeta)
{
    $type = strtolower($columnMeta['Type'] ?? '');
    $allowsNull = strtoupper($columnMeta['Null'] ?? '') === 'YES';

    if ($value === '' && $allowsNull) {
        return null;
    }

    if (is_string($value)) {
        $value = trim($value);
    }

    if (strpos($type, 'int') !== false) {
        return $value === null ? null : (int) $value;
    }

    if (strpos($type, 'decimal') !== false || strpos($type, 'double') !== false || strpos($type, 'float') !== false) {
        return $value === null ? null : (float) $value;
    }

    if (strpos($type, 'tinyint(1)') !== false || strpos($type, 'bool') !== false) {
        if ($value === null) {
            return null;
        }
        return $value === '' ? null : (int) $value;
    }

    return $value;
}
