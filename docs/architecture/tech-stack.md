# Tech Stack

## Technology Stack Table

| Category | Technology | Version | Purpose | Rationale |
|----------|------------|---------|---------|-----------|
| Frontend Language | JavaScript | ES6+ | Progressive enhancement, DOM manipulation | Widely supported, no build process needed, works with jQuery |
| Frontend Framework | jQuery | 3.7+ | DOM manipulation, AJAX, progressive enhancement | Lightweight, no build process, proven reliability, easy debugging |
| UI Component Library | Pico.css | 2.0+ | Class-less CSS framework | Minimal overhead, automatic theming, no custom CSS needed |
| State Management | Browser Storage + jQuery | Native | Client-side state persistence | Simple approach, no complex state management needed for current scope |
| Backend Language | PHP | 8.1+ | Server-side logic, templating | Zero configuration, built-in web server, DigitalOcean App Platform optimized |
| Backend Framework | Vanilla PHP | Native | Application structure and routing | No framework overhead, full control, easier debugging, faster deployment |
| API Style | REST | HTTP/1.1 | Public and admin endpoints | Simple, well-understood, cacheable, works with any client |
| Database | SQLite | 3.40+ | Data persistence and querying | File-based, zero configuration, easy backups, perfect for current scale |
| Cache | SQLite WAL mode | Built-in | Query result caching | Built into SQLite, no additional infrastructure needed |
| File Storage | External hosting | CDN | Tournament banner images | No local storage needed, CDN benefits, reduced server load |
| Authentication | osu! OAuth 2.0 | v2 | Admin authentication and authorization | Secure, familiar to target users, official osu! integration |
| Frontend Testing | Manual + Browser DevTools | Native | UI/UX validation and debugging | Appropriate for progressive enhancement approach, visual testing focus |
| Backend Testing | PHPUnit | 10+ | Unit and integration testing | PHP standard, supports mocking, good CI integration |
| E2E Testing | Manual Testing | Native | Cross-browser functionality validation | Cost-effective for current scope, thorough coverage |
| Build Tool | None | - | Direct file deployment | No build complexity, instant deployment, easier debugging |
| Bundler | None | - | Direct file inclusion | Simple approach, HTTP/2 makes multiple files efficient |
| IaC Tool | DigitalOcean App Spec | YAML | Infrastructure as code | Platform native, simple configuration, version controlled |
| CI/CD | DigitalOcean App Platform | Git-based | Automated deployment pipeline | Integrated with platform, zero configuration needed |
| Monitoring | DigitalOcean Insights | Platform | Performance and error monitoring | Built-in, no additional setup, comprehensive metrics |
| Logging | PHP error_log + Platform | Native | Error tracking and debugging | Platform aggregation, no additional services needed |
| CSS Framework | Pico.css | 2.0+ | Responsive styling and theming | Class-less approach, automatic dark/light themes, minimal overhead |

**Korean Deployment Specifics:**
- **Timezone:** Asia/Seoul (KST) for all timestamps
- **Character Encoding:** UTF-8 throughout the application
- **CDN Configuration:** CloudFlare CDN with Singapore edge servers
- **Database Collation:** UTF8_UNICODE_CI for proper Korean character handling

**Evolution Strategy:**
- Phase 1: Current vanilla stack
- Phase 2: jQuery → Alpine.js for enhanced reactivity
- Phase 3: Alpine.js → Vue.js for complex UI components
- Phase 4: SQLite → PostgreSQL for advanced features
- Phase 5: Monolith → Microservices for scale
