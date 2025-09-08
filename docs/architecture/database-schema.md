# Database Schema

```sql
-- Core tournament data with Korean language support
CREATE TABLE tournaments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    topic_id INTEGER UNIQUE NOT NULL,
    title TEXT NOT NULL,
    host TEXT NOT NULL,
    mode TEXT NOT NULL CHECK (mode IN ('Standard', 'Taiko', 'Catch', 'Mania')),
    banner_url TEXT,
    rank_range TEXT NOT NULL,
    registration_status TEXT NOT NULL CHECK (registration_status IN ('Open', 'Closed', 'Ongoing')),
    registration_link TEXT,
    discord_link TEXT,
    sheet_link TEXT,
    stream_link TEXT,
    forum_link TEXT NOT NULL,
    badge_prize BOOLEAN DEFAULT 0,
    start_date TEXT, -- ISO 8601 format with KST timezone
    end_date TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status TEXT NOT NULL DEFAULT 'pending_review' CHECK (status IN ('pending_review', 'approved', 'rejected')),
    language_detected TEXT NOT NULL DEFAULT 'en',
    parsed_terms_used TEXT -- JSON array of terms used during parsing
);

-- Admin users authenticated via osu! OAuth
CREATE TABLE admin_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    osu_user_id INTEGER UNIQUE NOT NULL,
    username TEXT NOT NULL,
    last_login TEXT NOT NULL,
    permissions TEXT NOT NULL DEFAULT 'admin' CHECK (permissions IN ('admin', 'moderator')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Comprehensive system logging
CREATE TABLE system_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    level TEXT NOT NULL CHECK (level IN ('error', 'warning', 'info', 'debug')),
    message TEXT NOT NULL,
    context TEXT, -- JSON context data
    timestamp TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    component TEXT NOT NULL CHECK (component IN ('parser', 'auth', 'admin', 'api')),
    admin_user_id INTEGER,
    tournament_id INTEGER,
    FOREIGN KEY (admin_user_id) REFERENCES admin_users(id),
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id)
);

-- Cross-language term mapping for international tournaments
CREATE TABLE term_mappings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    language TEXT NOT NULL, -- ISO language code (ko, ja, zh, ru, etc.)
    foreign_term TEXT NOT NULL,
    english_concept TEXT NOT NULL,
    confidence REAL NOT NULL DEFAULT 1.0 CHECK (confidence >= 0.0 AND confidence <= 1.0),
    usage_count INTEGER NOT NULL DEFAULT 1,
    variations TEXT, -- JSON array of term variations
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admin_users(id),
    UNIQUE (language, foreign_term)
);

-- Language usage statistics for data-driven term mapping priorities
CREATE TABLE language_statistics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    language TEXT NOT NULL,
    total_tournaments INTEGER NOT NULL DEFAULT 0,
    successful_parses INTEGER NOT NULL DEFAULT 0,
    failed_parses INTEGER NOT NULL DEFAULT 0,
    last_updated TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (language)
);

-- Performance optimization indexes
CREATE INDEX idx_tournaments_status ON tournaments(status);
CREATE INDEX idx_tournaments_mode_status ON tournaments(mode, status);
CREATE INDEX idx_tournaments_rank_status ON tournaments(rank_range, status);
CREATE INDEX idx_tournaments_language ON tournaments(language_detected);
CREATE INDEX idx_system_logs_timestamp ON system_logs(timestamp);
CREATE INDEX idx_system_logs_component ON system_logs(component);
CREATE INDEX idx_term_mappings_language ON term_mappings(language);
CREATE INDEX idx_term_mappings_usage ON term_mappings(usage_count DESC);

-- SQLite Performance Configuration
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA cache_size = 10000;
PRAGMA temp_store = memory;
PRAGMA mmap_size = 268435456;

-- UTF-8 encoding enforcement for Korean characters
PRAGMA encoding = 'UTF-8';
```

**Korean Language Considerations:**
- All TEXT fields use UTF-8 encoding for proper Korean character support
- Collation set to handle Korean sorting and comparison correctly
- Foreign key relationships maintain data integrity across language boundaries
- JSON fields store arrays of Korean/English term variations

**Evolution Path to PostgreSQL:**
When transitioning to PostgreSQL for enhanced features:
```sql
-- Enhanced PostgreSQL schema additions
ALTER TABLE tournaments ADD COLUMN full_text_search tsvector;
CREATE INDEX tournaments_fts_idx ON tournaments USING gin(full_text_search);

-- JSON columns for structured data
ALTER TABLE tournaments ADD COLUMN mappool_data jsonb;
ALTER TABLE term_mappings ADD COLUMN context_data jsonb;
```
