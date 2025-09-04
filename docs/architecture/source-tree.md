# Tourney Method Source Tree Structure

## Overview
This document describes the **planned source tree structure** for the Tourney Method greenfield project. The structure is designed for DigitalOcean App Platform deployment with Korean market optimization, using vanilla PHP, jQuery, and SQLite as specified in the PRD.

## Planned Project Structure

### Root Directory Layout (To Be Created)
```
tourney-method/                     # Git repository root
├── .bmad-core/                     # BMAD framework files ✅ (existing)
├── .do/                           # DigitalOcean App Platform config (to create)
│   └── app.yaml                   # Platform deployment configuration
├── .git/                          # Git version control ✅ (existing)
├── .gitignore                     # Deployment exclusions ✅ (existing)
├── composer.json                  # PHP dependencies (to create)
├── docs/                          # Documentation ✅ (existing)
├── public/                        # Web root (to create)
├── src/                          # PHP application code (to create)
├── data/                         # Database and migrations (partially created)
├── config/                       # Environment configuration (partially created)
├── scripts/                      # CLI scripts and utilities (to create)
├── tests/                        # Test suites (to create)
└── logs/                         # Application logs (to create)
```

## Target Implementation Structure

### Public Web Root (To Be Implemented)
```
public/                           # Web root - all publicly accessible files
├── index.php                     # Homepage with featured tournaments
├── tournaments.php               # All tournaments page with filtering
├── tournament.php                # Tournament detail page
├── admin/                        # Admin interface
│   ├── index.php                 # Admin dashboard
│   ├── login.php                 # OAuth login handler
│   ├── logout.php                # Session cleanup
│   ├── edit.php                  # Tournament edit form
│   └── logs.php                  # System logs viewer
├── api/                          # REST API endpoints
│   ├── tournaments.php           # Tournament CRUD operations
│   ├── filters.php               # Filter options endpoint
│   └── auth.php                  # OAuth callback handler
├── assets/                       # Static assets (auto-CDN)
│   ├── css/
│   │   ├── pico.min.css          # Pico.css framework
│   │   └── custom.css            # Tournament-specific styles
│   ├── js/
│   │   ├── jquery.min.js         # jQuery library
│   │   ├── main.js               # Core application logic
│   │   ├── tournaments.js        # Tournament list interactions
│   │   └── filters.js            # Filter panel functionality
│   └── images/
│       └── placeholder.png       # Tournament banner fallback
├── health.php                    # App Platform health check endpoint
└── .htaccess                     # URL rewriting and security (if needed)
```

### PHP Application Code (To Be Implemented)
```
src/                              # PHP application logic
├── config/
│   ├── Database.php              # SQLite connection configuration
│   ├── OAuth.php                 # osu! OAuth settings
│   └── Constants.php             # Application constants
├── models/                       # Data models
│   ├── Tournament.php            # Tournament entity and operations
│   ├── AdminUser.php             # Admin user management
│   ├── SystemLog.php             # Error and activity logging
│   └── ParsedData.php            # Parser result handling
├── services/                     # Business logic services
│   ├── TournamentParser.php      # Forum post parsing logic
│   ├── AuthService.php           # osu! OAuth integration
│   ├── FilterService.php         # Tournament filtering logic
│   └── ValidationService.php     # Input validation and sanitization
├── repositories/                 # Data access layer
│   ├── TournamentRepository.php
│   ├── AdminUserRepository.php
│   └── SystemLogRepository.php
├── utils/                        # Utility functions
│   ├── DatabaseHelper.php        # Database connection utilities
│   ├── SecurityHelper.php        # CSRF, XSS protection
│   ├── ValidationHelper.php      # Input validation utilities
│   └── DateHelper.php            # KST timezone handling
└── templates/                    # PHP view templates
    ├── layouts/
    │   ├── main.php              # Base layout template
    │   └── admin.php             # Admin layout template
    ├── components/
    │   ├── tournament-card.php   # Tournament card component
    │   ├── filter-panel.php      # Filter sidebar component
    │   └── pagination.php        # Pagination controls
    ├── pages/
    │   ├── home.php              # Homepage content
    │   ├── tournaments-list.php  # Tournament list page
    │   └── tournament-detail.php # Tournament detail view
    └── admin/
        ├── dashboard.php         # Admin dashboard content
        ├── edit-tournament.php   # Tournament edit form
        └── logs-viewer.php       # System logs display
```

