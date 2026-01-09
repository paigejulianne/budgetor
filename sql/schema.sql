-- Budgetor Database Schema
-- Run with: sudo mariadb < sql/schema.sql

-- Create database
CREATE DATABASE IF NOT EXISTS budgetor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE budgetor;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    initial_balance DECIMAL(15,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Budget items (rows like "Rent", "Power Bill", etc.)
CREATE TABLE IF NOT EXISTS budget_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    notes TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_sort (user_id, sort_order)
) ENGINE=InnoDB;

-- Date columns (the date headers)
CREATE TABLE IF NOT EXISTS date_columns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date_value DATE NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_user_date (user_id, date_value),
    INDEX idx_user_sort (user_id, sort_order)
) ENGINE=InnoDB;

-- Entries (individual cell values)
CREATE TABLE IF NOT EXISTS entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    budget_item_id INT NOT NULL,
    date_column_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (budget_item_id) REFERENCES budget_items(id) ON DELETE CASCADE,
    FOREIGN KEY (date_column_id) REFERENCES date_columns(id) ON DELETE CASCADE,
    UNIQUE KEY uk_item_date (budget_item_id, date_column_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- Entry history (tracks all changes for audit trail)
CREATE TABLE IF NOT EXISTS entry_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_id INT,
    user_id INT NOT NULL,
    budget_item_id INT NOT NULL,
    date_column_id INT NOT NULL,
    amount DECIMAL(15,2),
    action ENUM('created', 'updated', 'deleted') NOT NULL,
    notes TEXT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_item_date (budget_item_id, date_column_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- Grant privileges to web user (adjust username as needed)
-- GRANT ALL PRIVILEGES ON budgetor.* TO 'www-data'@'localhost' IDENTIFIED BY 'your_password_here';
-- FLUSH PRIVILEGES;
