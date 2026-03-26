-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 26, 2026 at 06:58 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

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
-- Table structure for table `password_reset_requests`
--

CREATE TABLE `password_reset_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `reset_token` varchar(64) NOT NULL,
  `current_code` varchar(6) NOT NULL,
  `code_expires_at` datetime NOT NULL,
  `request_expires_at` datetime NOT NULL,
  `status` enum('attiva','completata','scaduta','annullata') NOT NULL DEFAULT 'attiva',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_reset_requests`
--

INSERT INTO `password_reset_requests` (`id`, `user_id`, `reset_token`, `current_code`, `code_expires_at`, `request_expires_at`, `status`, `created_at`, `updated_at`, `used_at`) VALUES
(1, 9, '790974a765424161b126e76fd5c363793dab24a6877274ee67f9a1bb2c47b332', '947348', '2026-03-26 17:51:54', '2026-03-26 18:06:24', 'completata', '2026-03-26 17:51:24', '2026-03-26 17:51:42', '2026-03-26 17:51:42'),
(2, 9, '6d1760181857230fec6ca97a291e99df26ff97bdab670983ebb64eaa76156ef5', '698808', '2026-03-26 17:52:33', '2026-03-26 18:07:03', 'annullata', '2026-03-26 17:52:03', '2026-03-26 17:53:21', NULL),
(3, 9, 'e276e44ca9f9aa01e107e21be5b6f36480e23f6e45df0603faa552376daf44f4', '853048', '2026-03-26 17:53:51', '2026-03-26 18:08:21', 'annullata', '2026-03-26 17:53:21', '2026-03-26 17:53:25', NULL),
(4, 9, 'd152b564818f95603fe37252200c879713fc5399dfaec5d6d1ffe6814e31b872', '962579', '2026-03-26 18:04:06', '2026-03-26 18:08:25', 'attiva', '2026-03-26 17:53:25', '2026-03-26 18:03:36', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_password_reset_token` (`reset_token`),
  ADD KEY `ix_password_reset_user_status` (`user_id`,`status`),
  ADD KEY `ix_password_reset_request_expires` (`request_expires_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  ADD CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `userdata` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
