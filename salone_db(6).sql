-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Creato il: Mar 20, 2026 alle 09:06
-- Versione del server: 10.4.32-MariaDB
-- Versione PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `salone_db`
--

-- --------------------------------------------------------

--
-- Struttura della tabella `appuntamenti`
--

CREATE TABLE `appuntamenti` (
  `id` int(11) NOT NULL,
  `salone_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `servizio_id` int(11) NOT NULL,
  `data_ora` datetime NOT NULL,
  `stato` enum('attesa','confermato','completato','annullato') DEFAULT 'attesa',
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `appuntamenti`
--

INSERT INTO `appuntamenti` (`id`, `salone_id`, `cliente_id`, `servizio_id`, `data_ora`, `stato`, `note`, `created_at`) VALUES
(1, 1, 1, 4, '2026-03-18 10:00:00', 'confermato', 'Colore primaverile', '2026-03-13 09:47:46'),
(2, 1, 3, 6, '2026-03-19 11:00:00', 'confermato', NULL, '2026-03-13 09:47:46'),
(3, 2, 6, 11, '2026-03-18 08:30:00', 'confermato', NULL, '2026-03-13 09:47:46'),
(4, 2, 7, 15, '2026-03-20 10:00:00', 'confermato', 'Cheratina periodica', '2026-03-13 09:47:46'),
(5, 5, 9, 21, '2026-03-20 15:00:00', 'confermato', 'Prenotazione di prova', '2026-03-13 09:55:34');

-- --------------------------------------------------------

--
-- Struttura della tabella `clienti`
--

CREATE TABLE `clienti` (
  `id` int(11) NOT NULL,
  `salone_id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cognome` varchar(100) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `tag` varchar(50) DEFAULT 'normale',
  `note` text DEFAULT NULL,
  `whatsapp_chat_id` varchar(60) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `clienti`
--

INSERT INTO `clienti` (`id`, `salone_id`, `nome`, `cognome`, `telefono`, `email`, `tag`, `note`, `whatsapp_chat_id`, `created_at`) VALUES
(1, 1, 'Sofia', 'Russo', '3311001001', 'sofia.russo@mail.it', 'vip', 'Colorazione ogni 6 settimane', 'chat_1001', '2026-03-13 09:47:46'),
(2, 1, 'Beatrice', 'Colombo', '3321001002', 'beatrice@mail.it', 'normale', NULL, 'chat_1002', '2026-03-13 09:47:46'),
(3, 1, 'Aurora', 'Mancini', '3331001003', 'aurora@mail.it', 'vip', 'Allergia nickel', 'chat_1003', '2026-03-13 09:47:46'),
(4, 1, 'Giorgia', 'Barbieri', '3341001004', 'giorgia@mail.it', 'normale', 'Solo mattina', 'chat_1004', '2026-03-13 09:47:46'),
(5, 1, 'Noemi', 'Testa', '3351001005', 'noemi@mail.it', 'potenziale', 'Nuova', 'chat_1005', '2026-03-13 09:47:46'),
(6, 2, 'Marco', 'Pellegrini', '3411002001', 'marco@mail.it', 'normale', 'Taglio corto', 'chat_2001', '2026-03-13 09:47:46'),
(7, 2, 'Andrea', 'Monti', '3421002002', 'andrea@mail.it', 'vip', 'Cheratina', 'chat_2002', '2026-03-13 09:47:46'),
(8, 2, 'Valentina', 'Bruno', '3461002006', 'valentina@mail.it', 'vip', 'Combo colore+mani', 'chat_2006', '2026-03-13 09:47:46'),
(9, 5, 'Mario', 'Rossi', '3330001122', 'mario.rossi@example.com', 'vip', NULL, NULL, '2026-03-13 09:55:34'),
(10, 5, 'Gianni', 'Verdi', '3334445566', 'g.verdi@blabla.it', 'normale', NULL, NULL, '2026-03-13 09:55:34');

-- --------------------------------------------------------

--
-- Struttura della tabella `impostazioni`
--

CREATE TABLE `impostazioni` (
  `chiave` varchar(60) NOT NULL,
  `valore` text DEFAULT NULL,
  `salone_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `impostazioni`
--

INSERT INTO `impostazioni` (`chiave`, `valore`, `salone_id`) VALUES
('iva', '22', 1),
('iva', '22', 2),
('iva', '22', 3),
('iva', '22', 4),
('iva', '22', 5),
('iva', '22', 7),
('iva', '22', 8),
('iva', '22', 9),
('nome_salone', 'Glamour Milano', 1),
('nome_salone', 'Capelli & Co', 2),
('nome_salone', 'ciao blabla', 3),
('nome_salone', 'ciao blabla', 4),
('nome_salone', 'ciao bla bla', 5),
('nome_salone', 'jessimiao', 7),
('nome_salone', 'JESSI', 8),
('nome_salone', 'nicofrocio', 9),
('reminder_24h', '1', 1),
('reminder_24h', '1', 2),
('reminder_24h', '1', 3),
('reminder_24h', '1', 4),
('reminder_24h', '1', 5),
('reminder_24h', '1', 7),
('reminder_24h', '1', 8),
('reminder_24h', '1', 9),
('reminder_2h', '1', 1),
('reminder_2h', '1', 2),
('reminder_2h', '1', 3),
('reminder_2h', '1', 4),
('reminder_2h', '1', 5),
('reminder_2h', '1', 7),
('reminder_2h', '1', 8),
('reminder_2h', '1', 9),
('tema', 'chiaro', 1),
('tema', 'scuro', 2),
('tema', 'chiaro', 3),
('tema', 'chiaro', 4),
('tema', 'chiaro', 5),
('tema', 'chiaro', 7),
('tema', 'chiaro', 8),
('tema', 'chiaro', 9),
('valuta', 'EUR', 1),
('valuta', 'EUR', 2),
('valuta', 'EUR', 3),
('valuta', 'EUR', 4),
('valuta', 'EUR', 5),
('valuta', 'EUR', 7),
('valuta', 'EUR', 8),
('valuta', 'EUR', 9);

--
-- Trigger `impostazioni`
--
DELIMITER $$
CREATE TRIGGER `sync_impostazioni_to_saloni` AFTER UPDATE ON `impostazioni` FOR EACH ROW BEGIN
    IF NEW.chiave = 'nome_salone' THEN
        UPDATE saloni SET nome_salone = NEW.valore WHERE id = NEW.salone_id;
    ELSEIF NEW.chiave = 'iva' THEN
        UPDATE saloni SET iva = CAST(NEW.valore AS UNSIGNED) WHERE id = NEW.salone_id;
    ELSEIF NEW.chiave = 'orario_apertura' THEN
        UPDATE saloni SET orario_apertura = NEW.valore WHERE id = NEW.salone_id;
    ELSEIF NEW.chiave = 'orario_chiusura' THEN
        UPDATE saloni SET orario_chiusura = NEW.valore WHERE id = NEW.salone_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struttura della tabella `magazzino`
--

CREATE TABLE `magazzino` (
  `id` int(11) NOT NULL,
  `salone_id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `categoria` varchar(60) DEFAULT NULL,
  `quantita` int(11) DEFAULT 0,
  `soglia_minima` int(11) DEFAULT 5,
  `unita` varchar(20) DEFAULT 'pz',
  `prezzo_acquisto` decimal(8,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `magazzino`
--

INSERT INTO `magazzino` (`id`, `salone_id`, `nome`, `categoria`, `quantita`, `soglia_minima`, `unita`, `prezzo_acquisto`) VALUES
(1, 1, 'Shampoo nutriente 500ml', 'capelli', 18, 6, 'pz', 6.50),
(2, 2, 'Cheratina lisciante 200ml', 'capelli', 3, 4, 'pz', 18.50);

-- --------------------------------------------------------

--
-- Struttura della tabella `saloni`
--

CREATE TABLE `saloni` (
  `id` int(11) NOT NULL,
  `nome_salone` varchar(100) DEFAULT 'Nuovo Salone',
  `slug` varchar(100) DEFAULT NULL,
  `giorni_lavoro` varchar(100) DEFAULT 'lun,mar,mer,gio,ven,sab',
  `orario_apertura` time DEFAULT '09:00:00',
  `orario_chiusura` time DEFAULT '19:00:00',
  `iva` int(11) DEFAULT 22,
  `valuta` varchar(10) DEFAULT 'EUR',
  `tema` varchar(20) DEFAULT 'chiaro',
  `reminder_24h` tinyint(1) DEFAULT 1,
  `reminder_2h` tinyint(1) DEFAULT 1,
  `reminder_template_24h` text DEFAULT NULL,
  `reminder_template_2h` text DEFAULT NULL,
  `whatsapp_api_token` text DEFAULT NULL,
  `whatsapp_api_url` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `saloni`
--

INSERT INTO `saloni` (`id`, `nome_salone`, `slug`, `giorni_lavoro`, `orario_apertura`, `orario_chiusura`, `iva`, `valuta`, `tema`, `reminder_24h`, `reminder_2h`, `reminder_template_24h`, `reminder_template_2h`, `whatsapp_api_token`, `whatsapp_api_url`) VALUES
(1, 'Glamour Milano', 'glamour-milano', 'lun,mar,mer,gio,ven,sab', '09:00:00', '20:00:00', 22, 'EUR', 'chiaro', 1, 1, NULL, NULL, NULL, NULL),
(2, 'Capelli & Co', 'capelli-e-co', 'mar,mer,gio,ven,sab', '08:00:00', '18:00:00', 22, 'EUR', 'scuro', 1, 1, NULL, NULL, NULL, NULL),
(3, 'ciao blabla', 'ciao-blabla', 'lun,mar,mer,gio,ven,sab', '09:00:00', '19:00:00', 22, 'EUR', 'chiaro', 1, 1, NULL, NULL, NULL, NULL),
(4, 'ciao blabla', 'ciao-blabla-1', 'lun,mar,mer,gio,ven,sab', '09:00:00', '19:00:00', 22, 'EUR', 'chiaro', 1, 1, NULL, NULL, NULL, NULL),
(5, 'ciao bla bla', 'ciao-bla-bla', 'lun,mar,mer,gio,ven,sab', '09:00:00', '19:00:00', 22, 'EUR', 'chiaro', 1, 1, NULL, NULL, NULL, NULL),
(7, 'jessimiao', 'jessimiao', 'lun,mar,mer,gio,ven,sab', '09:00:00', '19:00:00', 22, 'EUR', 'chiaro', 1, 1, NULL, NULL, NULL, NULL),
(8, 'JESSI', 'jessi', 'lun,mar,mer,gio,ven,sab', '09:00:00', '19:00:00', 22, 'EUR', 'chiaro', 1, 1, NULL, NULL, NULL, NULL),
(9, 'nicofrocio', 'nicofrocio', 'lun,mar,mer,gio,ven,sab', '09:00:00', '19:00:00', 22, 'EUR', 'chiaro', 1, 1, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Struttura della tabella `servizi`
--

CREATE TABLE `servizi` (
  `id` int(11) NOT NULL,
  `salone_id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `categoria` varchar(60) DEFAULT NULL,
  `durata_minuti` int(11) DEFAULT 60,
  `prezzo` decimal(8,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `servizi`
--

INSERT INTO `servizi` (`id`, `salone_id`, `nome`, `categoria`, `durata_minuti`, `prezzo`) VALUES
(1, 1, 'Taglio donna', 'capelli', 45, 35.00),
(2, 1, 'Taglio uomo', 'capelli', 30, 20.00),
(3, 1, 'Piega', 'capelli', 30, 25.00),
(4, 1, 'Colorazione completa', 'capelli', 120, 75.00),
(5, 1, 'Meches', 'capelli', 90, 65.00),
(6, 1, 'Manicure', 'unghie', 45, 28.00),
(7, 1, 'Pedicure', 'unghie', 60, 35.00),
(8, 1, 'Manicure gel', 'unghie', 60, 40.00),
(9, 1, 'Ceretta gambe', 'corpo', 45, 30.00),
(10, 1, 'Pulizia viso', 'viso', 60, 45.00),
(11, 2, 'Taglio donna', 'capelli', 50, 30.00),
(12, 2, 'Taglio uomo', 'capelli', 25, 18.00),
(13, 2, 'Piega', 'capelli', 30, 22.00),
(14, 2, 'Colorazione', 'capelli', 100, 65.00),
(15, 2, 'Trattamento cheratina', 'capelli', 120, 90.00),
(16, 2, 'Manicure classica', 'unghie', 40, 22.00),
(17, 2, 'Manicure semipermanente', 'unghie', 60, 35.00),
(18, 2, 'Pedicure', 'unghie', 55, 28.00),
(19, 2, 'Ceretta inguine', 'corpo', 30, 25.00),
(20, 2, 'Massaggio rilassante', 'corpo', 60, 55.00),
(21, 5, 'Taglio Executive', 'capelli', 45, 40.00),
(22, 5, 'Barba & Relax', 'barba', 30, 25.00),
(23, 5, 'Trattamento Viso', 'estetica', 60, 55.00);

-- --------------------------------------------------------

--
-- Struttura della tabella `userdata`
--

CREATE TABLE `userdata` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `salone_id` int(11) NOT NULL DEFAULT 0,
  `nome` varchar(100) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `userdata`
--

INSERT INTO `userdata` (`id`, `username`, `password`, `salone_id`, `nome`, `email`) VALUES
(1, 'glamour_owner', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NULL, NULL),
(2, 'glamour_sara', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NULL, NULL),
(3, 'glamour_lucia', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NULL, NULL),
(4, 'capelli_owner', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, NULL, NULL),
(5, 'capelli_marco', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, NULL, NULL),
(6, 'capelli_elena', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, NULL, NULL),
(7, '123', '$2y$10$TndLzX5syXdAIRzIjt7kk.GOvlBBjBq8cTrGJhUU9.F/R7fmaXs4S', 3, NULL, NULL),
(8, 'ciao1', '$2y$10$soYOw/MijO42Zzz2pV6IVOtFmhZ6e1mw2lE.LDYQFQGAFWe3LhHWm', 4, NULL, NULL),
(9, 'ciao', '$2y$10$i3IiZdv8waIrGKztZaEaBu.Pvx3M7CItX5LkMe9ytRufKS6BO6eu6', 5, NULL, NULL),
(10, 'admin_salone5', '$2a$12$eKhOlKqBFEBVL8dCxHjiEedcjTKsK3A2c/dhVo9BV7zrr7igG8Ngi', 5, NULL, NULL),
(11, 'staff_salone5', '$2a$12$eKhOlKqBFEBVL8dCxHjiEedcjTKsK3A2c/dhVo9BV7zrr7igG8Ngi', 5, NULL, NULL),
(13, 'miao', '$2y$10$cC7CjVhjHXxq6oXAOGoGDuswX71p/wQtuLx7HbxeQjWu9gawYfHgy', 7, NULL, NULL),
(14, 'miao2@gmail.com', '$2y$10$D.PmhLeogGbBd/A4o.SZHOm4rT6xCBV6j6BLKcelJlSsg9mAnAb2e', 7, 'miao2', 'miao2@gmail.com'),
(15, 'miaomiao', '$2y$10$f8JaQuUAWGMkJYwoT61Eqes5kcAi9LFjtNHkufnL0QdXvMFpfLt.a', 8, NULL, NULL),
(16, 'jessimia@gmail.com', '$2y$10$LrP27f6e2rouzwK8.mvadOEe.89t9qFw46oiSvXz1KDtL0wPgnfb2', 8, 'jessi', 'jessimia@gmail.com'),
(17, 'nico', '$2y$10$W4VbQgBwErA9hbDd19PshuGuEIWrPVFPdEDf0Y0jFmllh16.Rdr9m', 9, NULL, NULL),
(18, 'simo@gmail.com', '$2y$10$uuZbs7PDtsSu3WJIaJZBJOJqsw9s7c8Bz3G.aLJh7PWiLBYMmhhEy', 9, 'simo', 'simo@gmail.com');

-- --------------------------------------------------------

--
-- Struttura della tabella `user_saloni`
--

CREATE TABLE `user_saloni` (
  `user_id` int(11) NOT NULL,
  `salone_id` int(11) NOT NULL,
  `ruolo` enum('proprietario','dipendente') NOT NULL DEFAULT 'dipendente',
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `user_saloni`
--

INSERT INTO `user_saloni` (`user_id`, `salone_id`, `ruolo`, `attivo`, `created_at`) VALUES
(1, 1, 'proprietario', 1, '2026-03-13 09:47:46'),
(2, 1, '', 1, '2026-03-13 09:47:46'),
(3, 1, '', 1, '2026-03-13 09:47:46'),
(4, 2, 'proprietario', 1, '2026-03-13 09:47:46'),
(5, 2, '', 1, '2026-03-13 09:47:46'),
(6, 2, '', 1, '2026-03-13 09:47:46'),
(7, 3, 'proprietario', 1, '2026-03-13 09:52:56'),
(8, 4, 'proprietario', 1, '2026-03-13 09:53:10'),
(9, 5, 'proprietario', 1, '2026-03-13 09:53:35'),
(10, 5, 'proprietario', 1, '2026-03-13 09:55:34'),
(11, 5, '', 1, '2026-03-13 09:55:34'),
(13, 7, 'proprietario', 1, '2026-03-18 12:13:20'),
(14, 7, 'dipendente', 1, '2026-03-18 12:14:00'),
(15, 8, 'proprietario', 1, '2026-03-18 12:18:19'),
(16, 8, 'dipendente', 0, '2026-03-18 12:19:14'),
(17, 9, 'proprietario', 1, '2026-03-20 06:16:52'),
(18, 9, 'dipendente', 1, '2026-03-20 06:17:40');

-- --------------------------------------------------------

--
-- Struttura della tabella `whatsapp_log`
--

CREATE TABLE `whatsapp_log` (
  `id` int(11) NOT NULL,
  `salone_id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `chat_id` varchar(60) DEFAULT NULL,
  `messaggio` text DEFAULT NULL,
  `tipo` enum('reminder','broadcast','coupon','manuale') DEFAULT 'manuale',
  `stato` enum('inviato','errore','in_coda') DEFAULT 'in_coda',
  `inviato_il` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `appuntamenti`
--
ALTER TABLE `appuntamenti`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `servizio_id` (`servizio_id`),
  ADD KEY `idx_appuntamenti_sal` (`salone_id`);

--
-- Indici per le tabelle `clienti`
--
ALTER TABLE `clienti`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_clienti_sal` (`salone_id`);

--
-- Indici per le tabelle `impostazioni`
--
ALTER TABLE `impostazioni`
  ADD PRIMARY KEY (`chiave`,`salone_id`),
  ADD UNIQUE KEY `uq_salone_chiave` (`salone_id`,`chiave`);

--
-- Indici per le tabelle `magazzino`
--
ALTER TABLE `magazzino`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_magazzino_sal` (`salone_id`);

--
-- Indici per le tabelle `saloni`
--
ALTER TABLE `saloni`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_slug` (`slug`);

--
-- Indici per le tabelle `servizi`
--
ALTER TABLE `servizi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_servizi_sal` (`salone_id`);

--
-- Indici per le tabelle `userdata`
--
ALTER TABLE `userdata`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `uq_userdata_email` (`email`),
  ADD KEY `fk_userdata_salone` (`salone_id`),
  ADD KEY `idx_userdata_email_username` (`email`,`username`);

--
-- Indici per le tabelle `user_saloni`
--
ALTER TABLE `user_saloni`
  ADD PRIMARY KEY (`user_id`,`salone_id`),
  ADD KEY `idx_user_saloni_salone_ruolo_attivo` (`salone_id`,`ruolo`,`attivo`);

--
-- Indici per le tabelle `whatsapp_log`
--
ALTER TABLE `whatsapp_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `idx_wlog_sal` (`salone_id`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `appuntamenti`
--
ALTER TABLE `appuntamenti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT per la tabella `clienti`
--
ALTER TABLE `clienti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT per la tabella `magazzino`
--
ALTER TABLE `magazzino`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT per la tabella `saloni`
--
ALTER TABLE `saloni`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT per la tabella `servizi`
--
ALTER TABLE `servizi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT per la tabella `userdata`
--
ALTER TABLE `userdata`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT per la tabella `whatsapp_log`
--
ALTER TABLE `whatsapp_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Limiti per le tabelle scaricate
--

--
-- Limiti per la tabella `appuntamenti`
--
ALTER TABLE `appuntamenti`
  ADD CONSTRAINT `appuntamenti_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clienti` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appuntamenti_ibfk_2` FOREIGN KEY (`servizio_id`) REFERENCES `servizi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_appuntamenti_salone` FOREIGN KEY (`salone_id`) REFERENCES `saloni` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `clienti`
--
ALTER TABLE `clienti`
  ADD CONSTRAINT `fk_clienti_salone` FOREIGN KEY (`salone_id`) REFERENCES `saloni` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `impostazioni`
--
ALTER TABLE `impostazioni`
  ADD CONSTRAINT `fk_impostazioni_salone` FOREIGN KEY (`salone_id`) REFERENCES `saloni` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `magazzino`
--
ALTER TABLE `magazzino`
  ADD CONSTRAINT `fk_magazzino_salone` FOREIGN KEY (`salone_id`) REFERENCES `saloni` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `servizi`
--
ALTER TABLE `servizi`
  ADD CONSTRAINT `fk_servizi_salone` FOREIGN KEY (`salone_id`) REFERENCES `saloni` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `userdata`
--
ALTER TABLE `userdata`
  ADD CONSTRAINT `fk_userdata_salone` FOREIGN KEY (`salone_id`) REFERENCES `saloni` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `user_saloni`
--
ALTER TABLE `user_saloni`
  ADD CONSTRAINT `fk_pivot_salone` FOREIGN KEY (`salone_id`) REFERENCES `saloni` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pivot_user` FOREIGN KEY (`user_id`) REFERENCES `userdata` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `whatsapp_log`
--
ALTER TABLE `whatsapp_log`
  ADD CONSTRAINT `fk_whatsapp_salone` FOREIGN KEY (`salone_id`) REFERENCES `saloni` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `whatsapp_log_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clienti` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
