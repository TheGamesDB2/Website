-- Create api_access_requests table
CREATE TABLE IF NOT EXISTS `api_access_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `app_name` varchar(255) NOT NULL,
  `app_description` text NOT NULL,
  `app_url` varchar(255) DEFAULT NULL,
  `request_date` datetime NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `processed_date` datetime DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  CONSTRAINT `api_access_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
