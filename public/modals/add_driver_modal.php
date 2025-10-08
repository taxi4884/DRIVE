<?php
$driverFetchError = null;
$existingDrivers = [];

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $driverFetchError = 'Datenbankverbindung nicht verfügbar.';
} else {
    try {
        $driverQuery = $pdo->query('SELECT * FROM Fahrer ORDER BY Nachname, Vorname');
        $existingDrivers = $driverQuery->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $exception) {
        $driverFetchError = 'Fahrer konnten nicht geladen werden.';
    }
}
?>

<div class="modal" id="driverModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('driverModal')">&times;</span>
        <h2>Neuen Fahrer hinzufügen</h2>
        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
            <!-- Vorname -->
            <label for="firstname">Vorname:</label>
            <input type="text" id="firstname" name="firstname" placeholder="Vorname" required>
            <br>

            <!-- Nachname -->
            <label for="lastname">Nachname:</label>
            <input type="text" id="lastname" name="lastname" placeholder="Nachname" required>
            <br>

            <!-- Telefonnummer -->
            <label for="phone">Telefonnummer:</label>
            <input type="tel" id="phone" name="phone" placeholder="Telefonnummer" required>
            <br>

            <!-- E-Mail -->
            <label for="email">E-Mail:</label>
            <input type="email" id="email" name="email" placeholder="E-Mail-Adresse" required>
            <br>

            <!-- P-Schein -->
            <label for="pschein">P-Schein gültig bis:</label>
            <input type="date" id="pschein" name="pschein" required>
            <br>

            <!-- Führerschein -->
            <label for="license">Führerschein gültig bis:</label>
            <input type="date" id="license" name="license" required>
            <br>

            <!-- Führerscheinnummer -->
            <label for="license_number">Führerscheinnummer:</label>
            <input type="text" id="license_number" name="license_number" placeholder="Führerscheinnummer" required>
            <br>

            <!-- Straße -->
            <label for="street">Straße:</label>
            <input type="text" id="street" name="street" placeholder="Straße" required>
            <br>

            <!-- Hausnummer -->
            <label for="house_number">Hausnummer:</label>
            <input type="text" id="house_number" name="house_number" placeholder="Hausnummer" required>
            <br>

            <!-- PLZ -->
            <label for="zip">PLZ:</label>
            <input type="text" id="zip" name="zip" placeholder="Postleitzahl" required>
            <br>

            <!-- Ort -->
            <label for="city">Ort:</label>
            <input type="text" id="city" name="city" placeholder="Ort" required>
            <br>

            <!-- Fahrernummer -->
            <label for="fahrernummer">Fahrernummer:</label>
            <input type="text" id="fahrernummer" name="fahrernummer" placeholder="Eindeutige Nummer" required>
            <br>

            <!-- Initialpasswort -->
            <label for="code">Initialpasswort:</label>
            <input type="text" id="code" name="code" placeholder="Passwort generieren oder manuell eingeben" required>
            <br>

            <button type="submit" name="add_driver">Fahrer hinzufügen</button>
        </form>

        <hr>

        <h3>Bestehende Fahrer</h3>

        <?php if ($driverFetchError !== null): ?>
            <p class="error-message"><?= htmlspecialchars($driverFetchError) ?></p>
        <?php elseif (empty($existingDrivers)): ?>
            <p>Es sind derzeit keine Fahrer in der Datenbank hinterlegt.</p>
        <?php else: ?>
            <div class="driver-table-wrapper">
                <table class="driver-table">
                    <thead>
                        <tr>
                            <?php foreach (array_keys($existingDrivers[0]) as $column): ?>
                                <th><?= htmlspecialchars(ucwords(str_replace('_', ' ', $column))) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($existingDrivers as $driverRow): ?>
                            <tr>
                                <?php foreach ($driverRow as $value): ?>
                                    <td><?= htmlspecialchars((string) $value) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    #driverModal .driver-table-wrapper {
        max-height: 300px;
        overflow: auto;
        margin-top: 1rem;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    #driverModal .driver-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }

    #driverModal .driver-table th,
    #driverModal .driver-table td {
        padding: 0.5rem 0.75rem;
        border-bottom: 1px solid #e0e0e0;
        text-align: left;
        white-space: nowrap;
    }

    #driverModal .driver-table th {
        background-color: #f5f5f5;
        position: sticky;
        top: 0;
        z-index: 1;
    }

    #driverModal .driver-table tr:nth-child(even) {
        background-color: #fafafa;
    }

    #driverModal .error-message {
        color: #d9534f;
        font-weight: 600;
    }
</style>
