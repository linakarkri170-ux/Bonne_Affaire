-- Run this if you already created BONNE AFFAIRE tables from the earlier schema.
CREATE TABLE IF NOT EXISTS user_sessions (
    session_id VARCHAR(128) PRIMARY KEY,
    session_data MEDIUMBLOB NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_user_sessions_expiry (expires_at)
);

INSERT IGNORE INTO categories (name) VALUES
    ('Véhicules'), ('Immobilier'), ('Téléphones'), ('Maison'), ('Mode'), ('Loisirs'), ('Autre');
INSERT IGNORE INTO cities (name) VALUES
    ('Casablanca'), ('Rabat'), ('Marrakech'), ('Fès'), ('Tanger');
