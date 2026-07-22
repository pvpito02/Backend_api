-- =============================================================
-- Système de Pointage — Mairie de Sandiara
-- Schéma de référence (enrichi pour Admin React + Mobile Flutter)
-- MySQL 8.0+ / MariaDB 10.6+
--
-- Source initiale : pointage_mairie_schema.sql
-- Enrichissements Tâche 1 Backend_api :
--   - Rôles admin / sous_admin (alignés UI)
--   - Sessions connexion/déconnexion + tokens Sanctum
--   - Sites GPS / géofencing multi-sites
--   - Demandes élargies + historique de statuts (traçabilité)
--   - Annonces avec image / lieu / période
--   - Modules mobile + config remote (paramètres admin)
--   - Horaires de travail + device tokens (push futur)
-- =============================================================

SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS pointage_mairie
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE pointage_mairie;

-- =============================================================
-- 1. RÔLES ET UTILISATEURS
-- =============================================================

CREATE TABLE IF NOT EXISTS roles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL UNIQUE,
  display_name VARCHAR(100) NOT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id BIGINT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(191) NOT NULL UNIQUE,
  phone VARCHAR(30) NULL,
  password VARCHAR(255) NOT NULL,
  avatar_url VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  email_verified_at TIMESTAMP NULL DEFAULT NULL,
  last_login_at TIMESTAMP NULL DEFAULT NULL,
  last_logout_at TIMESTAMP NULL DEFAULT NULL,
  last_login_ip VARCHAR(45) NULL,
  last_user_agent VARCHAR(255) NULL,
  remember_token VARCHAR(100) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tokens API Laravel Sanctum (mobile + admin)
