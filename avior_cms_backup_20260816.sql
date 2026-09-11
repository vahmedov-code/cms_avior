/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.6-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: avior_cms
-- ------------------------------------------------------
-- Server version	11.8.6-MariaDB-5ubuntu0.1 from Ubuntu

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `api_tokens`
--

DROP TABLE IF EXISTS `api_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `token` char(64) NOT NULL,
  `device_label` varchar(150) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `fk_api_tokens_user` (`user_id`),
  CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api_tokens`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `api_tokens` WRITE;
/*!40000 ALTER TABLE `api_tokens` DISABLE KEYS */;
INSERT INTO `api_tokens` VALUES
(1,1,'45260da1f03a0821758cbfa66993a9b5044b81839e23151d5baae00f1a82fbce',NULL,'2026-08-15 17:49:37','2026-08-16 03:11:52');
/*!40000 ALTER TABLE `api_tokens` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `phone` varchar(32) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `source` enum('avito','yandex','2gis','google_maps','referral','walkin') DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_clients_phone` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES
(1,'Тест','+79606267577','vahmedov@gmail.com',NULL,NULL,'2gis','2026-08-15 13:24:32'),
(2,'Дмитрий','89197674245',NULL,NULL,NULL,NULL,'2026-08-15 17:50:20'),
(3,'амир','+79684960997',NULL,NULL,NULL,'yandex','2026-08-15 18:07:59'),
(4,'Амир Ахмедов','79162213898',NULL,NULL,NULL,'yandex','2026-08-15 23:00:56'),
(5,'Фата','79654960997',NULL,NULL,NULL,'yandex','2026-08-15 23:39:29'),
(6,'Фата','79654960997',NULL,NULL,NULL,'yandex','2026-08-15 23:39:35'),
(7,'Фата','79654960997',NULL,NULL,NULL,'yandex','2026-08-15 23:40:07'),
(8,'Fata','79654960997',NULL,NULL,NULL,NULL,'2026-08-15 23:41:50'),
(9,'Fata','79654960997',NULL,NULL,NULL,NULL,'2026-08-15 23:45:12');
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `device_model_catalog`
--

DROP TABLE IF EXISTS `device_model_catalog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `device_model_catalog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=201 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `device_model_catalog`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `device_model_catalog` WRITE;
/*!40000 ALTER TABLE `device_model_catalog` DISABLE KEYS */;
INSERT INTO `device_model_catalog` VALUES
(1,'iPhone 6','2026-08-15 22:02:35'),
(2,'iPhone 6 Plus','2026-08-15 22:02:35'),
(3,'iPhone 6s','2026-08-15 22:02:35'),
(4,'iPhone 6s Plus','2026-08-15 22:02:35'),
(5,'iPhone SE (2016)','2026-08-15 22:02:35'),
(6,'iPhone 7','2026-08-15 22:02:35'),
(7,'iPhone 7 Plus','2026-08-15 22:02:35'),
(8,'iPhone 8','2026-08-15 22:02:35'),
(9,'iPhone 8 Plus','2026-08-15 22:02:35'),
(10,'iPhone X','2026-08-15 22:02:35'),
(11,'iPhone XR','2026-08-15 22:02:35'),
(12,'iPhone XS','2026-08-15 22:02:35'),
(13,'iPhone XS Max','2026-08-15 22:02:35'),
(14,'iPhone 11','2026-08-15 22:02:35'),
(15,'iPhone 11 Pro','2026-08-15 22:02:35'),
(16,'iPhone 11 Pro Max','2026-08-15 22:02:35'),
(17,'iPhone SE (2020)','2026-08-15 22:02:35'),
(18,'iPhone 12 mini','2026-08-15 22:02:35'),
(19,'iPhone 12','2026-08-15 22:02:35'),
(20,'iPhone 12 Pro','2026-08-15 22:02:35'),
(21,'iPhone 12 Pro Max','2026-08-15 22:02:35'),
(22,'iPhone 13 mini','2026-08-15 22:02:35'),
(23,'iPhone 13','2026-08-15 22:02:35'),
(24,'iPhone 13 Pro','2026-08-15 22:02:35'),
(25,'iPhone 13 Pro Max','2026-08-15 22:02:35'),
(26,'iPhone SE (2022)','2026-08-15 22:02:35'),
(27,'iPhone 14','2026-08-15 22:02:35'),
(28,'iPhone 14 Plus','2026-08-15 22:02:35'),
(29,'iPhone 14 Pro','2026-08-15 22:02:35'),
(30,'iPhone 14 Pro Max','2026-08-15 22:02:35'),
(31,'iPhone 15','2026-08-15 22:02:35'),
(32,'iPhone 15 Plus','2026-08-15 22:02:35'),
(33,'iPhone 15 Pro','2026-08-15 22:02:35'),
(34,'iPhone 15 Pro Max','2026-08-15 22:02:35'),
(35,'iPhone 16','2026-08-15 22:02:35'),
(36,'iPhone 16 Plus','2026-08-15 22:02:35'),
(37,'iPhone 16 Pro','2026-08-15 22:02:35'),
(38,'iPhone 16 Pro Max','2026-08-15 22:02:35'),
(39,'Samsung Galaxy S8','2026-08-15 22:02:35'),
(40,'Samsung Galaxy S8+','2026-08-15 22:02:35'),
(41,'Samsung Galaxy S9','2026-08-15 22:02:35'),
(42,'Samsung Galaxy S9+','2026-08-15 22:02:35'),
(43,'Samsung Galaxy S10e','2026-08-15 22:02:35'),
(44,'Samsung Galaxy S10','2026-08-15 22:02:35'),
(45,'Samsung Galaxy S10+','2026-08-15 22:02:35'),
(46,'Samsung Galaxy S20','2026-08-15 22:02:35'),
(47,'Samsung Galaxy S20+','2026-08-15 22:02:35'),
(48,'Samsung Galaxy S20 Ultra','2026-08-15 22:02:35'),
(49,'Samsung Galaxy S20 FE','2026-08-15 22:02:35'),
(50,'Samsung Galaxy S21','2026-08-15 22:02:35'),
(51,'Samsung Galaxy S21+','2026-08-15 22:02:35'),
(52,'Samsung Galaxy S21 Ultra','2026-08-15 22:02:35'),
(53,'Samsung Galaxy S21 FE','2026-08-15 22:02:35'),
(54,'Samsung Galaxy S22','2026-08-15 22:02:35'),
(55,'Samsung Galaxy S22+','2026-08-15 22:02:35'),
(56,'Samsung Galaxy S22 Ultra','2026-08-15 22:02:35'),
(57,'Samsung Galaxy S23','2026-08-15 22:02:35'),
(58,'Samsung Galaxy S23+','2026-08-15 22:02:35'),
(59,'Samsung Galaxy S23 Ultra','2026-08-15 22:02:35'),
(60,'Samsung Galaxy S23 FE','2026-08-15 22:02:35'),
(61,'Samsung Galaxy S24','2026-08-15 22:02:35'),
(62,'Samsung Galaxy S24+','2026-08-15 22:02:35'),
(63,'Samsung Galaxy S24 Ultra','2026-08-15 22:02:35'),
(64,'Samsung Galaxy S24 FE','2026-08-15 22:02:35'),
(65,'Samsung Galaxy S25','2026-08-15 22:02:35'),
(66,'Samsung Galaxy S25+','2026-08-15 22:02:35'),
(67,'Samsung Galaxy S25 Ultra','2026-08-15 22:02:35'),
(68,'Samsung Galaxy A12','2026-08-15 22:02:35'),
(69,'Samsung Galaxy A13','2026-08-15 22:02:35'),
(70,'Samsung Galaxy A14','2026-08-15 22:02:35'),
(71,'Samsung Galaxy A22','2026-08-15 22:02:35'),
(72,'Samsung Galaxy A23','2026-08-15 22:02:35'),
(73,'Samsung Galaxy A32','2026-08-15 22:02:35'),
(74,'Samsung Galaxy A33','2026-08-15 22:02:35'),
(75,'Samsung Galaxy A34','2026-08-15 22:02:35'),
(76,'Samsung Galaxy A50','2026-08-15 22:02:35'),
(77,'Samsung Galaxy A51','2026-08-15 22:02:35'),
(78,'Samsung Galaxy A52','2026-08-15 22:02:35'),
(79,'Samsung Galaxy A53','2026-08-15 22:02:35'),
(80,'Samsung Galaxy A54','2026-08-15 22:02:35'),
(81,'Samsung Galaxy A55','2026-08-15 22:02:35'),
(82,'Samsung Galaxy A72','2026-08-15 22:02:35'),
(83,'Samsung Galaxy A73','2026-08-15 22:02:35'),
(84,'Samsung Galaxy Note 8','2026-08-15 22:02:35'),
(85,'Samsung Galaxy Note 9','2026-08-15 22:02:35'),
(86,'Samsung Galaxy Note 10','2026-08-15 22:02:35'),
(87,'Samsung Galaxy Note 10+','2026-08-15 22:02:35'),
(88,'Samsung Galaxy Note 20','2026-08-15 22:02:35'),
(89,'Samsung Galaxy Note 20 Ultra','2026-08-15 22:02:35'),
(90,'Samsung Galaxy Z Flip','2026-08-15 22:02:35'),
(91,'Samsung Galaxy Z Flip3','2026-08-15 22:02:35'),
(92,'Samsung Galaxy Z Flip4','2026-08-15 22:02:35'),
(93,'Samsung Galaxy Z Flip5','2026-08-15 22:02:35'),
(94,'Samsung Galaxy Z Fold2','2026-08-15 22:02:35'),
(95,'Samsung Galaxy Z Fold3','2026-08-15 22:02:35'),
(96,'Samsung Galaxy Z Fold4','2026-08-15 22:02:35'),
(97,'Samsung Galaxy Z Fold5','2026-08-15 22:02:35'),
(98,'Redmi Note 8','2026-08-15 22:02:35'),
(99,'Redmi Note 9','2026-08-15 22:02:35'),
(100,'Redmi Note 10','2026-08-15 22:02:35'),
(101,'Redmi Note 11','2026-08-15 22:02:35'),
(102,'Redmi Note 12','2026-08-15 22:02:35'),
(103,'Redmi Note 13','2026-08-15 22:02:35'),
(104,'Redmi 9','2026-08-15 22:02:35'),
(105,'Redmi 10','2026-08-15 22:02:35'),
(106,'Redmi 12','2026-08-15 22:02:35'),
(107,'Xiaomi Mi 9','2026-08-15 22:02:35'),
(108,'Xiaomi Mi 10','2026-08-15 22:02:35'),
(109,'Xiaomi Mi 11','2026-08-15 22:02:35'),
(110,'Xiaomi 12','2026-08-15 22:02:35'),
(111,'Xiaomi 13','2026-08-15 22:02:35'),
(112,'Xiaomi 14','2026-08-15 22:02:35'),
(113,'POCO X3','2026-08-15 22:02:35'),
(114,'POCO X4','2026-08-15 22:02:35'),
(115,'POCO X5','2026-08-15 22:02:35'),
(116,'POCO F4','2026-08-15 22:02:35'),
(117,'POCO F5','2026-08-15 22:02:35'),
(118,'POCO M4','2026-08-15 22:02:35'),
(119,'Huawei P30','2026-08-15 22:02:35'),
(120,'Huawei P40','2026-08-15 22:02:35'),
(121,'Huawei P50','2026-08-15 22:02:35'),
(122,'Huawei Mate 20','2026-08-15 22:02:35'),
(123,'Huawei Mate 30','2026-08-15 22:02:35'),
(124,'Huawei Mate 40','2026-08-15 22:02:35'),
(125,'Honor 9X','2026-08-15 22:02:35'),
(126,'Honor 10X','2026-08-15 22:02:35'),
(127,'Honor 20','2026-08-15 22:02:35'),
(128,'Honor 50','2026-08-15 22:02:35'),
(129,'Honor 70','2026-08-15 22:02:35'),
(130,'Honor 90','2026-08-15 22:02:35'),
(131,'Honor X8','2026-08-15 22:02:35'),
(132,'Honor X9','2026-08-15 22:02:35'),
(133,'Google Pixel 5','2026-08-15 22:02:35'),
(134,'Google Pixel 6','2026-08-15 22:02:35'),
(135,'Google Pixel 6 Pro','2026-08-15 22:02:35'),
(136,'Google Pixel 7','2026-08-15 22:02:35'),
(137,'Google Pixel 7 Pro','2026-08-15 22:02:35'),
(138,'Google Pixel 8','2026-08-15 22:02:35'),
(139,'Google Pixel 8 Pro','2026-08-15 22:02:35'),
(140,'OnePlus 8','2026-08-15 22:02:35'),
(141,'OnePlus 9','2026-08-15 22:02:35'),
(142,'OnePlus 10','2026-08-15 22:02:35'),
(143,'OnePlus 11','2026-08-15 22:02:35'),
(144,'OnePlus Nord 2','2026-08-15 22:02:35'),
(145,'OnePlus Nord 3','2026-08-15 22:02:35'),
(146,'MacBook Air 13\" (2017, Intel)','2026-08-15 22:02:35'),
(147,'MacBook Air M1 (2020)','2026-08-15 22:02:35'),
(148,'MacBook Air M2 (2022)','2026-08-15 22:02:35'),
(149,'MacBook Air M3 (2024)','2026-08-15 22:02:35'),
(150,'MacBook Pro 13\" (Intel)','2026-08-15 22:02:35'),
(151,'MacBook Pro 13\" M1','2026-08-15 22:02:35'),
(152,'MacBook Pro 14\" M1 Pro','2026-08-15 22:02:35'),
(153,'MacBook Pro 14\" M2 Pro','2026-08-15 22:02:35'),
(154,'MacBook Pro 14\" M3','2026-08-15 22:02:35'),
(155,'MacBook Pro 16\" M1 Max','2026-08-15 22:02:35'),
(156,'MacBook Pro 16\" M3 Pro','2026-08-15 22:02:35'),
(157,'Lenovo ThinkPad T14','2026-08-15 22:02:35'),
(158,'Lenovo ThinkPad T480','2026-08-15 22:02:35'),
(159,'Lenovo ThinkPad X1 Carbon','2026-08-15 22:02:35'),
(160,'Lenovo ThinkPad E14','2026-08-15 22:02:35'),
(161,'Lenovo ThinkPad E15','2026-08-15 22:02:35'),
(162,'Lenovo IdeaPad 3','2026-08-15 22:02:35'),
(163,'Lenovo IdeaPad 5','2026-08-15 22:02:35'),
(164,'Lenovo IdeaPad Flex 5','2026-08-15 22:02:35'),
(165,'Lenovo Legion 5','2026-08-15 22:02:35'),
(166,'Lenovo Legion Y540','2026-08-15 22:02:35'),
(167,'Lenovo Yoga Slim 7','2026-08-15 22:02:35'),
(168,'HP Pavilion 15','2026-08-15 22:02:35'),
(169,'HP Pavilion x360','2026-08-15 22:02:35'),
(170,'HP EliteBook 840','2026-08-15 22:02:35'),
(171,'HP ProBook 450','2026-08-15 22:02:35'),
(172,'HP Omen 15','2026-08-15 22:02:35'),
(173,'HP Envy 13','2026-08-15 22:02:35'),
(174,'HP Envy x360','2026-08-15 22:02:35'),
(175,'Dell Inspiron 15 3000','2026-08-15 22:02:35'),
(176,'Dell Inspiron 15 5000','2026-08-15 22:02:35'),
(177,'Dell XPS 13','2026-08-15 22:02:35'),
(178,'Dell XPS 15','2026-08-15 22:02:35'),
(179,'Dell Latitude 5420','2026-08-15 22:02:35'),
(180,'Dell Latitude 7420','2026-08-15 22:02:35'),
(181,'Dell Vostro 15','2026-08-15 22:02:35'),
(182,'Asus VivoBook 15','2026-08-15 22:02:35'),
(183,'Asus VivoBook S14','2026-08-15 22:02:35'),
(184,'Asus ZenBook 14','2026-08-15 22:02:35'),
(185,'Asus ZenBook Duo','2026-08-15 22:02:35'),
(186,'Asus ROG Strix G15','2026-08-15 22:02:35'),
(187,'Asus ROG Zephyrus G14','2026-08-15 22:02:35'),
(188,'Asus TUF Gaming A15','2026-08-15 22:02:35'),
(189,'Acer Aspire 3','2026-08-15 22:02:35'),
(190,'Acer Aspire 5','2026-08-15 22:02:35'),
(191,'Acer Aspire 7','2026-08-15 22:02:35'),
(192,'Acer Nitro 5','2026-08-15 22:02:35'),
(193,'Acer Predator Helios 300','2026-08-15 22:02:35'),
(194,'Acer Swift 3','2026-08-15 22:02:35'),
(195,'Acer Swift 5','2026-08-15 22:02:35'),
(196,'MSI Modern 14','2026-08-15 22:02:35'),
(197,'MSI Katana GF66','2026-08-15 22:02:35'),
(198,'MSI Cyborg 15','2026-08-15 22:02:35'),
(199,'MSI Stealth 15','2026-08-15 22:02:35'),
(200,'MSI GF63 Thin','2026-08-15 22:02:35');
/*!40000 ALTER TABLE `device_model_catalog` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `expenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_expenses_user` (`created_by`),
  CONSTRAINT `fk_expenses_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `parts_catalog`
--

DROP TABLE IF EXISTS `parts_catalog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `parts_catalog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parts_catalog`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `parts_catalog` WRITE;
/*!40000 ALTER TABLE `parts_catalog` DISABLE KEYS */;
/*!40000 ALTER TABLE `parts_catalog` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `repair_parts`
--

DROP TABLE IF EXISTS `repair_parts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `repair_parts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `repair_id` int(10) unsigned NOT NULL,
  `category` enum('part','service') NOT NULL DEFAULT 'part',
  `name` varchar(255) NOT NULL,
  `qty` decimal(10,2) NOT NULL DEFAULT 1.00,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `warranty` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_repair_parts_repair` (`repair_id`),
  CONSTRAINT `fk_repair_parts_repair` FOREIGN KEY (`repair_id`) REFERENCES `repairs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repair_parts`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `repair_parts` WRITE;
/*!40000 ALTER TABLE `repair_parts` DISABLE KEYS */;
INSERT INTO `repair_parts` VALUES
(5,13,'part','Дисплей',1.00,15000.00,0.00,NULL);
/*!40000 ALTER TABLE `repair_parts` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `repair_status_log`
--

DROP TABLE IF EXISTS `repair_status_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `repair_status_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `repair_id` int(10) unsigned NOT NULL,
  `status` varchar(50) NOT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `changed_by` int(10) unsigned DEFAULT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_status_log_repair` (`repair_id`),
  KEY `fk_status_log_user` (`changed_by`),
  CONSTRAINT `fk_status_log_repair` FOREIGN KEY (`repair_id`) REFERENCES `repairs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_status_log_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repair_status_log`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `repair_status_log` WRITE;
/*!40000 ALTER TABLE `repair_status_log` DISABLE KEYS */;
INSERT INTO `repair_status_log` VALUES
(1,1,'принят','Заказ создан',1,'2026-08-15 13:24:32'),
(2,1,'согласование',NULL,1,'2026-08-15 13:45:49'),
(6,5,'принят','Заказ создан через мобильное приложение',1,'2026-08-15 17:50:59'),
(7,6,'принят','Заказ создан через мобильное приложение',1,'2026-08-15 18:07:59'),
(8,6,'готов',NULL,1,'2026-08-15 18:18:16'),
(11,8,'принят','Заказ создан',1,'2026-08-15 23:05:58'),
(12,8,'готов',NULL,1,'2026-08-15 23:07:04'),
(13,8,'принят',NULL,1,'2026-08-15 23:31:57'),
(14,13,'принят','Заказ создан',1,'2026-08-15 23:45:12'),
(15,13,'готов',NULL,1,'2026-08-15 23:57:27'),
(16,13,'выдан',NULL,1,'2026-08-15 23:58:34'),
(17,14,'выдан','Памятка по аккаунту оформлена',1,'2026-08-16 03:24:10');
/*!40000 ALTER TABLE `repair_status_log` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `repairs`
--

DROP TABLE IF EXISTS `repairs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `repairs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(20) NOT NULL,
  `order_type` enum('repair','pc_build','account_memo') NOT NULL DEFAULT 'repair',
  `client_id` int(10) unsigned NOT NULL,
  `device_type` varchar(100) NOT NULL,
  `device_model` varchar(150) DEFAULT NULL,
  `device_serial` varchar(100) DEFAULT NULL,
  `device_complete` varchar(255) DEFAULT NULL,
  `device_condition` varchar(255) DEFAULT NULL,
  `problem_description` text DEFAULT NULL,
  `status` enum('принят','диагностика','согласование','в ремонте','готов','выдан','отказ') NOT NULL DEFAULT 'принят',
  `price_estimate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_final` decimal(10,2) DEFAULT NULL,
  `prepayment` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deadline_date` date DEFAULT NULL,
  `receipt_note` varchar(500) DEFAULT NULL,
  `manager_name` varchar(150) DEFAULT NULL,
  `receipt_ready` tinyint(1) NOT NULL DEFAULT 0,
  `public_token` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_no` (`order_no`),
  UNIQUE KEY `public_token` (`public_token`),
  KEY `fk_repairs_client` (`client_id`),
  KEY `idx_repairs_status` (`status`),
  KEY `idx_repairs_order_no` (`order_no`),
  CONSTRAINT `fk_repairs_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repairs`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `repairs` WRITE;
/*!40000 ALTER TABLE `repairs` DISABLE KEYS */;
INSERT INTO `repairs` VALUES
(1,'26-001','repair',1,'смартфон','X7 pro',NULL,NULL,NULL,'yt drk.xftncz','согласование',6500.00,NULL,0.00,NULL,NULL,'Вейс Ахмедов',1,'2ef431fb9d53f238e5357bc365f7a835260620f12061687654a79a7711028fa4','2026-08-15 13:24:32','2026-08-15 18:44:50'),
(5,'26-005','repair',2,'Ноутбук',NULL,NULL,NULL,NULL,'не включается','принят',0.00,NULL,0.00,NULL,NULL,NULL,0,'39c4a35e47310bd48589809bc0a7398db152c235d33385c92150964600eb7dbb','2026-08-15 17:50:59','2026-08-15 18:44:50'),
(6,'26-006','repair',3,'смартфон','iphone 11',NULL,NULL,NULL,'разбит дисплей','готов',3000.00,NULL,0.00,NULL,NULL,'Вейс Ахмедов',1,'1c44f8f18d6a117439c89ce6bada7eb199f4b4252bfd494d00b59644302d26aa','2026-08-15 18:07:59','2026-08-15 18:44:50'),
(8,'26-004','repair',4,'Моноблок','Acer Aspire 5',NULL,NULL,NULL,'не включается','принят',10000.00,NULL,0.00,NULL,NULL,NULL,0,'14552afbe72eba215b699388e054f85fb1e8347a7f35e1247affa48653d1d1d7','2026-08-15 23:05:58','2026-08-15 23:31:57'),
(13,'26-007','repair',9,'Планшет',NULL,NULL,NULL,NULL,'не включается','выдан',0.00,NULL,0.00,NULL,NULL,NULL,0,'b632e3056287944f52da4670aedfcfa1e68711f5f1d1a8b8bd5065db51592a04','2026-08-15 23:45:12','2026-08-15 23:58:34'),
(14,'26-008','account_memo',3,'Apple ID',NULL,NULL,NULL,NULL,'Памятка по учётной записи оформлена и выдана клиенту на бумаге.','выдан',0.00,NULL,0.00,NULL,NULL,NULL,0,'19e5265c259695f358cb9050da2904bdf39a274c3254d0793efce0f319b277ff','2026-08-16 03:24:10','2026-08-16 03:24:10');
/*!40000 ALTER TABLE `repairs` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES
('ai_api_token','dda5f4376fe3784b7d57f96247aad2631579e8e304c238f4','2026-08-16 02:48:13'),
('bank_account','40802810938000520601','2026-08-16 02:58:02'),
('bank_bik','044525225','2026-08-16 02:58:02'),
('bank_corr_account','30101810400000000225','2026-08-16 02:58:02'),
('bank_name','ПАО Сбербанк','2026-08-16 02:58:02'),
('company_address','Можайское шоссе, 4к1, Москва','2026-08-15 22:29:55'),
('company_name','АВИОР','2026-08-15 22:29:55'),
('company_phone','+7 (901) 222-81-11','2026-08-15 22:29:55'),
('legal_email','vahmedov@gmail.com','2026-08-16 02:58:02'),
('legal_inn','070106428425','2026-08-16 02:58:02'),
('legal_kpp','','2026-08-16 02:58:02'),
('legal_name','ИП Дзыба Ф.М.','2026-08-16 02:58:02'),
('legal_ogrn','323070000034027','2026-08-16 02:58:02'),
('site_url','https://cms.avior.moscow','2026-08-15 22:29:55'),
('sms_api_key','064F914E-4DDD-45B8-D0E6-B95804F4C779','2026-08-15 22:29:55'),
('sms_gateway_login','-H6MPP','2026-08-15 22:59:48'),
('sms_gateway_password','ry9gats3aumizd','2026-08-15 22:59:48'),
('sms_provider','android_gateway','2026-08-15 22:55:26'),
('yandex_reviews_url','https://yandex.ru/maps/org/avior/213899285955/?add-review=true','2026-08-16 03:48:07');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `sms_log`
--

DROP TABLE IF EXISTS `sms_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sms_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `repair_id` int(10) unsigned DEFAULT NULL,
  `phone` varchar(32) NOT NULL,
  `message` text NOT NULL,
  `status` enum('sent','failed','not_configured') NOT NULL DEFAULT 'not_configured',
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_sms_log_repair` (`repair_id`),
  CONSTRAINT `fk_sms_log_repair` FOREIGN KEY (`repair_id`) REFERENCES `repairs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sms_log`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `sms_log` WRITE;
/*!40000 ALTER TABLE `sms_log` DISABLE KEYS */;
INSERT INTO `sms_log` VALUES
(1,1,'+79606267577','Заказ 26-001: статус — согласование.','not_configured','2026-08-15 22:22:48'),
(2,1,'+79606267577','Заказ 26-001: статус — согласование.','sent','2026-08-15 22:30:05'),
(3,1,'+79606267577','Заказ 26-001: статус — согласование.','failed','2026-08-15 22:36:29'),
(4,8,'79162213898','Заказ 26-004: статус — принят.','sent','2026-08-15 23:06:07'),
(5,8,'79162213898','Здравствуйте! Вас приветствует сервис АВИОР. Устройство готово. С вас 0 ₽.','sent','2026-08-15 23:07:18'),
(6,8,'79162213898','Здравствуйте! Вас приветствует сервис АВИОР. Заказ 26-004 принят в работу. Статус можно отследить здесь: https://avior.moscow/#status','sent','2026-08-15 23:36:54'),
(7,13,'79654960997','Здравствуйте! Вас приветствует сервис АВИОР. Заказ 26-007 принят в работу. Статус можно отследить здесь: https://avior.moscow/#status','sent','2026-08-15 23:45:36'),
(8,13,'79654960997','Здравствуйте! Вас приветствует сервис АВИОР. Устройство готово. С вас 15 000 ₽.','sent','2026-08-15 23:57:51'),
(9,14,'+79684960997','Здравствуйте! Вас приветствует сервис АВИОР. Если остались довольны ремонтом — будем очень благодарны за отзыв на Яндекс Картах: https://yandex.ru/maps/org/avior/213899285955/?add-review=true','sent','2026-08-16 03:48:27');
/*!40000 ALTER TABLE `sms_log` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `role` enum('admin','manager') NOT NULL DEFAULT 'manager',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(1,'veys','$2y$12$zjqRvcXDHl3LzKhbkbp0jeYZNprvgeIXy7W.ylshOTObFwjaLI69u','Вейс Ахмедов','admin','2026-08-15 13:21:27'),
(2,'elmir','$2y$12$IDJqDhzYWEMvP13ZY.dn0O1Pn3mud4T2NzRrCcnWVEKorG.WZZIGm','elmir nurmetov','manager','2026-08-15 17:48:28');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-08-16  4:09:17
