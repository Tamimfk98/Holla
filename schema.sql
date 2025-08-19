-- eSports Tournament Management System Database Schema
-- Core PHP 8+ Application
-- Created: August 19, 2025

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Database charset and collation
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','super_admin') NOT NULL DEFAULT 'admin',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_admin_status` (`status`),
  KEY `idx_admin_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Insert default admin account
-- Username: admin, Password: admin123 (change after first login)
--

INSERT INTO `admins` (`username`, `password`, `role`, `status`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `wallet_balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','suspended','banned') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_user_status` (`status`),
  KEY `idx_user_email` (`email`),
  KEY `idx_user_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tournaments`
--

CREATE TABLE `tournaments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `game_type` varchar(100) NOT NULL,
  `max_teams` int(11) NOT NULL DEFAULT 64,
  `entry_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `prize_pool` decimal(10,2) NOT NULL DEFAULT 0.00,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `status` enum('upcoming','active','completed','cancelled') NOT NULL DEFAULT 'upcoming',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tournament_status` (`status`),
  KEY `idx_tournament_game` (`game_type`),
  KEY `idx_tournament_dates` (`start_date`, `end_date`),
  KEY `idx_tournament_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tournament_registrations`
--

CREATE TABLE `tournament_registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `team_name` varchar(100) NOT NULL,
  `team_members` text,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_tournament` (`user_id`, `tournament_id`),
  KEY `idx_registration_user` (`user_id`),
  KEY `idx_registration_tournament` (`tournament_id`),
  KEY `idx_registration_status` (`status`),
  KEY `idx_registration_created` (`created_at`),
  CONSTRAINT `fk_registration_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_registration_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` enum('bkash','nagad','rocket','wallet') NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `transaction_id` varchar(100) NOT NULL,
  `status` enum('pending','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `admin_notes` text,
  `processed_at` timestamp NULL DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_registration_payment` (`registration_id`),
  KEY `idx_payment_status` (`status`),
  KEY `idx_payment_method` (`method`),
  KEY `idx_payment_transaction` (`transaction_id`),
  KEY `idx_payment_created` (`created_at`),
  KEY `fk_payment_processed_by` (`processed_by`),
  CONSTRAINT `fk_payment_registration` FOREIGN KEY (`registration_id`) REFERENCES `tournament_registrations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_payment_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `matches`
--

CREATE TABLE `matches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `team1_id` int(11) NOT NULL,
  `team2_id` int(11) NOT NULL,
  `round` varchar(50) DEFAULT NULL,
  `scheduled_date` datetime DEFAULT NULL,
  `winner_id` int(11) DEFAULT NULL,
  `score1` int(11) DEFAULT NULL,
  `score2` int(11) DEFAULT NULL,
  `status` enum('scheduled','live','completed','cancelled','pending_review') NOT NULL DEFAULT 'scheduled',
  `notes` text,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_match_tournament` (`tournament_id`),
  KEY `idx_match_team1` (`team1_id`),
  KEY `idx_match_team2` (`team2_id`),
  KEY `idx_match_winner` (`winner_id`),
  KEY `idx_match_status` (`status`),
  KEY `idx_match_scheduled` (`scheduled_date`),
  KEY `idx_match_round` (`round`),
  CONSTRAINT `fk_match_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_match_team1` FOREIGN KEY (`team1_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_match_team2` FOREIGN KEY (`team2_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_match_winner` FOREIGN KEY (`winner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `match_screenshots`
--

