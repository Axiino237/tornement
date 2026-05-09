-- Create database
CREATE DATABASE IF NOT EXISTS gaming41tournament;
USE gaming41tournament;

-- Users table
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    wallet_balance DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tournaments table
CREATE TABLE tournaments (
    tournament_id INT PRIMARY KEY AUTO_INCREMENT,
    owner_id INT,
    tournament_name VARCHAR(100) NOT NULL,
    game_name ENUM('Among Us', 'Minecraft', 'Free Fire', 'BGMI') NOT NULL,
    tournament_date DATETIME NOT NULL,
    max_players INT NOT NULL,
    is_team_based BOOLEAN DEFAULT FALSE,
    team_size INT,
    max_teams INT,
    room_id VARCHAR(50),
    room_password VARCHAR(50),
    is_paid BOOLEAN DEFAULT FALSE,
    registration_fee DECIMAL(10,2),
    winning_prize DECIMAL(10,2),
    upi_id VARCHAR(50),
    contact_info TEXT,
    auto_approval BOOLEAN DEFAULT FALSE,
    prize_style ENUM('single', 'top_3', 'per_kill') DEFAULT 'single',
    second_prize DECIMAL(10,2),
    third_prize DECIMAL(10,2),
    per_kill_prize DECIMAL(10,2),
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(user_id)
);

-- Tournament Participants table
CREATE TABLE tournament_participants (
    participant_id INT PRIMARY KEY AUTO_INCREMENT,
    tournament_id INT,
    user_id INT,
    team_name VARCHAR(50),
    transaction_id VARCHAR(100),
    is_approved BOOLEAN DEFAULT FALSE,
    kills INT DEFAULT 0,
    prize_awarded DECIMAL(10,2) DEFAULT 0.00,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(tournament_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Tournament Reports table
CREATE TABLE tournament_reports (
    report_id INT PRIMARY KEY AUTO_INCREMENT,
    tournament_id INT,
    reporter_id INT,
    report_reason TEXT NOT NULL,
    is_valid BOOLEAN DEFAULT FALSE,
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(tournament_id),
    FOREIGN KEY (reporter_id) REFERENCES users(user_id)
);

-- Tournament Winners table
CREATE TABLE tournament_winners (
    winner_id INT PRIMARY KEY AUTO_INCREMENT,
    tournament_id INT,
    user_id INT,
    team_name VARCHAR(50),
    position INT,
    kills INT DEFAULT 0,
    prize_awarded DECIMAL(10,2) DEFAULT 0.00,
    declared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(tournament_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Wallet Recharges table
CREATE TABLE wallet_recharges (
    recharge_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    amount DECIMAL(10,2) NOT NULL,
    transaction_id VARCHAR(100) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Wallet Withdrawals table
CREATE TABLE wallet_withdrawals (
    withdrawal_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    amount DECIMAL(10,2) NOT NULL,
    upi_id VARCHAR(100) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);