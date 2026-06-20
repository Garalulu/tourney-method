# Tourney Method API Documentation

**Version:** 1.0.0
**Last Updated:** 2026-03-26
**Base URL:** `https://yourdomain.com` (production) or `http://localhost` (local)

---

## Table of Contents

- [Overview](#overview)
- [Authentication](#authentication)
- [Response Format](#response-format)
- [Error Codes](#error-codes)
- [Rate Limiting](#rate-limiting)
- [Endpoints](#endpoints)
  - [Authentication](#authentication-endpoints)
  - [Tournaments](#tournament-endpoints)
  - [Users](#user-endpoints)
  - [Admin - Tournaments](#admin-tournament-management)
  - [Admin - Matches](#admin-match-management)
  - [Admin - Users](#admin-user-management)
  - [Admin - Imports](#admin-import-management)
  - [Admin - Audit Logs](#admin-audit-logs)
  - [SIP Integration](#sip-integration)
  - [Notifications](#notification-endpoints)
  - [Match Submission](#match-submission)
  - [User Profile](#user-profile)
- [Data Models](#data-models)
- [Webhooks](#webhooks)

---

## Overview

The Tourney Method API is a RESTful API for managing osu! tournament data, staff roles, and match submissions. All endpoints return JSON responses.

### Key Features

- **OAuth2 Authentication** via osu!
- **Role-Based Access Control** (player, admin, master)
- **Tournament Management** with approval workflow
- **Staff Role Management** with multi-role support
- **Match Submission** and review system
- **User Profile** and statistics tracking

---

## Authentication

### OAuth2 Flow

The API uses osu! OAuth2 for authentication. Users must authenticate via osu! to access the API.

#### 1. Initiate OAuth

```http
GET /auth/login
```

**Response:** Redirects to osu! OAuth authorization page

#### 2. OAuth Callback

```http
GET /auth/callback
```

**Response:** Redirects to dashboard or setup page

#### 3. Get Current User

```http
GET /auth/me
Authorization: Bearer {session_token}
```

**Response:** `200 OK`

```json
{
  "id": 1,
  "osu_id": 123456,
  "username": "player1",
  "avatar_url": "https://a.ppy.sh/123456",
  "country_code": "US",
  "main_mode": "osu",
  "role": "player",
  "setup_complete": true,
  "created_at": "2026-01-01T00:00:00Z"
}
```

**Error Responses:**

- `401 Unauthorized` - Not authenticated

```json
{
  "message": "Unauthenticated."
}
```

#### 4. Logout

```http
POST /auth/logout
```

**Response:** `302 Found` - Redirects to home

#### 5. Sync User Data

```http
POST /auth/sync
```

Triggers background sync of user data from osu! API (rank, badges, etc.).

**Response:** `200 OK`

```json
{
  "message": "Sync job dispatched successfully.",
  "data": {
    "user_id": 1,
    "username": "player1",
    "last_sync": "2026-02-25T12:00:00Z"
  }
}
```

**Rate Limit:** 1 request per 24 hours per user

---

## Response Format

All API responses follow a consistent format:

### Success Response

```json
{
  "message": "Operation successful",
  "data": {
    // Response data
  }
}
```

### Error Response

```json
{
  "message": "Error message",
  "errors": {
    "field_name": [
      "Error details"
    ]
  }
}
```

### Paginated Response

```json
{
  "data": [...],
  "links": {
    "first": "https://api.example.com/resource?page=1",
    "last": "https://api.example.com/resource?page=10",
    "prev": null,
    "next": "https://api.example.com/resource?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "path": "https://api.example.com/resource",
    "per_page": 20,
    "to": 20,
    "total": 200
  }
}
```

---

## Error Codes

| Status Code | Description |
|-------------|-------------|
| `200 OK` | Request successful |
| `201 Created` | Resource created successfully |
| `302 Found` | Redirect to another URL |
| `400 Bad Request` | Invalid request parameters |
| `401 Unauthorized` | Authentication required |
| `404 Not Found` | Resource not found |
| `409 Conflict` | Resource already exists |
| `422 Unprocessable Entity` | Validation error |
| `500 Internal Server Error` | Server error |

---

## Rate Limiting

- **User Sync:** 1 request per 24 hours per user
- **Tournament Parsing:** Limited by osu! API rate limits (60 req/min)
- **Match Submission:** 10 requests per minute per user
- **General API:** 60 requests per minute per IP

Rate limit headers are included in responses:

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1640000000
```

---

## Endpoints

### Authentication Endpoints

#### Get Current User

```http
GET /auth/me
```

Returns the currently authenticated user's profile.

**Authentication:** Required

**Response:** `200 OK`

```json
{
  "id": 1,
  "osu_id": 123456,
  "username": "player1",
  "avatar_url": "https://a.ppy.sh/123456",
  "country_code": "US",
  "main_mode": "osu",
  "role": "player",
  "setup_complete": true,
  "created_at": "2026-01-01T00:00:00Z"
}
```

---

### Tournament Endpoints

#### List Tournaments

```http
GET /tournaments
```

Returns a paginated list of approved tournaments with optional filters.

**Authentication:** Optional (required for `eligible_only` filter)

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `status` | string | No | Filter by status: `active` (default), `ended` |
| `mode` | string | No | Filter by game mode: `osu`, `taiko`, `catch`, `mania` |
| `is_badge` | boolean | No | Filter by badge eligibility |
| `year` | integer | No | Filter by year (for `ended` tournaments) |
| `search` | string | No | Search in title and description |
| `eligible_only` | boolean | No | Show only tournaments user is eligible for (requires auth) |
| `per_page` | integer | No | Items per page (max 50, default 20) |
| `page` | integer | No | Page number |

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": 1,
      "title": "Tournament Name",
      "description": "Tournament description",
      "forum_url": "https://osu.ppy.sh/community/forums/topics/123456",
      "banner_url": "https://example.com/banner.jpg",
      "badge_url": "https://example.com/badge.png",
      "modes": ["osu"],
      "is_bws": false,
      "is_badge": true,
      "rank_range_min": 1000,
      "rank_range_max": 50000,
      "registration_start": "2026-01-01T00:00:00Z",
      "registration_end": "2026-01-31T23:59:59Z",
      "tournament_start": "2026-02-01T00:00:00Z",
      "tournament_end": "2026-02-28T23:59:59Z",
      "created_at": "2026-01-01T00:00:00Z"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

#### Show Tournament

```http
GET /tournaments/{tournament}
```

Returns detailed information about a specific tournament.

**Authentication:** Optional (admin required for non-approved tournaments)

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `tournament` | integer | Tournament ID |

**Response:** `200 OK`

```json
{
  "data": {
    "id": 1,
    "title": "Tournament Name",
    "description": "Full tournament description",
    "forum_url": "https://osu.ppy.sh/community/forums/topics/123456",
    "banner_url": "https://example.com/banner.jpg",
    "badge_url": "https://example.com/badge.png",
    "discord_url": "https://discord.gg/invite",
    "staff_preview": "Manager: player1, Referee: player2",
    "modes": ["osu"],
    "is_bws": false,
    "is_badge": true,
    "rank_range_min": 1000,
    "rank_range_max": 50000,
    "registration_start": "2026-01-01T00:00:00Z",
    "registration_end": "2026-01-31T23:59:59Z",
    "tournament_start": "2026-02-01T00:00:00Z",
    "tournament_end": "2026-02-28T23:59:59Z",
    "created_at": "2026-01-01T00:00:00Z",
    "staff": [
      {
        "id": 1,
        "user": {
          "id": 1,
          "username": "player1",
          "avatar_url": "https://a.ppy.sh/123456"
        },
        "role": "manager"
      }
    ]
  }
}
```

#### Watch Tournament

```http
POST /tournaments/{tournament}/watch
```

Subscribe to tournament updates (requires authentication).

**Authentication:** Required

**Response:** `200 OK`

```json
{
  "message": "Now watching this tournament"
}
```

#### Unwatch Tournament

```http
DELETE /tournaments/{tournament}/watch
```

Unsubscribe from tournament updates.

**Authentication:** Required

**Response:** `200 OK`

```json
{
  "message": "Stopped watching this tournament"
}
```

---

### User Endpoints

#### Get User Profile

```http
GET /users/{user}
```

Returns public user profile with badges, rank history, and staff roles.

**Authentication:** Not required

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `user` | integer | User ID |

**Response:** HTML page (not JSON)

#### Get User Stats

```http
GET /users/{user}/stats
```

Returns user match statistics.

**Authentication:** Not required

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `user` | integer | User ID |

**Response:** `200 OK`

```json
{
  "data": {
    "user": {
      "id": 1,
      "username": "player1",
      "avatar_url": "https://a.ppy.sh/123456"
    },
    "stats": {
      "total_matches": 50,
      "tournaments_participated": 10,
      "wins": 25,
      "losses": 25,
      "win_rate": 0.5
    }
  }
}
```

#### Search Users

```http
GET /api/users/search
```

Search for users by username (for admin staff management).

**Authentication:** Required (admin or staff context)

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `q` | string | Yes | Search query (min 3 characters) |

**Response:** `200 OK`

```json
[
  {
    "id": 1,
    "osu_id": 123456,
    "username": "player1",
    "avatar_url": "https://a.ppy.sh/123456",
    "registered": true
  },
  {
    "id": "new:unregistered_user",
    "osu_id": null,
    "username": "unregistered_user",
    "avatar_url": "https://a.ppy.sh/",
    "registered": false,
    "is_new": true
  }
]
```

#### Sync User from osu!

```http
GET /api/users/{username}/sync
```

Sync user data from osu! API by username (creates or updates user).

**Authentication:** Not required (but logged when authenticated)

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `username` | string | osu! username |

**Response:** `200 OK` or `404 Not Found`

```json
{
  "id": 1,
  "osu_id": 123456,
  "username": "player1",
  "avatar_url": "https://a.ppy.sh/123456",
  "country_code": "US",
  "main_mode": "osu",
  "role": "player",
  "created_at": "2026-01-01T00:00:00Z"
}
```

---

### Admin - Tournament Management

#### List Pending Tournaments

```http
GET /admin/tournaments/pending
```

Returns tournaments awaiting admin review (pending, approved, rejected tabs).

**Authentication:** Required (admin or master)

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `tab` | string | Active queue tab: `pending`, `approved`, or `rejected` |
| `page` | integer | Page number for the active tab |
| `search` | string | Search in tournament title and host username |
| `modes` | array | Filter by game modes (`osu`, `taiko`, `catch`, `mania`; legacy `fruits` maps to `catch`) |
| `pending_page` | integer | Legacy page number for pending tab |
| `approved_page` | integer | Legacy page number for approved tab |
| `rejected_page` | integer | Legacy page number for rejected tab |

**Response:** HTML page with pagination

#### Show Tournament for Review

```http
GET /admin/tournaments/{tournament}
```

Returns tournament details for admin review (marks as viewed).

**Authentication:** Required (admin or master)

**Response:** HTML page

#### Update Tournament

```http
PATCH /admin/tournaments/{tournament}
```

Update tournament details.

**Authentication:** Required (admin or master)

**Request Body:**

```json
{
  "title": "Updated Title",
  "description": "Updated description",
  "banner_url": "https://example.com/new-banner.jpg",
  "badge_url": "https://example.com/new-badge.png",
  "discord_url": "https://discord.gg/new-invite",
  "modes": ["osu", "taiko"],
  "is_bws": false,
  "is_badge": true,
  "rank_range_min": 1000,
  "rank_range_max": 50000,
  "registration_start": "2026-01-01T00:00:00Z",
  "registration_end": "2026-01-31T23:59:59Z",
  "tournament_start": "2026-02-01T00:00:00Z",
  "tournament_end": "2026-02-28T23:59:59Z"
}
```

**Response:** `200 OK`

```json
{
  "message": "Tournament updated successfully"
}
```

#### Approve Tournament

```http
POST /admin/tournaments/{tournament}/approve
```

Approve a pending tournament (posts to Discord webhook).

**Authentication:** Required (admin or master)

**Response:** `200 OK`

```json
{
  "message": "Tournament approved successfully"
}
```

**Error Responses:**

- `400 Bad Request` - Tournament is not pending review

```json
{
  "message": "Tournament cannot be approved",
  "error": "Tournament is not pending review"
}
```

#### Reject Tournament

```http
POST /admin/tournaments/{tournament}/reject
```

Reject a pending tournament with reason.

**Authentication:** Required (admin or master)

**Request Body:**

```json
{
  "reason": "Missing information in description"
}
```

**Response:** `200 OK`

```json
{
  "message": "Tournament rejected"
}
```

#### Restore Tournament

```http
POST /admin/tournaments/{tournament}/restore
```

Restore rejected or approved tournament to pending review.

**Authentication:** Required (admin or master)

**Response:** `200 OK`

```json
{
  "message": "Tournament restored to pending review"
}
```

#### Reparse Tournament

```http
POST /admin/tournaments/{tournament}/reparse
```

Queue tournament for re-parsing from forum topic.

**Authentication:** Required (admin or master)

**Response:** `302 Found` - Redirects back with success message

#### Preview Reparse Conflicts

```http
GET /admin/tournaments/{tournament}/conflicts
```

Preview conflicts before re-parse (manual vs parsed fields).

**Authentication:** Required (admin or master)

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `preview_data` | JSON | Parsed tournament data to compare |

**Response:** `200 OK`

```json
{
  "conflicts": [
    {
      "field": "title",
      "manual_value": "Manual Title",
      "parsed_value": "Parsed Title"
    }
  ],
  "count": 1
}
```

#### Reparse with Conflict Resolution

```http
POST /admin/tournaments/{tournament}/conflicts/reparse
```

Re-parse tournament with conflict resolution (choose manual vs parsed per field).

**Authentication:** Required (admin or master)

**Request Body:**

```json
{
  "parsed_data": { ... },
  "resolution": {
    "title": "manual",
    "description": "parsed",
    "modes": "parsed"
  }
}
```

**Response:** `200 OK`

```json
{
  "message": "Tournament re-parsed with conflict resolution",
  "kept_manual": ["title"],
  "used_parsed": ["description", "modes"]
}
```

#### Delete Tournament

```http
DELETE /admin/tournaments/{tournament}
```

Soft delete a tournament.

**Authentication:** Required (admin or master)

**Response:** `200 OK`

```json
{
  "message": "Tournament deleted successfully"
}
```

---

### Admin - Tournament Staff Management

#### Fetch User Roles

```http
GET /admin/tournaments/{tournament}/staff?user_id={id}
```

Fetch user's existing roles for tournament (prevents duplicate assignments).

**Authentication:** Required (admin or master)

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `user_id` | integer | Yes | User ID |

**Response:** `200 OK`

```json
{
  "staff": [
    {
      "id": 1,
      "role": "manager",
      "source": "manual"
    }
  ]
}
```

#### Add Staff

```http
POST /admin/tournaments/{tournament}/staff
```

Add staff to tournament (supports registered users and unregistered usernames).

**Authentication:** Required (admin or master)

**Request Body:**

```json
{
  "user_id": "1" or "new:username",
  "roles": ["manager", "referee"]
}
```

**Response:** `201 Created`

```json
{
  "staff": [
    {
      "id": 1,
      "user": {
        "id": 1,
        "username": "player1",
        "osu_id": 123456,
        "avatar_url": "https://a.ppy.sh/123456"
      },
      "role": "manager"
    }
  ]
}
```

#### Update Staff Role

```http
PATCH /admin/tournaments/{tournament}/staff/{staff}
```

Update tournament staff role.

**Authentication:** Required (admin or master)

**Request Body:**

```json
{
  "role": "referee"
}
```

**Response:** `200 OK`

```json
{
  "success": true,
  "role": "referee"
}
```

#### Remove Staff

```http
DELETE /admin/tournaments/{tournament}/staff/{user}
```

Remove staff role from tournament user.

**Authentication:** Required (admin or master)

**Request Body:**

```json
{
  "role": "manager"
}
```

**Response:** `200 OK`

```json
{
  "success": true
}
```

---

### Admin - Match Management

#### List Pending Matches

```http
GET /admin/matches/pending
```

Returns paginated list of matches awaiting review.

**Authentication:** Required (admin or master)

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | integer | Page number |
| `per_page` | integer | Items per page (default 20) |

**Response:** `200 OK` (JSON) or HTML page

```json
{
  "data": [
    {
      "id": 1,
      "name": "Match Name",
      "submitted_by": {
        "id": 1,
        "username": "player1"
      },
      "submitted_at": "2026-02-01T00:00:00Z"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

#### Show Match

```http
GET /admin/matches/{match}
```

Returns match details for review.

**Authentication:** Required (admin or master)

**Response:** HTML page

#### Approve Match

```http
POST /admin/matches/{match}/approve
```

Approve a pending match.

**Authentication:** Required (admin or master)

**Response:** `302 Found` - Redirects to pending matches list

#### Reject Match

```http
POST /admin/matches/{match}/reject
```

Reject a pending match with reason.

**Authentication:** Required (admin or master)

**Request Body:**

```json
{
  "reason": "Invalid match data"
}
```

**Response:** `302 Found` - Redirects to pending matches list

#### Update Match

```http
PATCH /admin/matches/{match}
```

Update match details (e.g., tournament association).

**Authentication:** Required (admin or master)

**Request Body:**

```json
{
  "tournament_id": 1
}
```

**Response:** `302 Found` - Redirects back

---


### Admin - User Management

#### List Users

```http
GET /admin/users
```

Returns paginated list of users (master only).

**Authentication:** Required (master only)

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `role` | string | Filter by role (`player`, `admin`, `master`) |
| `per_page` | integer | Items per page (default 20) |
| `page` | integer | Page number |

**Response:** `200 OK` (JSON) or HTML page

```json
{
  "data": [
    {
      "id": 1,
      "osu_id": 123456,
      "username": "player1",
      "avatar_url": "https://a.ppy.sh/123456",
      "country_code": "US",
      "main_mode": "osu",
      "role": "player",
      "setup_complete": true,
      "created_at": "2026-01-01T00:00:00Z"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

#### Update User Role

```http
PATCH /admin/users/{user}/role
```

Update user role (master only).

**Authentication:** Required (master only)

**Request Body:**

```json
{
  "role": "admin"
}
```

**Response:** `200 OK` or `400 Bad Request`

```json
{
  "id": 1,
  "osu_id": 123456,
  "username": "player1",
  "avatar_url": "https://a.ppy.sh/123456",
  "country_code": "US",
  "main_mode": "osu",
  "role": "admin",
  "setup_complete": true,
  "created_at": "2026-01-01T00:00:00Z"
}
```

**Error Responses:**

- `400 Bad Request` - Cannot modify master role or invalid role

```json
{
  "message": "Cannot modify master user role."
}
```

---

### Admin - Import Management

#### List Import Jobs

```http
GET /admin/imports
```

Returns paginated list of tournament import jobs.

**Authentication:** Required (admin or master)

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | integer | Page number |

**Response:** HTML page

#### Show Import Job

```http
GET /admin/imports/{job}
```

Returns import job details and logs.

**Authentication:** Required (admin or master)

**Response:** HTML page

#### Manual Import

```http
POST /admin/imports/manual
```

Manually trigger tournament import from forum topic URL.

**Authentication:** Required (admin or master)

**Request Body:**

```json
{
  "forum_url": "https://osu.ppy.sh/community/forums/topics/123456"
}
```

**Response:** `302 Found` - Redirects to imports list

#### Delete Import Job

```http
DELETE /admin/imports/{job}
```

Delete import job record.

**Authentication:** Required (admin or master)

**Response:** `302 Found` - Redirects to imports list

---

### Admin - Audit Logs

#### List Audit Logs

```http
GET /admin/audit-log
```

Returns paginated list of admin audit logs.

**Authentication:** Required (admin or master)

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | integer | Page number |
| `action` | string | Filter by action type |
| `user_id` | integer | Filter by admin user ID |

**Response:** HTML page

---

### Notification Endpoints

#### Test Webhook

```http
POST /notifications/webhook/test
```

Send test notification to Discord webhook.

**Authentication:** Required

**Response:** `200 OK`

```json
{
  "message": "Test webhook sent successfully"
}
```

---

### Match Submission

#### Show Match Submission Form

```http
GET /matches/add
```

Returns match submission form.

**Authentication:** Required

**Response:** HTML page

#### Submit Match

```http
POST /matches
```

Submit a new match for review.

**Authentication:** Required

**Request Body:**

```json
{
  "mp_link": "https://osu.ppy.sh/community/matches/12345678",
  "tournament_id": 1
}
```

**Response:** `201 Created` or `302 Found` (HTML)

```json
{
  "id": 1,
  "osu_match_id": 12345678,
  "name": "Match Name",
  "tournament": {
    "id": 1,
    "title": "Tournament Name"
  },
  "start_time": "2026-02-01T00:00:00Z",
  "end_time": "2026-02-01T01:00:00Z",
  "status": "pending"
}
```

**Error Responses:**

- `409 Conflict` - Match already imported

```json
{
  "message": "This match has already been imported.",
  "errors": {
    "mp_link": ["This match has already been imported."]
  }
}
```

---

### User Profile

#### Get User Profile

```http
GET /users/{user}
```

Returns public user profile page with badges, rank history, staff roles, and match statistics.

**Authentication:** Not required

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `user` | integer | User ID |
| `tab` | string | Profile tab: `history` (default), `stats` |

**Response:** HTML page

---

### User Settings

#### Get Settings

```http
GET /users/me/settings
```

Returns user settings page.

**Authentication:** Required

**Response:** HTML page

#### Update Settings

```http
PATCH /users/me/settings
```

Update user settings (webhook URLs, notifications).

**Authentication:** Required

**Request Body:**

```json
{
  "discord_webhook": "https://discord.com/api/webhooks/...",
  "email_notifications": true
}
```

**Response:** `302 Found` - Redirects back

---

### Year Recap

#### Generate Recap

```http
POST /users/me/recap/{year}
```

Generate year-in-review recap for user.

**Authentication:** Required

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `year` | integer | Year (e.g., 2025) |

**Response:** `302 Found` - Redirects to recap page

#### Download Recap

```http
GET /users/me/recap/{year}/download
```

Download year recap as image/PDF.

**Authentication:** Required

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `year` | integer | Year (e.g., 2025) |

**Response:** Image/PDF file download

---

### User Setup

#### Show Setup Form

```http
GET /users/setup
```

Returns initial setup form for new users.

**Authentication:** Required

**Response:** HTML page

#### Complete Setup

```http
POST /users/setup
```

Complete initial user setup (main mode selection).

**Authentication:** Required

**Request Body:**

```json
{
  "main_mode": "osu"
}
```

**Response:** `302 Found` - Redirects to dashboard

### SIP Integration

#### Get SIP Queue

```http
GET /api/sip/queue
X-SIP-API-Token: {your_token}
```

Retrieve list of users queued for SIP stats synchronization (called by Google Sheets Apps Script).

**Authentication:** Required (X-SIP-API-Token header must match SIP_API_TOKEN in .env)

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `limit` | integer | No | Number of users to return (default: 50, max: 100) |

**Response:** `200 OK`

```json
{
  "queue": [
    {
      "id": 1,
      "user_id": 123,
      "osu_id": 123456,
      "username": "player1",
      "status": "pending"
    },
    {
      "id": 2,
      "user_id": 456,
      "osu_id": 789012,
      "username": "player2",
      "status": "pending"
    }
  ],
  "total": 2
}
```

**Error Responses:**

- `401 Unauthorized` - Invalid or missing X-SIP-API-Token header

```json
{
  "error": "Unauthorized"
}
```

**Note:** This endpoint is called by Google Sheets Apps Script, which then calls the skillissue.app API to get SIP values for each user.

#### Update SIP User Stats

```http
POST /api/sip/update
Content-Type: application/json
X-SIP-API-Token: {your_token}

{
  "results": [
    {
      "queue_id": 1,
      "osu_id": 123456,
      "sip": 85,
      "error": null
    },
    {
      "queue_id": 2,
      "osu_id": 789012,
      "sip": null,
      "error": "User not found"
    }
  ]
}
```

Update user SIP stats from Google Sheets Apps Script after fetching from skillissue.app API.

**Authentication:** Required (X-SIP-API-Token header must match SIP_API_TOKEN in .env)

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `results` | array | Yes | Array of SIP fetch results |
| `queue_id` | integer | Yes | Queue item ID from /api/sip/queue |
| `osu_id` | integer | Yes | osu! user ID |
| `sip` | integer/null | No | SIP value (0-100) or null if failed |
| `error` | string/null | No | Error message if fetch failed |

**Response:** `200 OK`

```json
{
  "message": "SIP data updated successfully",
  "updated": 1,
  "failed": 1
}
```

**Error Responses:**

- `400 Bad Request` - Invalid request format
- `401 Unauthorized` - Invalid or missing X-SIP-API-Token header

```json
{
  "message": "Unauthorized"
}
```

**Notes:**
- Endpoint is called by Google Sheets Apps Script after fetching SIP values
- Updates `users.sip` and `users.sip_updated_at` columns
- Marks queue items as completed or failed
- skillissue.app API only accepts requests from Google Apps Script context

---

## Data Models

### User

```json
{
  "id": 1,
  "osu_id": 123456,
  "username": "player1",
  "avatar_url": "https://a.ppy.sh/123456",
  "country_code": "US",
  "main_mode": "osu",
  "role": "player" | "admin" | "master",
  "setup_complete": true,
  "created_at": "2026-01-01T00:00:00Z",
  "osu_data_synced_at": "2026-02-25T12:00:00Z"
}
```

### Tournament

```json
{
  "id": 1,
  "title": "Tournament Name",
  "description": "Tournament description",
  "forum_url": "https://osu.ppy.sh/community/forums/topics/123456",
  "forum_topic_id": 123456,
  "banner_url": "https://example.com/banner.jpg",
  "badge_url": "https://example.com/badge.png",
  "discord_url": "https://discord.gg/invite",
  "modes": ["osu", "taiko", "catch", "mania"],
  "is_bws": false,
  "is_badge": true,
  "rank_range_min": 1000,
  "rank_range_max": 50000,
  "registration_start": "2026-01-01T00:00:00Z",
  "registration_end": "2026-01-31T23:59:59Z",
  "tournament_start": "2026-02-01T00:00:00Z",
  "tournament_end": "2026-02-28T23:59:59Z",
  "status": "pending_review" | "approved" | "rejected",
  "created_at": "2026-01-01T00:00:00Z"
}
```

### TournamentStaff

```json
{
  "id": 1,
  "tournament_id": 1,
  "user_id": 1,
  "role": "manager" | "referee" | "streamer" | "commentator" | "mapper" | "tester" | "designer",
  "status": "pending" | "approved" | "rejected",
  "source": "parsed" | "manual" | "user_submitted",
  "submitted_at": "2026-01-01T00:00:00Z",
  "reviewed_at": "2026-01-02T00:00:00Z",
  "reviewed_by": 2
}
```

### Match

```json
{
  "id": 1,
  "osu_match_id": 12345678,
  "name": "Match Name",
  "tournament_id": 1,
  "submitted_by": 1,
  "start_time": "2026-02-01T00:00:00Z",
  "end_time": "2026-02-01T01:00:00Z",
  "status": "pending" | "approved" | "rejected",
  "reviewed_by": 2,
  "reviewed_at": "2026-02-01T02:00:00Z",
  "created_at": "2026-02-01T00:00:00Z"
}
```

---

## Webhooks

The application supports Discord webhooks for tournament announcements and notifications.

### Webhook Configuration

Webhook URLs are configured via environment variables:

```bash
# Standard Mode
DISCORD_STD_BADGE_WEBHOOK=https://discord.com/api/webhooks/...
DISCORD_STD_NONBADGE_WEBHOOK=https://discord.com/api/webhooks/...

# Taiko Mode
DISCORD_TAIKO_BADGE_WEBHOOK=https://discord.com/api/webhooks/...
DISCORD_TAIKO_NONBADGE_WEBHOOK=https://discord.com/api/webhooks/...

# Catch Mode
DISCORD_CATCH_BADGE_WEBHOOK=https://discord.com/api/webhooks/...
DISCORD_CATCH_NONBADGE_WEBHOOK=https://discord.com/api/webhooks/...

# Mania Mode
DISCORD_MANIA_BADGE_WEBHOOK=https://discord.com/api/webhooks/...
DISCORD_MANIA_NONBADGE_WEBHOOK=https://discord.com/api/webhooks/...
```

### Webhook Events

- **Tournament Approved** - Posted to appropriate mode-specific webhook
- **Tournament Rejected** - Logged but not posted
- **Staff Role Approved** - Logged but not posted
- **Match Approved** - Logged but not posted

### SipFetchQueue

```json
{
  "id": 1,
  "user_id": 1,
  "osu_id": 123456,
  "username": "player1",
  "status": "pending" | "processing" | "completed" | "failed",
  "retry_count": 0,
  "error_message": null,
  "created_at": "2026-03-26T10:00:00Z",
  "updated_at": "2026-03-26T10:00:00Z"
}
```

---

## Support

For API issues or questions:

- **GitHub Issues:** https://github.com/your-username/tourney-method/issues
- **Documentation:** See `docs/CODEMAPS/` for detailed architecture
- **Contact:** admin@example.com

---

**Document Version:** 1.0.0
**Last Updated:** 2026-02-26
**Maintained By:** Development Team
