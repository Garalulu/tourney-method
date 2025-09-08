# Unified Project Structure

**Monorepo Organization:** Single repository with functional separation and vanilla PHP structure optimized for Korean deployment and future evolution.

## Directory Structure

```
tourney-method/
├── .bmad-core/                    # BMAD framework files (not deployed)
│   ├── tasks/
│   ├── templates/
│   └── core-config.yaml
├── docs/                          # Documentation (not deployed)
│   ├── prd.md
│   ├── architecture.md
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
│   └── .htaccess                  # Local development only
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

This unified structure provides:
1. **Clear Separation:** Public vs private code organization
2. **Security by Default:** Sensitive files outside web root
3. **Korean Optimization:** UTF-8 support and KST timezone handling
4. **Performance Focus:** Efficient asset loading and caching strategies
5. **Evolution Readiness:** Structure supports planned technology migrations
6. **Developer Experience:** Logical organization for solo developer maintenance
