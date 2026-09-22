CREATE DATABASE IF NOT EXISTS `gantavya_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `gantavya_db`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(20) NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_active_index` (`role`, `is_active`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `products` (
  `id` VARCHAR(10) NOT NULL,
  `type` ENUM('vehicle', 'trek', 'intl') NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NOT NULL,
  `base_price` DECIMAL(12,2) NOT NULL,
  `image` VARCHAR(255) NOT NULL,
  `max_pax` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `products_type_active_index` (`type`, `is_active`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `bookings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_code` VARCHAR(32) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `product_id` VARCHAR(10) NOT NULL,
  `service_type` ENUM('vehicle', 'trek', 'intl') NOT NULL,
  `product_title` VARCHAR(150) NOT NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `customer_email` VARCHAR(190) NOT NULL,
  `customer_phone` VARCHAR(20) NOT NULL,
  `service_date` DATE NOT NULL,
  `end_date` DATE NULL,
  `pickup_address` VARCHAR(255) NOT NULL,
  `destination_address` VARCHAR(255) NOT NULL,
  `travelers` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `days` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `metrics` VARCHAR(150) NOT NULL,
  `unit_price` DECIMAL(12,2) NOT NULL,
  `subtotal_amount` DECIMAL(12,2) NOT NULL,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `total_amount` DECIMAL(12,2) NOT NULL,
  `special_requests` TEXT NULL,
  `admin_note` TEXT NULL,
  `payment_status` ENUM('unpaid', 'initiated', 'pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'unpaid',
  `booking_status` ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  `paid_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bookings_code_unique` (`booking_code`),
  KEY `bookings_user_created_index` (`user_id`, `created_at`),
  KEY `bookings_status_index` (`booking_status`, `payment_status`),
  KEY `bookings_service_date_index` (`service_date`),
  CONSTRAINT `bookings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `bookings_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `provider` ENUM('khalti') NOT NULL DEFAULT 'khalti',
  `attempt_no` SMALLINT UNSIGNED NOT NULL,
  `purchase_order_id` VARCHAR(64) NOT NULL,
  `callback_token_hash` CHAR(64) NOT NULL,
  `pidx` VARCHAR(100) NULL,
  `transaction_id` VARCHAR(100) NULL,
  `amount_paisa` BIGINT UNSIGNED NOT NULL,
  `provider_status` VARCHAR(50) NOT NULL DEFAULT 'Initiated',
  `payment_url` TEXT NULL,
  `expires_at` DATETIME NULL,
  `raw_response` LONGTEXT NULL,
  `verified_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_purchase_order_unique` (`purchase_order_id`),
  UNIQUE KEY `payments_pidx_unique` (`pidx`),
  UNIQUE KEY `payments_transaction_unique` (`transaction_id`),
  UNIQUE KEY `payments_booking_attempt_unique` (`booking_id`, `attempt_no`),
  KEY `payments_status_index` (`provider_status`, `created_at`),
  CONSTRAINT `payments_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO `products` (`id`, `type`, `title`, `description`, `base_price`, `image`, `max_pax`, `is_active`) VALUES
('v1', 'vehicle', 'Mahindra Scorpio', 'Great for long trips, rough roads, highways, and mountain travel.', 7500.00, 'images/scorpio.jpg', 7, 1),
('v2', 'vehicle', 'Tourist Bus', 'Spacious seating with a comfortable ride, perfect for group travel and long-distance trips.', 18000.00, 'images/bus.jpg', 35, 1),
('v3', 'vehicle', 'Premium Car', 'Enjoy a smooth and comfortable ride, perfect for business trips, city tours, and airport transfers.', 4500.00, 'images/car.jpg', 4, 1),
('v4', 'vehicle', 'Adventure Motorbike', 'High-performance Royal Enfield or Himalayan bikes for solo or duo expeditions.', 2500.00, 'images/bike.jpg', 2, 1),
('v5', 'vehicle', 'City Scooty', 'Easy to ride and fuel-efficient for city travel and short trips.', 1200.00, 'images/scooty.jpg', 2, 1),
('v6', 'vehicle', 'Electric EV Van', 'Eco-friendly and comfortable 16-seater electric van for group tours and long drives.', 12000.00, 'images/ev-van.jpg', 16, 1),
('t1', 'trek', 'Everest Base Camp (EBC)', 'A guided Himalayan trek with trail support, tea house stays, and experienced guides.', 75000.00, 'images/ebc.jpg', 15, 1),
('t2', 'trek', 'Annapurna Base Camp (ABC)', 'Explore scenic trails surrounded by rhododendron forests and Himalayan landscapes.', 20000.00, 'images/abc.jpg', 15, 1),
('t3', 'trek', 'Tilicho Lake', 'Trek to one of the world\'s highest glacial lakes through the Manang Valley.', 20000.00, 'images/tilicho.jpg', 15, 1),
('t4', 'trek', 'Gosaikunda Lake', 'Explore the sacred freshwater lake surrounded by mountains and peaceful trails.', 15000.00, 'images/gosaikunda.jpg', 15, 1),
('t5', 'trek', 'Langtang Valley', 'Discover Tamang culture, Kyanjin Gompa, and the Langtang Himalayan landscape.', 20000.00, 'images/langtang.jpg', 15, 1),
('t6', 'trek', 'Dhorpatan', 'Explore Nepal\'s only hunting reserve with grasslands, wildlife, and nature trails.', 15000.00, 'images/dhorpatan.jpg', 15, 1),
('t7', 'trek', 'Mardi Himal', 'A popular short trek with Machhapuchhre views and peaceful forest trails.', 15000.00, 'images/mardi.jpg', 15, 1),
('t8', 'trek', 'Panchpokhari', 'Trek to five sacred lakes at the base of Jugal Himal.', 15000.00, 'images/panchpokhari.jpg', 15, 1),
('t9', 'trek', 'Bhairavkunda Lake', 'Explore sacred Bhairav Kunda Lake with mountain views and guided support.', 7000.00, 'images/bhairavkunda.jpg', 15, 1),
('i1', 'intl', 'Thailand', 'Explore beaches and temples across Bangkok and Phuket with a structured itinerary.', 95000.00, 'images/thailand.jpg', 10, 1),
('i2', 'intl', 'Dubai', 'Return flights, hotel reservation, desert safari, and visa processing support.', 115000.00, 'images/dubai.jpg', 10, 1),
('i3', 'intl', 'Switzerland', 'Experience the Swiss Alps, lakes, and rail journeys in a European package.', 250000.00, 'images/switzerland.jpg', 10, 1),
('i4', 'intl', 'Italy', 'Discover Rome, Venice, and Florence with guided tours and hotel stays.', 210000.00, 'images/italy.jpg', 10, 1),
('i5', 'intl', 'Japan', 'Experience Tokyo, Kyoto, Mount Fuji, and high-speed rail journeys.', 230000.00, 'images/japan.jpg', 10, 1),
('i6', 'intl', 'UK', 'Tour London, Edinburgh, and historic destinations across the United Kingdom.', 240000.00, 'images/uk.jpg', 10, 1),
('i7', 'intl', 'USA', 'Visit New York, Washington D.C., Las Vegas, and Los Angeles.', 320000.00, 'images/usa.jpg', 10, 1),
('i8', 'intl', 'France', 'Explore Paris, the Louvre, and the French Riviera with guided excursions.', 220000.00, 'images/france.jpg', 10, 1),
('i9', 'intl', 'Greece', 'Experience Santorini, Mykonos, and the history of Athens.', 200000.00, 'images/greece.jpg', 10, 1),
('i10', 'intl', 'China', 'Visit Beijing, the Great Wall, Shanghai, and Chengdu with travel support.', 140000.00, 'images/china.jpg', 10, 1),
('i11', 'intl', 'India', 'Journey across Delhi, Agra, and Jaipur with transport and hotel options.', 45000.00, 'images/india.jpg', 10, 1),
('i12', 'intl', 'Vietnam', 'Cruise Ha Long Bay, explore Hanoi, and visit vibrant floating markets.', 85000.00, 'images/vietnam.jpg', 10, 1)
ON DUPLICATE KEY UPDATE
  `type` = VALUES(`type`),
  `title` = VALUES(`title`),
  `description` = VALUES(`description`),
  `base_price` = VALUES(`base_price`),
  `image` = VALUES(`image`),
  `max_pax` = VALUES(`max_pax`);
