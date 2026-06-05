
<?php
// db_setup.php
require_once __DIR__ . '/db.php';

try {
    echo "Starting database tables creation...\n";
    
    // Create users table
    $usersTable = "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `telegram_id` VARCHAR(255) NOT NULL UNIQUE,
        `devices` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($usersTable);
    echo "Table 'users' created or already exists.\n";
    
    // Create verifications table
    $verificationsTable = "CREATE TABLE IF NOT EXISTS `verifications` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `bot` VARCHAR(255) NOT NULL,
        `verified_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `user_bot_unique` (`user_id`, `bot`),
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($verificationsTable);
    echo "Table 'verifications' created or already exists.\n";
    
    // Create webhooks table
    $webhooksTable = "CREATE TABLE IF NOT EXISTS `webhooks` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `telegram_id` VARCHAR(255) NOT NULL,
        `bot` VARCHAR(255) NOT NULL,
        `url` TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `tg_bot_webhook` (`telegram_id`, `bot`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($webhooksTable);
    echo "Table 'webhooks' created or already exists.\n";
    
    echo "Database setup completed successfully!\n";
} catch (PDOException $e) {
    echo "Error setting up database: " . $e->getMessage() . "\n";
    exit(1);
}
