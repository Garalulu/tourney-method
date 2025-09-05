-- Tournament Method Database Schema
-- Secure SQLite schema with proper constraints and indexes
-- Created: 2025-09-04

PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;

-- Users table for admin authentication
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    osu_id INTEGER UNIQUE NOT NULL,
    username VARCHAR(255) NOT NULL,
    is_admin BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME,
    
    -- Constraints
    CHECK (osu_id > 0),
    CHECK (username != '')
);

-- Create index for fast osu_id lookups
CREATE INDEX idx_users_osu_id ON users(osu_id);
CREATE INDEX idx_users_admin ON users(is_admin) WHERE is_admin = TRUE;

-- Tournaments table with proper validation
CREATE TABLE tournaments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    osu_topic_id INTEGER UNIQUE NOT NULL,
    title VARCHAR(500) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending_review',
    
    -- Tournament details
    rank_range_min INTEGER,
    rank_range_max INTEGER,
    team_size INTEGER,
    max_teams INTEGER,
    registration_open DATETIME,
    registration_close DATETIME,
    tournament_start DATETIME,
    
    -- URLs (storing only IDs/slugs for security)
    google_sheet_id VARCHAR(100),
    forum_url_slug VARCHAR(100),
    google_form_id VARCHAR(100),
    challonge_slug VARCHAR(100),
    youtube_id VARCHAR(20),
    twitch_username VARCHAR(50),
    
    -- Metadata
    raw_post_content TEXT,
    parsed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME,
    approved_by INTEGER,
    
    -- Constraints
    CHECK (status IN ('pending_review', 'approved', 'rejected', 'archived')),
    CHECK (osu_topic_id > 0),
    CHECK (title != ''),
    CHECK (rank_range_min IS NULL OR rank_range_min >= 0),
    CHECK (rank_range_max IS NULL OR rank_range_max >= 0),
    CHECK (rank_range_min IS NULL OR rank_range_max IS NULL OR rank_range_min <= rank_range_max),
    CHECK (team_size IS NULL OR team_size > 0),
    CHECK (max_teams IS NULL OR max_teams > 0),
    
    -- Foreign key
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Indexes for tournaments
CREATE INDEX idx_tournaments_status ON tournaments(status);
CREATE INDEX idx_tournaments_topic_id ON tournaments(osu_topic_id);
CREATE INDEX idx_tournaments_dates ON tournaments(tournament_start, registration_close);
CREATE INDEX idx_tournaments_rank_range ON tournaments(rank_range_min, rank_range_max);

-- System logs for error tracking and auditing
CREATE TABLE system_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    level VARCHAR(10) NOT NULL,
    message TEXT NOT NULL,
    context TEXT, -- JSON for additional context
    source VARCHAR(100), -- script/function that generated the log
    user_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Constraints
    CHECK (level IN ('DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY')),
    CHECK (message != ''),
    
    -- Foreign key
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Index for log queries
CREATE INDEX idx_system_logs_level ON system_logs(level);
CREATE INDEX idx_system_logs_created ON system_logs(created_at);
CREATE INDEX idx_system_logs_source ON system_logs(source);

-- Sessions table for secure session management
CREATE TABLE sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INTEGER NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    
    -- Security tracking
    csrf_token VARCHAR(64),
    is_admin_session BOOLEAN DEFAULT FALSE,
    
    -- Constraints
    CHECK (expires_at > created_at),
    
    -- Foreign key
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Session indexes
CREATE INDEX idx_sessions_user_id ON sessions(user_id);
CREATE INDEX idx_sessions_expires ON sessions(expires_at);
CREATE INDEX idx_sessions_last_activity ON sessions(last_activity);

-- API rate limiting table
CREATE TABLE api_rate_limits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    endpoint VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    request_count INTEGER DEFAULT 1,
    window_start DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Compound unique constraint
    UNIQUE(endpoint, ip_address, window_start)
);

-- Rate limiting index
CREATE INDEX idx_api_rate_limits_endpoint_ip ON api_rate_limits(endpoint, ip_address);
CREATE INDEX idx_api_rate_limits_window ON api_rate_limits(window_start);

-- Insert initial admin user (replace with actual osu! ID)
-- INSERT INTO users (osu_id, username, is_admin) VALUES (123456, 'YourOsuUsername', TRUE);

-- Create views for common queries
CREATE VIEW pending_tournaments AS
SELECT 
    id,
    title,
    rank_range_min,
    rank_range_max,
    parsed_at,
    osu_topic_id
FROM tournaments 
WHERE status = 'pending_review'
ORDER BY parsed_at DESC;

CREATE VIEW approved_tournaments AS
SELECT 
    t.*,
    u.username as approved_by_username
FROM tournaments t
LEFT JOIN users u ON t.approved_by = u.id
WHERE t.status = 'approved'
ORDER BY t.tournament_start DESC;