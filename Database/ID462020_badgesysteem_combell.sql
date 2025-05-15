-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: com-linweb985.srv.combell-ops.net:3306
-- Gegenereerd op: 15 mei 2025 om 11:53
-- Serverversie: 8.0.36-28
-- PHP-versie: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ID462020_badgesysteem`
--

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `bouwvolume`
--

CREATE TABLE `bouwvolume` (
  `id` int NOT NULL,
  `lengte` int DEFAULT NULL,
  `breedte` int DEFAULT NULL,
  `hoogte` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Gegevens worden geëxporteerd voor tabel `bouwvolume`
--

INSERT INTO `bouwvolume` (`id`, `lengte`, `breedte`, `hoogte`) VALUES
(1, 220, 220, 250),
(2, 200, 200, 300);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Filament`
--

CREATE TABLE `Filament` (
  `id` int NOT NULL,
  `Type` enum('PLA','ABS','PETG','TPU','Nylon') DEFAULT NULL,
  `Kleur` enum('rood','blauw','groen','zwart','wit','geel','transparant') DEFAULT NULL,
  `voorraad` int NOT NULL,
  `diameter` enum('1.75','2.85') DEFAULT '1.75'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Gegevens worden geëxporteerd voor tabel `Filament`
--

INSERT INTO `Filament` (`id`, `Type`, `Kleur`, `voorraad`, `diameter`) VALUES
(1, 'PLA', 'rood', 1000, '1.75'),
(2, 'PLA', 'blauw', 1000, '1.75'),
(3, 'PLA', 'groen', 500, '1.75'),
(4, 'PLA', 'zwart', 300, '1.75'),
(5, 'PLA', 'wit', 50, '1.75'),
(6, 'PLA', 'geel', 400, '1.75'),
(7, 'PLA', 'transparant', 0, '1.75'),
(8, 'PETG', 'rood', 0, '1.75'),
(9, 'PETG', 'blauw', 0, '1.75'),
(10, 'PETG', 'groen', 0, '1.75'),
(11, 'PETG', 'zwart', 0, '1.75'),
(12, 'PETG', 'wit', 0, '1.75');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Filament_compatibiliteit`
--

CREATE TABLE `Filament_compatibiliteit` (
  `printer_id` int NOT NULL,
  `filament_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Gegevens worden geëxporteerd voor tabel `Filament_compatibiliteit`
--

INSERT INTO `Filament_compatibiliteit` (`printer_id`, `filament_id`) VALUES
(1, 1),
(2, 1),
(3, 1),
(2, 2),
(2, 3),
(2, 4),
(4, 4),
(2, 5),
(4, 5),
(2, 6),
(4, 6),
(2, 7),
(4, 7),
(1, 8),
(2, 8),
(3, 8),
(4, 8),
(2, 9),
(2, 10),
(2, 11),
(2, 12);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `kostenbewijzing_onderzoekers`
--

CREATE TABLE `kostenbewijzing_onderzoekers` (
  `reservatie_id` int NOT NULL,
  `onderzoeksproject` varchar(255) DEFAULT NULL,
  `kostenpost` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `kostenbewijzing_studenten`
--

CREATE TABLE `kostenbewijzing_studenten` (
  `reservatie_id` int NOT NULL,
  `OPO_id` int DEFAULT NULL,
  `eigen_rekening` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Lokalen`
--

CREATE TABLE `Lokalen` (
  `id` int NOT NULL,
  `Locatie` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Gegevens worden geëxporteerd voor tabel `Lokalen`
--

INSERT INTO `Lokalen` (`id`, `Locatie`) VALUES
(0, '3D printerlokaal');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Onderzoeker_Goedkeuring`
--

CREATE TABLE `Onderzoeker_Goedkeuring` (
  `User_ID` int NOT NULL,
  `Goedgekeurd` tinyint(1) DEFAULT '0',
  `AanvraagDatum` datetime DEFAULT NULL,
  `GoedkeuringsDatum` datetime DEFAULT NULL,
  `GoedgekeurdDoor` int DEFAULT NULL,
  `Goedkeuringstoken` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Gegevens worden geëxporteerd voor tabel `Onderzoeker_Goedkeuring`
--

INSERT INTO `Onderzoeker_Goedkeuring` (`User_ID`, `Goedgekeurd`, `AanvraagDatum`, `GoedkeuringsDatum`, `GoedgekeurdDoor`, `Goedkeuringstoken`) VALUES
(6, 1, '2025-04-03 15:39:27', '2025-04-03 16:05:02', NULL, '6293e2d7146b2bbda132dec92907091722100c4d2c00e912b365003527534074'),
(7, 1, '2025-04-03 16:06:21', '2025-04-03 16:07:50', NULL, 'd1308f80e1600ec90908e9e9da1428208719577ec7bd58771fff3e09c901d52b'),
(9, 1, '2025-04-24 16:24:15', '2025-05-07 14:42:41', NULL, '1bae29c589d07fde66105010d3983cdaf9654b71373713e426161f023eed0d47'),
(11, 0, '2025-05-12 15:17:14', NULL, NULL, 'c3c0499b7d9f7c17853ec56023262888d5c315eada5d2a2e54c01ae9f7d7f2bd'),
(13, 1, '2025-05-12 19:00:15', '2025-05-12 20:36:47', NULL, '1edba4f1670818169cf807a960031022a8ba35fbf739fe2d757da90235b0ad78');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Openingsuren`
--

CREATE TABLE `Openingsuren` (
  `id` int NOT NULL,
  `Lokaal_id` int DEFAULT NULL,
  `Tijdstip_start` datetime DEFAULT NULL,
  `Tijdstip_einde` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Gegevens worden geëxporteerd voor tabel `Openingsuren`
--

INSERT INTO `Openingsuren` (`id`, `Lokaal_id`, `Tijdstip_start`, `Tijdstip_einde`) VALUES
(1, 0, '2025-04-07 13:00:00', '2025-04-07 18:00:00'),
(2, 0, '2025-04-28 13:00:00', '2025-04-28 18:00:00'),
(3, 0, '2025-04-24 13:26:00', '2025-04-24 18:26:00'),
(4, 0, '2025-05-07 12:27:00', '2025-05-07 17:27:00'),
(5, 0, '2025-05-07 12:00:00', '2025-05-07 17:00:00'),
(6, 0, '2025-05-08 12:00:00', '2025-05-08 17:00:00'),
(7, 0, '2025-05-20 12:00:00', '2025-05-20 17:00:00'),
(8, 0, '2025-05-12 11:31:00', '2025-05-12 20:31:00'),
(10, 0, '2025-05-14 08:00:00', '2025-05-14 23:00:00'),
(11, 0, '2025-05-15 08:00:00', '2025-05-15 23:00:00');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `opleidingen`
--

CREATE TABLE `opleidingen` (
  `id` int NOT NULL,
  `naam` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Gegevens worden geëxporteerd voor tabel `opleidingen`
--

INSERT INTO `opleidingen` (`id`, `naam`) VALUES
(1, 'ELO-ICT'),
(2, 'Ontwerp- en productietechnology'),
(3, 'MEB');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `OPOs`
--

CREATE TABLE `OPOs` (
  `id` int NOT NULL,
  `opleiding_id` int DEFAULT NULL,
  `naam` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Gegevens worden geëxporteerd voor tabel `OPOs`
--

INSERT INTO `OPOs` (`id`, `opleiding_id`, `naam`) VALUES
(0, 1, 'Test');

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Printer`
--

CREATE TABLE `Printer` (
  `Printer_ID` int NOT NULL,
  `Status` enum('beschikbaar','in_gebruik','onderhoud','defect') DEFAULT NULL,
  `LAATSTE_STATUS_CHANGE` datetime DEFAULT NULL,
  `netwerkadres` varchar(255) DEFAULT NULL,
  `Versie_Toestel` varchar(100) DEFAULT NULL,
  `Software` varchar(100) DEFAULT NULL,
  `Datadrager` enum('SD','USB','WIFI') DEFAULT NULL,
  `Bouwvolume_id` int DEFAULT NULL,
  `Opmerkingen` text,
  `foto` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Gegevens worden geëxporteerd voor tabel `Printer`
--

INSERT INTO `Printer` (`Printer_ID`, `Status`, `LAATSTE_STATUS_CHANGE`, `netwerkadres`, `Versie_Toestel`, `Software`, `Datadrager`, `Bouwvolume_id`, `Opmerkingen`, `foto`) VALUES
(1, 'beschikbaar', '2025-05-08 16:59:50', '192.168.189.223', 'Ender3 V3', 'Cura', 'SD', 1, 'FDM printer', 'assets/images/ender3v3.png'),
(2, 'beschikbaar', '2025-05-13 14:15:28', '192.168.189.141\n', 'Ender3 V2 (bowden drive)', 'Cura', 'SD', 1, 'FDM printer', 'assets/images/ender3v2.png'),
(3, 'beschikbaar', '2025-05-12 15:45:27', '192.168.189.134', 'Ender3 pro', 'Cura', 'SD', 1, 'FDM printer', 'assets/images/ender3pro.png'),
(4, 'defect', '2025-05-15 11:01:57', '', 'Shelly Test', 'Geen', 'WIFI', 1, '', NULL);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Reservatie`
--

CREATE TABLE `Reservatie` (
  `Reservatie_ID` int NOT NULL,
  `User_ID` int DEFAULT NULL,
  `Printer_ID` int DEFAULT NULL,
  `DATE_TIME_RESERVATIE` datetime DEFAULT NULL,
  `PRINT_START` datetime DEFAULT NULL,
  `PRINT_END` datetime DEFAULT NULL,
  `Comment` text,
  `Pincode` varchar(10) DEFAULT NULL,
  `filament_id` int DEFAULT NULL,
  `verbruik` float DEFAULT NULL,
  `HulpNodig` tinyint NOT NULL,
  `BeheerderPrinten` tinyint NOT NULL,
  `print_started` tinyint(1) DEFAULT '0',
  `print_start_time` datetime DEFAULT NULL,
  `print_completed` tinyint(1) DEFAULT '0',
  `print_end_time` datetime DEFAULT NULL,
  `last_update` datetime DEFAULT NULL,
  `Onderzoeksproject` varchar(255) DEFAULT NULL,
  `Kostenpost` varchar(255) DEFAULT NULL,
  `OPO_id` int DEFAULT NULL,
  `EigenRekening` tinyint(1) DEFAULT '0',
  `FilamentUnit` enum('gram','meter') DEFAULT 'gram',
  `feedback_gegeven` tinyint(1) DEFAULT '0',
  `feedback_tekst` text,
  `feedback_print_kwaliteit` int DEFAULT NULL,
  `feedback_gebruiksgemak` int DEFAULT NULL,
  `feedback_datum` datetime DEFAULT NULL,
  `feedback_token` varchar(100) DEFAULT NULL,
  `feedback_mail_verzonden` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Gegevens worden geëxporteerd voor tabel `Reservatie`
--

INSERT INTO `Reservatie` (`Reservatie_ID`, `User_ID`, `Printer_ID`, `DATE_TIME_RESERVATIE`, `PRINT_START`, `PRINT_END`, `Comment`, `Pincode`, `filament_id`, `verbruik`, `HulpNodig`, `BeheerderPrinten`, `print_started`, `print_start_time`, `print_completed`, `print_end_time`, `last_update`, `Onderzoeksproject`, `Kostenpost`, `OPO_id`, `EigenRekening`, `FilamentUnit`, `feedback_gegeven`, `feedback_tekst`, `feedback_print_kwaliteit`, `feedback_gebruiksgemak`, `feedback_datum`, `feedback_token`, `feedback_mail_verzonden`) VALUES
(1, 1, 1, '2025-03-26 21:34:12', '2025-03-27 10:00:00', '2025-03-27 16:30:00', 'dit is een test', '591511', 1, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(2, 1, 3, '2025-03-27 16:35:24', '2025-03-28 09:00:00', '2025-03-28 10:00:00', 'TEstststs', '3658', 6, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(3, 3, 1, '2025-03-30 09:27:09', '2025-03-30 09:00:00', '2025-03-30 10:00:00', '', '4242', NULL, NULL, 0, 0, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 1, 'Test', 5, 5, '2025-05-15 11:51:07', 'f2fdf5b8d47355bc4bc7f4143686275a', 1),
(4, 3, 3, '2025-04-03 08:50:28', '2025-04-03 09:00:00', '2025-04-03 10:00:00', 'dag wout krijg jij deze mail?', '1574', NULL, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(5, 3, 2, '2025-04-03 11:31:26', '2025-04-03 09:00:00', '2025-04-03 10:00:00', '<img src=\"https://source.unsplash.com/random\" alt=\"Willekeurige Foto\">', '3852', NULL, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(6, 1, 1, '2025-04-03 11:38:51', '2025-04-03 12:00:00', '2025-04-03 13:00:00', 'test', '172112', 1, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(7, 1, 3, '2025-04-03 11:40:05', '2025-04-03 15:00:00', '2025-04-03 15:30:00', 'test', '2831', 5, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(8, 1, 3, '2025-04-03 11:43:24', '2025-04-03 16:00:00', '2025-04-03 16:30:00', 'test', '1840', 4, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(9, 1, 2, '2025-04-03 11:50:39', '2025-04-04 08:30:00', '2025-04-04 10:00:00', 'test', '1912', 4, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(10, 3, 1, '2025-04-03 11:51:17', '2025-04-03 09:00:00', '2025-04-03 10:00:00', 'Test', '5568', NULL, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(12, 1, 3, '2025-04-03 12:06:42', '2025-04-03 12:08:00', '2025-04-03 14:30:00', 'test', '197911', 3, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(14, 4, 2, '2025-04-03 12:22:47', '2025-04-03 12:00:00', '2025-04-03 20:00:00', '', '265643', NULL, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(15, 4, 3, '2025-04-03 12:26:20', '2025-04-15 09:00:00', '2025-04-15 10:00:00', '', '707616', 6, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(16, 4, 1, '2025-04-03 14:33:50', '2025-04-18 09:00:00', '2025-04-23 23:00:00', '', '111111', 2, NULL, 0, 0, 1, '2025-04-23 21:30:33', 1, '2025-04-24 08:32:53', '2025-04-24 08:32:53', NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(17, 3, 1, '2025-04-03 14:35:10', '2025-04-03 14:37:00', '2025-04-03 15:07:00', '', '125295', NULL, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(18, 5, 3, '2025-04-03 14:38:45', '2025-04-19 09:00:00', '2025-04-19 10:00:00', '', '958115', NULL, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(19, 2, 3, '2025-04-03 15:24:39', '2025-04-23 09:00:00', '2025-04-23 10:00:00', '', '123456', 5, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(20, 4, 1, '2025-04-03 17:03:45', '2025-04-05 09:00:00', '2025-04-05 10:00:00', '', '216038', NULL, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(21, 1, 3, '2025-04-05 13:26:59', '2025-04-05 13:30:00', '2025-04-05 16:00:00', 'testen mailing', '174323', 5, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(22, 1, 2, '2025-04-05 13:46:15', '2025-04-05 13:45:00', '2025-04-05 14:00:00', 'test hulp bij het printen', '721436', 2, NULL, 1, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(23, 1, 1, '2025-04-05 15:39:06', '2025-04-05 15:45:00', '2025-04-05 16:00:00', 'gelieve dit te printen', '865259', 6, NULL, 0, 1, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(24, 1, 3, '2025-04-05 16:15:32', '2025-04-05 16:15:00', '2025-04-05 16:30:00', 'kan u dit printen?', '320296', 3, NULL, 0, 1, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(25, 4, 3, '2025-04-24 09:33:50', '2025-04-24 09:30:00', '2025-04-24 09:40:00', '', '111112', 10, NULL, 0, 0, 1, '2025-04-24 09:38:22', 1, '2025-04-24 09:51:44', '2025-04-24 09:51:44', NULL, NULL, NULL, 0, 'gram', 1, 'TOP!', 5, 5, '2025-05-15 09:17:11', '643bbde499b0339df205996b23988010', 1),
(26, 4, 3, '2025-05-03 14:33:50', '2025-09-23 09:00:00', '2025-10-30 10:00:00', '', '111113', 10, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(27, 2, 1, '2025-04-19 12:43:33', '2025-04-19 12:00:00', '2025-04-19 15:00:00', 'alexa dit is een test', '582308', 2, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(28, 4, 2, '2025-04-23 19:26:13', '2025-04-23 19:00:00', '2025-04-23 21:38:00', '', '795197', 6, NULL, 0, 0, 1, '2025-04-23 21:31:02', 1, '2025-04-23 21:41:36', '2025-04-23 21:41:36', NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(29, 4, 1, '2025-04-23 19:28:13', '2025-04-23 20:08:00', '2025-04-23 20:28:00', '', '795198', 6, NULL, 0, 0, 1, '2025-04-23 20:27:01', 1, '2025-04-23 20:28:33', '2025-04-23 20:28:33', NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(30, 4, 1, '2025-04-23 21:00:40', '2025-04-24 08:00:00', '2025-04-24 09:40:00', '', '159368', NULL, NULL, 0, 0, 1, '2025-04-24 09:31:08', 1, '2025-04-24 09:40:03', '2025-04-24 09:40:03', NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(31, 3, 2, '2025-04-24 08:42:23', '2025-04-24 08:00:00', '2025-04-24 10:00:00', '', '045058', 3, NULL, 0, 0, 1, '2025-04-24 08:51:31', 1, '2025-04-24 11:39:26', '2025-04-24 11:39:26', NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(32, 1, 3, '2025-04-24 09:55:33', '2025-04-24 10:00:00', '2025-04-24 12:00:00', 'test', '725043', 6, NULL, 0, 0, 1, '2025-04-24 10:21:21', 1, '2025-04-24 12:06:56', '2025-04-24 12:06:56', NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(33, 3, 1, '2025-04-24 12:37:03', '2025-04-26 09:00:00', '2025-04-26 10:00:00', '', '086071', NULL, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(34, 1, 2, '2025-04-24 13:19:06', '2025-04-24 12:00:00', '2025-04-24 13:25:00', '', '762233', 5, NULL, 0, 0, 1, '2025-04-24 13:19:38', 1, '2025-04-24 13:25:43', '2025-04-24 13:25:43', NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(35, 4, 3, '2025-04-24 14:02:00', '2025-04-24 14:00:00', '2025-04-24 20:00:00', '', '745449', 4, NULL, 0, 0, 1, '2025-04-24 16:31:04', 1, '2025-05-08 11:42:32', '2025-05-08 11:42:32', NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(36, 1, 1, '2025-04-24 14:49:39', '2025-04-24 14:50:00', '2025-04-24 16:00:00', '', '027450', 5, NULL, 0, 0, 1, '2025-04-24 14:50:30', 1, '2025-04-24 16:10:29', '2025-04-24 16:10:29', NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(37, 10, 2, '2025-05-06 17:54:12', '2025-05-07 09:00:00', '2025-05-07 10:00:00', '', '459764', NULL, NULL, 1, 1, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(38, 1, 2, '2025-05-08 09:10:18', '2025-05-08 14:00:00', '2025-05-08 15:00:00', '', '731466', 4, 100, 0, 0, 0, NULL, 0, NULL, NULL, '', '', NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(39, 1, 3, '2025-05-08 09:12:15', '2025-05-08 14:00:00', '2025-05-08 16:00:00', '', '213614', 6, 100, 0, 0, 1, '2025-05-08 14:11:44', 1, '2025-05-08 16:25:52', '2025-05-08 16:25:52', '', '', NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(40, 1, 1, '2025-05-08 11:11:51', '2025-05-08 12:30:00', '2025-05-08 13:33:00', '', '109331', 4, 111, 0, 0, 0, NULL, 0, NULL, NULL, '', '', NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(41, 1, 3, '2025-05-08 17:44:23', '2025-05-20 13:00:00', '2025-05-20 20:42:00', '', '734085', NULL, 0, 0, 0, 0, NULL, 0, NULL, NULL, '', '', NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(42, 4, 2, '2025-05-12 11:26:55', '2025-05-12 11:29:00', '2025-05-12 15:47:00', '', '526356', NULL, 0, 1, 0, 1, '2025-05-12 12:34:30', 1, '2025-05-13 19:51:36', '2025-05-13 19:51:36', '', '', NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(43, 1, 2, '2025-05-12 11:29:56', '2025-05-20 12:00:00', '2025-05-20 12:16:30', '', '685252', 6, 50, 0, 0, 0, NULL, 0, NULL, NULL, '', '', NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(44, 4, 1, '2025-05-12 11:30:17', '2025-05-13 13:00:00', '2025-05-13 23:06:00', '', '691857', 12, 0, 0, 0, 0, NULL, 1, NULL, NULL, '', '', NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, 'a6eeb3f7c952d121dc38c735ef5f3c6d', 1),
(45, 1, 3, '2025-05-12 11:47:52', '2025-05-12 12:00:00', '2025-05-12 13:06:00', '', '017813', 5, 0, 1, 0, 0, NULL, 0, NULL, NULL, '', '', NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(46, 7, 3, '2025-05-12 12:18:21', '2025-05-12 14:00:00', '2025-05-12 15:06:00', '', '300044', NULL, 0, 1, 0, 0, NULL, 0, NULL, NULL, 'test', 'test', NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(47, 12, 3, '2025-05-12 15:42:07', '2025-05-14 14:20:00', '2025-05-14 17:47:54', NULL, '557902', 6, 420, 1, 1, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 1, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(48, 2, 3, '2025-05-12 15:45:47', '2025-05-12 17:00:00', '2025-05-12 18:06:00', '', '860512', 8, 100, 1, 0, 0, NULL, 0, NULL, NULL, 'project', 'test', NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(49, 1, 1, '2025-05-12 15:46:05', '2025-05-12 17:00:00', '2025-05-12 18:06:00', '', '459503', 4, 100, 0, 0, 0, NULL, 0, NULL, NULL, '', '', NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(50, 2, 2, '2025-05-12 15:46:37', '2025-05-12 17:00:00', '2025-05-12 18:06:00', '', '687366', 5, 100, 1, 0, 0, NULL, 0, NULL, NULL, '', '', NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(51, 1, 3, '2025-05-13 11:24:50', '2025-05-13 14:00:00', '2025-05-13 15:06:00', '', '091776', NULL, 0, 0, 0, 0, NULL, 1, NULL, NULL, '', '', NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, 'f6415e483e2cc5c181956bfbba8ef4eb', 1),
(52, 1, 3, '2025-05-13 11:25:46', '2025-05-13 12:30:00', '2025-05-13 13:36:00', '', '424639', 4, 10, 0, 0, 0, NULL, 1, NULL, NULL, '', '', NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, 'e12962d05efbb0472f9902f9dcf67c34', 1),
(53, 1, 3, '2025-05-13 11:27:53', '2025-05-13 11:29:00', '2025-05-13 11:40:00', '', '737260', 7, 100, 0, 0, 0, NULL, 1, NULL, NULL, 'TEST', 'TEST', NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(54, 1, 3, '2025-05-13 11:38:05', '2025-05-13 11:41:00', '2025-05-13 11:52:00', '', '048050', 3, 100, 0, 0, 1, '2025-05-13 18:21:56', 1, NULL, NULL, '', '', 0, 0, 'gram', 0, NULL, NULL, NULL, NULL, '8c7a9b35f2de41c6b8743e76f0912ae47d8e6f3429a51fb8d5c07698ef37921a', 1),
(55, 2, 2, '2025-05-13 14:20:25', '2025-05-20 14:00:00', '2025-05-20 15:39:00', '', '617149', 6, 1000, 1, 0, 0, NULL, 0, NULL, NULL, '', 'test', 0, 0, 'gram', 0, NULL, NULL, NULL, NULL, NULL, 1),
(56, 1, 3, '2025-05-14 20:00:20', '2025-05-14 20:01:00', '2025-05-14 23:15:30', '', '387356', 5, 1.5, 0, 0, 0, '2025-05-14 21:28:42', 1, '2025-05-14 21:16:00', '2025-05-14 21:28:42', '', '', NULL, 0, 'gram', 1, 'Top! Doe zo verder', 5, 5, '2025-05-15 09:17:30', '6caca35496c81eed6f418c87e78c39de', 1),
(57, 4, 3, '2025-05-15 08:51:25', '2025-05-15 08:00:00', '2025-05-15 12:18:00', '', '208408', NULL, 1, 1, 0, 1, '2025-05-15 08:52:55', 1, NULL, '2025-05-15 08:52:55', '', '', NULL, 0, 'gram', 0, NULL, NULL, NULL, NULL, '5d55cb98f7580d05398a548f922e378e', 1);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `User`
--

CREATE TABLE `User` (
  `User_ID` int NOT NULL,
  `Voornaam` varchar(255) DEFAULT NULL,
  `Naam` varchar(255) DEFAULT NULL,
  `Emailadres` varchar(255) DEFAULT NULL,
  `Telefoon` varchar(50) DEFAULT NULL,
  `Wachtwoord` varchar(255) DEFAULT NULL,
  `Type` enum('student','onderzoeker','beheerder','docent') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `AanmaakAccount` datetime DEFAULT NULL,
  `LaatsteAanmelding` datetime DEFAULT NULL,
  `HuidigActief` tinyint(1) DEFAULT NULL,
  `HulpNodig` tinyint NOT NULL DEFAULT '1',
  `Akkoord_Afspraken` tinyint DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Gegevens worden geëxporteerd voor tabel `User`
--

INSERT INTO `User` (`User_ID`, `Voornaam`, `Naam`, `Emailadres`, `Telefoon`, `Wachtwoord`, `Type`, `AanmaakAccount`, `LaatsteAanmelding`, `HuidigActief`, `HulpNodig`, `Akkoord_Afspraken`) VALUES
(1, 'Lars', 'Van De Kerkhove', 'lars.vandekerkhove@student.vives.be', '', '$2y$10$.IraMIEe.OmAc0XMIP5Ntefbcq.QyJFyTeR58NwZ5Vi8YGUIgYPU6', 'beheerder', '2025-03-26 19:18:00', '2025-05-15 08:41:52', 1, 0, NULL),
(2, 'Alexa', '\'t Kindt', 'alexa.tkindt@student.vives.be', '0479308408', '$2y$10$hlPhszw51I1NybAqokDB..ZbOJAfWiWANnmHTLBO8ulX/JnC.IWLS', 'beheerder', '2025-03-26 21:46:01', '2025-05-15 11:00:06', 1, 1, NULL),
(3, 'Wout', 'Libbrecht', 'wout.libbrecht@student.vives.be', '', '$2y$10$BfwQtiV.VG61HAy0ziu0EeL5Qs8jeUkg8NqW9yzu3vEi.rZadYC66', 'beheerder', '2025-03-27 08:41:58', '2025-05-15 08:36:18', 1, 0, NULL),
(4, 'Piotr', 'Narel', 'piotr.narel@student.vives.be', '', '$2y$10$c40GuHyCFYNK2elctSz2ou8qjJlRSsH87L///oP10LUujM/scRiPi', 'beheerder', '2025-03-27 10:24:15', '2025-05-15 08:48:41', 1, 1, NULL),
(5, 'Emiel', 'vandendriessche', 'emiel.vandendriessche@student.vives.be', '', '$2y$10$pliZItol2r/qAeqclKVVieuY0yII6Txena7QRCwQ4yMLI0hl8coXO', 'student', '2025-04-03 14:38:13', '2025-05-14 19:37:06', 0, 1, 1),
(6, 'Lars', 'VDK', 'larsvandekerkhove@gmail.com', '0468137690', '$2y$10$zGEGbHJDMJpMjvUyj7vQfOz0jevlqQV1h9./a8wrAc74TEv8rn1Ky', 'onderzoeker', '2025-04-03 15:39:27', '2025-04-03 16:05:35', 0, 1, NULL),
(7, 'Alexa', '\'t kindt', 'tkindt.alexa@gmail.com', '', '$2y$10$pV6kR1dUgGHNTybhpJdxN.7VtNQ/8RovKB13QI4vQTE06hhVq.92a', 'onderzoeker', '2025-04-03 16:06:21', '2025-05-12 11:59:37', 0, 1, NULL),
(8, 'Piotr', 'Nar', '', '', '', 'student', '0000-00-00 00:00:00', '2025-04-24 17:49:01', 1, 0, NULL),
(9, 'Lars', 'Van De Kerkhove', 'lars.van.de.kerkhove@telenet.be', '', '$2y$10$ds5YJ/Wp.Y2ZUpmpjWcFf.kbO2C64IFSc8M/zqH3r6e3/.R8/0Hf6', 'onderzoeker', '2025-04-24 16:24:15', '2025-05-07 14:42:58', 0, 1, 1),
(10, 'Karolien', 'Vandersickel', 'karolien.vandersickel@vives.be', '', '$2y$10$TJP357XDSwVAJ017bMs9K.MezK/iv3CvKZcMT26e2uQ1Sb..pHiLy', 'beheerder', '2025-05-06 12:26:00', '2025-05-06 12:41:18', 1, 1, 1),
(11, 'Pleen', 'Van opet velt', 'pauline.vandevelde2309@gmail.com', '0468 derest krijg je vannacht', '$2y$10$44M75KjllOxzORkeEdcdsOvNNnwmkB96zKwTgUHTFd.It66/TeVAq', 'onderzoeker', '2025-05-12 15:17:14', '2025-05-12 15:17:14', 1, 1, 1),
(12, 'Spleen', 'Fein Fein Fein', 'pauline.vandevelde@student.vives.be', 'Call me baby', '$2y$10$.g2eSh7vlqhcyBjdoOSu6.DPQr4wzEshGM.hDk/noXu76wZoVOJJe', 'beheerder', '2025-05-12 15:27:44', '2025-05-12 20:03:49', 1, 1, 1),
(13, 'Stefaan', 'Vandevelde', 'stefaan.vandevelde.kortrijk@gmail.com', '0477840364', '$2y$10$IDoLs2NSNLcpZZIs/GdVt.23Ep3zk3nD07E3GNTUOtWMfIGvEu62q', 'beheerder', '2025-05-12 19:00:15', '2025-05-12 19:00:15', 1, 1, 1);

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `Vives`
--

CREATE TABLE `Vives` (
  `User_ID` int NOT NULL,
  `Voornaam` varchar(255) DEFAULT NULL,
  `Vives_id` varchar(50) DEFAULT NULL,
  `opleiding_id` int DEFAULT NULL,
  `Type` enum('student','medewerker','onderzoeker') DEFAULT NULL,
  `rfidkaartnr` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Gegevens worden geëxporteerd voor tabel `Vives`
--

INSERT INTO `Vives` (`User_ID`, `Voornaam`, `Vives_id`, `opleiding_id`, `Type`, `rfidkaartnr`) VALUES
(1, 'Lars', 'R0929963', 1, 'student', '040b2d72727680'),
(3, 'Wout', '', 1, '', 'b30f11da'),
(4, 'piotr2', 'R0990915', 1, '', '5c694305'),
(5, 'Emiel', 'R9999999', 1, 'student', '832f4c99'),
(7, 'Alexa', 'R0981937', 1, 'student', '044f91126a6e80'),
(8, 'Piotr', '', 1, '', '247731a7'),
(10, 'Karolien', 'U0105486', NULL, 'medewerker', '042d1b7a016b80'),
(12, 'Spleen', 'R0999444', 3, 'student', NULL);

--
-- Indexen voor geëxporteerde tabellen
--

--
-- Indexen voor tabel `bouwvolume`
--
ALTER TABLE `bouwvolume`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `Filament`
--
ALTER TABLE `Filament`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `Filament_compatibiliteit`
--
ALTER TABLE `Filament_compatibiliteit`
  ADD PRIMARY KEY (`printer_id`,`filament_id`),
  ADD KEY `filament_id` (`filament_id`);

--
-- Indexen voor tabel `kostenbewijzing_onderzoekers`
--
ALTER TABLE `kostenbewijzing_onderzoekers`
  ADD PRIMARY KEY (`reservatie_id`);

--
-- Indexen voor tabel `kostenbewijzing_studenten`
--
ALTER TABLE `kostenbewijzing_studenten`
  ADD PRIMARY KEY (`reservatie_id`);

--
-- Indexen voor tabel `Lokalen`
--
ALTER TABLE `Lokalen`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `Onderzoeker_Goedkeuring`
--
ALTER TABLE `Onderzoeker_Goedkeuring`
  ADD PRIMARY KEY (`User_ID`),
  ADD KEY `GoedgekeurdDoor` (`GoedgekeurdDoor`);

--
-- Indexen voor tabel `Openingsuren`
--
ALTER TABLE `Openingsuren`
  ADD PRIMARY KEY (`id`),
  ADD KEY `Lokaal_id` (`Lokaal_id`);

--
-- Indexen voor tabel `opleidingen`
--
ALTER TABLE `opleidingen`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `OPOs`
--
ALTER TABLE `OPOs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `opleiding_id` (`opleiding_id`);

--
-- Indexen voor tabel `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `Printer`
--
ALTER TABLE `Printer`
  ADD PRIMARY KEY (`Printer_ID`);

--
-- Indexen voor tabel `Reservatie`
--
ALTER TABLE `Reservatie`
  ADD PRIMARY KEY (`Reservatie_ID`),
  ADD KEY `User_ID` (`User_ID`),
  ADD KEY `Printer_ID` (`Printer_ID`),
  ADD KEY `filament_id` (`filament_id`),
  ADD KEY `Reservatie_ibfk_4` (`OPO_id`);

--
-- Indexen voor tabel `User`
--
ALTER TABLE `User`
  ADD PRIMARY KEY (`User_ID`);

--
-- Indexen voor tabel `Vives`
--
ALTER TABLE `Vives`
  ADD PRIMARY KEY (`User_ID`),
  ADD KEY `opleiding_id` (`opleiding_id`);

--
-- AUTO_INCREMENT voor geëxporteerde tabellen
--

--
-- AUTO_INCREMENT voor een tabel `Openingsuren`
--
ALTER TABLE `Openingsuren`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT voor een tabel `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Beperkingen voor geëxporteerde tabellen
--

--
-- Beperkingen voor tabel `Filament_compatibiliteit`
--
ALTER TABLE `Filament_compatibiliteit`
  ADD CONSTRAINT `Filament_compatibiliteit_ibfk_1` FOREIGN KEY (`printer_id`) REFERENCES `Printer` (`Printer_ID`),
  ADD CONSTRAINT `Filament_compatibiliteit_ibfk_2` FOREIGN KEY (`filament_id`) REFERENCES `Filament` (`id`);

--
-- Beperkingen voor tabel `Onderzoeker_Goedkeuring`
--
ALTER TABLE `Onderzoeker_Goedkeuring`
  ADD CONSTRAINT `Onderzoeker_Goedkeuring_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `User` (`User_ID`),
  ADD CONSTRAINT `Onderzoeker_Goedkeuring_ibfk_2` FOREIGN KEY (`GoedgekeurdDoor`) REFERENCES `User` (`User_ID`);

--
-- Beperkingen voor tabel `Openingsuren`
--
ALTER TABLE `Openingsuren`
  ADD CONSTRAINT `Openingsuren_ibfk_1` FOREIGN KEY (`Lokaal_id`) REFERENCES `Lokalen` (`id`);

--
-- Beperkingen voor tabel `OPOs`
--
ALTER TABLE `OPOs`
  ADD CONSTRAINT `OPOs_ibfk_1` FOREIGN KEY (`opleiding_id`) REFERENCES `opleidingen` (`id`);

--
-- Beperkingen voor tabel `Reservatie`
--
ALTER TABLE `Reservatie`
  ADD CONSTRAINT `Reservatie_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `User` (`User_ID`),
  ADD CONSTRAINT `Reservatie_ibfk_2` FOREIGN KEY (`Printer_ID`) REFERENCES `Printer` (`Printer_ID`),
  ADD CONSTRAINT `Reservatie_ibfk_3` FOREIGN KEY (`filament_id`) REFERENCES `Filament` (`id`),
  ADD CONSTRAINT `Reservatie_ibfk_4` FOREIGN KEY (`OPO_id`) REFERENCES `OPOs` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Beperkingen voor tabel `Vives`
--
ALTER TABLE `Vives`
  ADD CONSTRAINT `Vives_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `User` (`User_ID`),
  ADD CONSTRAINT `Vives_ibfk_2` FOREIGN KEY (`opleiding_id`) REFERENCES `opleidingen` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
