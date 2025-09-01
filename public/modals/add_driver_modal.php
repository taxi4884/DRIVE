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
    </div>
</div>
