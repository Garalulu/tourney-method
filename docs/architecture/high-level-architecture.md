# High Level Architecture

## Technical Summary

Tourney Method is a traditional web application built with vanilla PHP and progressive enhancement, designed specifically for the osu! tournament community. The architecture leverages DigitalOcean App Platform for managed deployment, providing optimal performance for users worldwide. The system integrates with osu! OAuth 2.0 for admin authentication and the osu! Forum API for automated tournament data parsing.

**Enhanced Architecture (v1.3 - September 2025):**
The system now features comprehensive tournament metadata extraction with confidence scoring, star rating parsing from tournament brackets, Discord integration, registration date extraction, and normalized data models. The enhanced ForumPostParserService provides robust parsing of complex tournament structures with support for multiple game modes, team formats, and BWS calculations.

The frontend uses jQuery with Pico.css for a lightweight, responsive experience that works across devices without heavy framework overhead. The backend implements a clean repository pattern with SQLite for data persistence, featuring enhanced security measures including CSRF protection, secure session management, and rate limiting. This architecture achieves the PRD goals of automated tournament discovery, admin-curated data quality, and comprehensive tournament information display.

## Platform and Infrastructure Choice

**Platform:** DigitalOcean App Platform
**Key Services:** Managed PHP runtime, Nginx web server, automatic SSL, built-in CDN, scheduled jobs
**Deployment Host and Regions:** Singapore (SGP1) for optimal Korean latency (30-60ms to Seoul/Busan)

**Platform Decision Rationale:**
- **Cost Efficiency:** $5/month vs $25/month for traditional droplet (60% savings)
- **Korean Market Optimization:** Singapore region provides best latency to Korea
- **Zero Server Management:** Automatic OS updates, security patches, scaling
- **Integrated Services:** SSL, CDN, monitoring, and scheduled jobs built-in
- **Git-based Deployment:** Direct deployment from GitHub repository

**Alternative Considered:** Traditional DigitalOcean droplet was rejected due to higher maintenance overhead and cost.

## Repository Structure

**Structure:** Monorepo with functional organization
**Monorepo Tool:** Native Git (no additional tooling required)
**Package Organization:** Functional separation (public/, src/, data/, config/, scripts/, tests/)

The monorepo approach simplifies development and deployment while maintaining clear separation of concerns through directory structure rather than separate repositories.

## High Level Architecture Diagram

```mermaid
graph TB
    A[Korean Users] --> B[CloudFlare CDN]
    B --> C[DigitalOcean App Platform<br/>Singapore SGP1]
    C --> D[Nginx Web Server]
    D --> E[PHP Application]
    E --> F[SQLite Database]
    
    G[osu! Forum API] --> H[Daily Parser<br/>Cron Job]
    H --> E
    
    I[Admin Users] --> J[osu! OAuth 2.0]
    J --> E
    
    E --> K[Tournament Data<br/>Processing]
    K --> L[Cross-Language<br/>Term Mapping]
    
    M[Public Interface<br/>jQuery + Pico.css] --> D
    N[Admin Interface<br/>Tournament Review] --> D
    
    style C fill:#0080FF,color:#fff
    style F fill:#90EE90,color:#000
    style H fill:#FFB6C1,color:#000
    style L fill:#FFA500,color:#000
```

## Architectural Patterns

- **Monolithic Architecture:** Single deployable unit with clear internal boundaries - _Rationale:_ Simplicity for solo developer, easy deployment and debugging, cost-effective for initial scale
- **Repository Pattern:** Abstract data access logic - _Rationale:_ Enables testing and future database migration flexibility from SQLite to PostgreSQL
- **Progressive Enhancement:** JavaScript enhances but doesn't replace core functionality - _Rationale:_ Accessibility, performance, and reliability for diverse user devices and network conditions
- **MVC Separation:** Clear separation of models, views, and controllers - _Rationale:_ Maintainability and organization despite not using a formal MVC framework
- **Enhanced Service Layer Pattern:** Business logic encapsulated in service classes with comprehensive parsing capabilities - _Rationale:_ Complex tournament parsing logic isolated from controllers, enabling thorough testing and confidence scoring
- **Security-First Design:** CSRF protection, secure sessions, rate limiting, and input validation throughout - _Rationale:_ Proactive security measures integrated into core architecture rather than added as afterthoughts
- **Data Migration Pattern:** Versioned schema migrations with backward compatibility - _Rationale:_ Safe evolution of database structure while maintaining data integrity and system availability
- **Confidence-Based Parsing:** Quality assessment and scoring for all extracted data - _Rationale:_ Enables admin prioritization of reviews and quality metrics for parser improvements

## Recent Architectural Enhancements (v1.3)

### Enhanced Parsing Architecture
- **Comprehensive Metadata Extraction:** Title parsing, star ratings, Discord links, registration dates
- **Confidence Scoring System:** Quality assessment for all extracted fields with JSON storage
- **Normalized Data Models:** Standardized game modes, team formats, and rank ranges
- **Multi-Format Date Parsing:** Support for various international date formats
- **osu! API Integration:** Real-time username resolution for tournament hosts

### Security Architecture Improvements  
- **Session Management:** Secure session tracking with CSRF tokens and admin session flagging
- **Input Validation:** Comprehensive sanitization and validation for all user inputs
- **Rate Limiting:** API endpoint protection with configurable windows
- **URL Security:** Storage of IDs/slugs only, full URL reconstruction on display

### Database Architecture Evolution
- **Three-Phase Migration Strategy:** Incremental feature rollout with zero downtime
- **Optimized Indexing:** Strategic indexes for common query patterns and new fields
- **View-Based Queries:** Materialized common queries for improved performance
- **Constraint Enforcement:** Data integrity validation at database level
