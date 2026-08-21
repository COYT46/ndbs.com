-- --------------------------------------------------------
-- Máy chủ:                      127.0.0.1
-- Phiên bản máy chủ:            10.4.32-MariaDB - mariadb.org binary distribution
-- HĐH máy chủ:                  Win64
-- HeidiSQL Phiên bản:           12.20.0.7320
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Đang kết xuất đổ cấu trúc cho bảng ndbs.cache
DROP TABLE IF EXISTS `cache`;
CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Đang kết xuất đổ dữ liệu cho bảng ndbs.cache: ~0 rows (xấp xỉ)

-- Đang kết xuất đổ cấu trúc cho bảng ndbs.cache_locks
DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Đang kết xuất đổ dữ liệu cho bảng ndbs.cache_locks: ~0 rows (xấp xỉ)

-- Đang kết xuất đổ cấu trúc cho bảng ndbs.failed_jobs
DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Đang kết xuất đổ dữ liệu cho bảng ndbs.failed_jobs: ~0 rows (xấp xỉ)

-- Đang kết xuất đổ cấu trúc cho bảng ndbs.migrations
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Đang kết xuất đổ dữ liệu cho bảng ndbs.migrations: ~13 rows (xấp xỉ)
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
	(19, '2014_10_12_000000_create_users_table', 1),
	(20, '2014_10_12_100000_create_password_reset_tokens_table', 1),
	(21, '2019_08_19_000000_create_failed_jobs_table', 1),
	(22, '2019_12_14_000001_create_personal_access_tokens_table', 1),
	(23, '2025_02_16_154426_create_sepay_table', 1),
	(24, '2026_06_21_231003_add_role_to_users_table', 1),
	(25, '2026_06_21_231005_create_vehicle_logs_table', 1),
	(26, '2026_06_21_235535_create_sessions_table', 1),
	(27, '2026_07_02_233000_add_fields_to_users_and_vehicle_logs_tables', 1),
	(28, '2026_07_03_000000_create_cache_table', 1),
	(29, '2026_07_11_000001_add_deleted_to_users_table', 2),
	(30, '2026_08_19_160000_create_settings_and_monthly_tickets_tables', 2),
	(31, '2026_08_20_143000_allow_reuse_monthly_code_on_vehicle_logs', 2);

-- Đang kết xuất đổ cấu trúc cho bảng ndbs.monthly_tickets
DROP TABLE IF EXISTS `monthly_tickets`;
CREATE TABLE IF NOT EXISTS `monthly_tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(6) NOT NULL,
  `plate_number` varchar(255) NOT NULL,
  `duration_months` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `price` int(10) unsigned NOT NULL DEFAULT 0,
  `starts_on` date NOT NULL,
  `expires_on` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `monthly_tickets_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Đang kết xuất đổ dữ liệu cho bảng ndbs.monthly_tickets: ~0 rows (xấp xỉ)

-- Đang kết xuất đổ cấu trúc cho bảng ndbs.password_reset_tokens
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Đang kết xuất đổ dữ liệu cho bảng ndbs.password_reset_tokens: ~0 rows (xấp xỉ)

-- Đang kết xuất đổ cấu trúc cho bảng ndbs.personal_access_tokens
DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Đang kết xuất đổ dữ liệu cho bảng ndbs.personal_access_tokens: ~0 rows (xấp xỉ)

-- Đang kết xuất đổ cấu trúc cho bảng ndbs.sessions
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Đang kết xuất đổ dữ liệu cho bảng ndbs.sessions: ~1 rows (xấp xỉ)
INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
	('z1CD3pHjBBNF1iaT8uIuWARDcRuLakcSXa3CzuQo', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiM1k4eDZ1Vnp2TEVrMk9odTFNYjExa2VlQk43RG9kR1k3WjhvWUtnciI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6Mzk6Imh0dHA6Ly9uZGJzLmNvbS9tYW5hZ2VyL21vbnRobHktdGlja2V0cyI7fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjE7fQ==', 1787217989);

-- Đang kết xuất đổ cấu trúc cho bảng ndbs.settings
DROP TABLE IF EXISTS `settings`;
CREATE TABLE IF NOT EXISTS `settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Đang kết xuất đổ dữ liệu cho bảng ndbs.settings: ~2 rows (xấp xỉ)
INSERT INTO `settings` (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES
	(1, 'daily_price_per_hour', '1000', '2026-08-20 09:22:19', '2026-08-20 09:22:19'),
	(2, 'monthly_price_per_month', '100000', '2026-08-20 09:22:19', '2026-08-20 09:22:19');

-- Đang kết xuất đổ cấu trúc cho bảng ndbs.users
DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fullname` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'guard',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Đang kết xuất đổ dữ liệu cho bảng ndbs.users: ~2 rows (xấp xỉ)
INSERT INTO `users` (`id`, `fullname`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `role`, `is_active`, `deleted`) VALUES
	(1, 'Quản lý', 'admin@admin.com', NULL, '$2y$12$FyNxWcYwf/vkTgV0KiI4ie7fBeHf5dHYaynUolGF9ktkDN6OnJG1C', NULL, '2026-07-02 18:51:05', '2026-07-02 18:51:05', 'manager', 1, 0),
	(2, 'Bảo vệ', 'baove@baove.com', NULL, '$2y$12$FyNxWcYwf/vkTgV0KiI4ie7fBeHf5dHYaynUolGF9ktkDN6OnJG1C', NULL, '2026-07-02 18:51:05', '2026-07-02 20:12:53', 'guard', 1, 0);

-- Đang kết xuất đổ cấu trúc cho bảng ndbs.vehicle_logs
DROP TABLE IF EXISTS `vehicle_logs`;
CREATE TABLE IF NOT EXISTS `vehicle_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `plate_number` varchar(255) DEFAULT NULL,
  `code` varchar(255) NOT NULL,
  `ticket_type` varchar(16) NOT NULL DEFAULT 'daily',
  `monthly_ticket_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('in','out') NOT NULL DEFAULT 'in',
  `entry_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `exit_time` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `entry_image` varchar(255) DEFAULT NULL,
  `exit_image` varchar(255) DEFAULT NULL,
  `exit_plate_number` varchar(255) DEFAULT NULL,
  `guard_in_id` bigint(20) unsigned DEFAULT NULL,
  `guard_out_id` bigint(20) unsigned DEFAULT NULL,
  `is_valid` tinyint(1) DEFAULT NULL,
  `fee` int(10) unsigned DEFAULT NULL,
  `hourly_rate` int(10) unsigned DEFAULT NULL,
  `monthly_match` tinyint(1) DEFAULT NULL,
  `monthly_confirmed` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vehicle_logs_code_index` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Đang kết xuất đổ dữ liệu cho bảng ndbs.vehicle_logs: ~0 rows (xấp xỉ)

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
