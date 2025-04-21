-- MySQL Schema for Parking Management System

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS parking_db;
USE parking_db;

-- Create ENUM types using MySQL syntax
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    contact_no VARCHAR(20) NOT NULL,
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    society_name VARCHAR(100),
    company_name VARCHAR(100),
    user_type ENUM('admin', 'user') DEFAULT 'user',
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS vehicles (
    vehicle_id VARCHAR(10) PRIMARY KEY,
    user_id INT,
    vehicle_type ENUM('2-wheeler', '4-wheeler', 'commercial') NOT NULL,
    vehicle_number VARCHAR(20) NOT NULL UNIQUE,
    vehicle_make VARCHAR(50),
    vehicle_model VARCHAR(50),
    vehicle_color VARCHAR(30),
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS parking_areas (
    area_id INT AUTO_INCREMENT PRIMARY KEY,
    area_name VARCHAR(50) NOT NULL,
    total_2_wheeler_slots INT NOT NULL DEFAULT 0,
    total_4_wheeler_slots INT NOT NULL DEFAULT 0,
    total_commercial_slots INT NOT NULL DEFAULT 0,
    occupied_2_wheeler_slots INT NOT NULL DEFAULT 0,
    occupied_4_wheeler_slots INT NOT NULL DEFAULT 0,
    occupied_commercial_slots INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS parking_transactions (
    transaction_id VARCHAR(10) PRIMARY KEY,
    vehicle_id VARCHAR(10),
    area_id INT,
    entry_time TIMESTAMP NOT NULL,
    exit_time TIMESTAMP NULL,
    expected_duration VARCHAR(20),
    status ENUM('in', 'out', 'completed') DEFAULT 'in',
    bill_amount DECIMAL(10,2) DEFAULT 0.00,
    bill_status ENUM('pending', 'paid') DEFAULT 'pending',
    feedback TEXT,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id),
    FOREIGN KEY (area_id) REFERENCES parking_areas(area_id)
);

CREATE TABLE IF NOT EXISTS payments (
    payment_id VARCHAR(10) PRIMARY KEY,
    transaction_id VARCHAR(10),
    amount DECIMAL(10,2) NOT NULL,
    payment_type ENUM('cash', 'online') NOT NULL,
    payment_date TIMESTAMP NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    payment_ref_photo VARCHAR(255),
    FOREIGN KEY (transaction_id) REFERENCES parking_transactions(transaction_id)
);

CREATE TABLE IF NOT EXISTS parking_rates (
    rate_id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_type ENUM('2-wheeler', '4-wheeler', 'commercial') NOT NULL,
    hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    daily_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    weekly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    monthly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00
);

-- Insert default parking rates
INSERT INTO parking_rates (vehicle_type, hourly_rate, daily_rate, weekly_rate, monthly_rate)
VALUES 
('2-wheeler', 20.00, 100.00, 500.00, 1500.00),
('4-wheeler', 40.00, 200.00, 1000.00, 3000.00),
('commercial', 60.00, 300.00, 1500.00, 4500.00);

-- Insert initial admin user (password: admin123)
INSERT INTO users (username, password, full_name, email, contact_no, user_type)
VALUES ('admin', '$2y$10$8H.hHqY9yGYB4BfwQPeB2eQgbhHvxaH8h1lBIQJwFQBZ.F4Ywj/hy', 'System Administrator', 'admin@parking.com', '1234567890', 'admin');

-- Insert sample parking areas
INSERT INTO parking_areas (area_name, total_2_wheeler_slots, total_4_wheeler_slots, total_commercial_slots)
VALUES 
('A Block', 100, 50, 20),
('B Block', 80, 40, 15),
('C Block', 120, 60, 25);
   

CREATE TABLE IF NOT EXISTS contact_messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    submission_date DATETIME NOT NULL,
    status ENUM('new', 'read', 'replied') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
