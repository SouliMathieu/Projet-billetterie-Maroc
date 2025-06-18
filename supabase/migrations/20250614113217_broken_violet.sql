-- Script SQL complet pour créer la base de données billetterie
-- Base de données pour la plateforme de réservation de billets football marocain
-- Date de création: Décembre 2024

-- Création de la base de données
CREATE DATABASE IF NOT EXISTS billetterie CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE billetterie;

-- =====================================================
-- TABLE DES UTILISATEURS
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    telephone VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    points_fidelite INT DEFAULT 0,
    date_naissance DATE NULL,
    ville VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_points (points_fidelite)
);

-- =====================================================
-- TABLE DES ÉQUIPES
-- =====================================================
CREATE TABLE IF NOT EXISTS equipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    ville VARCHAR(100) NOT NULL,
    logo_url VARCHAR(500) DEFAULT NULL,
    couleur_principale VARCHAR(7) DEFAULT '#000000',
    couleur_secondaire VARCHAR(7) DEFAULT '#FFFFFF',
    fondation_annee INT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ville (ville),
    INDEX idx_nom (nom)
);

-- =====================================================
-- TABLE DES STADES
-- =====================================================
CREATE TABLE IF NOT EXISTS stades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    ville VARCHAR(100) NOT NULL,
    capacite_totale INT NOT NULL,
    capacite_vip INT DEFAULT 0,
    capacite_normale INT DEFAULT 0,
    capacite_tribune INT DEFAULT 0,
    adresse TEXT DEFAULT NULL,
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    annee_construction INT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ville (ville),
    INDEX idx_capacite (capacite_totale)
);

-- =====================================================
-- TABLE DES MATCHS
-- =====================================================
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
    statut ENUM('programme', 'en_cours', 'termine', 'annule', 'reporte') DEFAULT 'programme',
    type_match ENUM('championnat', 'coupe', 'amical', 'continental') DEFAULT 'championnat',
    importance ENUM('normale', 'derby', 'finale', 'demi_finale') DEFAULT 'normale',
    meteo_prevue VARCHAR(50) DEFAULT NULL,
    arbitre_principal VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (equipe_domicile_id) REFERENCES equipes(id) ON DELETE CASCADE,
    FOREIGN KEY (equipe_exterieur_id) REFERENCES equipes(id) ON DELETE CASCADE,
    FOREIGN KEY (stade_id) REFERENCES stades(id) ON DELETE CASCADE,
    INDEX idx_date_match (date_match),
    INDEX idx_statut (statut),
    INDEX idx_equipe_domicile (equipe_domicile_id),
    INDEX idx_equipe_exterieur (equipe_exterieur_id),
    CONSTRAINT chk_equipes_differentes CHECK (equipe_domicile_id != equipe_exterieur_id),
    CONSTRAINT chk_date_future CHECK (date_match > NOW())
);

-- =====================================================
-- TABLE DES RÉSERVATIONS
-- =====================================================
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    match_id INT NOT NULL,
    nom_client VARCHAR(100) NOT NULL,
    email_client VARCHAR(100) NOT NULL,
    telephone_client VARCHAR(20) NOT NULL,
    nombre_billets INT NOT NULL CHECK (nombre_billets > 0 AND nombre_billets <= 6),
    categorie ENUM('VIP', 'Normale', 'Tribune') NOT NULL,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    prix_total DECIMAL(10,2) NOT NULL,
    reduction_appliquee DECIMAL(10,2) DEFAULT 0.00,
    points_utilises INT DEFAULT 0,
    statut ENUM('en_attente', 'confirme', 'annule', 'expire', 'rembourse') DEFAULT 'en_attente',
    paypal_order_id VARCHAR(100) DEFAULT NULL,
    paypal_payment_id VARCHAR(100) DEFAULT NULL,
    transaction_id VARCHAR(100) DEFAULT NULL,
    qr_code_path VARCHAR(255) DEFAULT NULL,
    pdf_path VARCHAR(255) DEFAULT NULL,
    email_sent BOOLEAN DEFAULT FALSE,
    sms_sent BOOLEAN DEFAULT FALSE,
    confirmed_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 15 MINUTE),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (match_id) REFERENCES matchs(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_match_id (match_id),
    INDEX idx_statut (statut),
    INDEX idx_created_at (created_at),
    INDEX idx_expires_at (expires_at)
);

