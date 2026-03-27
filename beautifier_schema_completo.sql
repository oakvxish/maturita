SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

CREATE DATABASE IF NOT EXISTS `salone_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `salone_db`;

DROP TABLE IF EXISTS `password_reset_audit`;
DROP TABLE IF EXISTS `user_recovery_codes`;
DROP TABLE IF EXISTS `appuntamenti`;
DROP TABLE IF EXISTS `magazzino`;
DROP TABLE IF EXISTS `clienti`;
DROP TABLE IF EXISTS `servizi`;
DROP TABLE IF EXISTS `impostazioni`;
DROP TABLE IF EXISTS `user_saloni`;
DROP TABLE IF EXISTS `userdata`;
DROP TABLE IF EXISTS `saloni`;

CREATE TABLE `saloni` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome_salone` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(180) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_saloni_slug` (`slug`),
  KEY `ix_saloni_nome_salone` (`nome_salone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `userdata` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `nome` VARCHAR(150) DEFAULT NULL,
  `salone_id` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_userdata_username` (`username`),
  UNIQUE KEY `ux_userdata_email` (`email`),
  KEY `ix_userdata_salone_id` (`salone_id`),
  CONSTRAINT `fk_userdata_salone` FOREIGN KEY (`salone_id`) REFERENCES `saloni` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_saloni` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `salone_id` INT UNSIGNED NOT NULL,
  `ruolo` ENUM('proprietario','dipendente') NOT NULL DEFAULT 'dipendente',
  `attivo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_user_saloni_user_salone` (`user_id`,`salone_id`),
  KEY `ix_user_saloni_salone` (`salone_id`),
  KEY `ix_user_saloni_attivo` (`attivo`),
  KEY `ix_user_saloni_ruolo` (`ruolo`),
  CONSTRAINT `fk_user_saloni_user` FOREIGN KEY (`user_id`) REFERENCES `userdata` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_saloni_salone` FOREIGN KEY (`salone_id`) REFERENCES `saloni` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `impostazioni` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `salone_id` INT UNSIGNED NOT NULL,
  `chiave` VARCHAR(120) NOT NULL,
  `valore` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_impostazioni_salone_chiave` (`salone_id`,`chiave`),
  KEY `ix_impostazioni_chiave` (`chiave`),
  CONSTRAINT `fk_impostazioni_salone` FOREIGN KEY (`salone_id`) REFERENCES `saloni` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clienti` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `salone_id` INT UNSIGNED NOT NULL,
  `nome` VARCHAR(120) NOT NULL,
  `cognome` VARCHAR(120) DEFAULT NULL,
  `telefono` VARCHAR(40) DEFAULT NULL,
  `email` VARCHAR(190) DEFAULT NULL,
  `tag` VARCHAR(80) DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_clienti_salone` (`salone_id`),
  KEY `ix_clienti_nome` (`nome`,`cognome`),
  KEY `ix_clienti_telefono` (`telefono`),
  KEY `ix_clienti_email` (`email`),
  KEY `ix_clienti_tag` (`tag`),
  CONSTRAINT `fk_clienti_salone` FOREIGN KEY (`salone_id`) REFERENCES `saloni` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `servizi` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `salone_id` INT UNSIGNED NOT NULL,
  `nome` VARCHAR(150) NOT NULL,
  `categoria` VARCHAR(120) DEFAULT NULL,
  `durata_minuti` INT UNSIGNED NOT NULL DEFAULT 60,
  `prezzo` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_servizi_salone` (`salone_id`),
  KEY `ix_servizi_categoria` (`categoria`),
  KEY `ix_servizi_nome` (`nome`),
  CONSTRAINT `fk_servizi_salone` FOREIGN KEY (`salone_id`) REFERENCES `saloni` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_servizi_durata_minuti` CHECK (`durata_minuti` > 0),
  CONSTRAINT `ck_servizi_prezzo` CHECK (`prezzo` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appuntamenti` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `salone_id` INT UNSIGNED NOT NULL,
  `cliente_id` INT UNSIGNED NOT NULL,
  `servizio_id` INT UNSIGNED NOT NULL,
  `data_ora` DATETIME NOT NULL,
  `stato` ENUM('attesa','confermato','completato','annullato') NOT NULL DEFAULT 'attesa',
  `note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_appuntamenti_salone_data` (`salone_id`,`data_ora`),
  KEY `ix_appuntamenti_cliente` (`cliente_id`),
  KEY `ix_appuntamenti_servizio` (`servizio_id`),
  KEY `ix_appuntamenti_stato` (`stato`),
  CONSTRAINT `fk_appuntamenti_salone` FOREIGN KEY (`salone_id`) REFERENCES `saloni` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_appuntamenti_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clienti` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_appuntamenti_servizio` FOREIGN KEY (`servizio_id`) REFERENCES `servizi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `magazzino` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `salone_id` INT UNSIGNED NOT NULL,
  `nome` VARCHAR(150) NOT NULL,
  `categoria` VARCHAR(120) DEFAULT NULL,
  `quantita` INT NOT NULL DEFAULT 0,
  `soglia_minima` INT NOT NULL DEFAULT 0,
  `unita` VARCHAR(30) NOT NULL DEFAULT 'pz',
  `prezzo_acquisto` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_magazzino_salone` (`salone_id`),
  KEY `ix_magazzino_categoria` (`categoria`),
  KEY `ix_magazzino_nome` (`nome`),
  CONSTRAINT `fk_magazzino_salone` FOREIGN KEY (`salone_id`) REFERENCES `saloni` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_magazzino_quantita` CHECK (`quantita` >= 0),
  CONSTRAINT `ck_magazzino_soglia_minima` CHECK (`soglia_minima` >= 0),
  CONSTRAINT `ck_magazzino_prezzo_acquisto` CHECK (`prezzo_acquisto` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_recovery_codes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `code_hash` VARCHAR(255) NOT NULL,
  `generated_by_user_id` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` DATETIME DEFAULT NULL,
  `revoked_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_recovery_codes_user` (`user_id`),
  KEY `ix_recovery_codes_generated_by` (`generated_by_user_id`),
  KEY `ix_recovery_codes_used_at` (`used_at`),
  KEY `ix_recovery_codes_revoked_at` (`revoked_at`),
  CONSTRAINT `fk_recovery_codes_user` FOREIGN KEY (`user_id`) REFERENCES `userdata` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_recovery_codes_generated_by_user` FOREIGN KEY (`generated_by_user_id`) REFERENCES `userdata` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_reset_audit` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `event_type` VARCHAR(100) NOT NULL,
  `event_status` VARCHAR(50) NOT NULL,
  `detail` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_password_reset_audit_user` (`user_id`),
  KEY `ix_password_reset_audit_event_type` (`event_type`),
  KEY `ix_password_reset_audit_status` (`event_status`),
  KEY `ix_password_reset_audit_created_at` (`created_at`),
  CONSTRAINT `fk_password_reset_audit_user` FOREIGN KEY (`user_id`) REFERENCES `userdata` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
