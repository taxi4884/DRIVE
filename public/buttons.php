<div class="btn-group">
    <!-- Hinzufügen-Button mit Dropdown -->
    <div class="dropdown">
        <button class="btn dropdown-toggle">Hinzufügen</button>
        <div class="dropdown-menu">
            <button class="btn dropdown-item" onclick="openModal('driverModal')">Fahrer</button>
            <button class="btn dropdown-item" onclick="openModal('vehicleModal')">Fahrzeug</button>
        </div>
    </div>

    <!-- Restliche Buttons -->
    <button class="btn" onclick="openModal('transferModal')">Fahrzeugübergabe</button>
    <button class="btn" onclick="openModal('maintenanceModal')">Wartungstermin</button>
    <button class="btn" onclick="openModal('controlModal')">Fahrzeugkontrolle</button>
</div>