CREATE TABLE IF NOT EXISTS personal_access_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tokenable_type VARCHAR(255) NOT NULL,
  tokenable_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  token VARCHAR(64) NOT NULL,
  abilities TEXT NULL,
  last_used_at TIMESTAMP NULL DEFAULT NULL,
  expires_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY personal_access_tokens_token_unique (token),
  INDEX personal_access_tokens_tokenable_index (tokenable_type, tokenable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 2. STRUCTURE ORGANISATIONNELLE
-- =============================================================

CREATE TABLE IF NOT EXISTS departements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL UNIQUE,
  nom VARCHAR(150) NOT NULL,
  responsable_id BIGINT UNSIGNED NULL,
  email VARCHAR(191) NULL,
  telephone VARCHAR(30) NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_departements_responsable FOREIGN KEY (responsable_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL UNIQUE,
  matricule VARCHAR(30) NOT NULL UNIQUE,
  prenom VARCHAR(100) NOT NULL,
  nom VARCHAR(100) NOT NULL,
  sexe ENUM('M','F') NULL,
  date_naissance DATE NULL,
  date_entree DATE NULL,
  poste VARCHAR(150) NULL,
  departement_id BIGINT UNSIGNED NULL,
  supervisor_id BIGINT UNSIGNED NULL,
  email VARCHAR(191) NULL UNIQUE,
  telephone VARCHAR(30) NULL,
  photo_url VARCHAR(255) NULL,
  statut ENUM('Actif','Inactif','Retraité','Suspendu') NOT NULL DEFAULT 'Actif',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  heure_travail_par_jour DECIMAL(4,2) NULL DEFAULT 8.00,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_agents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_agents_departement FOREIGN KEY (departement_id) REFERENCES departements(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_agents_supervisor FOREIGN KEY (supervisor_id) REFERENCES agents(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 3. SITES GPS / GÉOFENCING (paramètres localisation admin)
-- =============================================================

CREATE TABLE IF NOT EXISTS sites (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  latitude DECIMAL(10,8) NOT NULL,
  longitude DECIMAL(11,8) NOT NULL,
  radius_meters DECIMAL(8,2) NOT NULL DEFAULT 150.00,
  qr_payload VARCHAR(150) NOT NULL UNIQUE,
  maps_url VARCHAR(255) NULL,
  services_rule ENUM('ALL_EXCEPT_TECHNIQUE','TECHNIQUE_ONLY','ALL','CUSTOM') NOT NULL DEFAULT 'ALL',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_departement (
  site_id BIGINT UNSIGNED NOT NULL,
  departement_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (site_id, departement_id),
  CONSTRAINT fk_sd_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
  CONSTRAINT fk_sd_departement FOREIGN KEY (departement_id) REFERENCES departements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 4. POINTAGES ET PRÉSENCE
-- =============================================================

CREATE TABLE IF NOT EXISTS pointages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  agent_id BIGINT UNSIGNED NOT NULL,
  site_id BIGINT UNSIGNED NULL,
  type ENUM('ENTREE','SORTIE') NOT NULL,
  date_pointage DATE NOT NULL,
  heure_pointage TIME NOT NULL,
  statut ENUM('A_L_HEURE','RETARD','ANOMALIE','VALIDE','MODIFIE') NOT NULL DEFAULT 'A_L_HEURE',
  source ENUM('QR','MANUEL','GPS','OFFLINE','AUTRE') NOT NULL DEFAULT 'QR',
  latitude DECIMAL(10,8) NULL,
  longitude DECIMAL(11,8) NULL,
  device_id VARCHAR(100) NULL,
  is_visitor TINYINT(1) NOT NULL DEFAULT 0,
  pending_sync TINYINT(1) NOT NULL DEFAULT 0,
  note TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_pointages_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pointages_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_pointages_agent_date (agent_id, date_pointage),
  INDEX idx_pointages_date_statut (date_pointage, statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pointage_anomalies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pointage_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(50) NOT NULL,
  severite ENUM('faible','moyenne','elevee') NOT NULL DEFAULT 'moyenne',
  description TEXT NOT NULL,
  resolved TINYINT(1) NOT NULL DEFAULT 0,
  resolved_by BIGINT UNSIGNED NULL,
  resolved_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_anomalies_pointage FOREIGN KEY (pointage_id) REFERENCES pointages(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_anomalies_resolver FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 5. DEMANDES (mobile) + TRAÇABILITÉ
-- =============================================================

CREATE TABLE IF NOT EXISTS absence_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  agent_id BIGINT UNSIGNED NOT NULL,
  type_demande ENUM(
    'ABSENCE','CONGE','PERMISSION','MALADIE','FORMATION',
    'MISSION','CORRECTION','DEMISSION','RETRAITE'
  ) NOT NULL DEFAULT 'ABSENCE',
  date_debut DATE NOT NULL,
  date_fin DATE NOT NULL,
  heure_debut TIME NULL,
  heure_fin TIME NULL,
  motif TEXT NOT NULL,
  extra_json JSON NULL,
  document_path VARCHAR(255) NULL,
  statut ENUM('EN_ATTENTE','EN_COURS','APPROUVEE','REJETEE','ANNULEE') NOT NULL DEFAULT 'EN_ATTENTE',
  lue_par_admin_at TIMESTAMP NULL DEFAULT NULL,
  lue_par_admin_id BIGINT UNSIGNED NULL,
  approuve_par BIGINT UNSIGNED NULL,
  date_approbation TIMESTAMP NULL DEFAULT NULL,
  motif_rejet TEXT NULL,
  commentaire TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_absence_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_absence_approver FOREIGN KEY (approuve_par) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_absence_reader FOREIGN KEY (lue_par_admin_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_absence_agent_dates (agent_id, date_debut, date_fin),
  INDEX idx_absence_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS demande_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  absence_request_id BIGINT UNSIGNED NOT NULL,
  from_statut VARCHAR(30) NULL,
  to_statut VARCHAR(30) NOT NULL,
  changed_by BIGINT UNSIGNED NULL,
  changed_by_label VARCHAR(150) NULL,
  detail TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_dsh_request FOREIGN KEY (absence_request_id) REFERENCES absence_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_dsh_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_dsh_request (absence_request_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS overtime_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  agent_id BIGINT UNSIGNED NOT NULL,
  date_travail DATE NOT NULL,
  heures_sup DECIMAL(4,2) NOT NULL,
  motif TEXT NOT NULL,
  statut ENUM('EN_ATTENTE','APPROUVEE','REFUSEE') NOT NULL DEFAULT 'EN_ATTENTE',
  approuve_par BIGINT UNSIGNED NULL,
  date_approbation TIMESTAMP NULL DEFAULT NULL,
  commentaire TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_overtime_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_overtime_approver FOREIGN KEY (approuve_par) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 6. MISSIONS, SANCTIONS ET RETRAITES
-- =============================================================

CREATE TABLE IF NOT EXISTS missions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  agent_id BIGINT UNSIGNED NOT NULL,
  titre VARCHAR(150) NOT NULL,
  description TEXT NULL,
  lieu VARCHAR(150) NOT NULL,
  date_debut DATE NOT NULL,
  date_fin DATE NOT NULL,
  statut ENUM('PLANIFIEE','EN_COURS','TERMINEE','ANNULEE') NOT NULL DEFAULT 'PLANIFIEE',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_missions_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_missions_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sanctions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  agent_id BIGINT UNSIGNED NOT NULL,
  type_sanction ENUM('AVERTISSEMENT','LETTRE','SUSPENSION','AUTRE') NOT NULL,
  titre VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  date_debut DATE NOT NULL,
  date_fin DATE NULL,
  severite ENUM('faible','moyenne','elevee') NOT NULL DEFAULT 'moyenne',
  statut ENUM('ACTIVE','TERMINEE','ANNULEE') NOT NULL DEFAULT 'ACTIVE',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_sanctions_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_sanctions_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS retraites (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  agent_id BIGINT UNSIGNED NOT NULL,
  date_depart DATE NOT NULL,
  motif TEXT NULL,
  statut ENUM('EN_COURS','VALIDE','REJETE','TERMINE') NOT NULL DEFAULT 'EN_COURS',
  montant_pension DECIMAL(10,2) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_retraits_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_retraits_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 7. NOTIFICATIONS, ANNONCES, QR, CONFIG
-- =============================================================

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  title VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  type VARCHAR(50) NOT NULL DEFAULT 'info',
  categorie VARCHAR(50) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at TIMESTAMP NULL DEFAULT NULL,
  related_model VARCHAR(100) NULL,
  related_id BIGINT UNSIGNED NULL,
  play_sound TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_notifications_user_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(150) NOT NULL,
  content TEXT NOT NULL,
  when_label VARCHAR(100) NULL,
  place VARCHAR(150) NULL,
  image_url VARCHAR(255) NULL,
  published_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  starts_at TIMESTAMP NULL DEFAULT NULL,
  expires_at TIMESTAMP NULL DEFAULT NULL,
  duration_hours INT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  priority INT NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_announcements_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qr_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  agent_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(100) NOT NULL UNIQUE,
  issued_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NULL DEFAULT NULL,
  statut ENUM('ACTIF','EXPIRE','REVOQUE') NOT NULL DEFAULT 'ACTIF',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_qr_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS holidays (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  libelle VARCHAR(150) NOT NULL,
  date_holiday DATE NOT NULL,
  type_holiday ENUM('FERIE','JOURNALIER','SPECIAL') NOT NULL DEFAULT 'FERIE',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_holiday_date (date_holiday)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clés/valeurs (paramètres admin → mobile)
CREATE TABLE IF NOT EXISTS remote_configs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  key_name VARCHAR(100) NOT NULL UNIQUE,
  value_text TEXT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modules visibles sur le mobile (Paramètres → Modules)
CREATE TABLE IF NOT EXISTS mobile_features (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  feature_key VARCHAR(50) NOT NULL UNIQUE,
  label VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Horaires de travail (Paramètres → Horaires)
CREATE TABLE IF NOT EXISTS work_schedules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL DEFAULT 'default',
  entry_time TIME NOT NULL DEFAULT '08:00:00',
  exit_time TIME NOT NULL DEFAULT '17:00:00',
  friday_exit_time TIME NOT NULL DEFAULT '13:00:00',
  late_tolerance_minutes INT UNSIGNED NOT NULL DEFAULT 15,
  work_saturday TINYINT(1) NOT NULL DEFAULT 1,
  block_sunday TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tokens push FCM / APNs (préparation notifs ringtone mobile)
CREATE TABLE IF NOT EXISTS device_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  token VARCHAR(255) NOT NULL,
  platform ENUM('android','ios','web') NOT NULL DEFAULT 'android',
  device_name VARCHAR(100) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_used_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_device_token (token),
  CONSTRAINT fk_device_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_device_user (user_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  model_type VARCHAR(100) NULL,
  model_id BIGINT UNSIGNED NULL,
  details JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_audit_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 8. DONNÉES DE RÉFÉRENCE INITIALES
-- =============================================================

INSERT INTO roles (name, display_name, description) VALUES
  ('super_admin', 'Super Administrateur', 'Gère tous les comptes, paramètres et validations'),
  ('admin', 'Administrateur', 'Administration courante (RH, pointages, agents)'),
  ('sous_admin', 'Sous-administrateur', 'Droits limités — consultation et actions courantes'),
  ('agent', 'Agent', 'Pointage mobile, demandes et consultation personnelle'),
  ('rh', 'RH', 'Alias historique — à mapper vers admin si besoin'),
  ('direction', 'Direction', 'Vue globale — à mapper vers admin / super_admin si besoin');

INSERT INTO departements (code, nom, description) VALUES
  ('TECH', 'Service Technique', 'Maintenance et interventions techniques'),
  ('ETAT_CIVIL', 'État Civil', 'Gestion des actes et services administratifs'),
  ('INFORMATIQUE', 'Informatique', 'Support technique et digitalisation'),
  ('COURRIER', 'Bureau Courriel', 'Correspondance et organisation administrative'),
  ('URBANISME', 'Urbanisme', 'Planification et suivi urbain'),
  ('ACCUEIL', 'Accueil', 'Gestion du guichet et accueil du public'),
  ('ARCHIVES', 'Archives', 'Conservation et archivage'),
  ('FINANCES', 'Finances', 'Gestion budgétaire et comptabilité'),
  ('SECRETARIAT', 'Secrétariat', 'Appui de direction et organisation'),
  ('RH', 'Ressources humaines', 'Gestion du personnel');

INSERT INTO sites (code, name, latitude, longitude, radius_meters, qr_payload, maps_url, services_rule) VALUES
  (
    'nouvelle-mairie',
    'Nouvelle Mairie',
    14.4359287,
    -16.7972649,
    150.00,
    'SANDIARA:BORNE:NOUVELLE-MAIRIE',
    'https://maps.app.goo.gl/E5CbMQK4gVc46PNC9',
    'ALL_EXCEPT_TECHNIQUE'
  ),
  (
    'ancienne-mairie',
    'Ancienne Mairie',
    14.4361428,
    -16.7926273,
    150.00,
    'SANDIARA:BORNE:ANCIENNE-MAIRIE',
    'https://maps.app.goo.gl/nSqHCdRw4rNibXhFA',
    'TECHNIQUE_ONLY'
  );

INSERT INTO work_schedules (name, entry_time, exit_time, friday_exit_time, late_tolerance_minutes, work_saturday, block_sunday) VALUES
  ('default', '08:00:00', '17:00:00', '13:00:00', 15, 1, 1);

INSERT INTO mobile_features (feature_key, label, description, is_visible, sort_order) VALUES
  ('scan', 'Scanner QR', 'Pointage par QR code', 1, 10),
  ('historique', 'Historique', 'Voir les pointages passés', 1, 20),
  ('planning', 'Mon planning', 'Horaires et planning agent', 1, 30),
  ('demandes', 'Demandes', 'Absences / congés / permissions…', 1, 40),
  ('stats', 'Statistiques', 'Stats personnelles', 1, 50),
  ('profil', 'Profil', 'Fiche et photo agent', 1, 60),
  ('annonces', 'Annonces', 'Bannière infos mairie', 1, 70),
  ('missions', 'Missions', 'Déplacements hors site', 1, 80),
  ('carte', 'Carte GPS', 'Carte des sites de pointage', 1, 90),
  ('partage_rh', 'Partage RH', 'Envoyer un justificatif RH', 0, 100);

INSERT INTO remote_configs (key_name, value_text, description) VALUES
  ('app_name', 'Système de Pointage QR', 'Nom de l’application'),
  ('org_name', 'Mairie de Sandiara', 'Organisme'),
  ('tagline', 'Une commune green and clean', 'Slogan'),
  ('app_version', '1.0.0', 'Version minimale mobile'),
  ('force_app_update', '0', 'Forcer la mise à jour mobile'),
  ('maintenance_mode', '0', 'Mode maintenance mobile'),
  ('support_phone', '+221 33 XXX XX XX', 'Téléphone support'),
  ('support_email', 'rh@sandiara.sn', 'Email support'),
  ('gps_strict', '1', 'Géofencing strict'),
  ('offline_allowed', '1', 'Pointage hors-ligne autorisé'),
  ('require_photo_on_scan', '0', 'Photo obligatoire au scan'),
  ('default_radius_meters', '150', 'Rayon GPS par défaut'),
  ('mission_exception', '1', 'Exception GPS en mission'),
  ('notif_retards', '1', 'Notifier les retards'),
  ('notif_absence', '1', 'Alerte absence'),
  ('notif_reminder_scan', '1', 'Rappel pointage'),
  ('session_minutes', '60', 'Durée de session admin'),
  ('demo_mode', '1', 'Mode démonstration');

INSERT INTO holidays (libelle, date_holiday, type_holiday) VALUES
  ('Jour de l’An', '2026-01-01', 'FERIE'),
  ('Fête de la Victoire', '2026-05-01', 'FERIE'),
  ('Aïd al-Adha', '2026-05-31', 'FERIE'),
  ('Tabaski', '2026-06-06', 'FERIE');
