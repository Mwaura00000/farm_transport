-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Mar 23, 2026 at 11:34 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `farm_transport_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `transport_request_id` int(11) NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `transporter_id` int(11) NOT NULL,
  `pickup_location` varchar(255) DEFAULT NULL,
  `destination_location` varchar(255) DEFAULT NULL,
  `distance` decimal(10,2) DEFAULT NULL,
  `cargo_type` varchar(50) DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transport_requests`
--

CREATE TABLE `transport_requests` (
  `id` int(11) NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `transporter_id` int(11) DEFAULT NULL,
  `produce_id` int(11) DEFAULT NULL,
  `cargo_type` enum('perishable','livestock','grain','equipment','fertilizer') NOT NULL,
  `pickup_location` varchar(255) NOT NULL,
  `pickup_county` varchar(100) DEFAULT NULL,
  `pickup_town` varchar(100) DEFAULT NULL,
  `pickup_exact_address` varchar(255) DEFAULT NULL,
  `pickup_description` text DEFAULT NULL,
  `destination_location` varchar(255) NOT NULL,
  `delivery_county` varchar(100) DEFAULT NULL,
  `delivery_town` varchar(100) DEFAULT NULL,
  `delivery_exact_address` varchar(255) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `distance` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','accepted','in_transit','delivered','cancelled') DEFAULT 'pending',
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `otp_code` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transport_requests`
--

INSERT INTO `transport_requests` (`id`, `farmer_id`, `transporter_id`, `produce_id`, `cargo_type`, `pickup_location`, `pickup_county`, `pickup_town`, `pickup_exact_address`, `pickup_description`, `destination_location`, `delivery_county`, `delivery_town`, `delivery_exact_address`, `emergency_contact_name`, `emergency_contact_phone`, `distance`, `total_price`, `status`, `request_date`, `otp_code`) VALUES
(8, 14, 15, 10, '', '-0.172054, 35.974353', 'Nakuru', 'Njoro', 'near kabarak university', 'tarmac', '', 'Kakamega', 'Khayega', 'Kakamega Municipal Market', 'John', '0708663288', 192.20, NULL, 'delivered', '2026-03-24 04:15:00', '3414'),
(9, 14, 15, 11, '', '-0.172054, 35.974353', 'Nakuru', 'Molo', 'near olrangai school', 'tarmac', '', 'Mombasa', 'Changamwe', 'Kongowea Market', 'Brian Mwaura', '0708663288', 650.00, NULL, 'accepted', '2026-03-30 04:30:00', '0690');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'farmer',
  `verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `role`, `verified`, `created_at`) VALUES
(9, 'System Admin', 'admin@agrimove.com', '0700000000', '$2y$10$n.J3LvEZLCa3I00CEXPLgOvEKxFArFQT37sXF.KDILhvxYnawyBR2', 'admin', 0, '2026-03-21 17:17:36'),
(14, 'Esther Lavaya', 'esther@gmail.com', '0717542053', '$2y$10$9VaLjWPnVCrWxVK66brSSu1tQxbrBoha3ZCfX/o2F.ybTGcaGVr2K', 'farmer', 0, '2026-03-22 04:11:30'),
(15, 'Brian Mwaura', 'mwaura@gmail.com', '0708663288', '$2y$10$Tmf.U1LQ9J5HIxlNoDdSOeX9AWh2MvhRoka8m95LQ1OU2M5y5MjmG', 'transporter', 0, '2026-03-22 04:14:01'),
(16, 'Samuel Kiragu', 'samuel@gmail.com', '0728103657', '$2y$10$JsXb2HLq/r/q8kiu0pmFJOyVlTiNgCnZGVcnxLFXxIrhRhIQRxIKu', 'transporter', 0, '2026-03-22 04:23:57');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transport_request_id` (`transport_request_id`),
  ADD KEY `farmer_id` (`farmer_id`),
  ADD KEY `transporter_id` (`transporter_id`);

--
-- Indexes for table `transport_requests`
--
ALTER TABLE `transport_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `farmer_id` (`farmer_id`),
  ADD KEY `transporter_id` (`transporter_id`),
  ADD KEY `produce_id` (`produce_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transport_requests`
--
ALTER TABLE `transport_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`transport_request_id`) REFERENCES `transport_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`transporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transport_requests`
--
ALTER TABLE `transport_requests`
  ADD CONSTRAINT `transport_requests_ibfk_1` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transport_requests_ibfk_2` FOREIGN KEY (`transporter_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `transport_requests_ibfk_3` FOREIGN KEY (`produce_id`) REFERENCES `produce` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
