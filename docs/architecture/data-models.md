# Data Models

## Tournament (Enhanced v1.3)
**Purpose:** Core entity representing osu! tournaments with comprehensive parsed and admin-curated metadata

**Key Attributes:**
- id: integer - Primary key for tournament identification
- osu_topic_id: integer - osu! forum topic ID (unique constraint)
- title: string(500) - Tournament display name
- status: enum - pending_review, approved, rejected, archived admin workflow status

**Core Tournament Details:**
- rank_range_min: integer - Minimum rank (numeric value)
- rank_range_max: integer - Maximum rank (numeric value) 
- team_size: integer - Team size for team tournaments
- max_teams: integer - Maximum number of teams
- registration_open: datetime - Registration opening date/time
- registration_close: datetime - Registration closing date/time
- tournament_start: datetime - Tournament start date/time
- end_date: datetime - Tournament end date (converted to Sunday)

**URL Storage (Security-Optimized):**
- google_sheet_id: string(100) - Google Sheets ID only
- forum_url_slug: string(100) - Forum URL slug only
- google_form_id: string(100) - Google Forms ID only
- challonge_slug: string(100) - Challonge tournament slug only
- youtube_id: string(20) - YouTube video ID only
- twitch_username: string(50) - Twitch username only
- discord_link: string(100) - Discord invite code only

**Enhanced Extraction Fields (Migration 001):**
- host_name: string(100) - Extracted tournament host from forum posts
- rank_range: string(50) - Extracted rank restrictions (Open, 100K+, etc.)
- tournament_dates: text - Extracted tournament date information
- has_badge: boolean - Extracted badge award status
- banner_url: text - Extracted tournament banner image URL
- extraction_confidence: text - JSON confidence scores for extraction quality

**Normalized Metadata (Migration 002):**
- team_vs: integer - Team format (1=1v1, 2=2v2, 0=special/variable)
- game_mode: string(10) - Standardized modes (STD, TAIKO, CATCH, MANIA4, MANIA7, MANIA0, ETC)
- is_bws: boolean - BWS tournament indicator

**Additional Metadata (Migration 003):**
- star_rating_min: decimal(3,1) - Minimum star rating for tournament maps
- star_rating_max: decimal(3,1) - Maximum star rating for tournament maps
- star_rating_qualifier: decimal(3,1) - Qualifier star rating (if different)

**Core Metadata:**
- raw_post_content: text - Original forum post content
- parsed_at: datetime - Parse timestamp
- approved_at: datetime - Admin approval timestamp
- approved_by: integer - Admin user ID who approved

```typescript
interface Tournament {
  // Core identification
  id: number;
  osu_topic_id: number;
  title: string;
  status: 'pending_review' | 'approved' | 'rejected' | 'archived';
  
  // Tournament details
  rank_range_min?: number;
  rank_range_max?: number;
  team_size?: number;
  max_teams?: number;
  registration_open?: Date;
  registration_close?: Date;
  tournament_start?: Date;
  end_date?: Date;
  
  // URL references (IDs/slugs only)
  google_sheet_id?: string;
  forum_url_slug?: string;
  google_form_id?: string;
  challonge_slug?: string;
  youtube_id?: string;
  twitch_username?: string;
  discord_link?: string;
  
  // Enhanced extraction fields
  host_name?: string;
  rank_range?: string;
  tournament_dates?: string;
  has_badge: boolean;
  banner_url?: string;
  extraction_confidence?: string; // JSON
  
  // Normalized metadata
  team_vs?: number;
  game_mode?: string;
  is_bws: boolean;
  
  // Star ratings
  star_rating_min?: number;
  star_rating_max?: number;
  star_rating_qualifier?: number;
  
  // Core metadata
  raw_post_content?: string;
  parsed_at: Date;
  approved_at?: Date;
  approved_by?: number;
}
```

**Relationships:**
- One tournament belongs to one approving admin user (approved_by → users.id)
- One tournament has many system log entries for parsing/admin actions

## User (Admin Authentication)
**Purpose:** Authorized users who can review and approve parsed tournament data via osu! OAuth

