# Tourney Method Coding Standards

## Overview
These coding standards ensure consistency, maintainability, and security for the Tourney Method project - a greenfield vanilla PHP + jQuery + SQLite application focused on osu! tournament discovery.

## PHP Standards

### Code Style
- **PSR-4 Autoloading**: All classes in `src/` follow PSR-4 namespace structure
- **PSR-12 Basic Coding Standard**: Extended coding style with project-specific adaptations
- **Indentation**: 4 spaces (no tabs)
- **Line Length**: 120 characters maximum
- **File Endings**: Unix line endings (LF)
- **Encoding**: UTF-8 without BOM

### Naming Conventions
```php
// Classes: PascalCase
class TournamentParser {}
class AuthService {}

// Methods/Functions: camelCase
public function parseForumPost() {}
private function validateTournamentData() {}

// Variables: camelCase
$tournamentData = [];
$isApproved = false;

// Constants: UPPER_SNAKE_CASE
const MAX_TOURNAMENTS_PER_PAGE = 20;
const DEFAULT_RANK_RANGE = 'Open';

// Database columns: snake_case
'tournament_id', 'created_at', 'rank_range'
```

### Class Structure
```php
<?php

namespace TourneyMethod\Services;

/**
 * Parses osu! forum posts to extract tournament data
 */
class TournamentParser
{
    private $repository;
    private $validator;
    
    public function __construct(
        TournamentRepository $repository,
        ValidationService $validator
    ) {
        $this->repository = $repository;
        $this->validator = $validator;
    }
    
    // Public methods first
    public function parseForumPost(array $postData): array {}
    
    // Private methods last
    private function extractTournamentTitle(string $content): ?string {}
}
```

### Security Requirements (NON-NEGOTIABLE)
```php
// 1. ALWAYS use prepared statements
$stmt = $db->prepare("SELECT * FROM tournaments WHERE id = ?");
$stmt->execute([$tournamentId]);

// 2. ALWAYS escape output
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');

// 3. ALWAYS use CSRF tokens on forms
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    throw new SecurityException('CSRF token mismatch');
}

// 4. ALWAYS validate and sanitize input
$tournamentId = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if (!$tournamentId) {
    throw new ValidationException('Invalid tournament ID');
}
```

### Error Handling
```php
// Use specific exception types
throw new ValidationException("Tournament title is required");
throw new DatabaseException("Failed to save tournament: " . $e->getMessage());
throw new AuthenticationException("Invalid OAuth token");

// Log errors with context
error_log("Parser failed for topic_id {$topicId}: " . $e->getMessage());

// Never expose internal details to users
public function getPublicErrorMessage(): string {
    return "An error occurred. Please try again later.";
}
```

## Frontend Standards

### JavaScript (jQuery)
```javascript
// Use strict mode
'use strict';

// Namespace pattern
var TourneyMethod = TourneyMethod || {};

TourneyMethod.Tournaments = {
    // Public methods
    init: function() {
        this.bindEvents();
        this.loadTournaments();
    },
    
    // Private methods (underscore prefix)
    _validateFilters: function(filters) {
        return filters.rankRange && filters.mode;
    }
};

// Event binding pattern
$(document).ready(function() {
    TourneyMethod.Tournaments.init();
});
```

### CSS Organization
```css
/* Component-based structure */
/* 1. Layout components */
.tournament-list {}
.tournament-card {}

/* 2. State modifiers */
.tournament-card--featured {}
.filter-panel--collapsed {}

/* 3. Utility classes */
.text-center { text-align: center; }
.visually-hidden { /* accessibility */ }

/* Korean character support */
.korean-text {
    font-family: 'Malgun Gothic', 'Apple SD Gothic Neo', sans-serif;
    word-break: keep-all;
}
```

### HTML Structure
```html
<!-- Semantic HTML5 with accessibility -->
<main role="main">
    <section class="tournament-list" aria-label="Tournament listings">
        <h2 id="tournaments-heading">Active Tournaments</h2>
        <div class="tournament-grid" role="grid" aria-labelledby="tournaments-heading">
            <!-- Tournament cards -->
        </div>
    </section>
</main>

<!-- Progressive enhancement -->
<noscript>
    <p>This site works without JavaScript but provides enhanced features when enabled.</p>
</noscript>
```

## Database Standards

### Schema Conventions
```sql
-- Table names: plural, snake_case
CREATE TABLE tournaments (
    -- Primary keys: {table}_id
    tournament_id INTEGER PRIMARY KEY AUTOINCREMENT,
    
    -- Foreign keys: {referenced_table}_id
    admin_user_id INTEGER,
    
    -- Timestamps: created_at, updated_at
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Boolean fields: is_{condition}
    is_approved BOOLEAN DEFAULT 0,
    
    -- Status fields: {entity}_status
    tournament_status TEXT DEFAULT 'pending_review'
);
```

### Query Patterns
```php
// Repository pattern - always use prepared statements
class TournamentRepository {
    public function findByStatus(string $status): array {
        $stmt = $this->db->prepare("
            SELECT tournament_id, title, rank_range, created_at
            FROM tournaments 
            WHERE tournament_status = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
```

## File Organization Standards

