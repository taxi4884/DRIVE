<?php
require_once __DIR__ . '/../../includes/modal.php';

renderModal('driverModal', 'Neuen Fahrer hinzufügen', __DIR__ . '/add_driver_modal.php', ['pdo' => $pdo]);
renderModal('vehicleModal', 'Neues Fahrzeug hinzufügen', __DIR__ . '/add_vehicle_modal.php');
renderModal('maintenanceModal', 'Wartungstermin hinzufügen', __DIR__ . '/add_maintanance_modal.php', ['pdo' => $pdo]);
renderModal('transferModal', 'Fahrzeugübergabe', __DIR__ . '/add_transfer_modal.php', ['pdo' => $pdo]);
renderModal('controlModal', 'Fahrzeugkontrolle', __DIR__ . '/add_control_modal.php', ['pdo' => $pdo]);
?>
