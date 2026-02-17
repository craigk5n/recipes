<?php
/**
 * Migration 01: Initial Schema
 * Creates the core tables for the application.
 */

use Recipes\Database\Database;

Database::query("
    CREATE TABLE IF NOT EXISTS `rec_recipe` (
      `rec_id` int NOT NULL AUTO_INCREMENT,
      `rec_title` varchar(255) NOT NULL,
      `rec_last_updated` datetime DEFAULT NULL,
      `rec_source` varchar(255) DEFAULT NULL,
      `rec_date_added` datetime DEFAULT NULL,
      `rec_url` varchar(2048) DEFAULT NULL,
      `rec_favorite` tinyint(1) DEFAULT '0',
      PRIMARY KEY (`rec_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

Database::query("
    CREATE TABLE IF NOT EXISTS `rec_ingr` (
      `rec_id` int NOT NULL,
      `rec_ingr_num` int NOT NULL,
      `rec_quantity` float DEFAULT NULL,
      `rec_quantity_type` varchar(255) DEFAULT NULL,
      `rec_name` varchar(255) NOT NULL,
      `rec_prep` varchar(255) DEFAULT NULL,
      PRIMARY KEY (`rec_id`,`rec_ingr_num`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

Database::query("
    CREATE TABLE IF NOT EXISTS `rec_instructions` (
      `rec_id` int NOT NULL,
      `rec_instructions` text,
      PRIMARY KEY (`rec_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

Database::query("
    CREATE TABLE IF NOT EXISTS `rec_photo` (
      `photo_id` int NOT NULL AUTO_INCREMENT,
      `rec_id` int NOT NULL,
      `photo_data` longblob,
      `original_name` varchar(255) DEFAULT NULL,
      `mime_type` varchar(50) DEFAULT NULL,
      `file_size` int DEFAULT NULL,
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      `is_primary` tinyint(1) DEFAULT '0',
      PRIMARY KEY (`photo_id`),
      KEY `rec_id` (`rec_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

Database::query("
    CREATE TABLE IF NOT EXISTS `rec_note` (
      `note_id` int NOT NULL AUTO_INCREMENT,
      `rec_id` int NOT NULL,
      `note_text` text,
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`note_id`),
      KEY `rec_id` (`rec_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