-- =====================================================
-- TABLE DES BILLETS INDIVIDUELS
-- =====================================================
CREATE TABLE IF NOT EXISTS billets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    numero_billet VARCHAR(20) NOT NULL UNIQUE,
    section VARCHAR(10) NOT NULL,
    rangee VARCHAR(10) NOT NULL,
    siege VARCHAR(10) NOT NULL,
    qr_code VARCHAR(500) UNIQUE NOT NULL,
    qr_code_image_path VARCHAR(255) DEFAULT NULL,
    statut ENUM('valide', 'utilise', 'annule', 'perdu') DEFAULT 'valide',
    date_utilisation TIMESTAMP NULL,
    controle_par VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    INDEX idx_reservation_id (reservation_id),
    INDEX idx_qr_code (qr_code),
    INDEX idx_statut (statut)
);

-- =====================================================
-- TABLE DES TRANSACTIONS DE FIDÉLITÉ
-- =====================================================
CREATE TABLE IF NOT EXISTS loyalty_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reservation_id INT DEFAULT NULL,
    points INT NOT NULL,
    type ENUM('earned', 'used', 'expired', 'bonus', 'refund') NOT NULL,
    description TEXT DEFAULT NULL,
    points_balance_after INT NOT NULL,
    expiration_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_created_at (created_at)
);

-- =====================================================
-- TABLE DES ÉQUIPES FAVORITES
-- =====================================================
CREATE TABLE IF NOT EXISTS user_favorite_teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    equipe_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (equipe_id) REFERENCES equipes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (user_id, equipe_id),
    INDEX idx_user_id (user_id)
);

-- =====================================================
-- TABLE DES ADMINISTRATEURS
-- =====================================================
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'moderateur') DEFAULT 'admin',
    permissions JSON DEFAULT NULL,
    derniere_connexion TIMESTAMP NULL,
    actif BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
);

-- =====================================================
-- TABLE DES INFORMATIONS DE VILLES
-- =====================================================
CREATE TABLE IF NOT EXISTS ville_infos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ville VARCHAR(100) NOT NULL UNIQUE,
    region VARCHAR(100) DEFAULT NULL,
    population INT DEFAULT NULL,
    transport_info TEXT DEFAULT NULL,
    activites_touristiques TEXT DEFAULT NULL,
    restaurants TEXT DEFAULT NULL,
    hotels TEXT DEFAULT NULL,
    liens_utiles TEXT DEFAULT NULL,
    climat_description TEXT DEFAULT NULL,
    langues_parlees VARCHAR(200) DEFAULT 'Arabe, Français',
    monnaie VARCHAR(10) DEFAULT 'MAD',
    fuseau_horaire VARCHAR(50) DEFAULT 'UTC+1',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ville (ville),
    INDEX idx_region (region)
);

-- =====================================================
-- TABLE DES NOTIFICATIONS
-- =====================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    titre VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error', 'match', 'reservation') DEFAULT 'info',
    lu BOOLEAN DEFAULT FALSE,
    action_url VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_lu (lu),
    INDEX idx_created_at (created_at)
);

-- =====================================================
-- TABLE DES LOGS D'ACTIVITÉ
-- =====================================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    admin_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50) DEFAULT NULL,
    record_id INT DEFAULT NULL,
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- =====================================================
-- INSERTION DES DONNÉES DE TEST
-- =====================================================

-- Insertion des équipes du championnat marocain
INSERT INTO equipes (nom, ville, logo_url, couleur_principale, couleur_secondaire, fondation_annee, description) VALUES
('Raja Club Athletic', 'Casablanca', 'https://via.placeholder.com/100x100/00ff00/ffffff?text=RCA', '#00AA00', '#FFFFFF', 1949, 'Club de football le plus titré du Maroc'),
('Wydad Athletic Club', 'Casablanca', 'https://via.placeholder.com/100x100/ff0000/ffffff?text=WAC', '#DC143C', '#FFFFFF', 1937, 'Club populaire de Casablanca, rival historique du Raja'),
('Forces Armées Royales', 'Rabat', 'https://via.placeholder.com/100x100/000080/ffffff?text=FAR', '#000080', '#FFD700', 1956, 'Club militaire de la capitale'),
('Fath Union Sport', 'Rabat', 'https://via.placeholder.com/100x100/ffff00/000000?text=FUS', '#FFD700', '#000000', 1946, 'Club historique de Rabat'),
('Hassania Union Sport Agadir', 'Agadir', 'https://via.placeholder.com/100x100/ff8000/ffffff?text=HUSA', '#FF8C00', '#FFFFFF', 1946, 'Représentant du Sud du Maroc'),
('Difaâ Hassani El Jadida', 'El Jadida', 'https://via.placeholder.com/100x100/800080/ffffff?text=DHJ', '#800080', '#FFFFFF', 1948, 'Club côtier d\'El Jadida'),
('Ittihad Riadi Tanger', 'Tanger', 'https://via.placeholder.com/100x100/0066cc/ffffff?text=IRT', '#0066CC', '#FFFFFF', 1983, 'Club du détroit de Gibraltar'),
('Olympique Club de Safi', 'Safi', 'https://via.placeholder.com/100x100/cc0000/ffffff?text=OCS', '#CC0000', '#FFFFFF', 1923, 'Doyen du football marocain'),
('Moghreb Athletic Tétouan', 'Tétouan', 'https://via.placeholder.com/100x100/009900/ffffff?text=MAT', '#009900', '#FFFFFF', 1922, 'Club du Nord du Maroc'),
('Renaissance Sportive de Berkane', 'Berkane', 'https://via.placeholder.com/100x100/ff6600/ffffff?text=RSB', '#FF6600', '#FFFFFF', 1938, 'Club de l\'Oriental'),
('Chabab Rif Al Hoceima', 'Al Hoceima', 'https://via.placeholder.com/100x100/0099ff/ffffff?text=CRAH', '#0099FF', '#FFFFFF', 1926, 'Club rifain'),
('Union Touarga Sport', 'Rabat', 'https://via.placeholder.com/100x100/990099/ffffff?text=UTS', '#990099', '#FFFFFF', 1960, 'Club de la banlieue de Rabat');