### Database Structure (To Be Implemented)
```
data/                             # Data storage and scripts
├── database/
│   └── schema.sql                # Database schema definition ✅ (exists)
├── migrations/                   # Database migration scripts (to create)
│   ├── 001_initial_schema.sql
│   ├── 002_add_indexes.sql
│   └── 003_korean_optimization.sql
└── seeds/                        # Development seed data (to create)
    └── tournaments_sample.sql
```

### Scripts and Automation (To Be Implemented)
```
scripts/                          # Command-line scripts
├── parser/
│   ├── daily_parser.php          # Daily tournament parsing cron job
│   └── manual_parse.php          # One-time parsing script
├── maintenance/
│   ├── backup_database.php       # Database backup utility
│   └── cleanup_logs.php          # Log rotation script
└── setup/
    ├── app_platform_deploy.php   # App Platform deployment initialization
    └── migrate.php                # Database migration runner
```

### Testing Structure (To Be Implemented)
```
tests/                            # Test suites
├── unit/                         # Unit tests
│   ├── Models/
│   │   ├── TournamentTest.php
│   │   └── AdminUserTest.php
│   ├── Services/
│   │   ├── TournamentParserTest.php
│   │   └── ValidationServiceTest.php
│   └── Utils/
│       ├── SecurityHelperTest.php
│       └── DateHelperTest.php
├── integration/                  # Integration tests
│   ├── DatabaseTest.php
│   ├── ParserIntegrationTest.php
│   └── AuthIntegrationTest.php
└── fixtures/                     # Test data
    ├── sample_forum_posts.html
    └── test_tournaments.json
```

## Current Project State

### What Already Exists ✅
```
.bmad-core/                       # BMAD framework files
├── core-config.yaml              # Project configuration
├── tasks/                        # Development task workflows
├── templates/                    # Document templates
└── checklists/                   # Quality assurance checklists

docs/                             # Documentation
├── prd.md                        # Product Requirements Document
├── architecture-progress.md      # Architecture documentation
├── DEPLOYMENT.md                 # App Platform deployment guide
├── front-end-spec.md             # UI/UX specifications
├── architecture/                 # Architecture documentation
│   ├── coding-standards.md       # Development standards
│   ├── tech-stack.md             # Technology stack details
│   └── source-tree.md            # This document
└── stories/                      # Development user stories

data/
├── schema.sql                    # Database schema ✅ (exists)
└── .htaccess                     # Security protection

config/
└── database.php                  # Basic database config ✅ (exists)

public/
└── index.php                     # Basic homepage ✅ (exists)

src/                              # Empty directory ✅ (exists)
logs/                             # Empty directory ✅ (exists)

.gitignore                        # Git exclusions ✅ (exists)
```

### What Needs to Be Created 🔨
```
.do/app.yaml                      # App Platform configuration
composer.json                     # PHP dependencies
phpunit.xml                       # Testing configuration

public/                           # Complete web interface
├── tournaments.php               # Tournament list page
├── tournament.php                # Tournament detail page
├── admin/                        # Complete admin interface
├── api/                          # REST API endpoints
├── assets/                       # CSS, JS, images
└── health.php                    # Health check endpoint

src/                              # Complete PHP application
├── models/                       # All data models
├── services/                     # All business logic
├── repositories/                 # All data access
├── utils/                        # All utility functions
└── templates/                    # All view templates

scripts/                          # All automation scripts
tests/                            # Complete test suite
data/migrations/                  # Database migrations
config/                           # Complete configuration
```

## Implementation Priority (Based on Stories)

