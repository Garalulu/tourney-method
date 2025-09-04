# Tourney Method Architecture Progress

## Current Status: ✅ COMPLETED SECTIONS

### ✅ Introduction
- Starter template analysis: Greenfield project with vanilla PHP + jQuery + SQLite
- Korean deployment focus: DigitalOcean Singapore for optimal Korean latency
- Technology constraints: Vanilla PHP, jQuery, SQLite, no frameworks
- Architecture evolution path: Monolith → Enhanced services → Framework → Microservices

### ✅ High Level Architecture  
- Platform: DigitalOcean Singapore droplet for Korean market
- Tech stack: LAMP with SQLite, jQuery, Pico.css
- Repository structure: Monorepo with functional organization
- Architecture diagram: Traditional web app with osu! API integration
- Patterns: Monolithic, MVC, Repository, Progressive Enhancement

### ✅ Tech Stack
- Definitive technology selection table completed
- Korean deployment specifics: Singapore server, CloudFlare CDN, KST timezone
- No build tools, direct file deployment
- Evolution path: jQuery → Alpine.js → Vue.js → Mobile apps

### ✅ Data Models
- Tournament, AdminUser, SystemLog, ParsedData models defined
- TypeScript interfaces provided
- Language statistics collection (no ML approach)
- Korean raw data processing focus (not translation)
- **UPDATED**: Mappool schema added but deferred to Phase 2 (Google Sheets parsing prerequisite)

### ✅ API Specification
- Complete OpenAPI 3.0 REST API spec
- Public and admin endpoints defined
- Korean considerations: UTF-8, KST timezone, character encoding
- Evolution path: v1 → v2 enhanced → v3 real-time → WebSockets

### ✅ External APIs
- osu! OAuth 2.0 for admin authentication  
- osu! Forum API for tournament parsing
- External image hosting (no local storage)
- Evolution: Discord → Multi-platform → Korean platforms → Advanced integrations

### ✅ Core Workflows
- Daily tournament parsing with language fallback
- Admin authentication via osu! OAuth
- Tournament review and approval workflow
- Cross-language term mapping with admin management
- **KEY FEATURE**: Multiple term variations support (e.g., "Registration", "Player Reg", "Reg")

### ✅ Database Schema
- Complete SQLite schema with evolution to PostgreSQL
- Cross-language term mapping tables
- Language statistics collection (comprehensive: Korean, Russian, Chinese, Spanish, Portuguese, Polish, German, French, Japanese, English)
- **UPDATED**: Mappool schema designed but implementation deferred
- Admin-driven term mapping (no ML/AI features)
- Host-user relationship evolution planned

### ✅ Frontend Architecture
- jQuery + Pico.css progressive enhancement
- Component architecture: TournamentCard, FilterPanel, ModalViewer
- State management: Browser storage + jQuery
- **UPDATED**: Term mapping admin interface added
- Korean UTF-8 support throughout
- **REMOVED**: Mappool features deferred to Phase 2

### ✅ Backend Architecture  
- Vanilla PHP monolithic structure
- Repository pattern with clean separation
- Service layer architecture
- Authentication/authorization with osu! OAuth
- **UPDATED**: Term mapping management service
- Evolution path: Vanilla → Structured → Framework → Microservices

### ✅ Unified Project Structure

**Monorepo Organization:** Single repository with functional separation and vanilla PHP structure optimized for Korean deployment and future evolution.

#### Directory Structure

