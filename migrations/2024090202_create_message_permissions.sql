CREATE TABLE IF NOT EXISTS message_permissions (
    driver_id INT NOT NULL,
    recipient_id INT NOT NULL,
    PRIMARY KEY (driver_id, recipient_id),
    CONSTRAINT fk_msg_perm_driver FOREIGN KEY (driver_id) REFERENCES Fahrer(FahrerID),
    CONSTRAINT fk_msg_perm_recipient FOREIGN KEY (recipient_id) REFERENCES Benutzer(BenutzerID)
);