### Epic 1: Core Data Pipeline & Admin Foundation
```
Priority 1: Basic Infrastructure
├── .do/app.yaml                  # App Platform setup
├── composer.json                 # Dependency management
├── src/config/Database.php       # Database connection
├── src/utils/SecurityHelper.php  # Security utilities
└── scripts/setup/migrate.php     # Database initialization

Priority 2: Authentication System
├── src/models/AdminUser.php      # Admin user model
├── src/services/AuthService.php  # OAuth integration
├── public/admin/login.php        # Login interface
└── public/api/auth.php           # OAuth callback

Priority 3: Parser System
├── src/models/Tournament.php     # Tournament model
├── src/services/TournamentParser.php  # Parsing logic
├── src/repositories/TournamentRepository.php  # Data access
└── scripts/parser/daily_parser.php    # Automation script

Priority 4: Admin Interface
├── public/admin/index.php        # Dashboard
├── public/admin/edit.php         # Edit form
├── src/templates/admin/          # Admin templates
└── public/admin/logs.php         # Logs viewer
```

### Epic 2: Public Interface & Launch
```
Priority 5: Public Pages
├── public/index.php              # Homepage (enhance existing)
├── public/tournaments.php        # Tournament list
├── public/tournament.php         # Tournament detail
└── src/templates/pages/          # Page templates

Priority 6: API & Filtering
├── public/api/tournaments.php    # Tournament API
├── public/api/filters.php        # Filter options
├── src/services/FilterService.php    # Filter logic
└── public/assets/js/             # Frontend JavaScript

Priority 7: Frontend Assets
├── public/assets/css/custom.css  # Custom styles
├── public/assets/js/main.js      # Core functionality
├── public/assets/js/tournaments.js   # List interactions
└── public/assets/js/filters.js   # Filter functionality
```

## Korean Market Optimizations (To Be Implemented)

### Korean-Specific Structure
```
src/utils/KoreanHelper.php        # Korean text processing
public/assets/css/korean.css      # Korean typography
data/seeds/korean_terms.sql       # Korean terminology
config/korean.php                 # Korean-specific config
```

## App Platform Integration Points

### Deployment Configuration (To Be Created)
```
.do/app.yaml                      # Main deployment config
├── services[].web                # Web service definition
├── jobs[].tournament-parser      # Daily parsing job
└── envs[]                        # Environment variables

scripts/setup/app_platform_deploy.php  # Deployment initialization
├── Create /tmp directories
├── Initialize SQLite database
├── Run migrations
└── Set up Korean optimizations
```

### Container Runtime Structure
```
/tmp/                             # App Platform persistent storage
├── tournaments.db                # SQLite database (runtime)
├── backups/                      # Database backups
├── logs/                         # Application logs
└── sessions/                     # PHP sessions
```

## File Naming Conventions (To Be Followed)

### Implementation Standards
```
PHP Classes:        PascalCase.php       (TournamentParser.php)
PHP Templates:      kebab-case.php       (tournament-list.php)
JavaScript:         kebab-case.js        (tournament-filters.js)
CSS:               kebab-case.css        (tournament-card.css)
SQL Migrations:    001_descriptive.sql   (001_initial_schema.sql)
Configuration:     lowercase.php         (database.php)
API Endpoints:     lowercase.php         (tournaments.php)
```

## Security Structure (To Be Implemented)

### Protection Layers
```
App Platform Protection (Automatic):
├── src/          # Protected - application code
├── data/         # Protected - database files
├── config/       # Protected - configuration
├── scripts/      # Protected - CLI scripts
└── logs/         # Protected - log files

public/           # Served - web accessible only

Application Security (To Implement):
├── src/utils/SecurityHelper.php  # CSRF, XSS protection
├── src/utils/ValidationHelper.php # Input validation
└── public/.htaccess              # Additional headers
```

## Testing Strategy (To Be Implemented)

### Test Coverage Plan
```
Unit Tests (80% target):
├── All models (Tournament, AdminUser, SystemLog)
├── All services (Parser, Auth, Filter, Validation)
├── All repositories (data access methods)
└── All utilities (Security, Database, Date helpers)

Integration Tests (Key Workflows):
├── Database operations (CRUD, migrations)
├── Parser integration (forum API → database)
├── Authentication flow (OAuth → session)
└── API endpoints (request → response)
```

---

**Note**: This is the **planned structure** for a greenfield project. Implementation will follow the user stories in Epic 1 (Core Data Pipeline & Admin Foundation) and Epic 2 (Public Interface & Launch), with the goal of deploying to DigitalOcean App Platform for $5/month Korean market optimization.