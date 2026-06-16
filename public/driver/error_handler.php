<?php
/**
 * Globale Fehlerbehandlung für Fahrer-Bereich.
 */
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (!function_exists('driver_log_exception')) {
    /**
     * Schreibt eine Ausnahme in das PHP-Error-Log und informiert das Technik-Team per Mail.
     */
    function driver_log_exception(Throwable $throwable, array $context = []): void
    {
        $context = array_merge(driver_collect_error_context(), $context);

        $logEntry = sprintf(
            '[%s] %s: %s in %s:%d',
            date('c'),
            get_class($throwable),
            $throwable->getMessage(),
            $throwable->getFile(),
            $throwable->getLine()
        );

        if (!empty($context)) {
            $logEntry .= ' | Kontext: ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $logEntry .= "\nStacktrace:\n" . $throwable->getTraceAsString() . "\n";
        error_log($logEntry);

        driver_send_error_mail($throwable, $context);
    }
}

if (!function_exists('driver_collect_error_context')) {
    /**
     * Erzeugt Kontextinformationen zum aktuellen Fehlerfall.
     */
    function driver_collect_error_context(): array
    {
        $context = [
            'timestamp' => date('c'),
        ];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $session = $_SESSION;
            if (isset($session['user_id'])) {
                $fahrerId = (int) $session['user_id'];
                $context['fahrer_id'] = $fahrerId;
                $fahrer = driver_lookup_driver_details($fahrerId);
                if ($fahrer !== null) {
                    $context['fahrer'] = array_filter([
                        'id' => $fahrer['FahrerID'] ?? null,
                        'name' => trim(($fahrer['Vorname'] ?? '') . ' ' . ($fahrer['Nachname'] ?? '')) ?: null,
                        'email' => $fahrer['Email'] ?? null,
                        'telefon' => $fahrer['Telefon'] ?? null,
                    ]);
                }
            }
        }

        $request = [];
        $request['method'] = $_SERVER['REQUEST_METHOD'] ?? null;
        $request['uri'] = $_SERVER['REQUEST_URI'] ?? null;
        $request['query_string'] = $_SERVER['QUERY_STRING'] ?? null;
        $request['referrer'] = $_SERVER['HTTP_REFERER'] ?? null;
        $request['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $request['ip'] = $_SERVER['REMOTE_ADDR'] ?? null;
        $request['forwarded_for'] = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;

        if (!empty($_GET)) {
            $request['query_params'] = driver_mask_sensitive_data($_GET);
        }

        if (!empty($_POST)) {
            $request['post_params'] = driver_mask_sensitive_data($_POST);
        }

        if (!empty($_FILES)) {
            $request['files'] = array_map(static function (array $file): array {
                return array_intersect_key($file, array_flip(['name', 'type', 'size', 'error']));
            }, $_FILES);
        }

        $context['request'] = array_filter($request, static function ($value) {
            return $value !== null && $value !== '' && $value !== [];
        });

        return $context;
    }
}

if (!function_exists('driver_mask_sensitive_data')) {
    /**
     * Maskiert sensible Inhalte innerhalb eines Arrays.
     */
    function driver_mask_sensitive_data(array $data): array
    {
        $sensitiveKeys = ['password', 'passwort', 'pwd', 'token', 'csrf', 'csrf_token'];
        $masked = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $masked[$key] = driver_mask_sensitive_data($value);
                continue;
            }

            if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                $masked[$key] = '***MASKIERT***';
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }
}

