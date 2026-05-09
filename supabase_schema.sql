-- Supabase PostgreSQL Schema (Complete)

-- Create custom types for enums
DO $$ BEGIN
    CREATE TYPE game_name_enum AS ENUM ('Among Us', 'Minecraft', 'Free Fire', 'BGMI');
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

DO $$ BEGIN
    CREATE TYPE prize_style_enum AS ENUM ('single', 'top_3', 'per_kill');
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

DO $$ BEGIN
    CREATE TYPE tournament_status_enum AS ENUM ('active', 'completed', 'cancelled');
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

DO $$ BEGIN
    CREATE TYPE wallet_status_enum AS ENUM ('pending', 'approved', 'rejected');
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

DO $$ BEGIN
    CREATE TYPE user_role_enum AS ENUM ('admin', 'user');
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

DO $$ BEGIN
    CREATE TYPE user_status_enum AS ENUM ('active', 'inactive');
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    wallet_balance DECIMAL(10,2) DEFAULT 0.00,
    verification_token VARCHAR(100),
    is_email_verified BOOLEAN DEFAULT FALSE,
    role user_role_enum DEFAULT 'user',
    status user_status_enum DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Games table
CREATE TABLE IF NOT EXISTS games (
    game_id SERIAL PRIMARY KEY,
    game_name VARCHAR(100) NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tournaments table
CREATE TABLE IF NOT EXISTS tournaments (
    tournament_id SERIAL PRIMARY KEY,
    owner_id INT,
    tournament_name VARCHAR(100) NOT NULL,
    game_name VARCHAR(100) NOT NULL, -- Changed from enum to varchar to support games table
    tournament_date TIMESTAMP NOT NULL,
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
    prize_style prize_style_enum DEFAULT 'single',
    second_prize DECIMAL(10,2),
    third_prize DECIMAL(10,2),
    per_kill_prize DECIMAL(10,2),
    status tournament_status_enum DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(user_id)
);

-- Tournament Participants table
CREATE TABLE IF NOT EXISTS tournament_participants (
    participant_id SERIAL PRIMARY KEY,
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
CREATE TABLE IF NOT EXISTS tournament_reports (
    report_id SERIAL PRIMARY KEY,
    tournament_id INT,
    reporter_id INT,
    report_reason TEXT NOT NULL,
    is_valid BOOLEAN DEFAULT FALSE,
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(tournament_id),
    FOREIGN KEY (reporter_id) REFERENCES users(user_id)
);

-- Tournament Winners table
CREATE TABLE IF NOT EXISTS tournament_winners (
    winner_id SERIAL PRIMARY KEY,
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
CREATE TABLE IF NOT EXISTS wallet_recharges (
    recharge_id SERIAL PRIMARY KEY,
    user_id INT,
    amount DECIMAL(10,2) NOT NULL,
    transaction_id VARCHAR(100) NOT NULL,
    status wallet_status_enum DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Wallet Withdrawals table
CREATE TABLE IF NOT EXISTS wallet_withdrawals (
    withdrawal_id SERIAL PRIMARY KEY,
    user_id INT,
    amount DECIMAL(10,2) NOT NULL,
    upi_id VARCHAR(100) NOT NULL,
    status wallet_status_enum DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
    setting_id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Audit Logs table
CREATE TABLE IF NOT EXISTS audit_logs (
    log_id SERIAL PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Insert Default Settings
INSERT INTO settings (setting_key, setting_value) VALUES 
('admin_upi_id', 'example@upi'),
('admin_qr_path', 'assets/images/qr_placeholder.png'),
('smtp_host', 'smtp.gmail.com'),
('smtp_port', '587'),
('smtp_user', ''),
('smtp_pass', ''),
('smtp_from_email', ''),
('smtp_from_name', 'Tournament Admin')
ON CONFLICT (setting_key) DO NOTHING;

-- Insert Initial Games
INSERT INTO games (game_name, image_url) VALUES 
('Free Fire', 'assets/images/games/freefire.webp'),
('BGMI', 'assets/images/games/bgmi.webp'),
('Minecraft', 'assets/images/games/minecraft.webp'),
('Among Us', 'assets/images/games/amongus.webp')
ON CONFLICT DO NOTHING;