```
tourney-method/
├── .bmad-core/                    # BMAD framework files (not deployed)
│   ├── tasks/
│   ├── templates/
│   └── core-config.yaml
├── docs/                          # Documentation (not deployed)
│   ├── prd.md
│   ├── architecture-progress.md
│   └── front-end-spec.md
├── public/                        # Web root - all publicly accessible files
│   ├── index.php                  # Homepage with featured tournaments
│   ├── tournaments.php            # All tournaments page with filtering
│   ├── tournament.php             # Tournament detail page
│   ├── admin/                     # Admin interface
│   │   ├── index.php              # Admin dashboard
│   │   ├── login.php              # OAuth login handler
│   │   ├── logout.php             # Session cleanup
│   │   ├── edit.php               # Tournament edit form
│   │   ├── approve.php            # Tournament approval handler
│   │   └── logs.php               # System logs viewer
│   ├── api/                       # REST API endpoints
│   │   ├── tournaments.php        # Tournament CRUD operations
│   │   ├── filters.php            # Filter options endpoint
│   │   └── auth.php               # OAuth callback handler
│   ├── assets/                    # Static assets
│   │   ├── css/
│   │   │   ├── pico.min.css       # Pico.css framework
│   │   │   └── custom.css         # Tournament-specific styles
│   │   ├── js/
│   │   │   ├── jquery.min.js      # jQuery library
│   │   │   ├── main.js            # Core application logic
│   │   │   ├── tournaments.js     # Tournament list interactions
│   │   │   ├── modal.js           # Tournament detail modals
│   │   │   └── filters.js         # Filter panel functionality
│   │   └── images/
│   │       └── placeholder.png    # Tournament banner fallback
│   └── .htaccess                  # URL rewriting and security headers
├── src/                           # PHP application logic
│   ├── config/
│   │   ├── database.php           # SQLite connection configuration
│   │   ├── oauth.php              # osu! OAuth settings
│   │   └── constants.php          # Application constants
│   ├── models/                    # Data models
│   │   ├── Tournament.php         # Tournament entity and operations
│   │   ├── AdminUser.php          # Admin user management
│   │   ├── SystemLog.php          # Error and activity logging
│   │   ├── ParsedData.php         # Parser result handling
│   │   └── TermMapping.php        # Cross-language term management
│   ├── services/                  # Business logic services
│   │   ├── TournamentParser.php   # Forum post parsing logic
│   │   ├── AuthService.php        # osu! OAuth integration
│   │   ├── FilterService.php      # Tournament filtering logic
│   │   ├── ValidationService.php  # Input validation and sanitization
│   │   └── TermMappingService.php  # Language term mapping
│   ├── repositories/              # Data access layer
│   │   ├── TournamentRepository.php
│   │   ├── AdminUserRepository.php
│   │   ├── SystemLogRepository.php
│   │   └── TermMappingRepository.php
│   ├── utils/                     # Utility functions
│   │   ├── DatabaseHelper.php     # Database connection utilities
│   │   ├── SecurityHelper.php     # CSRF, XSS protection
│   │   ├── ValidationHelper.php   # Input validation utilities
│   │   └── DateHelper.php         # KST timezone handling
│   └── templates/                 # PHP view templates
│       ├── layouts/
│       │   ├── main.php           # Base layout template
│       │   └── admin.php          # Admin layout template
│       ├── components/
│       │   ├── tournament-card.php    # Tournament card component
│       │   ├── filter-panel.php       # Filter sidebar component
│       │   ├── modal.php              # Tournament detail modal
│       │   └── pagination.php         # Pagination controls
│       ├── pages/
│       │   ├── home.php               # Homepage content
│       │   ├── tournaments-list.php   # Tournament list page
│       │   └── tournament-detail.php  # Tournament detail view
│       └── admin/
│           ├── dashboard.php          # Admin dashboard content
│           ├── edit-tournament.php    # Tournament edit form
│           └── logs-viewer.php        # System logs display
├── data/                          # Data storage and scripts
│   ├── database/
│   │   ├── tournaments.db         # SQLite database file
│   │   └── schema.sql             # Database schema definition
│   ├── migrations/                # Database migration scripts
│   │   ├── 001_initial_schema.sql
│   │   ├── 002_add_term_mapping.sql
│   │   └── 003_add_indexes.sql
│   └── seeds/                     # Development seed data
│       ├── tournaments_sample.sql
│       └── term_mappings_initial.sql
├── scripts/                       # Command-line scripts
│   ├── parser/
│   │   ├── daily_parser.php       # Daily tournament parsing cron job
│   │   └── manual_parse.php       # One-time parsing script
│   ├── maintenance/
│   │   ├── backup_database.php    # Database backup utility
│   │   └── cleanup_logs.php       # Log rotation script
│   └── setup/
│       ├── install.php            # Initial setup script
│       └── migrate.php            # Database migration runner
├── tests/                         # Test files
│   ├── unit/
│   │   ├── TournamentParserTest.php
│   │   ├── ValidationServiceTest.php
│   │   └── TermMappingTest.php
│   ├── integration/
│   │   ├── DatabaseTest.php
│   │   └── ParserIntegrationTest.php
│   └── fixtures/
│       ├── sample_forum_posts.html
│       └── test_tournaments.json
├── config/                        # Environment configuration
│   ├── production.php             # Production settings
│   ├── development.php            # Development settings
│   └── testing.php                # Testing environment settings
├── logs/                          # Application logs
│   ├── access.log                 # Web server access logs
│   ├── error.log                  # PHP error logs
│   ├── parser.log                 # Tournament parser logs
│   └── security.log               # Security event logs
├── .gitignore                     # Git ignore patterns
├── composer.json                  # PHP dependencies (minimal)
├── phpunit.xml                    # PHPUnit test configuration
├── DEPLOYMENT.md                  # Deployment instructions
└── README.md                      # Project documentation
```

#### File Organization Patterns

**PHP Class Structure:**
- **PSR-4 Autoloading:** `src/` directory follows PSR-4 namespace structure
- **Single Responsibility:** Each class handles one specific concern
- **Dependency Injection:** Services injected via constructor parameters
- **Repository Pattern:** Data access separated from business logic

**Frontend Asset Organization:**
- **Progressive Enhancement:** CSS/JS loaded based on page requirements
- **Component-Based CSS:** Styles organized by component functionality
- **Modular JavaScript:** Feature-specific JS files loaded as needed
- **Performance Optimization:** Critical CSS inlined, non-critical deferred

