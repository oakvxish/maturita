-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Creato il: Mar 18, 2026 alle 13:50
-- Versione del server: 10.4.27-MariaDB
-- Versione PHP: 8.0.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `salone`
--

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
  `orario_chiusura` time DEFAULT '19:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `saloni`
--

INSERT INTO `saloni` (`id`, `nome_salone`, `slug`, `giorni_lavoro`, `orario_apertura`, `orario_chiusura`) VALUES
(1, 'Glamour Milano', 'glamour-milano', 'lun,mar,mer,gio,ven,sab', '09:00:00', '20:00:00'),
(2, 'Capelli & Co', 'capelli-e-co', 'mar,mer,gio,ven,sab', '08:00:00', '18:00:00'),
(3, 'ciao blabla', 'ciao-blabla', 'lun,mar,mer,gio,ven,sab', '09:00:00', '19:00:00'),
(4, 'ciao blabla', 'ciao-blabla-1', 'lun,mar,mer,gio,ven,sab', '09:00:00', '19:00:00'),
(5, 'ciao bla bla', 'ciao-bla-bla', 'lun,mar,mer,gio,ven,sab', '09:00:00', '19:00:00');

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `saloni`
--
ALTER TABLE `saloni`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_slug` (`slug`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `saloni`
--
ALTER TABLE `saloni`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