if (!function_exists('driver_lookup_driver_details')) {
    /**
     * Versucht, zusätzliche Fahrerdaten aus der Datenbank zu laden.
     */
    function driver_lookup_driver_details(int $fahrerId): ?array
    {
        if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
            return null;
        }

        /** @var PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        try {
            $stmt = $pdo->prepare('SELECT FahrerID, Vorname, Nachname, Email, Telefon FROM Fahrer WHERE FahrerID = ?');
            if ($stmt !== false && $stmt->execute([$fahrerId])) {
                $fahrer = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($fahrer !== false) {
                    return $fahrer;
                }
            }
        } catch (Throwable $dbError) {
            error_log('[driver_lookup_driver_details] ' . $dbError->getMessage());
        }

        return null;
    }
}

if (!function_exists('driver_send_error_mail')) {
    /**
     * Versendet eine Fehlermeldung an das Technik-Team.
     */
    function driver_send_error_mail(Throwable $throwable, array $context): void
    {
        $empfaenger = 'technik@taxi4884.de';

        $fahrerBezeichnung = 'unbekannt';
        if (isset($context['fahrer'])) {
            $teile = [];
            if (!empty($context['fahrer']['name'])) {
                $teile[] = $context['fahrer']['name'];
            }
            if (!empty($context['fahrer']['id'])) {
                $teile[] = 'ID ' . $context['fahrer']['id'];
            } elseif (!empty($context['fahrer_id'])) {
                $teile[] = 'ID ' . $context['fahrer_id'];
            }
            if (!empty($context['fahrer']['email'])) {
                $teile[] = $context['fahrer']['email'];
            }

            $fahrerBezeichnung = implode(' | ', $teile) ?: $fahrerBezeichnung;
        } elseif (!empty($context['fahrer_id'])) {
            $fahrerBezeichnung = 'ID ' . $context['fahrer_id'];
        }

        $subject = sprintf('Fahrerportal-Fehler (%s)', $fahrerBezeichnung);

        $request = $context['request'] ?? [];
        $aktion = trim(($request['method'] ?? 'unbekannte Methode') . ' ' . ($request['uri'] ?? 'unbekannte URL'));
        $geraet = $request['user_agent'] ?? 'unbekanntes Gerät';
        $ip = $request['ip'] ?? 'unbekannte IP';

        $bodyParts = [];
        $bodyParts[] = "Hallo Technik-Team,\n";
        $bodyParts[] = "\nIm Fahrerportal ist soeben ein Fehler aufgetreten. Die wichtigsten Details:";
        $bodyParts[] = "\nZeitpunkt: " . ($context['timestamp'] ?? date('c'));
        $bodyParts[] = "\nFahrer: " . $fahrerBezeichnung;
        if (!empty($context['fahrer']['telefon'] ?? null)) {
            $bodyParts[] = "\nTelefon: " . $context['fahrer']['telefon'];
        }
        $bodyParts[] = "\nGerät/User-Agent: " . $geraet;
        $bodyParts[] = "\nClient-IP: " . $ip;
        if (!empty($request['forwarded_for'] ?? null)) {
            $bodyParts[] = "\nX-Forwarded-For: " . $request['forwarded_for'];
        }
        $bodyParts[] = "\nAktion: " . $aktion;
        if (!empty($request['referrer'] ?? null)) {
            $bodyParts[] = "\nReferrer: " . $request['referrer'];
        }

        if (!empty($request['query_params'] ?? [])) {
            $bodyParts[] = "\nGET-Parameter: " . json_encode($request['query_params'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (!empty($request['post_params'] ?? [])) {
            $bodyParts[] = "\nPOST-Parameter: " . json_encode($request['post_params'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $bodyParts[] = "\n\nFehler: " . get_class($throwable) . ' - ' . $throwable->getMessage();
        $bodyParts[] = "\nDatei: " . $throwable->getFile() . ':' . $throwable->getLine();
        $bodyParts[] = "\n\nStacktrace:\n" . $throwable->getTraceAsString();

        if (!empty($context)) {
            $bodyParts[] = "\n\nGesamter Kontext:\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $body = implode('\n', $bodyParts) . "\n";

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: fahrerportal@taxi4884.de',
        ];

        try {
            if (!@mail($empfaenger, $subject, $body, implode("\r\n", $headers))) {
                error_log('[driver_send_error_mail] Versand fehlgeschlagen');
            }
        } catch (Throwable $mailError) {
            error_log('[driver_send_error_mail] ' . $mailError->getMessage());
        }
    }
}

if (!function_exists('driver_render_error_page')) {
    /**
     * Gibt eine generische Fehlerseite aus.
     */
    function driver_render_error_page(): void
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        include __DIR__ . '/error_page.php';
    }
}

set_exception_handler(static function (Throwable $throwable): void {
    driver_log_exception($throwable);
    driver_render_error_page();
    exit;
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    $exception = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
    driver_log_exception($exception);
    driver_render_error_page();
});
