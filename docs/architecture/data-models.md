# Data Models

## Tournament
**Purpose:** Core entity representing osu! tournaments with all parsed and admin-curated information

**Key Attributes:**
- id: integer - Primary key for tournament identification
- topic_id: integer - osu! forum topic ID (unique constraint)
- title: string - Tournament display name
- host: string - Tournament organizer username
- mode: string - Game mode (Standard, Taiko, Catch, Mania)
- banner_url: string - Tournament banner image URL
- rank_range: string - Eligible player rank range
- registration_status: enum - Open, Closed, Ongoing registration status
- created_at: timestamp - Tournament creation time (KST)
- updated_at: timestamp - Last modification time (KST)
- status: enum - pending_review, approved, rejected admin workflow status
- language_detected: string - Detected primary language of forum post

```typescript
interface Tournament {
  id: number;
  topic_id: number;
  title: string;
  host: string;
  mode: 'Standard' | 'Taiko' | 'Catch' | 'Mania';
  banner_url?: string;
  rank_range: string;
  registration_status: 'Open' | 'Closed' | 'Ongoing';
  registration_link?: string;
  discord_link?: string;
  sheet_link?: string;
  stream_link?: string;
  forum_link: string;
  badge_prize: boolean;
  start_date?: Date;
  end_date?: Date;
  created_at: Date;
  updated_at: Date;
  status: 'pending_review' | 'approved' | 'rejected';
  language_detected: string;
  parsed_terms_used: string; // JSON array of terms used during parsing
}
```

**Relationships:**
- One tournament has many parsed data entries (historical parsing attempts)
- One tournament is created by one admin user (approval tracking)

## AdminUser
**Purpose:** Authorized users who can review and approve parsed tournament data

**Key Attributes:**
- id: integer - Primary key for admin identification
- osu_user_id: integer - osu! user ID from OAuth
- username: string - osu! username
- last_login: timestamp - Last authentication time
- permissions: string - Admin permission level
- created_at: timestamp - Admin account creation time (KST)

```typescript
interface AdminUser {
  id: number;
  osu_user_id: number;
  username: string;
  last_login: Date;
  permissions: 'admin' | 'moderator';
  created_at: Date;
}
```

**Relationships:**
- One admin user approves many tournaments
- One admin user creates many system log entries

## SystemLog
**Purpose:** Comprehensive logging for parser execution, errors, and admin actions

**Key Attributes:**
- id: integer - Primary key for log entry identification
- level: enum - Log severity level
- message: text - Human-readable log message
- context: text - JSON context data for debugging
- timestamp: timestamp - Log entry creation time (KST)
- component: string - System component that generated the log

```typescript
interface SystemLog {
  id: number;
  level: 'error' | 'warning' | 'info' | 'debug';
  message: string;
  context: string; // JSON string with contextual data
  timestamp: Date;
  component: 'parser' | 'auth' | 'admin' | 'api';
}
```

**Relationships:**
- System logs reference admin users for admin action tracking
- System logs reference tournaments for parsing-related entries

## TermMapping
**Purpose:** Cross-language term mapping for parsing tournaments in Korean, English, and other languages

**Key Attributes:**
- id: integer - Primary key for term mapping identification
- language: string - ISO language code
- foreign_term: string - Non-English term found in tournaments
- english_concept: string - English equivalent concept
- confidence: float - Admin confidence in mapping accuracy
- usage_count: integer - Number of times term has been encountered
- created_by: integer - Admin who created the mapping
- created_at: timestamp - Mapping creation time (KST)

```typescript
interface TermMapping {
  id: number;
  language: string; // 'ko', 'ja', 'zh', 'ru', etc.
  foreign_term: string;
  english_concept: string;
  confidence: number; // 0.0 to 1.0
  usage_count: number;
  created_by: number; // AdminUser.id
  created_at: Date;
  variations: string; // JSON array of term variations
}
```

**Relationships:**
- One term mapping belongs to one admin user (created_by)
- Term mappings are used by parser to understand foreign language tournaments