CREATE TABLE `match_screenshots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `match_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `screenshot_url` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_match_team_screenshot` (`match_id`, `team_id`),
  KEY `idx_screenshot_match` (`match_id`),
  KEY `idx_screenshot_team` (`team_id`),
  KEY `idx_screenshot_uploaded` (`uploaded_at`),
  CONSTRAINT `fk_screenshot_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_screenshot_team` FOREIGN KEY (`team_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('add','subtract','transfer','prize','refund') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `balance_after` decimal(10,2) NOT NULL,
  `description` varchar(255) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` enum('payment','prize','admin','transfer') DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wallet_user` (`user_id`),
  KEY `idx_wallet_type` (`type`),
  KEY `idx_wallet_reference` (`reference_type`, `reference_id`),
  KEY `idx_wallet_created` (`created_at`),
  KEY `fk_wallet_created_by` (`created_by`),
  CONSTRAINT `fk_wallet_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_wallet_created_by` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prizes`
--

CREATE TABLE `prizes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `position` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `winner_id` int(11) DEFAULT NULL,
  `claimed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prize_tournament` (`tournament_id`),
  KEY `idx_prize_position` (`position`),
  KEY `idx_prize_winner` (`winner_id`),
  KEY `idx_prize_claimed` (`claimed_at`),
  CONSTRAINT `fk_prize_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_prize_winner` FOREIGN KEY (`winner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') NOT NULL DEFAULT 'info',
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notification_user` (`user_id`),
  KEY `idx_notification_type` (`type`),
  KEY `idx_notification_read` (`read_at`),
  KEY `idx_notification_created` (`created_at`),
  CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` enum('string','integer','boolean','json') NOT NULL DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Insert default settings
--

INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('site_name', 'eSports Hub', 'string', 'Website name'),
('site_description', 'Professional eSports Tournament Management Platform', 'string', 'Website description'),
('default_currency', 'BDT', 'string', 'Default currency code'),
('currency_symbol', '৳', 'string', 'Currency symbol'),
('max_upload_size', '5242880', 'integer', 'Maximum file upload size in bytes (5MB)'),
('allowed_image_types', '["image/jpeg","image/png","image/gif","image/webp"]', 'json', 'Allowed image MIME types'),
('tournament_auto_approve', 'false', 'boolean', 'Auto-approve tournament registrations'),
('payment_methods', '["bkash","nagad","rocket"]', 'json', 'Available payment methods'),
('bkash_merchant_number', '01XXXXXXXXX', 'string', 'bKash merchant number'),
('nagad_merchant_number', '01XXXXXXXXX', 'string', 'Nagad merchant number'),
('rocket_merchant_number', '01XXXXXXXXX', 'string', 'Rocket merchant number'),
('email_notifications', 'true', 'boolean', 'Enable email notifications'),
('maintenance_mode', 'false', 'boolean', 'Enable maintenance mode');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_admin` (`admin_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_table` (`table_name`),
  KEY `idx_audit_record` (`record_id`),
  KEY `idx_audit_created` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Views for reporting and analytics
--

--
-- View for tournament statistics
--
CREATE VIEW `tournament_stats` AS
SELECT 
    t.id,
    t.name,
    t.game_type,
    t.status,
    t.max_teams,
    t.entry_fee,
    t.prize_pool,
    COUNT(DISTINCT tr.id) as total_registrations,
    COUNT(DISTINCT CASE WHEN tr.status = 'approved' THEN tr.id END) as approved_registrations,
    COUNT(DISTINCT m.id) as total_matches,
    COUNT(DISTINCT CASE WHEN m.status = 'completed' THEN m.id END) as completed_matches,
    SUM(DISTINCT CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) as total_revenue,
    t.created_at,
    t.start_date,
    t.end_date
FROM tournaments t
LEFT JOIN tournament_registrations tr ON t.id = tr.tournament_id
LEFT JOIN matches m ON t.id = m.tournament_id
LEFT JOIN payments p ON tr.id = p.registration_id
GROUP BY t.id;

--
-- View for user statistics
--
CREATE VIEW `user_stats` AS
SELECT 
    u.id,
    u.username,
    u.email,
    u.full_name,
    u.status,
    u.wallet_balance,
    COUNT(DISTINCT tr.id) as total_registrations,
    COUNT(DISTINCT CASE WHEN tr.status = 'approved' THEN tr.id END) as approved_registrations,
    COUNT(DISTINCT m.id) as total_matches,
    COUNT(DISTINCT CASE WHEN m.winner_id = u.id THEN m.id END) as matches_won,
    SUM(DISTINCT CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) as total_spent,
    u.created_at,
    u.last_login
FROM users u
LEFT JOIN tournament_registrations tr ON u.id = tr.user_id
LEFT JOIN matches m ON (u.id = m.team1_id OR u.id = m.team2_id) AND m.status = 'completed'
LEFT JOIN payments p ON tr.id = p.registration_id
GROUP BY u.id;

--
-- View for payment statistics
--
CREATE VIEW `payment_stats` AS
SELECT 
    DATE(p.created_at) as payment_date,
    p.method,
    p.status,
    COUNT(*) as transaction_count,
    SUM(p.amount) as total_amount,
    AVG(p.amount) as average_amount
FROM payments p
GROUP BY DATE(p.created_at), p.method, p.status
ORDER BY payment_date DESC;

-- --------------------------------------------------------

--
-- Triggers for audit logging and data integrity
--

DELIMITER //

-- Trigger for user updates
CREATE TRIGGER `audit_users_update` 
AFTER UPDATE ON `users` 
FOR EACH ROW 
BEGIN
    INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, created_at)
    VALUES (NEW.id, 'UPDATE', 'users', NEW.id, 
            JSON_OBJECT('username', OLD.username, 'email', OLD.email, 'status', OLD.status, 'wallet_balance', OLD.wallet_balance),
            JSON_OBJECT('username', NEW.username, 'email', NEW.email, 'status', NEW.status, 'wallet_balance', NEW.wallet_balance),
            CURRENT_TIMESTAMP);
END//

-- Trigger for tournament registration updates
CREATE TRIGGER `audit_tournament_registrations_update` 
AFTER UPDATE ON `tournament_registrations` 
FOR EACH ROW 
BEGIN
    INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, created_at)
    VALUES (NEW.user_id, 'UPDATE', 'tournament_registrations', NEW.id, 
            JSON_OBJECT('status', OLD.status),
            JSON_OBJECT('status', NEW.status),
            CURRENT_TIMESTAMP);
