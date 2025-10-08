CREATE TABLE IF NOT EXISTS schulungseinladungen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teilnehmer_id INT NOT NULL,
    termin DATE NULL,
    eingeladen_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_schulungseinladungen_teilnehmer (teilnehmer_id),
    CONSTRAINT fk_schulungseinladungen_teilnehmer
        FOREIGN KEY (teilnehmer_id)
        REFERENCES schulungsteilnehmer (id)
        ON DELETE CASCADE
);
