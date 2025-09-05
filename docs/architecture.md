# Tourney Method Architecture Progress

## Current Status: ✅ COMPLETED SECTIONS

### ✅ Introduction
- Starter template analysis: Greenfield project with vanilla PHP + jQuery + SQLite
- Korean deployment focus: DigitalOcean Singapore for optimal Korean latency
- Technology constraints: Vanilla PHP, jQuery, SQLite, no frameworks
- Architecture evolution path: Monolith → Enhanced services → Framework → Microservices

### ✅ High Level Architecture  
- Platform: DigitalOcean App Platform (Singapore SGP1) for Korean market  
- Tech stack: PHP/Nginx on App Platform with SQLite, jQuery, Pico.css
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

**Access Control via App Platform:**
```yaml
# App Platform automatically protects non-public directories
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

### 🔄 Development Workflow

The development workflow is designed to be lightweight and efficient, aligning with the vanilla PHP and direct deployment approach.

- **Local Environment Setup**: Developers will set up a local PHP development environment with SQLite, mirroring the App Platform production setup.
- **Code Editing**: Any standard IDE or text editor can be used. VS Code with PHP extensions is recommended for consistency.
- **Version Control**: Git is used for source code management. A monorepo strategy is employed, with all project components residing in a single repository.
- **Dependency Management**: Minimal external PHP dependencies are managed via Composer, primarily for development tools like PHPUnit. Frontend assets (jQuery, Pico.css) are included directly.
- **Testing**:
    - **Unit Tests**: PHPUnit is utilized for unit testing core PHP logic (e.g., `TournamentParserTest.php`, `ValidationServiceTest.php`).
    - **Integration Tests**: Basic integration tests cover database interactions and API endpoints.
    - **Manual Testing**: Extensive manual testing is performed across various browsers and devices to ensure UI/UX consistency and functionality.
- **Debugging**: Standard PHP debugging tools (e.g., Xdebug) can be integrated with IDEs for server-side debugging. Browser developer tools are used for frontend debugging.
- **Code Review**: Given the initial solo developer context, code reviews are informal. As the team grows, a pull request-based review process will be adopted.
- **Build Process**: There is no formal build process. Files are directly deployed.
- **Deployment**: Git-based automatic deployment via App Platform. Database migrations are applied during the build process via scripts in `.do/app.yaml`.

### 🚀 Deployment Architecture

The Tourney Method is deployed on **DigitalOcean App Platform**, chosen for its cost-effectiveness, ease of management, and optimization for the Korean market. This managed service significantly simplifies deployment and operations compared to a traditional droplet.

- **Platform**: DigitalOcean App Platform
- **Region**: Singapore (SGP1) - selected for optimal latency to Korean users (30-60ms to Seoul/Busan).
- **Cost Efficiency**: Utilizes the Basic Web Service tier at $5/month, representing a 60% saving compared to a traditional droplet.
- **Deployment Model**: Git-based automatic deployment. Pushing changes to the `main` branch of the GitHub repository triggers an automatic build and deployment on the App Platform.
- **Runtime Environment**: Managed PHP 8.1+ runtime with Nginx as the web server, fully managed by the App Platform.
- **Database**: SQLite (file-based) stored in the persistent `/tmp/tournaments.db` directory provided by the App Platform, ensuring data persistence across deployments and restarts.
- **Scheduled Jobs**: The daily tournament parser runs as a scheduled job directly on the App Platform, configured to execute `php scripts/parser/daily_parser.php` daily at 2 AM KST.
- **Built-in Services**:
    - **SSL/HTTPS**: Automatic Let's Encrypt certificate management and HTTPS enforcement.
    - **CDN**: Built-in global CDN for enhanced asset delivery and improved performance for Korean users.
    - **Automatic Scaling**: Basic automatic scaling capabilities are available to handle traffic fluctuations.
    - **Monitoring**: Integrated performance and error monitoring, with centralized log aggregation.
- **Configuration Management**: Environment variables are securely managed through the App Platform dashboard for sensitive data like OAuth credentials.
- **Timezone**: The application and platform are configured to use `Asia/Seoul` (KST) timezone for consistent timestamping.
- **Security Features**: App Platform provides built-in DDoS protection, network isolation, and automatic OS/runtime security updates. Application-level security (prepared statements, XSS/CSRF protection) is implemented within the PHP codebase.
- **Maintenance**: Zero server maintenance required, as the platform handles OS updates, patching, and infrastructure management.

This architecture provides a robust, low-cost, and low-maintenance solution optimized for the Korean market, aligning perfectly with the project's greenfield and minimalist approach.

### 🔒⚡ Security and Performance

Security is a non-negotiable aspect, addressed at both the platform and application layers.

-   **Platform-Level Security (DigitalOcean App Platform)**:
    -   **Automatic HTTPS**: All traffic is encrypted with automatically managed Let's Encrypt SSL certificates.
    -   **DDoS Protection**: Built-in platform-level protection against distributed denial-of-service attacks.
    -   **Network Isolation**: Applications run in isolated environments, enhancing security.
    -   **Automatic Security Updates**: The platform handles OS and runtime security patches, reducing maintenance overhead.
    -   **Secure Environment Variables**: Sensitive data like API keys and OAuth secrets are securely stored and managed as environment variables, not committed to the repository.
-   **Application-Level Security (Vanilla PHP)**:
    -   **SQL Injection Prevention**: All database queries utilize PDO prepared statements, ensuring user input cannot manipulate SQL queries.
    -   **XSS Prevention**: All user-generated or external data rendered to HTML is meticulously escaped using `htmlspecialchars()` to prevent cross-site scripting attacks.
    -   **CSRF Protection**: All state-changing forms, particularly within the admin panel, are protected with anti-CSRF tokens to prevent cross-site request forgery.
    -   **Secure File Permissions**: Strict file permissions are enforced (e.g., 600 for sensitive files like the SQLite database, 644 for web-accessible files).
    -   **App Platform Protection**: Platform-managed Nginx configuration ensures only `public/` directory is web-accessible, with automatic protection of sensitive directories (`src/`, `data/`, `config/`, `scripts/`, `logs/`).
    -   **Admin Authentication**: Secure admin login is implemented via osu! OAuth 2.0, with user ID verification against a hard-coded list of authorized admins.

#### Performance

Performance is a core non-functional requirement, targeting a fast and responsive user experience, especially for Korean users.

-   **Lightweight Technology Stack**: The choice of Vanilla PHP, jQuery, and Pico.css minimizes overhead, reducing resource consumption and improving load times.
-   **DigitalOcean App Platform Optimizations**:
    -   **Singapore Region (SGP1)**: Provides optimal latency for Korean users.
    -   **Built-in CDN**: Automatically caches and serves static assets globally, significantly speeding up content delivery.
    -   **Managed Runtime**: Optimized PHP and Nginx configuration managed by the platform.
-   **No Build Process**: Direct file deployment eliminates build overhead, simplifying the CI/CD pipeline and speeding up deployments.
-   **Database Optimizations (SQLite)**:
    -   `PRAGMA journal_mode = WAL`: Improves concurrency for better performance.
    -   `PRAGMA synchronous = NORMAL`: Balances data integrity with write performance.
    -   `PRAGMA cache_size`: Configured for an optimal cache size (e.g., 10000 pages).
    -   `PRAGMA temp_store = memory`: Uses in-memory for temporary tables.
-   **PHP Runtime Optimizations**:
    -   `opcache_enable = 1`: PHP's built-in opcode cache is enabled to store precompiled script bytecode in shared memory, reducing parsing and compilation overhead on subsequent requests.
    -   **Memory Limits**: PHP memory limits are set to align with App Platform's `basic-xxs` tier (e.g., `memory_limit = 128M`).
-   **Frontend Performance**:
    -   **Pico.css**: A class-less CSS framework that is extremely lightweight.
    -   **Minimal JavaScript**: jQuery is used sparingly for progressive enhancement, avoiding heavy frameworks.
    -   **Lazy Loading**: Images (e.g., tournament banners) are lazy-loaded to reduce initial page weight.
    -   **Responsive Images**: Images are served at appropriate sizes for different devices.
    -   **Efficient Data Loading**: Initial tournament lists are limited (e.g., 10 items), with more loaded on demand via pagination.
-   **Performance Goals**:
    -   **Page Load**: Target < 2 seconds on a standard internet connection.
    -   **Interaction Response**: Target < 100ms for filter applications and modal opens.
    -   **API Endpoints**: Target < 1 second response time.

This combined approach ensures that the Tourney Method is not only secure against common vulnerabilities but also delivers a fast and fluid experience to its users, particularly within the target Korean market.

### 🧪 Testing Strategy

A pragmatic testing strategy is adopted to ensure the reliability and correctness of the Tourney Method application, balancing thoroughness with the constraints of a solo-developer greenfield project.

-   **Test Framework**: PHPUnit is the primary testing framework for backend PHP code.
-   **Test Types**:
    -   **Unit Tests**: Focus on isolated components (e.g., models, services, utility functions) to verify their individual logic. This includes testing critical logic like data parsing, validation, and BWS calculation.
    -   **Integration Tests**: Cover interactions between different components, particularly the data pipeline (e.g., parser saving data to the database, API endpoints interacting with services and repositories). This ensures that the system's critical flows work as expected.
-   **Test Location**: Tests are organized within the `tests/` directory, separated into `unit/`, `integration/`, and `fixtures/` subdirectories.
-   **Test Data**: `fixtures/` are used to store sample data for testing, such as mock API responses or sample forum posts.
-   **Test-Driven Development (TDD)**: While not strictly enforced, a TDD-inspired approach is encouraged for complex or critical components to ensure testability and correctness from the outset.
-   **Continuous Testing**: Tests are run frequently during development. Automated testing will be integrated into the deployment pipeline on DigitalOcean App Platform (e.g., as part of the build command) to ensure no regressions are introduced.
-   **Manual Testing**: Given the UI/UX focus and progressive enhancement, extensive manual testing across various browsers and devices is crucial to verify frontend functionality, responsiveness, and user experience.
-   **Security Testing**: Specific tests are implemented to verify security measures, such as SQL injection prevention, XSS protection, and CSRF token validation.
-   **Performance Testing**: Basic performance checks are conducted to ensure the application meets its performance goals, especially for page load times and API response times.

### 📏 Coding Standards

Adherence to established coding standards is crucial for maintaining a clean, consistent, and maintainable codebase, especially for a solo-developer project with future expansion in mind. The Tourney Method follows a set of pragmatic standards adapted from common PHP and web development best practices.

-   **PHP Standards**:
    -   **Code Style**: Primarily follows PSR-12 (Extended Coding Style) with 4-space indentation, 120-character line limit, and UTF-8 encoding without BOM.
    -   **Naming Conventions**: PascalCase for classes, camelCase for methods/variables, UPPER_SNAKE_CASE for constants, and snake_case for database columns.
    -   **Class Structure**: Clear separation of public and private methods, with dependencies injected via constructors.
    -   **Security Requirements**: Non-negotiable adherence to prepared statements for database queries, `htmlspecialchars()` for output escaping, and CSRF tokens for form protection.
    -   **Error Handling**: Use of specific exception types and logging errors with context, avoiding exposure of internal details to users.
-   **Frontend Standards (JavaScript/CSS/HTML)**:
    -   **JavaScript (jQuery)**: Use strict mode, namespace patterns to avoid global pollution, and clear event binding patterns.
    -   **CSS Organization**: Component-based structure, state modifiers, utility classes, and specific rules for Korean character support.
    -   **HTML Structure**: Emphasis on semantic HTML5 with accessibility in mind, including `noscript` fallbacks for progressive enhancement.
-   **Database Standards**:
    -   **Schema Conventions**: Plural, snake_case for table names; `{table}_id` for primary keys; `{referenced_table}_id` for foreign keys; `created_at`/`updated_at` for timestamps; `is_{condition}` for booleans; `{entity}_status` for status fields.
    -   **Query Patterns**: Strict use of the Repository pattern with prepared statements for all database interactions.
-   **File Organization Standards**:
    -   **Directory Structure**: Follows the greenfield design with clear separation of `public/`, `src/`, `data/`, `config/`, `scripts/`, `tests/`, and `logs/` directories.
    -   **File Naming**: Consistent naming conventions for PHP classes (`PascalCase.php`), templates (`kebab-case.php`), JavaScript (`kebab-case.js`), and CSS (`kebab-case.css`).
-   **Documentation Standards**:
    -   **Code Comments**: Use PHPDoc blocks for classes and methods, with `TODO` and `FIXME` tags for future work.
    -   **README Requirements**: Comprehensive `README.md` covering setup, deployment, environment variables, and getting started.
-   **Version Control Standards**:
    -   **Git Commit Messages**: Follow a conventional commit format (e.g., `type(scope): description`).
    -   **Branch Naming**: Consistent naming for feature, bug fix, and hotfix branches.

### 🚨 Error Handling Strategy

A robust error handling strategy is critical for maintaining application stability, providing a good user experience, and enabling efficient debugging and problem resolution. The Tourney Method implements a multi-layered approach to error handling.

-   **Graceful Failure**: The application is designed to fail gracefully, especially when dealing with external dependencies (e.g., osu! API). In case of API failures or other critical issues, the system will log the error and continue operation without crashing or exposing sensitive information to the user.
-   **Specific Exception Types**: Custom exception types are used to categorize errors (e.g., `ValidationException`, `DatabaseException`, `AuthenticationException`). This allows for more precise error handling and clearer debugging.
-   **Centralized Logging**: All errors, warnings, and informational messages are logged to a centralized system.
    -   **Application Logs**: PHP's `error_log` is used for application-level errors, which are then aggregated by the DigitalOcean App Platform's logging service.
    -   **System Logs Table**: A dedicated `system_logs` table in the SQLite database stores critical system events, particularly errors from the daily parser script. This provides an in-application view of system health.
-   **Contextual Logging**: Errors are logged with sufficient context (e.g., `topic_id` for parser failures, user ID for authentication issues) to facilitate rapid diagnosis.
-   **User-Friendly Messages**: Internal error details are never exposed directly to end-users. Generic, user-friendly error messages are displayed instead, prompting users to try again later or contact support if necessary.
-   **Alerting**: For critical errors (e.g., persistent parser failures, database connection issues), an alerting mechanism will be considered in future phases to notify administrators proactively.
-   **Input Validation**: Comprehensive input validation is performed at the application layer to prevent invalid data from entering the system, thereby reducing the likelihood of errors.
-   **Security Exceptions**: Specific handling for security-related exceptions (e.g., CSRF token mismatch) to ensure that security breaches are logged and handled appropriately without compromising the system.

### 📊 Monitoring and Observability

Effective monitoring and observability are crucial for understanding the application's health, performance, and user experience, especially in a production environment. The Tourney Method leverages both platform-provided and application-level monitoring capabilities.

-   **Platform-Managed Monitoring (DigitalOcean App Platform)**:
    -   **Application Logs**: Centralized log aggregation is provided by the App Platform, allowing for easy viewing and analysis of application logs (e.g., PHP `error_log` output).
    -   **Performance Metrics**: Built-in dashboards provide key performance indicators (KPIs) such as CPU usage, memory consumption, request volume, response times, and error rates. These metrics are vital for tracking overall system health and identifying potential bottlenecks.
    -   **Health Checks**: The App Platform automatically performs health checks against a defined endpoint (e.g., `public/health.php`) to ensure the application is responsive and available.
    -   **Uptime Monitoring**: The platform provides uptime monitoring and alerts for service disruptions.
-   **Application-Level Observability**:
    -   **System Logs Table**: The `system_logs` database table serves as an in-application log viewer for critical events, particularly parser execution status and errors. This provides an in-application view of system health.
    -   **Contextual Logging**: As part of the error handling strategy, logs include sufficient context to aid in debugging and root cause analysis.
    -   **Korean Performance Monitoring**: Specific performance testing scripts are planned to measure latency and response times from a Korean perspective, ensuring the application meets its performance goals for the target market.
    -   **Parser Monitoring**: Dedicated monitoring for the daily tournament parser job, including checking its execution history and logs to ensure it runs successfully and on schedule.
-   **Alerting**: While basic monitoring is in place, future enhancements will include proactive alerting for critical issues (e.g., parser failures, high error rates, resource exhaustion) to notify administrators via external channels.
-   **Cost Monitoring**: DigitalOcean provides tools to monitor resource consumption and costs, ensuring the application remains within budget.

### ✅ Checklist Results Report

The Tourney Method project has undergone a comprehensive Product Owner Master Checklist validation, confirming its readiness for development.

-   **Overall Readiness**: The project achieved 100% readiness, with all critical gaps resolved.
-   **Critical Blocking Issues**: Zero critical blocking issues were identified.
-   **Documentation Status**: The complete documentation package has been delivered, including OpenAPI 3.0 API specifications, a Korean Production Deployment Guide, and a Development Environment Setup guide.
-   **Implementation Readiness**: Full specifications have been provided for all features, ensuring clear guidance for development.
-   **Key Gaps Resolved**: Significant improvements were made in URL ID extraction specifications, admin management system, parser error recovery strategy, field validation rules, and security configuration.
-   **Korean Market Optimizations**: Confirmed deployment on DigitalOcean App Platform in Singapore for optimal Korean latency, with KST timezone and UTF-8 support throughout the application.
-   **Technology Stack Confirmation**: The chosen minimalist stack (Vanilla PHP, jQuery, SQLite on DigitalOcean App Platform) has been validated.
-   **Final Approval**: Full approval has been granted, authorizing immediate development execution starting with Story 1.1.
-   **Risk Assessment**: The overall risk status was updated to "LOW" after addressing high-risk items.

This validation confirms that the architecture and project plan are solid, well-documented, and ready for implementation, providing a robust foundation for the Tourney Method.

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