-- Insertion des stades
INSERT INTO stades (nom, ville, capacite_totale, capacite_vip, capacite_normale, capacite_tribune, adresse, latitude, longitude, annee_construction, description) VALUES
('Complexe Sportif Mohammed V', 'Casablanca', 45000, 2500, 25000, 17500, 'Quartier des Sports, Casablanca', 33.5731, -7.5898, 1955, 'Stade mythique du football marocain'),
('Complexe Sportif Prince Moulay Abdellah', 'Rabat', 52000, 3000, 30000, 19000, 'Hay Riad, Rabat', 33.9716, -6.8498, 1983, 'Plus grand stade du Maroc'),
('Stade Adrar', 'Agadir', 45480, 2200, 25000, 18280, 'Agadir, Maroc', 30.4278, -9.5981, 2013, 'Stade moderne du Sud'),
('Stade El Abdi', 'El Jadida', 8000, 400, 4500, 3100, 'El Jadida, Maroc', 33.2316, -8.5007, 1983, 'Stade côtier d\'El Jadida'),
('Grand Stade de Tanger', 'Tanger', 65000, 4000, 35000, 26000, 'Tanger, Maroc', 35.7595, -5.8340, 2011, 'Stade ultramoderne du détroit'),
('Stade El Massira', 'Safi', 10000, 500, 6000, 3500, 'Safi, Maroc', 32.2994, -9.2372, 1976, 'Stade historique de Safi'),
('Stade Saniat Rmel', 'Tétouan', 11000, 600, 6500, 3900, 'Tétouan, Maroc', 35.5889, -5.3626, 1996, 'Stade du Nord'),
('Stade Municipal de Berkane', 'Berkane', 15000, 800, 8500, 5700, 'Berkane, Maroc', 34.9218, -2.3200, 1983, 'Stade de l\'Oriental'),
('Stade Chabab Rif Al Hoceima', 'Al Hoceima', 8000, 400, 4500, 3100, 'Al Hoceima, Maroc', 35.2517, -3.9372, 1985, 'Stade rifain'),
('Stade Père Jégo', 'Casablanca', 20000, 1000, 12000, 7000, 'Casablanca, Maroc', 33.5892, -7.6114, 1939, 'Stade historique de Casablanca');

-- Insertion des matchs (tous après le 18 juin 2025)
INSERT INTO matchs (equipe_domicile_id, equipe_exterieur_id, stade_id, date_match, coefficient_multiplicateur, type_match, importance, arbitre_principal) VALUES
-- Juin 2025
(1, 2, 1, '2025-06-20 20:00:00', 2.0, 'championnat', 'derby', 'Noureddine El Jaafari'), -- Derby de Casablanca
(3, 4, 2, '2025-06-22 18:00:00', 1.6, 'championnat', 'derby', 'Redouane Jiyed'), -- Derby de Rabat
(5, 6, 3, '2025-06-25 19:00:00', 1.3, 'championnat', 'normale', 'Samir Guezzaz'),
(7, 8, 5, '2025-06-27 17:00:00', 1.2, 'championnat', 'normale', 'Mostafa Akarkad'),
(9, 10, 7, '2025-06-29 16:00:00', 1.4, 'championnat', 'normale', 'Abdelhak Etchiali'),

