# Database Schema

## Current Schema (v1.3 - September 2025)

### Core Tables

```sql
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

-- Enhanced tournaments table with comprehensive metadata extraction
CREATE TABLE tournaments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    osu_topic_id INTEGER UNIQUE NOT NULL,
    title VARCHAR(500) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending_review',
    
    -- Core tournament details
    rank_range_min INTEGER,
    rank_range_max INTEGER,
    team_size INTEGER,
    max_teams INTEGER,
    registration_open DATETIME,
    registration_close DATETIME,
    tournament_start DATETIME,
    end_date DATETIME,
    
    -- URL storage (storing only IDs/slugs for security)
    google_sheet_id VARCHAR(100),
    forum_url_slug VARCHAR(100),
    google_form_id VARCHAR(100),
    challonge_slug VARCHAR(100),
    youtube_id VARCHAR(20),
    twitch_username VARCHAR(50),
    discord_link VARCHAR(100),
    
    -- Enhanced extraction fields (Migration 001)
    host_name VARCHAR(100),
    rank_range VARCHAR(50),
    tournament_dates TEXT,
    has_badge BOOLEAN DEFAULT 0,
    banner_url TEXT,
    extraction_confidence TEXT, -- JSON for confidence scores
    
    -- Normalized metadata fields (Migration 002)
    team_vs INTEGER,
    game_mode VARCHAR(10),
    is_bws BOOLEAN DEFAULT 0,
    
    -- Additional metadata fields (Migration 003)
    star_rating_min DECIMAL(3,1),
    star_rating_max DECIMAL(3,1),
    star_rating_qualifier DECIMAL(3,1),
    
    -- Core metadata
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
    CHECK (team_vs IS NULL OR team_vs >= 0),
    
    -- Foreign key
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

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

-- Performance optimization indexes
CREATE INDEX idx_users_osu_id ON users(osu_id);
CREATE INDEX idx_users_admin ON users(is_admin) WHERE is_admin = TRUE;

CREATE INDEX idx_tournaments_status ON tournaments(status);
CREATE INDEX idx_tournaments_topic_id ON tournaments(osu_topic_id);
CREATE INDEX idx_tournaments_dates ON tournaments(tournament_start, registration_close);
CREATE INDEX idx_tournaments_rank_range ON tournaments(rank_range_min, rank_range_max);
CREATE INDEX idx_tournaments_host_name ON tournaments(host_name);
CREATE INDEX idx_tournaments_rank_range_str ON tournaments(rank_range);
CREATE INDEX idx_tournaments_has_badge ON tournaments(has_badge) WHERE has_badge = 1;
CREATE INDEX idx_tournaments_team_vs ON tournaments(team_vs);
CREATE INDEX idx_tournaments_game_mode ON tournaments(game_mode);
CREATE INDEX idx_tournaments_is_bws ON tournaments(is_bws) WHERE is_bws = 1;
CREATE INDEX idx_tournaments_discord_link ON tournaments(discord_link);
CREATE INDEX idx_tournaments_star_rating ON tournaments(star_rating_min, star_rating_max);
CREATE INDEX idx_tournaments_end_date ON tournaments(end_date);

CREATE INDEX idx_system_logs_level ON system_logs(level);
CREATE INDEX idx_system_logs_created ON system_logs(created_at);
CREATE INDEX idx_system_logs_source ON system_logs(source);

CREATE INDEX idx_sessions_user_id ON sessions(user_id);
CREATE INDEX idx_sessions_expires ON sessions(expires_at);
CREATE INDEX idx_sessions_last_activity ON sessions(last_activity);

CREATE INDEX idx_api_rate_limits_endpoint_ip ON api_rate_limits(endpoint, ip_address);
CREATE INDEX idx_api_rate_limits_window ON api_rate_limits(window_start);

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

```

### Migration History

#### Migration 001 (2025-09-06): Story 1.4 Data Extraction Fields
Enhanced tournament extraction capabilities:
- `host_name`: Extracted tournament host from forum posts
- `rank_range`: Extracted rank restrictions (Open, 100K+, etc.)
- `tournament_dates`: Extracted tournament date information
- `has_badge`: Extracted badge award status
- `banner_url`: Extracted tournament banner image URL
- `extraction_confidence`: JSON confidence scores for extraction quality

#### Migration 002 (2025-09-11): Normalized Metadata Extraction
Added normalized fields for tournament metadata:
- `team_vs`: Integer representation (1=1v1, 2=2v2, 0=special/variable)
- `game_mode`: Standardized game modes (STD, TAIKO, CATCH, MANIA4, MANIA7, MANIA0, ETC)
- `is_bws`: Boolean indicator for BWS tournaments

#### Migration 003 (2025-09-11): Additional Metadata Fields
Extended metadata extraction:
- `discord_link`: Discord server invite code (without full URL)
- `star_rating_min`: Minimum star rating for tournament maps
- `star_rating_max`: Maximum star rating for tournament maps
- `star_rating_qualifier`: Qualifier star rating (if different from main range)
- `end_date`: Tournament end date (Grand Final date converted to Sunday)

### SQLite Configuration

```sql
-- Performance and reliability settings
PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA cache_size = 10000;
PRAGMA temp_store = memory;
PRAGMA mmap_size = 268435456;
PRAGMA encoding = 'UTF-8';
```

### Key Features

**Enhanced Parser Integration:**
- Comprehensive metadata extraction from forum posts
- Star rating extraction from tournament brackets
- Registration date parsing with multiple format support
- Discord link extraction from BBCode and imagemaps

**Security Features:**
- Prepared statements for all database operations
- URL storage as IDs/slugs only (not full URLs)
- CSRF token tracking in sessions
- Rate limiting for API endpoints

**Performance Optimizations:**
- Strategic indexes for common query patterns
- Views for frequently accessed data
- WAL mode for concurrent read/write operations

**Data Integrity:**
- Foreign key constraints enforced
- Check constraints for data validation
- Proper UTF-8 encoding for international characters
