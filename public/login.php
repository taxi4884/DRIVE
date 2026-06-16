<?php
require_once '../includes/db.php';
session_start();

$submittedUsername = '';

if (!function_exists('driverApiReadEnvLogin')) {
    function driverApiReadEnvLogin(string $key, ?string $fallback = null): ?string
    {
        $value = getenv($key);
        if ($value !== false && trim((string)$value) !== '') {
            return trim((string)$value);
        }
        static $envValues = null;
        if ($envValues === null) {
            $envValues = [];
            $envFile = realpath(__DIR__ . '/../includes/.env');
            if ($envFile && is_readable($envFile)) {
                $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach ($lines as $line) {
                    $line = trim((string)$line);
                    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                    [$k, $v] = explode('=', $line, 2);
                    $envValues[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
                }
            }
        }
        if (isset($envValues[$key]) && trim((string)$envValues[$key]) !== '') {
            return trim((string)$envValues[$key]);
        }
        return $fallback;
    }
}

if (!function_exists('driverApiEnvByCompanyLogin')) {
    function driverApiEnvByCompanyLogin(string $baseKey, ?int $companyId = null, ?string $companyName = null, ?string $fallback = null): ?string
    {
        $candidates = [];
        if ($companyId !== null && $companyId > 0) {
            $candidates[] = $baseKey . '_COMPANY_' . $companyId;
            $candidates[] = $baseKey . '_' . $companyId;
        }
        if ($companyName !== null && trim($companyName) !== '') {
            $slug = strtoupper((string)preg_replace('/[^A-Z0-9]+/', '_', strtoupper($companyName)));
            $slug = trim($slug, '_');
            if ($slug !== '') {
                $candidates[] = $baseKey . '_COMPANY_' . $slug;
                $candidates[] = $baseKey . '_' . $slug;
            }
        }
        $candidates[] = $baseKey;
        foreach ($candidates as $k) {
            $v = driverApiReadEnvLogin($k);
            if ($v !== null && trim($v) !== '') return $v;
        }
        return $fallback;
    }
}

if (!function_exists('syncDriverShiftsCurrentMonthOnLogin')) {
    function syncDriverShiftsCurrentMonthOnLogin(PDO $pdo, array $driver): void
    {
        $pdo->exec("ALTER TABLE Fahrer ADD COLUMN IF NOT EXISTS shift_tracking_enabled TINYINT(1) NOT NULL DEFAULT 0");
        $pdo->exec("CREATE TABLE IF NOT EXISTS driver_shift_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            driver_id INT NOT NULL,
            shift_date DATE NOT NULL,
            start_time DATETIME NULL,
            end_time DATETIME NULL,
            duration_minutes INT NOT NULL DEFAULT 0,
            taxidaten_shift_id VARCHAR(100) NULL,
            source VARCHAR(30) NOT NULL DEFAULT 'taxidaten',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_driver_shift (driver_id, shift_date, taxidaten_shift_id),
            KEY idx_driver_date (driver_id, shift_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if ((int)($driver['shift_tracking_enabled'] ?? 0) !== 1) {
            return;
        }

        $personalnummer = trim((string)($driver['Personalnummer'] ?? ''));
        if ($personalnummer === '') return;

        $companyId = isset($driver['company_id']) ? (int)$driver['company_id'] : null;
        $companyName = null;
        if ($companyId) {
            $stC = $pdo->prepare('SELECT name FROM companies WHERE id=? LIMIT 1');
            $stC->execute([$companyId]);
            $companyName = (string)($stC->fetchColumn() ?: '');
        }

        $tokenUrl = driverApiEnvByCompanyLogin('TAXIDATEN_TOKEN_URL', $companyId, $companyName, 'https://extern.taxidaten.com/token');
        $odataBase = rtrim((string)driverApiEnvByCompanyLogin('TAXIDATEN_ODATA_BASE', $companyId, $companyName, 'https://extern.taxidaten.com/odata'), '/');
        $apiUser = driverApiEnvByCompanyLogin('TAXIDATEN_API_USERNAME', $companyId, $companyName);
        $apiPass = driverApiEnvByCompanyLogin('TAXIDATEN_API_PASSWORD', $companyId, $companyName);
        $externalUser = driverApiEnvByCompanyLogin('TAXIDATEN_EXTERNAL_USER', $companyId, $companyName);
        if (!$apiUser || !$apiPass) return;
        if (!$externalUser) $externalUser = base64_encode($apiUser);

        $tokenCh = curl_init($tokenUrl);
        curl_setopt_array($tokenCh, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded; charset=UTF-8'],
            CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'password','username' => $apiUser,'password' => $apiPass]),
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $tokenResp = curl_exec($tokenCh);
        $tokenHttp = (int)curl_getinfo($tokenCh, CURLINFO_HTTP_CODE);
        curl_close($tokenCh);
        if ($tokenResp === false || $tokenHttp < 200 || $tokenHttp >= 300) return;
        $tokenJson = json_decode((string)$tokenResp, true);
        $accessToken = is_array($tokenJson) ? ($tokenJson['access_token'] ?? null) : null;
        if (!is_string($accessToken) || trim($accessToken) === '') return;

        $monthStart = (new DateTimeImmutable('first day of this month 00:00:00'))->format('Y-m-d');
        $monthEnd = (new DateTimeImmutable('last day of this month 23:59:59'))->format('Y-m-d');

        $url = $odataBase . '/schichten?%24orderby=id%20desc&%24top=500&%24filter=' . rawurlencode("persnr eq '{$personalnummer}'");
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $accessToken,
                'ExternalUser: ' . $externalUser,
            ],
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false || $http < 200 || $http >= 300) return;

        $json = json_decode((string)$resp, true);
        $rows = [];
        if (is_array($json)) {
            if (isset($json['value']) && is_array($json['value'])) $rows = $json['value'];
            elseif (array_keys($json) === range(0, count($json)-1)) $rows = $json;
        }

        $up = $pdo->prepare('INSERT INTO driver_shift_logs (driver_id, shift_date, start_time, end_time, duration_minutes, taxidaten_shift_id, source)
            VALUES (:driver_id,:shift_date,:start_time,:end_time,:duration_minutes,:shift_id,:source)
            ON DUPLICATE KEY UPDATE start_time=VALUES(start_time), end_time=VALUES(end_time), duration_minutes=VALUES(duration_minutes), updated_at=CURRENT_TIMESTAMP');

        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $startRaw = (string)($r['beginn'] ?? $r['start'] ?? $r['startzeit'] ?? $r['von'] ?? '');
            $endRaw = (string)($r['ende'] ?? $r['end'] ?? $r['endzeit'] ?? $r['bis'] ?? '');
            $shiftId = (string)($r['id'] ?? $r['e_link'] ?? '');

            $startTs = $startRaw !== '' ? strtotime($startRaw) : false;
            $endTs = $endRaw !== '' ? strtotime($endRaw) : false;
            if ($startTs === false) continue;
            $shiftDate = date('Y-m-d', $startTs);
            if ($shiftDate < $monthStart || $shiftDate > substr($monthEnd,0,10)) continue;

            $durationMinutes = 0;
            if (isset($r['dauer'])) {
                $durationMinutes = (int)$r['dauer'];
            } elseif ($endTs !== false && $endTs >= $startTs) {
                $durationMinutes = (int)round(($endTs - $startTs) / 60);
            }

            $up->execute([
                ':driver_id' => (int)$driver['FahrerID'],
                ':shift_date' => $shiftDate,
                ':start_time' => date('Y-m-d H:i:s', $startTs),
                ':end_time' => $endTs !== false ? date('Y-m-d H:i:s', $endTs) : null,
                ':duration_minutes' => max(0, $durationMinutes),
                ':shift_id' => $shiftId !== '' ? $shiftId : ('start_' . $startTs),
                ':source' => 'taxidaten',
            ]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedUsername = trim($_POST['username'] ?? ''); // Für Benutzer: Email, Für Fahrer: Fahrernummer
    $password = trim($_POST['password'] ?? ''); // Für Benutzer: Passwort, Für Fahrer: Code

    // Benutzer-Login prüfen
    $stmt = $pdo->prepare("SELECT * FROM Benutzer WHERE Email = ?");
    $stmt->execute([$submittedUsername]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['Passwort'])) {
        $_SESSION['user_role'] = 'admin';
        $_SESSION['user_id'] = $user['BenutzerID'];
        $_SESSION['company_id'] = $user['company_id'] ?? null;
        header("Location: dashboard.php");
        exit();
    }

    // Fahrer-Login prüfen
    $stmt = $pdo->prepare("SELECT * FROM Fahrer WHERE Fahrernummer = ? AND Code = ?");
    $stmt->execute([$submittedUsername, $password]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($driver) {
        try {
            syncDriverShiftsCurrentMonthOnLogin($pdo, $driver);
        } catch (Throwable $e) {
            // Login darf daran nicht scheitern
        }

        $_SESSION['user_role'] = 'fahrer';
        $_SESSION['user_id'] = $driver['FahrerID'];
        $_SESSION['company_id'] = $driver['company_id'] ?? null;
        header("Location: ../driver/dashboard.php");
        exit();
    }

    $error = 'Login fehlgeschlagen. Bitte Daten prüfen.';
}

$title = 'Login';
$showNav = false;
include __DIR__ . '/../includes/layout.php';
?>
<link rel="stylesheet" href="css/index.css?v=<?= filemtime(__DIR__ . '/css/index.css'); ?>">
<div class="login-shell">
    <div class="login-wrapper">
        <header class="login-header" aria-label="Firmenlogo">
            <img src="images/4884-logo.png" alt="Ihr Leipzig Taxi 4884" class="logo">
        </header>

        <main class="login-card" role="main">
            <h1 class="login-title">Login</h1>

            <?php if (isset($error)): ?>
                <div class="login-alert" role="alert" aria-live="polite">
                    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="/login.php" method="POST" class="login-form" id="loginForm" autocomplete="on">
                <div class="field-group">
                    <label for="username">Benutzername <small>(E-Mail oder Fahrernummer)</small></label>
                    <div class="input-wrap">
                        <i class="bi bi-person" aria-hidden="true"></i>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            inputmode="email"
                            autocomplete="username"
                            autocapitalize="none"
                            autocorrect="off"
                            spellcheck="false"
                            enterkeyhint="next"
                            value="<?= htmlspecialchars($submittedUsername) ?>"
                            required
                        >
                    </div>
                </div>

                <div class="field-group">
                    <label for="password">Passwort</label>
                    <div class="input-wrap">
                        <i class="bi bi-lock" aria-hidden="true"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            enterkeyhint="go"
                            required
                        >
                        <button type="button" class="password-toggle" id="passwordToggle" aria-label="Passwort anzeigen" aria-controls="password" aria-pressed="false">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="login-btn">Login</button>
            </form>
        </main>
    </div>
</div>

<div id="loginOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; padding:18px 20px; max-width:320px; width:calc(100% - 40px); text-align:center; box-shadow:0 8px 30px rgba(0,0,0,.25);">
        <div style="font-weight:700; margin-bottom:6px;">Anmeldung läuft…</div>
        <div style="font-size:.95rem; color:#555;">Deine Daten werden importiert.</div>
    </div>
</div>

<script>
(function () {
    const loginForm = document.getElementById('loginForm');
    const overlay = document.getElementById('loginOverlay');
    if (loginForm && overlay) {
        loginForm.addEventListener('submit', () => {
            overlay.style.display = 'flex';
        });
    }

    const toggle = document.getElementById('passwordToggle');
    const password = document.getElementById('password');
    if (!toggle || !password) return;

    toggle.addEventListener('click', function () {
        const isPassword = password.getAttribute('type') === 'password';
        password.setAttribute('type', isPassword ? 'text' : 'password');
        this.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
        this.setAttribute('aria-label', isPassword ? 'Passwort verbergen' : 'Passwort anzeigen');
        this.innerHTML = isPassword
            ? '<i class="bi bi-eye-slash" aria-hidden="true"></i>'
            : '<i class="bi bi-eye" aria-hidden="true"></i>';
    });
})();
</script>
</body>
</html>