-- Juillet 2025
(2, 3, 1, '2025-07-02 20:30:00', 1.5, 'championnat', 'normale', 'Noureddine El Jaafari'),
(4, 5, 2, '2025-07-05 19:00:00', 1.3, 'championnat', 'normale', 'Redouane Jiyed'),
(6, 7, 4, '2025-07-08 18:00:00', 1.2, 'championnat', 'normale', 'Samir Guezzaz'),
(8, 9, 6, '2025-07-11 17:30:00', 1.4, 'championnat', 'normale', 'Mostafa Akarkad'),
(10, 11, 8, '2025-07-14 16:00:00', 1.3, 'championnat', 'normale', 'Abdelhak Etchiali'),
(1, 12, 1, '2025-07-17 20:00:00', 1.4, 'championnat', 'normale', 'Noureddine El Jaafari'),
(2, 4, 1, '2025-07-20 19:30:00', 1.6, 'championnat', 'normale', 'Redouane Jiyed'),
(3, 5, 2, '2025-07-23 18:00:00', 1.3, 'championnat', 'normale', 'Samir Guezzaz'),
(6, 8, 4, '2025-07-26 17:00:00', 1.2, 'championnat', 'normale', 'Mostafa Akarkad'),
(7, 9, 5, '2025-07-29 16:30:00', 1.4, 'championnat', 'normale', 'Abdelhak Etchiali'),

-- Août 2025
(11, 1, 9, '2025-08-01 19:00:00', 1.5, 'championnat', 'normale', 'Noureddine El Jaafari'),
(12, 2, 10, '2025-08-04 18:30:00', 1.4, 'championnat', 'normale', 'Redouane Jiyed'),
(4, 6, 2, '2025-08-07 20:00:00', 1.3, 'championnat', 'normale', 'Samir Guezzaz'),
(5, 7, 3, '2025-08-10 17:00:00', 1.2, 'championnat', 'normale', 'Mostafa Akarkad'),
(8, 10, 6, '2025-08-13 16:00:00', 1.4, 'championnat', 'normale', 'Abdelhak Etchiali'),
(9, 11, 7, '2025-08-16 19:30:00', 1.3, 'championnat', 'normale', 'Noureddine El Jaafari'),
(1, 3, 1, '2025-08-19 20:30:00', 1.7, 'championnat', 'normale', 'Redouane Jiyed'),
(2, 5, 1, '2025-08-22 18:00:00', 1.5, 'championnat', 'normale', 'Samir Guezzaz'),
(4, 7, 2, '2025-08-25 17:30:00', 1.3, 'championnat', 'normale', 'Mostafa Akarkad'),
(6, 9, 4, '2025-08-28 16:00:00', 1.2, 'championnat', 'normale', 'Abdelhak Etchiali'),
(8, 11, 6, '2025-08-31 19:00:00', 1.4, 'championnat', 'normale', 'Noureddine El Jaafari'),

-- Septembre 2025
(10, 12, 8, '2025-09-03 18:30:00', 1.3, 'championnat', 'normale', 'Redouane Jiyed'),
(1, 4, 1, '2025-09-06 20:00:00', 1.5, 'championnat', 'normale', 'Samir Guezzaz'),
(2, 6, 1, '2025-09-09 19:30:00', 1.4, 'championnat', 'normale', 'Mostafa Akarkad'),
(3, 7, 2, '2025-09-12 17:00:00', 1.3, 'championnat', 'normale', 'Abdelhak Etchiali'),
(5, 8, 3, '2025-09-15 16:30:00', 1.2, 'championnat', 'normale', 'Noureddine El Jaafari'),
(9, 12, 7, '2025-09-18 18:00:00', 1.4, 'championnat', 'normale', 'Redouane Jiyed'),
(10, 1, 8, '2025-09-21 19:00:00', 1.5, 'championnat', 'normale', 'Samir Guezzaz'),
(11, 2, 9, '2025-09-24 17:30:00', 1.6, 'championnat', 'normale', 'Mostafa Akarkad'),
(4, 8, 2, '2025-09-27 16:00:00', 1.3, 'championnat', 'normale', 'Abdelhak Etchiali'),
(6, 10, 4, '2025-09-30 18:30:00', 1.2, 'championnat', 'normale', 'Noureddine El Jaafari'),

