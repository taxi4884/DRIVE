CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES Benutzer(BenutzerID),
    CONSTRAINT fk_messages_recipient FOREIGN KEY (recipient_id) REFERENCES Benutzer(BenutzerID)
);