**Key Attributes:**
- id: integer - Primary key for user identification
- osu_id: integer - osu! user ID from OAuth (unique)
- username: string(255) - osu! username
- is_admin: boolean - Admin permission flag
- created_at: datetime - Account creation timestamp
- last_login: datetime - Last authentication timestamp

```typescript
interface User {
  id: number;
  osu_id: number;
  username: string;
  is_admin: boolean;
  created_at: Date;
  last_login?: Date;
}
```

**Relationships:**
- One user approves many tournaments
- One user has many sessions
- One user creates many system log entries

## SystemLog (Enhanced Logging)
**Purpose:** Comprehensive logging for parser execution, errors, and admin actions

**Key Attributes:**
- id: integer - Primary key for log entry identification
- level: enum - Extended log severity levels
- message: text - Human-readable log message
- context: text - JSON additional context for debugging
- source: string(100) - Script/function that generated the log
- user_id: integer - User who triggered the log entry
- created_at: datetime - Log entry creation timestamp

```typescript
interface SystemLog {
  id: number;
  level: 'DEBUG' | 'INFO' | 'NOTICE' | 'WARNING' | 'ERROR' | 'CRITICAL' | 'ALERT' | 'EMERGENCY';
  message: string;
  context?: string; // JSON string with contextual data
  source?: string;
  user_id?: number;
  created_at: Date;
}
```

**Relationships:**
- System logs optionally reference users for admin action tracking
- System logs track parser operations and admin actions

## Session (Secure Session Management)
**Purpose:** Secure session tracking with CSRF protection and admin session identification

**Key Attributes:**
- id: string(128) - Session ID (primary key)
- user_id: integer - Associated user ID
- ip_address: string(45) - Client IP address
- user_agent: text - Client user agent string
- created_at: datetime - Session creation timestamp
- last_activity: datetime - Last session activity timestamp
- expires_at: datetime - Session expiration timestamp
- csrf_token: string(64) - CSRF protection token
- is_admin_session: boolean - Admin session flag

```typescript
interface Session {
  id: string;
  user_id: number;
  ip_address?: string;
  user_agent?: string;
  created_at: Date;
  last_activity: Date;
  expires_at: Date;
  csrf_token?: string;
  is_admin_session: boolean;
}
```

**Relationships:**
- One session belongs to one user
- Sessions are automatically cleaned up on user deletion

## ApiRateLimit (Rate Limiting)
**Purpose:** API endpoint rate limiting for security and performance

**Key Attributes:**
- id: integer - Primary key for rate limit entry
- endpoint: string(100) - API endpoint path
- ip_address: string(45) - Client IP address
- request_count: integer - Number of requests in window
- window_start: datetime - Rate limiting window start time

```typescript
interface ApiRateLimit {
  id: number;
  endpoint: string;
  ip_address: string;
  request_count: number;
  window_start: Date;
}
```

**Relationships:**
- Rate limits are keyed by endpoint + IP address + time window
- Automatic cleanup of expired rate limit windows

## Data Flow Overview

### Parser Integration Flow:
1. **Forum Post Retrieval** → Raw forum post content
2. **Enhanced Parsing** → Comprehensive metadata extraction using ForumPostParserService
3. **Database Storage** → Tournament entity with all extracted fields
4. **Admin Review** → Manual verification and approval via admin UI
5. **Public Display** → Approved tournaments visible on public site

### Key Enhancements:
- **Security-First URL Storage**: Only IDs/slugs stored, full URLs reconstructed on display
- **Comprehensive Metadata**: Star ratings, Discord links, registration dates, team formats
- **Parsing Confidence**: JSON confidence scores for extraction quality assessment
- **Normalized Data**: Standardized game modes, team formats, and rank ranges
- **Enhanced Session Management**: CSRF protection and admin session tracking

### Migration Trail:
- **v1.0**: Basic tournament structure
- **v1.1** (Migration 001): Enhanced extraction fields
- **v1.2** (Migration 002): Normalized metadata
- **v1.3** (Migration 003): Additional metadata and star ratings