-- Octobre 2025 - Matchs de Coupe
(1, 5, 1, '2025-10-03 20:00:00', 1.8, 'coupe', 'demi_finale', 'Redouane Jiyed'),
(2, 3, 1, '2025-10-06 19:30:00', 1.9, 'coupe', 'demi_finale', 'Samir Guezzaz'),
(7, 11, 5, '2025-10-09 17:00:00', 1.4, 'championnat', 'normale', 'Mostafa Akarkad'),
(8, 12, 6, '2025-10-12 16:30:00', 1.3, 'championnat', 'normale', 'Abdelhak Etchiali'),
(9, 1, 7, '2025-10-15 18:00:00', 1.5, 'championnat', 'normale', 'Noureddine El Jaafari'),
(10, 2, 8, '2025-10-18 19:00:00', 1.6, 'championnat', 'normale', 'Redouane Jiyed'),
(3, 6, 2, '2025-10-21 17:30:00', 1.3, 'championnat', 'normale', 'Samir Guezzaz'),
(4, 9, 2, '2025-10-24 16:00:00', 1.2, 'championnat', 'normale', 'Mostafa Akarkad'),
(5, 11, 3, '2025-10-27 18:30:00', 1.4, 'championnat', 'normale', 'Abdelhak Etchiali'),
(1, 2, 1, '2025-10-30 20:30:00', 2.5, 'coupe', 'finale', 'Noureddine El Jaafari'), -- FINALE DE LA COUPE

-- Novembre 2025
(7, 12, 5, '2025-11-02 17:00:00', 1.3, 'championnat', 'normale', 'Redouane Jiyed'),
(8, 1, 6, '2025-11-05 18:00:00', 1.5, 'championnat', 'normale', 'Samir Guezzaz'),
(9, 2, 7, '2025-11-08 19:30:00', 1.6, 'championnat', 'normale', 'Mostafa Akarkad'),
(10, 3, 8, '2025-11-11 16:30:00', 1.4, 'championnat', 'normale', 'Abdelhak Etchiali'),
(11, 4, 9, '2025-11-14 17:30:00', 1.3, 'championnat', 'normale', 'Noureddine El Jaafari'),
(12, 5, 10, '2025-11-17 18:00:00', 1.2, 'championnat', 'normale', 'Redouane Jiyed'),
(6, 1, 4, '2025-11-20 19:00:00', 1.5, 'championnat', 'normale', 'Samir Guezzaz'),
(7, 2, 5, '2025-11-23 17:00:00', 1.6, 'championnat', 'normale', 'Mostafa Akarkad'),
(8, 3, 6, '2025-11-26 16:00:00', 1.4, 'championnat', 'normale', 'Abdelhak Etchiali'),
(9, 4, 7, '2025-11-29 18:30:00', 1.3, 'championnat', 'normale', 'Noureddine El Jaafari'),

-- Décembre 2025
(10, 5, 8, '2025-12-02 17:30:00', 1.2, 'championnat', 'normale', 'Redouane Jiyed'),
(11, 6, 9, '2025-12-05 18:00:00', 1.4, 'championnat', 'normale', 'Samir Guezzaz'),
(12, 7, 10, '2025-12-08 16:30:00', 1.3, 'championnat', 'normale', 'Mostafa Akarkad'),
(1, 8, 1, '2025-12-11 19:30:00', 1.5, 'championnat', 'normale', 'Abdelhak Etchiali'),
(2, 9, 1, '2025-12-14 20:00:00', 1.6, 'championnat', 'normale', 'Noureddine El Jaafari'),
(3, 10, 2, '2025-12-17 17:00:00', 1.4, 'championnat', 'normale', 'Redouane Jiyed'),
(4, 11, 2, '2025-12-20 18:30:00', 1.3, 'championnat', 'normale', 'Samir Guezzaz'),
(5, 12, 3, '2025-12-23 16:00:00', 1.2, 'championnat', 'normale', 'Mostafa Akarkad'),
(6, 2, 4, '2025-12-26 19:00:00', 1.7, 'championnat', 'normale', 'Abdelhak Etchiali'), -- Boxing Day
(1, 7, 1, '2025-12-29 20:30:00', 1.5, 'championnat', 'normale', 'Noureddine El Jaafari'); -- Fin d'année

-- Insertion des informations de villes
INSERT INTO ville_infos (ville, region, population, transport_info, activites_touristiques, restaurants, hotels, liens_utiles, climat_description) VALUES
('Casablanca', 'Casablanca-Settat', 3359818, 
 'Train ONCF depuis Rabat (1h), Aéroport Mohammed V (30min), Tramway Casa, Bus M''dina Bus, Taxi/Careem/InDrive disponible', 
 'Mosquée Hassan II, Corniche Ain Diab, Quartier des Habous, Marché Central, Villa des Arts, Musée Abderrahman Slaoui',
 'Rick''s Café, La Sqala, Le Cabestan, Restaurant Al Mounia, Brasserie La Tour, Le Petit Rocher',
 'Four Seasons Casablanca, Hyatt Regency, Sofitel Tour Blanche, Hotel Barceló Anfa, Movenpick Hotel',
 'ONCF: www.oncf.ma, Aéroport: www.onda.ma, Tramway: www.casatramway.ma, CTM: www.ctm.ma',
 'Climat méditerranéen, températures douces toute l''année, été chaud et sec'),