**Configuration Management:**
- **Environment-Specific:** Separate config files for production/development
- **Sensitive Data:** OAuth secrets and API keys via environment variables
- **Database Configuration:** SQLite path and connection settings centralized
- **Korean Deployment:** KST timezone, UTF-8 encoding, Singapore CDN settings

#### Development vs Production Structure

**Development Additions:**
```
├── .vscode/                       # VS Code configuration
│   ├── settings.json              # Editor settings
│   └── launch.json                # Debug configuration
├── docker/                        # Local development containers
│   ├── Dockerfile.dev
│   └── docker-compose.yml
└── vendor/                        # Composer dependencies (dev only)
```

**Production Exclusions:**
- `.bmad-core/`, `docs/`, `tests/`, `docker/` directories not deployed
- Development dependencies excluded via `composer install --no-dev`
- Source maps and debug assets removed from production builds

#### Security File Structure

**Access Control via .htaccess:**
```apache
# Deny access to sensitive directories
<Directory "src">
    Require all denied
</Directory>
<Directory "data">
    Require all denied
</Directory>
<Directory "scripts">
    Require all denied
</Directory>
<Directory "config">
    Require all denied
</Directory>
<Directory "logs">
    Require all denied
</Directory>
```

**File Permission Strategy:**
- **Web Root (public/):** 644 for files, 755 for directories
- **Application Code (src/):** 600 for files, 700 for directories
- **Database File:** 600 permissions, owned by web server user
- **Log Files:** 640 permissions, web server and admin group access
- **Configuration:** 600 permissions with environment variable fallbacks

#### Evolution Path Structure

**Phase 1: Current Structure**
- Monolithic vanilla PHP application
- File-based SQLite database
- Direct file deployment to DigitalOcean

**Phase 2: Enhanced Services**
```
src/
├── api/                           # API versioning structure
│   ├── v1/                        # Current REST API
│   └── v2/                        # Enhanced API with real-time features
├── external/                      # External service integrations
│   ├── GoogleSheetsService.php    # Mappool parsing
│   └── DiscordService.php         # Community integrations
└── advanced/
    ├── CacheService.php           # Redis integration
    └── QueueService.php           # Background job processing
```

**Phase 3: Framework Migration**
```
app/                               # Laravel/Symfony structure
├── Http/Controllers/
├── Models/
├── Services/
└── Resources/
framework/                         # Framework core (auto-generated)
public/                           # Web root (unchanged)
resources/                        # Views and assets
├── views/
└── assets/
```

**Phase 4: Microservices Preparation**
```
services/
├── tournament-parser/             # Parsing service
├── user-management/               # Authentication service
├── notification-service/          # Real-time notifications
└── analytics-service/             # Usage analytics
shared/
├── models/                        # Shared data models
└── utils/                         # Common utilities
```

This unified structure provides:
1. **Clear Separation:** Public vs private code organization
2. **Security by Default:** Sensitive files outside web root
3. **Korean Optimization:** UTF-8 support and KST timezone handling
4. **Performance Focus:** Efficient asset loading and caching strategies
5. **Evolution Readiness:** Structure supports planned technology migrations
6. **Developer Experience:** Logical organization for solo developer maintenance

## 🔄 CURRENT TASK: Development Workflow

## ⏭️ REMAINING SECTIONS

1. **Development Workflow** ← CURRENT
2. Deployment Architecture  
3. Security and Performance
4. Testing Strategy
5. Coding Standards
6. Error Handling Strategy
7. Monitoring and Observability
8. Checklist Results Report

## 🎯 KEY DECISIONS MADE

### Language Processing Approach
- **NO ML/AI**: Admin-managed term mapping only
- **Fallback parsing**: English first, then registered languages
- **Multiple variations**: Each concept supports multiple term variations
- **Statistics collection**: Track all major tournament languages for data-driven decisions

### Mappool Features  
- **DEFERRED**: Requires Google Sheets parsing implementation first
- Database schema ready but frontend/backend integration postponed
- Beatmap + mod attributes system designed for future implementation

### Korean Market Focus
- **Singapore deployment** for optimal latency
- **Language statistics** for Korean + 9 other major tournament languages  
- **No translation system**: Raw data processing only
- **Cultural considerations** built into evolution planning

### Technology Constraints Maintained
- **Vanilla PHP**: No framework dependencies
- **SQLite**: File-based database for simplicity  
- **jQuery**: No modern frontend frameworks
- **Monorepo**: Single repository structure
- **Progressive enhancement**: Works without JavaScript

## 📋 ADMIN WORKFLOW HIGHLIGHTS

1. **Daily Parsing**: Automated forum parsing with language fallback
2. **Term Mapping**: Admin adds foreign terms → English concept mappings
3. **Tournament Review**: Visual highlighting of failed parsing fields
4. **Language Analytics**: Usage statistics to guide term mapping priorities
5. **Cross-language Support**: 10+ languages with admin-curated term dictionaries

This architecture provides a solid foundation for Korean tournament discovery while remaining flexible for international expansion.