END//

-- Trigger for payment updates
CREATE TRIGGER `audit_payments_update` 
AFTER UPDATE ON `payments` 
FOR EACH ROW 
BEGIN
    INSERT INTO audit_logs (admin_id, action, table_name, record_id, old_values, new_values, created_at)
    VALUES (NEW.processed_by, 'UPDATE', 'payments', NEW.id, 
            JSON_OBJECT('status', OLD.status, 'admin_notes', OLD.admin_notes),
            JSON_OBJECT('status', NEW.status, 'admin_notes', NEW.admin_notes),
            CURRENT_TIMESTAMP);
END//

-- Trigger for wallet balance updates
CREATE TRIGGER `track_wallet_changes` 
AFTER UPDATE ON `users` 
FOR EACH ROW 
BEGIN
    IF OLD.wallet_balance != NEW.wallet_balance THEN
        INSERT INTO wallet_transactions (user_id, type, amount, balance_after, description, created_at)
        VALUES (NEW.id, 
                CASE WHEN NEW.wallet_balance > OLD.wallet_balance THEN 'add' ELSE 'subtract' END,
                ABS(NEW.wallet_balance - OLD.wallet_balance),
                NEW.wallet_balance,
                'Wallet balance updated',
                CURRENT_TIMESTAMP);
    END IF;
END//

DELIMITER ;

-- --------------------------------------------------------

--
-- Indexes for performance optimization
--

-- Composite indexes for common queries
CREATE INDEX `idx_tournament_registrations_status_tournament` ON `tournament_registrations` (`status`, `tournament_id`);
CREATE INDEX `idx_payments_status_created` ON `payments` (`status`, `created_at`);
CREATE INDEX `idx_matches_tournament_status` ON `matches` (`tournament_id`, `status`);
CREATE INDEX `idx_matches_teams_status` ON `matches` (`team1_id`, `team2_id`, `status`);
CREATE INDEX `idx_wallet_transactions_user_type` ON `wallet_transactions` (`user_id`, `type`);

-- Full-text search indexes
ALTER TABLE `tournaments` ADD FULLTEXT(`name`, `description`);
ALTER TABLE `users` ADD FULLTEXT(`username`, `full_name`);