('Rabat', 'Rabat-Salé-Kénitra', 1932000,
 'Train ONCF depuis Casablanca (1h), Aéroport Rabat-Salé (20min), Tramway Rabat-Salé, Bus Stareo, Taxi/Careem',
 'Tour Hassan, Kasbah des Oudayas, Mausolée Mohammed V, Médina de Rabat, Musée Mohammed VI, Jardins Exotiques',
 'Dar Zaki, Le Dhow, Villa Mandarine, Restaurant Dinarjat, Le Ziryab, Cosmopolitan',
 'La Villa des Orangers, Sofitel Rabat Jardin des Roses, Hotel Farah Rabat, Riad Kalaa',
 'ONCF: www.oncf.ma, Tramway: www.tram-way.ma, Stareo: www.stareo.ma',
 'Climat océanique tempéré, hivers doux, étés modérément chauds'),

('Agadir', 'Souss-Massa', 924000,
 'Aéroport Al Massira (25min), Bus CTM/Supratours, Bus urbain Alsa, Taxi/Careem, Location de voiture',
 'Plage d''Agadir, Kasbah d''Agadir, Vallée des Oiseaux, Souk El Had, Marina d''Agadir, Crocoparc',
 'Pure Passion Restaurant, Jour et Nuit, Le Tapis Rouge, Daffy''s, Villa Blanche, Le Jardin d''Eau',
 'Royal Atlas, Sofitel Agadir Thalassa, Iberostar Founty Beach, Hotel Argana',
 'Aéroport Agadir: www.onda.ma, CTM: www.ctm.ma, Alsa: www.alsa.ma',
 'Climat semi-aride, soleil toute l''année, températures agréables'),

('El Jadida', 'Casablanca-Settat', 194934,
 'Train ONCF depuis Casablanca (1h30), Bus CTM, Taxi/Careem, Route côtière panoramique',
 'Cité Portugaise (UNESCO), Plage d''El Jadida, Citerne Portugaise, Remparts, Phare Sidi Bouafi',
 'Restaurant La Broche, Villa Blanca, Le Toit, Restaurant du Port, Mazagan Beach Resort',
 'Mazagan Beach & Golf Resort, Hotel Suisse, Riad El Maati, Hotel de Provence',
 'ONCF: www.oncf.ma, CTM: www.ctm.ma, Office de Tourisme: www.visiteljadida.com',
 'Climat océanique, brises marines rafraîchissantes, hivers doux'),

('Tanger', 'Tanger-Tétouan-Al Hoceïma', 1065601,
 'Aéroport Ibn Battouta (15min), Port Tanger Med, Train ONCF, Bus CTM, Ferry vers l''Espagne',
 'Médina de Tanger, Grottes d''Hercule, Cap Spartel, Kasbah, Musée de la Légation Américaine',
 'El Morocco Club, Le Saveur du Poisson, Restaurant Hammadi, Villa Josephine, Nord-Pinus',
 'La Mamounia Tanger, Hotel Continental, Villa Josephine, Hilton Garden Inn',
 'Aéroport: www.onda.ma, Port: www.tmpa.ma, ONCF: www.oncf.ma',
 'Climat méditerranéen, influence atlantique, étés tempérés'),

('Safi', 'Marrakech-Safi', 308508,
 'Train ONCF, Bus CTM/Supratours, Route côtière, Taxi/Careem',
 'Médina de Safi, Poterie traditionnelle, Colline des Potiers, Château de Mer, Plage de Safi',
 'Restaurant Salam, Le Refuge, Restaurant du Port, Café Maure, Villa Maroc',
 'Hotel Salam, Riad Watier, Hotel Majestic, Hotel Atlantide',
 'ONCF: www.oncf.ma, CTM: www.ctm.ma, Artisanat: www.safi-pottery.com',
 'Climat océanique, vents alizés, températures modérées'),

('Tétouan', 'Tanger-Tétouan-Al Hoceïma', 380787,
 'Bus CTM depuis Tanger (1h), Taxi collectif, Route de montagne panoramique',
 'Médina de Tétouan (UNESCO), Musée Ethnographique, École des Beaux-Arts, Montagnes du Rif',
 'Restaurant Restinga, Blanco Riad, Restaurant Riad Tetouan, Café Central',
 'Blanco Riad, Hotel Marina Smir, Riad Tetouan, Hotel Chams',
 'CTM: www.ctm.ma, Patrimoine UNESCO: whc.unesco.org',
 'Climat méditerranéen montagnard, étés chauds, hivers frais'),

