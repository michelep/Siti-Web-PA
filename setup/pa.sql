-- MariaDB dump 10.19  Distrib 10.6.23-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: pa
-- ------------------------------------------------------
-- Server version	10.11.18-MariaDB-0+deb12u1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `Cap_Coordinates`
--

DROP TABLE IF EXISTS `Cap_Coordinates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Cap_Coordinates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `istat_code` varchar(10) DEFAULT NULL,
  `cap` varchar(10) DEFAULT NULL,
  `comune` varchar(150) NOT NULL,
  `provincia` varchar(10) DEFAULT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cap` (`cap`),
  KEY `idx_istat` (`istat_code`)
) ENGINE=InnoDB AUTO_INCREMENT=32768 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Domains`
--

DROP TABLE IF EXISTS `Domains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Domains` (
  `URL` varchar(128) NOT NULL,
  `Codice_IPA` varchar(32) NOT NULL,
  `IP` varchar(256) NOT NULL,
  `Add_Date` datetime NOT NULL,
  `Upd_Date` datetime DEFAULT NULL,
  UNIQUE KEY `URL` (`URL`,`Codice_IPA`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Enti`
--

DROP TABLE IF EXISTS `Enti`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Enti` (
  `Codice_IPA` varchar(16) NOT NULL COMMENT 'Codice IPA',
  `Denominazione_ente` varchar(255) NOT NULL,
  `Codice_comune_ISTAT` varchar(16) DEFAULT NULL,
  `CAP` varchar(10) DEFAULT NULL,
  `Indirizzo` varchar(255) DEFAULT NULL,
  `Sito_istituzionale` varchar(100) DEFAULT NULL,
  `Data_aggiornamento` datetime DEFAULT NULL,
  `Data_inserimento` datetime DEFAULT NULL,
  `Tipologia` varchar(128) DEFAULT NULL,
  `Data_controllo` datetime DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  PRIMARY KEY (`Codice_IPA`),
  KEY `idx_coords` (`latitude`,`longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Enti_History`
--

DROP TABLE IF EXISTS `Enti_History`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Enti_History` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `Codice_IPA` varchar(255) DEFAULT NULL,
  `event_type` varchar(50) NOT NULL COMMENT 'e.g. status_change, hosting_change, title_change, mx_change, entry_added, entry_removed',
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_codice_ipa` (`Codice_IPA`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=1058 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Tags`
--

DROP TABLE IF EXISTS `Tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Tags` (
  `tagName` varchar(32) NOT NULL,
  `Codice_IPA` varchar(16) NOT NULL,
  `tagValue` varchar(128) NOT NULL,
  `addDate` datetime NOT NULL,
  `remDate` datetime DEFAULT NULL,
  PRIMARY KEY (`tagName`,`Codice_IPA`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