-- --------------------------------------------------------

--
-- Stored procedures for common operations
--

DELIMITER //

-- Procedure to calculate tournament revenue
CREATE PROCEDURE `GetTournamentRevenue`(IN tournament_id INT)
BEGIN
    SELECT 
        t.name as tournament_name,
        t.entry_fee,
        COUNT(tr.id) as total_registrations,
        COUNT(CASE WHEN p.status = 'completed' THEN 1 END) as paid_registrations,
        SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) as total_revenue,
        SUM(CASE WHEN p.status = 'pending' THEN p.amount ELSE 0 END) as pending_revenue
    FROM tournaments t
    LEFT JOIN tournament_registrations tr ON t.id = tr.tournament_id
    LEFT JOIN payments p ON tr.id = p.registration_id
    WHERE t.id = tournament_id
    GROUP BY t.id;
END//

-- Procedure to get user match statistics
CREATE PROCEDURE `GetUserMatchStats`(IN user_id INT)
BEGIN
    SELECT 
        COUNT(*) as total_matches,
        COUNT(CASE WHEN m.winner_id = user_id THEN 1 END) as matches_won,
        COUNT(CASE WHEN m.status = 'completed' AND m.winner_id != user_id THEN 1 END) as matches_lost,
        COUNT(CASE WHEN m.status IN ('scheduled', 'live') THEN 1 END) as upcoming_matches,
        ROUND(
            (COUNT(CASE WHEN m.winner_id = user_id THEN 1 END) * 100.0 / 
             NULLIF(COUNT(CASE WHEN m.status = 'completed' THEN 1 END), 0)), 2
        ) as win_percentage
    FROM matches m
    WHERE (m.team1_id = user_id OR m.team2_id = user_id);
END//

-- Procedure to update match result
CREATE PROCEDURE `UpdateMatchResult`(
    IN match_id INT,
    IN winner_id INT,
    IN score1 INT,
    IN score2 INT,
    IN notes TEXT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    UPDATE matches 
    SET winner_id = winner_id,
        score1 = score1,
        score2 = score2,
        notes = notes,
        status = 'completed',
        completed_at = CURRENT_TIMESTAMP
    WHERE id = match_id;
    
    -- Add notification for both teams
    INSERT INTO notifications (user_id, title, message, type)
    SELECT team1_id, 'Match Result Updated', 
           CONCAT('Your match result has been updated. ', 
                  CASE WHEN team1_id = winner_id THEN 'Congratulations, you won!' ELSE 'Better luck next time!' END),
           CASE WHEN team1_id = winner_id THEN 'success' ELSE 'info' END
    FROM matches WHERE id = match_id;
    
    INSERT INTO notifications (user_id, title, message, type)
    SELECT team2_id, 'Match Result Updated', 
           CONCAT('Your match result has been updated. ', 
                  CASE WHEN team2_id = winner_id THEN 'Congratulations, you won!' ELSE 'Better luck next time!' END),
           CASE WHEN team2_id = winner_id THEN 'success' ELSE 'info' END
    FROM matches WHERE id = match_id;
    
    COMMIT;
END//

DELIMITER ;

-- --------------------------------------------------------

-- Set auto_increment starting values
ALTER TABLE `admins` AUTO_INCREMENT = 1;
ALTER TABLE `users` AUTO_INCREMENT = 1;
ALTER TABLE `tournaments` AUTO_INCREMENT = 1;
ALTER TABLE `tournament_registrations` AUTO_INCREMENT = 1;
ALTER TABLE `payments` AUTO_INCREMENT = 1;
ALTER TABLE `matches` AUTO_INCREMENT = 1;
ALTER TABLE `match_screenshots` AUTO_INCREMENT = 1;
ALTER TABLE `wallet_transactions` AUTO_INCREMENT = 1;
ALTER TABLE `prizes` AUTO_INCREMENT = 1;
ALTER TABLE `notifications` AUTO_INCREMENT = 1;
ALTER TABLE `settings` AUTO_INCREMENT = 1;
ALTER TABLE `audit_logs` AUTO_INCREMENT = 1;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