('Berkane', 'Oriental', 109237,
 'Bus CTM/Supratours, Taxi collectif depuis Oujda, Route vers Nador',
 'Grottes de Taforalt, Montagnes des Beni Snassen, Saidia Beach (40km), Oasis de Figuig',
 'Restaurant Atlas, Café Salam, Restaurant Berkane, Café Central',
 'Hotel Atlas Berkane, Hotel Rif, Auberge des Amandiers',
 'CTM: www.ctm.ma, Région Oriental: www.oriental.ma',
 'Climat semi-aride, étés chauds, hivers doux, proximité mer'),

('Al Hoceima', 'Tanger-Tétouan-Al Hoceïma', 56716,
 'Aéroport Charif Al Idrissi, Bus CTM, Route côtière du Rif, Ferry saisonnier',
 'Parc National d''Al Hoceima, Plages de Quemado, Cala Iris, Montagnes du Rif',
 'Restaurant Al Khayma, Café Salam, Restaurant du Port, Villa Florido',
 'Hotel Mohammed V, Riad Al Hoceima, Hotel Quemado, Villa Florido',
 'Aéroport: www.onda.ma, Parc National: www.eauxetforets.gov.ma',
 'Climat méditerranéen, étés secs, hivers doux, brises marines');

-- Insertion d'un administrateur par défaut
INSERT INTO admins (nom, email, password_hash, role) VALUES
('Administrateur Principal', 'admin@billetterie.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');

-- =====================================================
-- TRIGGERS ET PROCÉDURES STOCKÉES
-- =====================================================

-- Trigger pour mettre à jour les points de fidélité après confirmation de réservation
DELIMITER //
CREATE TRIGGER after_reservation_confirmed
AFTER UPDATE ON reservations
FOR EACH ROW
BEGIN
    IF NEW.statut = 'confirme' AND OLD.statut != 'confirme' THEN
        -- Calculer les points (1 point par 10 DH dépensés)
        SET @points_earned = FLOOR(NEW.prix_total / 10);
        
        -- Mettre à jour les points de l'utilisateur
        UPDATE users 
        SET points_fidelite = points_fidelite + @points_earned 
        WHERE id = NEW.user_id;
        
        -- Enregistrer la transaction de fidélité
        INSERT INTO loyalty_transactions (user_id, reservation_id, points, type, description, points_balance_after)
        SELECT NEW.user_id, NEW.id, @points_earned, 'earned', 
               CONCAT('Points gagnés pour la réservation #', NEW.id),
               (SELECT points_fidelite FROM users WHERE id = NEW.user_id);
    END IF;
END//
DELIMITER ;

-- Trigger pour nettoyer les réservations expirées
DELIMITER //
CREATE EVENT IF NOT EXISTS cleanup_expired_reservations
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
    UPDATE reservations 
    SET statut = 'expire' 
    WHERE statut = 'en_attente' 
    AND expires_at < NOW();
END//
DELIMITER ;

-- Activer l'event scheduler
SET GLOBAL event_scheduler = ON;

-- =====================================================
-- VUES UTILES
-- =====================================================

-- Vue pour les statistiques des matchs
CREATE OR REPLACE VIEW match_statistics AS
SELECT 
    m.id,
    m.date_match,
    ed.nom as equipe_domicile,
    ee.nom as equipe_exterieur,
    s.nom as stade_nom,
    s.ville as stade_ville,
    s.capacite_totale,
    COALESCE(SUM(CASE WHEN r.statut = 'confirme' THEN r.nombre_billets ELSE 0 END), 0) as billets_vendus,
    COALESCE(SUM(CASE WHEN r.statut = 'confirme' THEN r.prix_total ELSE 0 END), 0) as revenus_total,
    ROUND((COALESCE(SUM(CASE WHEN r.statut = 'confirme' THEN r.nombre_billets ELSE 0 END), 0) / s.capacite_totale) * 100, 2) as taux_remplissage
FROM matchs m
JOIN equipes ed ON m.equipe_domicile_id = ed.id
JOIN equipes ee ON m.equipe_exterieur_id = ee.id
JOIN stades s ON m.stade_id = s.id
LEFT JOIN reservations r ON m.id = r.match_id
GROUP BY m.id;

