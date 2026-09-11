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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api_tokens`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `api_tokens` WRITE;
/*!40000 ALTER TABLE `api_tokens` DISABLE KEYS */;
INSERT INTO `api_tokens` VALUES
(1,1,'45260da1f03a0821758cbfa66993a9b5044b81839e23151d5baae00f1a82fbce',NULL,'2026-08-15 17:49:37','2026-08-16 16:04:25'),
(2,1,'8c6196bec21def1f43ab55f52dd7b318474e2c76d9f88985b4a6580826e27ec6','Android','2026-08-16 18:28:59','2026-08-20 11:15:43'),
(3,1,'f4e433ebc7ec6ea037f1b33ce8e952604693c6c8df2127b64b46505ac423a671','Android','2026-08-21 10:13:37','2026-08-21 10:14:38');
/*!40000 ALTER TABLE `api_tokens` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `b2b_partners`
--

DROP TABLE IF EXISTS `b2b_partners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `b2b_partners` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `address` varchar(300) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `channel` enum('telegram_whatsapp','call','in_person') DEFAULT NULL,
  `status` enum('not_contacted','contacted','interested','partner','declined') NOT NULL DEFAULT 'not_contacted',
  `notes` text DEFAULT NULL,
  `last_contact_at` date DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_b2b_partners_user` (`created_by`),
  CONSTRAINT `fk_b2b_partners_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `b2b_partners`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `b2b_partners` WRITE;
