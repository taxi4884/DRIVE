ALTER TABLE verwaltung_abwesenheit
    ADD COLUMN startdatum DATE NULL AFTER datum,
    ADD COLUMN enddatum DATE NULL AFTER startdatum,
    ADD COLUMN startzeit TIME NULL AFTER enddatum,
    ADD COLUMN endzeit TIME NULL AFTER startzeit,
    DROP COLUMN von,
    DROP COLUMN bis;
