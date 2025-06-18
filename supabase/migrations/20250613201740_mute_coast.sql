-- Script SQL complet pour créer la base de données billetterie
-- Utilisez ce script dans phpMyAdmin ou votre interface MySQL préférée

CREATE DATABASE IF NOT EXISTS billetterie;
USE billetterie;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    telephone VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    points_fidelite INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des équipes
CREATE TABLE IF NOT EXISTS equipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    ville VARCHAR(100) NOT NULL,
    logo_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des stades
CREATE TABLE IF NOT EXISTS stades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    ville VARCHAR(100) NOT NULL,
    capacite_totale INT NOT NULL,
    capacite_vip INT DEFAULT 0,
    capacite_normale INT DEFAULT 0,
    capacite_tribune INT DEFAULT 0,
    adresse TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des matchs
CREATE TABLE IF NOT EXISTS matchs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipe_domicile_id INT NOT NULL,
    equipe_exterieur_id INT NOT NULL,
    stade_id INT NOT NULL,
    date_match DATETIME NOT NULL,
    prix_base_vip DECIMAL(10,2) DEFAULT 500.00,
    prix_base_normale DECIMAL(10,2) DEFAULT 200.00,
    prix_base_tribune DECIMAL(10,2) DEFAULT 100.00,
    coefficient_multiplicateur DECIMAL(3,2) DEFAULT 1.00,
    statut ENUM('programme', 'en_cours', 'termine', 'annule') DEFAULT 'programme',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipe_domicile_id) REFERENCES equipes(id),
    FOREIGN KEY (equipe_exterieur_id) REFERENCES equipes(id),
    FOREIGN KEY (stade_id) REFERENCES stades(id)
);

-- Table des réservations
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    match_id INT NOT NULL,
    nom_client VARCHAR(100) NOT NULL,
    email_client VARCHAR(100) NOT NULL,
    telephone_client VARCHAR(20) NOT NULL,
    nombre_billets INT NOT NULL,
    categorie ENUM('VIP', 'Normale', 'Tribune') NOT NULL,
    prix_total DECIMAL(10,2) NOT NULL,
    statut ENUM('en_attente', 'confirme', 'annule') DEFAULT 'en_attente',
    paypal_order_id VARCHAR(100),
    paypal_payment_id VARCHAR(100),
    qr_code_path VARCHAR(255),
    pdf_path VARCHAR(255),
    email_sent BOOLEAN DEFAULT FALSE,
    confirmed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 15 MINUTE),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (match_id) REFERENCES matchs(id)
);

-- Table des billets individuels
CREATE TABLE IF NOT EXISTS billets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    numero_siege VARCHAR(10) NOT NULL,
    section VARCHAR(10) NOT NULL,
    rangee VARCHAR(10) NOT NULL,
    qr_code VARCHAR(255) UNIQUE NOT NULL,
    statut ENUM('valide', 'utilise', 'annule') DEFAULT 'valide',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id)
);

-- Table des transactions de fidélité
CREATE TABLE IF NOT EXISTS loyalty_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reservation_id INT,
    points INT NOT NULL,
    type ENUM('earned', 'used', 'expired') NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (reservation_id) REFERENCES reservations(id)
);

-- Table des équipes favorites des utilisateurs
CREATE TABLE IF NOT EXISTS user_favorite_teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    equipe_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (equipe_id) REFERENCES equipes(id),
    UNIQUE KEY unique_favorite (user_id, equipe_id)
);

-- Table des administrateurs
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des informations de villes
CREATE TABLE IF NOT EXISTS ville_infos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ville VARCHAR(100) NOT NULL,
    transport_info TEXT,
    activites_touristiques TEXT,
    restaurants TEXT,
    liens_utiles TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insertion de données de test
INSERT INTO equipes (nom, ville, logo_url) VALUES
('Raja Casablanca', 'Casablanca', 'https://via.placeholder.com/100x100/00ff00/ffffff?text=RCA'),
('Wydad Casablanca', 'Casablanca', 'https://via.placeholder.com/100x100/ff0000/ffffff?text=WAC'),
('FAR Rabat', 'Rabat', 'https://via.placeholder.com/100x100/000080/ffffff?text=FAR'),
('FUS Rabat', 'Rabat', 'https://via.placeholder.com/100x100/ffff00/000000?text=FUS'),
('Hassania Agadir', 'Agadir', 'https://via.placeholder.com/100x100/ff8000/ffffff?text=HUSA'),
('Difaâ El Jadida', 'El Jadida', 'https://via.placeholder.com/100x100/800080/ffffff?text=DHJ');

INSERT INTO stades (nom, ville, capacite_totale, capacite_vip, capacite_normale, capacite_tribune, adresse) VALUES
('Stade Mohammed V', 'Casablanca', 45000, 2000, 25000, 18000, 'Quartier des Sports, Casablanca'),
('Complexe Sportif Prince Moulay Abdellah', 'Rabat', 52000, 3000, 30000, 19000, 'Hay Riad, Rabat'),
('Stade Adrar', 'Agadir', 45480, 2000, 25000, 18480, 'Agadir, Maroc'),
('Stade El Abdi', 'El Jadida', 5000, 200, 3000, 1800, 'El Jadida, Maroc');

INSERT INTO matchs (equipe_domicile_id, equipe_exterieur_id, stade_id, date_match, coefficient_multiplicateur) VALUES
(1, 2, 1, '2024-12-25 16:00:00', 1.8), -- Derby Casablanca
(3, 4, 2, '2024-12-22 15:00:00', 1.4), -- Derby Rabat
(1, 3, 1, '2024-12-28 17:00:00', 1.3),
(5, 6, 3, '2024-12-30 16:00:00', 1.2),
(2, 5, 1, '2025-01-05 17:00:00', 1.5),
(4, 1, 2, '2025-01-12 16:00:00', 1.6);

INSERT INTO ville_infos (ville, transport_info, activites_touristiques, restaurants, liens_utiles) VALUES
('Casablanca', 
 'Train ONCF depuis Rabat (1h), Bus CTM, Tramway local, Taxi/Careem disponible', 
 'Mosquée Hassan II, Corniche Ain Diab, Quartier des Habous, Marché Central',
 'Rick''s Café, La Sqala, Le Cabestan, Restaurant Al Mounia',
 'ONCF: www.oncf.ma, CTM: www.ctm.ma, Careem: careem.com'),
('Rabat',
 'Train ONCF depuis Casablanca (1h), Bus urbain, Tramway Rabat-Salé, Taxi/Careem',
 'Tour Hassan, Kasbah des Oudayas, Mausolée Mohammed V, Médina de Rabat',
 'Dar Zaki, Le Dhow, Villa Mandarine, Restaurant Dinarjat',
 'ONCF: www.oncf.ma, Tramway: www.tram-way.ma'),
('Agadir',
 'Aéroport Al Massira, Bus CTM/Supratours, Bus urbain Alsa, Taxi/Careem',
 'Plage d''Agadir, Kasbah d''Agadir, Vallée des Oiseaux, Souk El Had',
 'Pure Passion Restaurant, Jour et Nuit, Le Tapis Rouge, Daffy''s',
 'Aéroport Agadir: www.onda.ma, CTM: www.ctm.ma'),
('El Jadida',
 'Train ONCF, Bus CTM, Taxi/Careem',
 'Cité Portugaise, Plage d''El Jadida, Citerne Portugaise',
 'Restaurant La Broche, Villa Blanca, Le Toit',
 'ONCF: www.oncf.ma, CTM: www.ctm.ma');

INSERT INTO admins (nom, email, password_hash) VALUES
('Admin Principal', 'admin@billetterie.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');