/*!40000 ALTER TABLE `b2b_partners` DISABLE KEYS */;
/*!40000 ALTER TABLE `b2b_partners` ENABLE KEYS */;
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
  `client_type` enum('individual','legal_entity') NOT NULL DEFAULT 'individual',
  `contact_person` varchar(150) DEFAULT NULL,
  `inn` varchar(12) DEFAULT NULL,
  `kpp` varchar(9) DEFAULT NULL,
  `ogrn` varchar(15) DEFAULT NULL,
  `legal_address` varchar(255) DEFAULT NULL,
  `bank_name` varchar(150) DEFAULT NULL,
  `bank_account` varchar(20) DEFAULT NULL,
  `bank_bik` varchar(9) DEFAULT NULL,
  `bank_corr_account` varchar(20) DEFAULT NULL,
  `phone` varchar(32) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `source` enum('avito','yandex','2gis','google_maps','referral','walkin','site') DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_clients_phone` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=275 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES
(1,'Тест','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (960) 626-75-77','vahmedov@gmail.com',NULL,NULL,'2gis','2026-08-15 13:24:32'),
(2,'Дмитрий','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (919) 767-42-45',NULL,NULL,NULL,NULL,'2026-08-15 17:50:20'),
(3,'амир','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (968) 496-09-97',NULL,NULL,NULL,'yandex','2026-08-15 18:07:59'),
(4,'Амир Ахмедов','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (916) 221-38-98',NULL,NULL,NULL,'yandex','2026-08-15 23:00:56'),
(5,'Фата','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (965) 496-09-97',NULL,NULL,NULL,'yandex','2026-08-15 23:39:29'),
(6,'Фата','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (965) 496-09-97',NULL,NULL,NULL,'yandex','2026-08-15 23:39:35'),
(7,'Фата','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (965) 496-09-97',NULL,NULL,NULL,'yandex','2026-08-15 23:40:07'),
(8,'Fata','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (965) 496-09-97',NULL,NULL,NULL,NULL,'2026-08-15 23:41:50'),
(9,'Fata','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (965) 496-09-97',NULL,NULL,NULL,NULL,'2026-08-15 23:45:12'),
(10,'1','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (910) 475-02-06',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(11,'Vtkrbt','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (991) 246-52-10',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(12,'Адлан','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (916) 084-81-02',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','avito','2026-08-16 04:15:40'),
(13,'Айсылу','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (919) 018-24-29',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(14,'Але','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (926) 407-43-34',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','referral','2026-08-16 04:15:40'),
(15,'Алекс','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (977) 392-32-45',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(16,'Александр','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (964) 790-78-06',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(17,'Александр','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (915) 482-00-01',NULL,NULL,'Источник (ЛайвСклад): Повторное обращение. Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(18,'Александр','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (916) 762-39-49',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(19,'Александр','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (965) 113-25-21',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(20,'Александра','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (916) 981-62-07',NULL,NULL,'Источник (ЛайвСклад): Повторное обращение. Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(21,'Алексей','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (925) 406-32-92',NULL,NULL,'Источник (ЛайвСклад): Полиграфия. Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(22,'Алексей','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (915) 108-38-94',NULL,NULL,'Источник (ЛайвСклад): Повторное обращение. Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(23,'Алексей','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (999) 823-92-90',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','avito','2026-08-16 04:15:40'),
(24,'Алексей','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (910) 422-61-17',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(25,'Алла','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (926) 533-66-05',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','avito','2026-08-16 04:15:40'),
(26,'амир','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (925) 913-27-38',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(27,'Анастасия','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (968) 333-10-00',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','avito','2026-08-16 04:15:40'),
(28,'Анастасия','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (926) 083-72-77',NULL,NULL,'Источник (ЛайвСклад): Полиграфия. Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(29,'Анастасия','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (981) 910-40-08',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(30,'Андрей','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (916) 001-78-78',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(31,'Андрей','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (964) 241-74-18',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(32,'Анна','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (962) 445-62-04',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','referral','2026-08-16 04:15:40'),
(33,'Анна','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (985) 300-46-10',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(34,'Анна','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (926) 659-10-04',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(35,'Антон','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (917) 539-15-34',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(36,'Аркадий','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (916) 676-39-74',NULL,NULL,'Источник (ЛайвСклад): Повторное обращение. Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(37,'Артём','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (906) 986-89-06',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(38,'Аслан','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (903) 552-25-03',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(39,'Афанасий','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (905) 525-16-54',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(40,'Борис','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (985) 241-02-97',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(41,'Ботиржон старший дворник','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (903) 507-75-55',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','referral','2026-08-16 04:15:40'),
(42,'Вадим','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (960) 443-92-75',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(43,'Вадим','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (999) 922-09-57',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(44,'Валерий','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (965) 501-45-90',NULL,NULL,'Источник (ЛайвСклад): Повторное обращение. Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(45,'Василий','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (919) 411-58-41',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(46,'Вася','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (960) 626-75-77',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(47,'Вероника','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (916) 909-53-03',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(48,'Вероника','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (926) 727-72-55',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(49,'Виталий','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (925) 880-88-02',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(50,'Владимир','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (903) 979-45-00',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(51,'Владимир Хуавей','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (916) 764-00-58',NULL,NULL,'Источник (ЛайвСклад): Повторное обращение. Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(52,'Владислав','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (985) 725-15-62',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(53,'Владислав','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (951) 342-26-29',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(54,'Вова','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (977) 151-73-77',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(55,'Глеб','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (926) 899-99-56',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(56,'Данил','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (999) 455-77-24',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(57,'Данила','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (968) 446-68-67',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(58,'Денис','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (991) 169-20-73',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(59,'Дмитрий','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (925) 148-50-71',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(60,'Дмитрий','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (905) 708-50-02',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(61,'Дмитрий','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (919) 767-42-45',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(62,'Дмитрий Иванович','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (915) 339-86-63',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(63,'евгений','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (958) 594-60-61',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','avito','2026-08-16 04:15:40'),
(64,'Евгений','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (926) 000-19-19',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(65,'Евгений','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (926) 234-67-17',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(66,'Евгений','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (925) 117-45-25',NULL,NULL,'Источник (ЛайвСклад): Повторное обращение. Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(67,'Евгений','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (928) 629-54-31',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(68,'Евгений','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (707) 522-31-83',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(69,'Евгения','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (915) 400-85-89',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(70,'Екатерина','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (915) 262-03-60',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(71,'Екатерина','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (905) 204-85-74',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(72,'екатерина','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (915) 713-78-77',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(73,'Екатерина','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (916) 144-48-58',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(74,'Екатерина','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (963) 762-26-66',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(75,'Елена','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (905) 788-83-81',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(76,'елена','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (916) 257-89-17',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(77,'Елизавета','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (929) 988-87-37',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(78,'Жулдуз','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (926) 738-23-83',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(79,'Заур','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (988) 809-59-60',NULL,NULL,'Источник (ЛайвСклад): Повторное обращение. Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(80,'Иван','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (982) 436-01-60',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(81,'Иван','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (903) 266-66-67',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(82,'Игорь','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (995) 100-68-81',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(83,'Игорь','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (985) 800-00-07',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(84,'Игорь','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (915) 273-19-91',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(85,'Илья','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (908) 289-38-18',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(86,'имя','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (916) 409-54-86',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(87,'Ирина','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (926) 444-98-98',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(88,'Ирина','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (985) 158-58-58',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(89,'Ирина','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (910) 480-28-91',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(90,'Ирина','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (903) 154-07-77',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(91,'Исмаил','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (977) 893-81-26',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','referral','2026-08-16 04:15:40'),
(92,'Катя','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (926) 701-77-52',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(93,'Кирилл','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (965) 427-34-43',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(94,'Кирилл','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (909) 900-69-00',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(95,'Константин','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (902) 992-32-86',NULL,NULL,'Источник (ЛайвСклад): Повторное обращение. Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(96,'Константин','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (999) 989-24-22',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(97,'Константин Муханов','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (903) 733-10-26',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','referral','2026-08-16 04:15:40'),
(98,'Ксения','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (928) 198-02-93',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(99,'Лидия','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (968) 700-95-64',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(100,'Любовь','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (926) 520-22-99',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(101,'Людмила','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (916) 129-62-82',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(102,'Макс','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (977) 802-62-61',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','referral','2026-08-16 04:15:40'),
(103,'Максим','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (903) 273-02-56',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(104,'Максим','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (906) 045-88-93',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(105,'Маргарита','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (926) 536-79-99',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(106,'Мария','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (915) 898-76-49',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(107,'Мария','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (985) 115-13-78',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(108,'Мария','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (915) 081-96-03',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(109,'Марк','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (901) 780-14-95',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(110,'михаил','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (968) 814-03-81',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(111,'Нарек','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (926) 873-43-67',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(112,'Нарина','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (993) 903-51-01',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(113,'Наталья','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (915) 008-43-59',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(114,'Наталья','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (916) 885-99-42',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','avito','2026-08-16 04:15:40'),
(115,'Наталья','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (927) 726-79-18',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(116,'не знаю','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (903) 726-61-06',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','referral','2026-08-16 04:15:40'),
(117,'Никита','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (926) 777-49-99',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','avito','2026-08-16 04:15:40'),
(118,'Никита','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (918) 313-00-11',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(119,'Николай','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (999) 980-82-69',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(120,'Николай','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (985) 265-30-93',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(121,'Олег','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (916) 010-15-24',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(122,'Олег','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (901) 181-15-23',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(123,'Ольга','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (911) 265-20-29',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(124,'Ольга lenovo','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (963) 610-11-32',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(125,'ООО  «ЮНИМАРКЕТ»','legal_entity',NULL,'7703402228',NULL,NULL,NULL,NULL,'40702810838000082763','044525225','30101810400000000225','+7 (926) 613-13-62',NULL,NULL,'Компания: ООО  «ЮНИМАРКЕТ». ИНН 7703402228. Р/с 40702810838000082763. К/с 30101810400000000225. БИК 044525225. Импортировано из ЛайвСклад 19.08.2026.','referral','2026-08-16 04:15:40'),
(126,'ООО \"ПРОФРЕШ\"','legal_entity',NULL,'7725382984',NULL,NULL,NULL,'ООО «Банк Точка»','40702810801500088173','044525104','30101810745374525104','+7 (985) 387-75-50',NULL,'198322, г Санкт-Петербург, Красносельский р-н, наб Дудергофского канала, д 4 к 1 стр 1, кв 467','Компания: ООО \"ПРОФРЕШ\". ИНН 7725382984. Р/с 40702810801500088173. К/с 30101810745374525104. БИК 044525104. Банк: ООО «Банк Точка». Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(127,'ООО Кибер Юниэн','legal_entity',NULL,'9718250074',NULL,NULL,NULL,'ООО «Банк Точка»','40702810520000086121','044525104','30101810745374525104','+7 (916) 616-43-47',NULL,NULL,'Компания: ООО Кибер Юниэн. ИНН 9718250074. Р/с 40702810520000086121. К/с 30101810745374525104. БИК 044525104. Банк: ООО «Банк Точка». Импортировано из ЛайвСклад 19.08.2026.','referral','2026-08-16 04:15:40'),
(128,'Рачева Александра','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (916) 474-34-63',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(129,'Руслан','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (999) 003-04-41',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(130,'Рустам','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (968) 767-66-66',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(131,'Саид','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (999) 542-18-12',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(132,'света','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (905) 799-02-52',NULL,NULL,'Источник (ЛайвСклад): Повторное обращение. Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(133,'Светлана','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (926) 048-01-06',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(134,'Светлана','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (916) 948-71-25',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(135,'сергей','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (915) 012-49-70',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(136,'Сергей','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (917) 468-62-00',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(137,'Сергей','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (965) 282-31-37',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(138,'Сергей','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (903) 590-34-06',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(139,'Сергей Женя','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (925) 357-33-95',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(140,'София','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (900) 248-36-79',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(141,'Станислав','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (916) 231-90-23',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(142,'Умар','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (991) 660-44-38',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','referral','2026-08-16 04:15:40'),
(143,'Фёдор','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (915) 198-14-14',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(144,'фарид','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (926) 424-86-82',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','yandex','2026-08-16 04:15:40'),
(145,'Фархад','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (916) 835-83-83',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(146,'Эвелина','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (909) 154-17-99',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(147,'Эльза','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (902) 573-75-52',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','avito','2026-08-16 04:15:40'),
(148,'Юлия','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (925) 381-99-69',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(149,'Юлия','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (903) 215-27-00',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(150,'Юрий','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (985) 767-33-80',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(151,'Юрий','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (916) 904-93-97',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','avito','2026-08-16 04:15:40'),
(152,'Юрий','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (915) 396-42-49',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.','referral','2026-08-16 04:15:40'),
(153,'Юрий','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (905) 506-92-26',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(154,'Ярослав','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (980) 188-23-97',NULL,NULL,'Импортировано из ЛайвСклад 19.08.2026.',NULL,'2026-08-16 04:15:40'),
(265,'???','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (916) 916-80-23',NULL,NULL,NULL,NULL,'2026-08-16 12:33:50'),
(266,'test','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'77777777',NULL,NULL,NULL,'referral','2026-08-16 18:32:15'),
(267,'Ильи клиент','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'1111',NULL,NULL,NULL,'referral','2026-08-17 14:20:10'),
(268,'конь','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+796062675777',NULL,NULL,NULL,'site','2026-08-17 18:26:10'),
(269,'Степан  Шахнин','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (916) 905-49-99',NULL,NULL,NULL,'walkin','2026-08-18 08:50:05'),
(270,'Магнитола мерс','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (903) 138-73-50',NULL,NULL,NULL,'avito','2026-08-18 14:13:05'),
(271,'Кристина соседка','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (968) 030-67-59',NULL,NULL,NULL,'walkin','2026-08-18 14:15:50'),
(272,'Кристина','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (980) 357-27-73',NULL,NULL,NULL,'site','2026-08-18 14:27:07'),
(273,'Александр слаботочка','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (968) 068-26-24',NULL,NULL,NULL,'walkin','2026-08-19 15:34:00'),
(274,'Мила','individual',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'+7 (963) 711-61-54',NULL,NULL,NULL,'yandex','2026-08-21 10:14:29');
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
INSERT INTO `expenses` VALUES
(1,'Аренда',NULL,60000.00,'2026-08-17',1,'2026-08-17 14:22:32');
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
  `stock_qty` decimal(10,2) NOT NULL DEFAULT 0.00,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parts_catalog`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `parts_catalog` WRITE;