### Directory Structure (Greenfield Design)
```
src/
├── config/          # Configuration files
├── models/          # Data models (Tournament, AdminUser, etc.)
├── services/        # Business logic (TournamentParser, AuthService)
├── repositories/    # Data access layer
├── utils/           # Helper functions and utilities
└── templates/       # PHP view templates

public/
├── index.php        # Homepage entry point
├── admin/           # Admin interface
├── api/             # REST API endpoints
└── assets/          # CSS, JS, images
```

### File Naming
- **PHP Classes**: `PascalCase.php` (e.g., `TournamentParser.php`)
- **PHP Templates**: `kebab-case.php` (e.g., `tournament-list.php`)
- **JavaScript**: `kebab-case.js` (e.g., `tournament-filters.js`)
- **CSS**: `kebab-case.css` (e.g., `tournament-card.css`)

## Documentation Standards

### Code Comments
```php
/**
 * Parses osu! forum posts to extract structured tournament data
 * 
 * Handles Korean and English tournament announcements with fallback parsing.
 * Failed fields are marked for admin review.
 * 
 * @param array $postData Raw forum post data from osu! API
 * @return array Structured tournament data with status flags
 * @throws ValidationException When post data is invalid
 */
public function parseForumPost(array $postData): array {
    // Extract title using multiple pattern matching
    $title = $this->extractTournamentTitle($postData['content']);
    
    // TODO: Add Korean title pattern matching (Issue #123)
    // FIXME: Handle edge case where rank range spans multiple lines
    
    return $parsedData;
}
```

### README Requirements
- **Setup Instructions**: One-command local development setup
- **Deployment Steps**: DigitalOcean App Platform deployment guide
- **Environment Variables**: Required OAuth and API configuration
- **Getting Started**: Greenfield development workflow guide

## Testing Standards

### Unit Test Structure
```php
class TournamentParserTest extends PHPUnit\Framework\TestCase {
    private $parser;
    
    protected function setUp(): void {
        $this->parser = new TournamentParser(
            $this->createMock(TournamentRepository::class),
            $this->createMock(ValidationService::class)
        );
    }
    
    /**
     * @test
     * @dataProvider validTournamentPostProvider
     */
    public function it_extracts_tournament_data_from_valid_posts($input, $expected) {
        $result = $this->parser->parseForumPost($input);
        $this->assertEquals($expected, $result);
    }
}
```

## Performance Standards

### Response Time Targets
- **Public pages**: < 2 seconds (including Korean content)
- **Admin pages**: < 3 seconds
- **API endpoints**: < 1 second
- **Database queries**: < 500ms

### Optimization Requirements
```php
// Database query optimization
public function getActiveTournaments(): array {
    // Use indexes on tournament_status and created_at
    $stmt = $this->db->prepare("
        SELECT tournament_id, title, rank_range, banner_url
        FROM tournaments 
        WHERE tournament_status = 'approved' 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Frontend performance
// Critical CSS inlined, non-critical deferred
// Images lazy-loaded with placeholder fallbacks
// jQuery loaded from CDN with local fallback
```

## Korean Localization Standards

### Character Encoding
- **Database**: UTF-8 collation for all text fields
- **HTTP Headers**: `Content-Type: text/html; charset=UTF-8`
- **Input Processing**: Proper Korean character validation

### Timezone Handling
```php
// Always use KST for Korean users
date_default_timezone_set('Asia/Seoul');

// Store UTC in database, display in KST
$kstDate = new DateTime($utcTimestamp);
$kstDate->setTimezone(new DateTimeZone('Asia/Seoul'));
```

## Version Control Standards

### Git Commit Messages
```
type(scope): brief description

feat(parser): add Korean title pattern matching
fix(admin): resolve CSRF token validation issue
docs(api): update tournament endpoint documentation
refactor(auth): simplify OAuth callback handling
test(parser): add unit tests for rank range extraction
```

### Branch Naming
- **Feature**: `feature/korean-parsing-support`
- **Bug Fix**: `fix/oauth-token-expiration`
- **Hotfix**: `hotfix/security-vulnerability-patch`

## Deployment Standards

### Production Checklist
- [ ] Environment variables configured (OAuth, API keys)
- [ ] Database permissions set (600 for SQLite file)
- [ ] Web server security headers enabled
- [ ] Error reporting disabled in production
- [ ] Log rotation configured
- [ ] HTTPS certificate valid
- [ ] Korean timezone (Asia/Seoul) configured

## Architectural Decisions

### Greenfield Technology Choices (Intentional Design)
- **Vanilla PHP**: Chosen for deployment simplicity and direct App Platform compatibility
- **jQuery**: Selected for progressive enhancement and reliable browser support
- **Direct Deployment**: Enables streamlined CI/CD with zero build complexity
- **SQLite**: Optimal for App Platform persistent storage and Korean market cost efficiency
- **Monolithic Start**: Appropriate for MVP scope with clear microservices evolution path

These are **deliberate architectural decisions** for our greenfield MVP, designed for rapid deployment and future scalability.

---

**Note**: These standards reflect our greenfield architectural decisions, optimized for DigitalOcean App Platform deployment and Korean market requirements. Security requirements are absolutely mandatory from day one.