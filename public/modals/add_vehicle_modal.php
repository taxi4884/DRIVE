<div class="modal" id="vehicleModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('vehicleModal')">&times;</span>
        <h2>Neues Fahrzeug hinzufügen</h2>
        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
            <label for="brand">Marke:</label>
            <input type="text" id="brand" name="brand" placeholder="Marke" required>
            <br>

            <label for="model">Modell:</label>
            <input type="text" id="model" name="model" placeholder="Modell" required>
            <br>

            <label for="concession_number">Konzessionsnummer:</label>
            <input type="text" id="concession_number" name="concession_number" placeholder="Konzessionsnummer" required>
            <br>

            <label for="license_plate">Kennzeichen:</label>
            <input type="text" id="license_plate" name="license_plate" placeholder="Kennzeichen" required>
            <br>

            <label for="mileage">Kilometerstand:</label>
            <input type="number" id="mileage" name="mileage" placeholder="Kilometerstand" required>
            <br>

            <label for="hu_date">HU (Hauptuntersuchung):</label>
            <input type="date" id="hu_date" name="hu_date" required>
            <br>

            <label for="eichungsdatum">Eichungsdatum:</label>
            <input type="date" id="eichungsdatum" name="eichungsdatum" required>
            <br>

            <button type="submit" name="add_vehicle">Fahrzeug hinzufügen</button>
        </form>
    </div>
</div>