/*!40000 ALTER TABLE `parts_catalog` DISABLE KEYS */;
INSERT INTO `parts_catalog` VALUES
(3,'Замена акб',1000.00,-2.00,'2026-08-19 15:35:32'),
(4,'акб iPhone 11 ( orig)',1500.00,-1.00,'2026-08-16 16:06:54'),
(5,'Чистка сеток',1000.00,-2.00,'2026-08-18 14:10:51'),
(7,'Чистка разъема',700.00,-1.00,'2026-08-18 14:22:54'),
(8,'Реболл процессора',7000.00,-1.00,'2026-08-18 14:27:36'),
(9,'куллеры GPU и CPU',2000.00,-1.00,'2026-08-19 15:32:35'),
(11,'АКБ A56',1500.00,-1.00,'2026-08-19 15:36:00'),
(12,'разъем акб',500.00,-1.00,'2026-08-19 15:36:23'),
(13,'замена дисплея',5000.00,-1.00,'2026-08-21 10:17:11'),
(14,'установка пленки Premium',2000.00,-1.00,'2026-08-21 10:17:47');
/*!40000 ALTER TABLE `parts_catalog` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `repair_id` int(10) unsigned NOT NULL,
  `method` enum('cash','card','manual') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `receipt_printed` tinyint(1) NOT NULL DEFAULT 0,
  `kkm_response` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_payments_repair` (`repair_id`),
  KEY `fk_payments_user` (`created_by`),
  CONSTRAINT `fk_payments_repair` FOREIGN KEY (`repair_id`) REFERENCES `repairs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repair_parts`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `repair_parts` WRITE;
/*!40000 ALTER TABLE `repair_parts` DISABLE KEYS */;
INSERT INTO `repair_parts` VALUES
(8,19,'part','Чистка сеток',1.00,1000.00,0.00,NULL),
(9,21,'part','Чистка сеток',1.00,1000.00,0.00,NULL),
(10,23,'part','Чистка разъема',1.00,700.00,0.00,NULL),
(11,24,'part','Реболл процессора',1.00,7000.00,1000.00,NULL),
(12,24,'service','замена куллеров',1.00,2000.00,0.00,NULL),
(13,24,'part','куллеры GPU и CPU',1.00,2000.00,1950.00,NULL),
(14,27,'service','Замена разъема АКБ',1.00,2000.00,0.00,NULL),
(16,27,'part','Замена АКБ',1.00,1000.00,0.00,NULL),
(17,27,'part','АКБ A56',1.00,1500.00,750.00,NULL),
(18,27,'part','разъем акб',1.00,500.00,500.00,NULL),
(19,28,'part','замена дисплея',1.00,5000.00,1300.00,NULL),
(20,28,'part','установка пленки Premium',1.00,2000.00,200.00,NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repair_status_log`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `repair_status_log` WRITE;
/*!40000 ALTER TABLE `repair_status_log` DISABLE KEYS */;
INSERT INTO `repair_status_log` VALUES
(6,5,'принят','Заказ создан через мобильное приложение',1,'2026-08-15 17:50:59'),
(19,16,'принят','Заказ создан',1,'2026-08-16 12:33:50'),
(20,16,'отказ','сказал придет не пришел',1,'2026-08-16 12:34:19'),
(23,5,'диагностика',NULL,1,'2026-08-16 12:55:48'),
(27,19,'принят','Заказ создан через мобильное приложение',1,'2026-08-17 14:20:10'),
(28,19,'выдан',NULL,1,'2026-08-17 14:21:34'),
(30,21,'принят','Заказ создан через мобильное приложение',1,'2026-08-18 08:50:05'),
(31,21,'выдан',NULL,1,'2026-08-18 14:10:28'),
(32,22,'принят','Заказ создан',1,'2026-08-18 14:13:05'),
(33,23,'принят','Заказ создан',1,'2026-08-18 14:15:50'),
(34,23,'готов',NULL,1,'2026-08-18 14:22:58'),
(35,22,'в ремонте',NULL,1,'2026-08-18 14:23:28'),
(36,24,'принят','Заказ создан',1,'2026-08-18 14:27:07'),
(37,24,'в ремонте',NULL,1,'2026-08-18 14:28:00'),
(38,23,'выдан',NULL,1,'2026-08-18 20:24:25'),
(39,25,'принят','Заказ создан через мобильное приложение',1,'2026-08-18 20:33:21'),
(40,25,'в ремонте',NULL,1,'2026-08-18 20:33:27'),
(42,27,'принят','Заказ создан',1,'2026-08-19 15:34:00'),
(43,27,'готов',NULL,1,'2026-08-19 15:36:28'),
(44,24,'ждёт детали',NULL,1,'2026-08-19 15:49:58'),
(45,28,'принят','Заказ создан через мобильное приложение',1,'2026-08-21 10:14:29'),
(46,28,'выдан',NULL,1,'2026-08-21 10:20:11');
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
  `status` enum('принят','диагностика','согласование','ждёт детали','в ремонте','готов','выдан','отказ') NOT NULL DEFAULT 'принят',
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
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repairs`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `repairs` WRITE;
/*!40000 ALTER TABLE `repairs` DISABLE KEYS */;
INSERT INTO `repairs` VALUES
(5,'26-005','repair',2,'Ноутбук',NULL,NULL,NULL,NULL,'не включается','диагностика',0.00,NULL,0.00,NULL,NULL,NULL,0,'39c4a35e47310bd48589809bc0a7398db152c235d33385c92150964600eb7dbb','2026-08-15 17:50:59','2026-08-16 12:55:48'),
(16,'26-010','repair',265,'Смартфон',NULL,NULL,NULL,NULL,NULL,'отказ',0.00,NULL,0.00,NULL,NULL,'Вейс Ахмедов',1,'23b62ec176f4b0cfa8c088c85d7b6fe16968a77467351a9daaa3fcbb03308e9e','2026-08-16 12:33:50','2026-08-16 14:10:40'),
(19,'26-012','repair',267,'Смартфон','iPhone 12 Pro',NULL,NULL,NULL,'чистка сеток','выдан',1000.00,1000.00,0.00,NULL,NULL,'Вейс Ахмедов',1,'496e520d7f4bed25105d543400d6b2c30f999cf115a47c30e3dd9bfe838e08f4','2026-08-17 14:20:10','2026-08-17 19:48:34'),
(21,'26-013','repair',269,'Смартфон','Samsung Galaxy S24+',NULL,NULL,NULL,'динамик слуховой','выдан',1000.00,NULL,0.00,NULL,NULL,NULL,0,'6315299ed0995b72ae7369303266a58cf21d51f8d47079352adda35442b21e65','2026-08-18 08:50:05','2026-08-18 14:10:28'),
(22,'26-014','repair',270,'дисплей для мото',NULL,NULL,NULL,NULL,'залит','в ремонте',3000.00,NULL,0.00,NULL,NULL,NULL,0,'d11789b4037e1475c47a59dbf1535ea4a80293a30bad6e35731c50e33a3738d4','2026-08-18 14:13:05','2026-08-18 14:23:28'),
(23,'26-015','repair',271,'Смартфон','Tecno Pova',NULL,NULL,NULL,'плохо заряжается','выдан',0.00,NULL,0.00,NULL,NULL,NULL,0,'4a50c3e3ba6eb43f9570cb8ec38dbdf2450a50a08af702486371edba902d2485','2026-08-18 14:15:50','2026-08-18 20:24:25'),
(24,'26-016','repair',272,'Ноутбук','Lenovo Legion',NULL,NULL,NULL,'не стабильная работа','ждёт детали',7000.00,NULL,0.00,NULL,NULL,NULL,0,'03fe9986e51b07c832326df40320b0e9acc7926eda759b33a17b0e623cbb7fa5','2026-08-18 14:27:07','2026-08-19 15:49:58'),
(25,'26-017','repair',97,'Ноутбук','iru',NULL,NULL,NULL,'перепайка разъёма питание обслуживание','в ремонте',10000.00,NULL,0.00,NULL,NULL,NULL,0,'744452e38259caf978dfaae2353adae92b05f6aaa1438b069d10ff25820b5c00','2026-08-18 20:33:21','2026-08-18 20:33:27'),
(27,'26-018','repair',273,'Смартфон','Samsung A56',NULL,NULL,NULL,NULL,'готов',5000.00,NULL,0.00,NULL,NULL,'Вейс Ахмедов',1,'adbb155a785794224f3d280c62191f00c3edef70fb61f1fdec4b74e59e886640','2026-08-19 15:34:00','2026-08-20 11:16:07'),
(28,'26-019','repair',274,'Смартфон','iPhone 11',NULL,NULL,NULL,'разбит дисплей','выдан',6500.00,NULL,0.00,NULL,NULL,NULL,0,'6ffa9e03bc041a95dcc52a216bad9cac48e2e6f7753837abbe871aaaa024ca96','2026-08-21 10:14:29','2026-08-21 10:20:11');
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
('bulk_sms_api_key','064F914E-4DDD-45B8-D0E6-B95804F4C779','2026-08-16 22:40:56'),
('company_address','Можайское шоссе, 4к1, Москва','2026-08-15 22:29:55'),
('company_name','АВИОР','2026-08-15 22:29:55'),
('company_phone','+7 (901) 222-81-11','2026-08-15 22:29:55'),
('kkm_login','User','2026-08-17 08:14:25'),
('kkm_num_device','1','2026-08-17 08:14:25'),
('kkm_password','','2026-08-17 08:14:25'),
('kkm_server_url','http://localhost:5893','2026-08-17 08:14:25'),
('lead_intake_secret','e05c2e66e1b53a79f6ecec1aacecd3e51d40e63d20d8ced2','2026-08-17 18:20:33'),
('legal_email','vahmedov@gmail.com','2026-08-16 02:58:02'),
('legal_inn','070106428425','2026-08-16 02:58:02'),
('legal_kpp','','2026-08-16 02:58:02'),
('legal_name','ИП Дзыба Ф.М.','2026-08-16 02:58:02'),
('legal_ogrn','323070000034027','2026-08-16 02:58:02'),
('site_url','https://cms.avior.moscow','2026-08-15 22:29:55'),
('sms_api_key','064F914E-4DDD-45B8-D0E6-B95804F4C779','2026-08-15 22:29:55'),
('sms_gateway_login','T_UFE4','2026-08-18 23:01:45'),
('sms_gateway_password','b_qjirjecdhj66','2026-08-18 23:01:45'),
('sms_provider','android_gateway','2026-08-15 22:55:26'),
('tax_operator','sbis','2026-08-18 23:01:45'),
('tax_operator_login','','2026-08-18 23:01:45'),
('tax_operator_token','','2026-08-18 23:01:45'),
('yandex_reviews_url','https://yandex.ru/maps/org/avior/213899285955/?add-review=true','2026-08-16 03:48:07'),
('ypay_api_key','3bd6152d-5b28-4c74-ad81-6cd32ae3eb92','2026-08-17 14:51:38'),
('ypay_merchant_id','3bd6152d-5b28-4c74-ad81-6cd32ae3eb92','2026-08-17 08:14:25'),
('ypay_sandbox','1','2026-08-17 08:14:25'),
('ypay_software_auth','','2026-08-17 08:14:25');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `sms_campaigns`
--

DROP TABLE IF EXISTS `sms_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sms_campaigns` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `message` text NOT NULL,
  `recipients_count` int(10) unsigned NOT NULL DEFAULT 0,
  `sent_count` int(10) unsigned NOT NULL DEFAULT 0,
  `failed_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_sms_campaigns_user` (`created_by`),
  CONSTRAINT `fk_sms_campaigns_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sms_campaigns`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `sms_campaigns` WRITE;
/*!40000 ALTER TABLE `sms_campaigns` DISABLE KEYS */;
/*!40000 ALTER TABLE `sms_campaigns` ENABLE KEYS */;
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
  `campaign_id` int(10) unsigned DEFAULT NULL,
  `phone` varchar(32) NOT NULL,
  `message` text NOT NULL,
  `status` enum('sent','failed','not_configured') NOT NULL DEFAULT 'not_configured',
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_sms_log_repair` (`repair_id`),
  KEY `fk_sms_log_campaign` (`campaign_id`),
  CONSTRAINT `fk_sms_log_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `sms_campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sms_log_repair` FOREIGN KEY (`repair_id`) REFERENCES `repairs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sms_log`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `sms_log` WRITE;
/*!40000 ALTER TABLE `sms_log` DISABLE KEYS */;
INSERT INTO `sms_log` VALUES
(1,NULL,NULL,'+79606267577','Заказ 26-001: статус — согласование.','not_configured','2026-08-15 22:22:48'),
(2,NULL,NULL,'+79606267577','Заказ 26-001: статус — согласование.','sent','2026-08-15 22:30:05'),
(3,NULL,NULL,'+79606267577','Заказ 26-001: статус — согласование.','failed','2026-08-15 22:36:29'),
(4,NULL,NULL,'79162213898','Заказ 26-004: статус — принят.','sent','2026-08-15 23:06:07'),
(5,NULL,NULL,'79162213898','Здравствуйте! Вас приветствует сервис АВИОР. Устройство готово. С вас 0 ₽.','sent','2026-08-15 23:07:18'),
(6,NULL,NULL,'79162213898','Здравствуйте! Вас приветствует сервис АВИОР. Заказ 26-004 принят в работу. Статус можно отследить здесь: https://avior.moscow/#status','sent','2026-08-15 23:36:54'),
(7,NULL,NULL,'79654960997','Здравствуйте! Вас приветствует сервис АВИОР. Заказ 26-007 принят в работу. Статус можно отследить здесь: https://avior.moscow/#status','sent','2026-08-15 23:45:36'),
(8,NULL,NULL,'79654960997','Здравствуйте! Вас приветствует сервис АВИОР. Устройство готово. С вас 15 000 ₽.','sent','2026-08-15 23:57:51'),
(9,NULL,NULL,'+79684960997','Здравствуйте! Вас приветствует сервис АВИОР. Если остались довольны ремонтом — будем очень благодарны за отзыв на Яндекс Картах: https://yandex.ru/maps/org/avior/213899285955/?add-review=true','sent','2026-08-16 03:48:27'),
(10,NULL,NULL,'79654960997','Здравствуйте! Вас приветствует сервис АВИОР. Если остались довольны ремонтом — будем очень благодарны за отзыв на Яндекс Картах: https://yandex.ru/maps/org/avior/213899285955/?add-review=true','sent','2026-08-16 18:33:53'),
(11,21,NULL,'+7 (916) 905-49-99','Здравствуйте! Вас приветствует сервис АВИОР. Если остались довольны ремонтом — будем очень благодарны за отзыв на Яндекс Картах: https://yandex.ru/maps/org/avior/213899285955/?add-review=true','sent','2026-08-18 14:10:20'),
(12,21,NULL,'+7 (916) 905-49-99','Здравствуйте! Вас приветствует сервис АВИОР. Если остались довольны ремонтом — будем очень благодарны за отзыв на Яндекс Картах: https://yandex.ru/maps/org/avior/213899285955/?add-review=true','sent','2026-08-19 08:24:00'),
(13,NULL,NULL,'+7 (965) 496-09-97','Здравствуйте! Вас приветствует сервис АВИОР. Заказ 26-018 принят в работу. Статус можно отследить здесь: https://avior.moscow/#status','sent','2026-08-19 08:27:07');
/*!40000 ALTER TABLE `sms_log` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `stock_movements`
--

DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_movements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `part_id` int(10) unsigned NOT NULL,
  `type` enum('in','out') NOT NULL,
  `qty` decimal(10,2) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `repair_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_stock_movements_part` (`part_id`),
  KEY `fk_stock_movements_repair` (`repair_id`),
  KEY `fk_stock_movements_user` (`created_by`),
  CONSTRAINT `fk_stock_movements_part` FOREIGN KEY (`part_id`) REFERENCES `parts_catalog` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_movements_repair` FOREIGN KEY (`repair_id`) REFERENCES `repairs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_stock_movements_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_movements`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `stock_movements` WRITE;
/*!40000 ALTER TABLE `stock_movements` DISABLE KEYS */;
INSERT INTO `stock_movements` VALUES
(1,3,'out',1.00,'Заказ 26-006',NULL,1,'2026-08-16 16:06:31'),
(2,4,'out',1.00,'Заказ 26-006',NULL,1,'2026-08-16 16:06:54'),
(3,5,'out',1.00,'Заказ 26-012',19,1,'2026-08-17 14:21:22'),
(4,5,'out',1.00,'Заказ 26-013',21,1,'2026-08-18 14:10:51'),
(5,7,'out',1.00,'Заказ 26-015',23,1,'2026-08-18 14:22:54'),
(6,8,'out',1.00,'Заказ 26-016',24,1,'2026-08-18 14:27:36'),
(7,9,'out',1.00,'Заказ 26-016',24,1,'2026-08-19 15:32:35'),
(8,3,'out',1.00,'Заказ 26-018',27,1,'2026-08-19 15:34:49'),
(9,3,'in',1.00,'Удалено из заказа 26-018',27,1,'2026-08-19 15:34:54'),
(10,3,'out',1.00,'Заказ 26-018',27,1,'2026-08-19 15:35:32'),
(11,11,'out',1.00,'Заказ 26-018',27,1,'2026-08-19 15:36:00'),
(12,12,'out',1.00,'Заказ 26-018',27,1,'2026-08-19 15:36:23'),
(13,13,'out',1.00,'Заказ 26-019',28,1,'2026-08-21 10:17:11'),
(14,14,'out',1.00,'Заказ 26-019',28,1,'2026-08-21 10:17:47');
/*!40000 ALTER TABLE `stock_movements` ENABLE KEYS */;
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
  `role` enum('owner','admin','engineer') NOT NULL DEFAULT 'engineer',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(1,'veys','$2y$12$zjqRvcXDHl3LzKhbkbp0jeYZNprvgeIXy7W.ylshOTObFwjaLI69u','Вейс Ахмедов','owner','2026-08-15 13:21:27'),
(2,'elmir','$2y$12$IDJqDhzYWEMvP13ZY.dn0O1Pn3mud4T2NzRrCcnWVEKorG.WZZIGm','elmir nurmetov','owner','2026-08-15 17:48:28'),
(3,'ilya','$2y$12$NkCxJ58l8o0GhwzVDmVrmepEXCWk4t9Cx/Mboudx7dzbZpCgVIPDS','Илья Ковальчук','owner','2026-08-18 08:55:52');
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

-- Dump completed on 2026-08-21 10:34:59