-- Vue pour les places disponibles par match
CREATE OR REPLACE VIEW places_disponibles AS
SELECT 
    m.id as match_id,
    s.capacite_vip - COALESCE(SUM(CASE WHEN r.categorie = 'VIP' AND r.statut IN ('confirme', 'en_attente') THEN r.nombre_billets ELSE 0 END), 0) as vip_disponibles,
    s.capacite_normale - COALESCE(SUM(CASE WHEN r.categorie = 'Normale' AND r.statut IN ('confirme', 'en_attente') THEN r.nombre_billets ELSE 0 END), 0) as normale_disponibles,
    s.capacite_tribune - COALESCE(SUM(CASE WHEN r.categorie = 'Tribune' AND r.statut IN ('confirme', 'en_attente') THEN r.nombre_billets ELSE 0 END), 0) as tribune_disponibles
FROM matchs m
JOIN stades s ON m.stade_id = s.id
LEFT JOIN reservations r ON m.id = r.match_id
GROUP BY m.id;

-- =====================================================
-- INDEX POUR OPTIMISATION
-- =====================================================

-- Index composites pour les requêtes fréquentes
CREATE INDEX idx_match_date_statut ON matchs(date_match, statut);
CREATE INDEX idx_reservation_user_statut ON reservations(user_id, statut);
CREATE INDEX idx_reservation_match_categorie ON reservations(match_id, categorie, statut);
CREATE INDEX idx_loyalty_user_type ON loyalty_transactions(user_id, type);

-- =====================================================
-- CONTRAINTES DE SÉCURITÉ
-- =====================================================

-- Contrainte pour s'assurer que les prix sont positifs
ALTER TABLE matchs ADD CONSTRAINT chk_prix_positifs 
CHECK (prix_base_vip > 0 AND prix_base_normale > 0 AND prix_base_tribune > 0);

-- Contrainte pour s'assurer que les capacités sont cohérentes
ALTER TABLE stades ADD CONSTRAINT chk_capacites_coherentes 
CHECK (capacite_totale = capacite_vip + capacite_normale + capacite_tribune);

-- =====================================================
-- COMMENTAIRES SUR LES TABLES
-- =====================================================

ALTER TABLE users COMMENT = 'Table des utilisateurs de la plateforme';
ALTER TABLE equipes COMMENT = 'Table des équipes de football';
ALTER TABLE stades COMMENT = 'Table des stades et leurs caractéristiques';
ALTER TABLE matchs COMMENT = 'Table des matchs programmés';
ALTER TABLE reservations COMMENT = 'Table des réservations de billets';
ALTER TABLE billets COMMENT = 'Table des billets individuels avec QR codes';
ALTER TABLE loyalty_transactions COMMENT = 'Table des transactions du programme de fidélité';
ALTER TABLE ville_infos COMMENT = 'Table des informations touristiques des villes';
ALTER TABLE notifications COMMENT = 'Table des notifications utilisateurs';
ALTER TABLE activity_logs COMMENT = 'Table des logs d''activité pour audit';

-- =====================================================
-- DONNÉES DE TEST SUPPLÉMENTAIRES
-- =====================================================

-- Insertion d'utilisateurs de test
INSERT INTO users (nom, email, telephone, password_hash, points_fidelite) VALUES
('Ahmed Benali', 'ahmed.benali@email.com', '+212661234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 150),
('Fatima Zahra', 'fatima.zahra@email.com', '+212662345678', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 75),
('Mohammed Alami', 'mohammed.alami@email.com', '+212663456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 200),
('Aicha Bennani', 'aicha.bennani@email.com', '+212664567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 50),
('Youssef Tazi', 'youssef.tazi@email.com', '+212665678901', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 300);

-- =====================================================
-- FINALISATION
-- =====================================================

-- Affichage du résumé de création
SELECT 'Base de données billetterie créée avec succès!' as message;
SELECT COUNT(*) as nombre_equipes FROM equipes;
SELECT COUNT(*) as nombre_stades FROM stades;
SELECT COUNT(*) as nombre_matchs FROM matchs;
SELECT COUNT(*) as nombre_villes FROM ville_infos;

-- Affichage des prochains matchs
SELECT 
    CONCAT(ed.nom, ' vs ', ee.nom) as match_info,
    DATE_FORMAT(m.date_match, '%d/%m/%Y à %H:%i') as date_heure,
    s.nom as stade,
    s.ville
FROM matchs m
JOIN equipes ed ON m.equipe_domicile_id = ed.id
JOIN equipes ee ON m.equipe_exterieur_id = ee.id
JOIN stades s ON m.stade_id = s.id
WHERE m.date_match > NOW()
ORDER BY m.date_match
LIMIT 10;