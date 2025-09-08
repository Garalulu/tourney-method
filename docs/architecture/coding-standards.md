# Coding Standards

## Critical Fullstack Rules

- **Security First:** All database queries use prepared statements, all output escaped with htmlspecialchars(), CSRF tokens required on all admin forms
- **Korean UTF-8 Support:** All text processing handles Korean characters correctly, database uses UTF-8 encoding, timestamps in KST timezone
- **Progressive Enhancement:** All functionality works without JavaScript, JavaScript enhances but doesn't replace core features
- **API Consistency:** All API responses include success/error status, timestamps in ISO format with KST timezone, proper HTTP status codes
- **Error Handling:** Never expose internal errors to users, all errors logged with context, graceful degradation for external API failures
- **Admin Authentication:** Only whitelisted osu! user IDs can access admin functions, sessions expire after 24 hours of inactivity
- **Performance Budgets:** Page loads under 2 seconds, API responses under 500ms, tournament list limited to prevent memory issues

## Naming Conventions

| Element | Frontend | Backend | Example |
|---------|----------|---------|---------|
| Components | PascalCase | PascalCase | `TournamentCard.js`, `TournamentController.php` |
| Functions | camelCase | camelCase | `getTournaments()`, `parseForumPost()` |
| API Routes | kebab-case | kebab-case | `/api/tournaments`, `/api/admin/pending-review` |
| Database Tables | snake_case | snake_case | `tournaments`, `admin_users`, `term_mappings` |
| CSS Classes | kebab-case | - | `.tournament-card`, `.filter-panel` |
| JavaScript Files | kebab-case | - | `tournament-modal.js`, `korean-utils.js` |
| PHP Files | PascalCase | PascalCase | `TournamentRepository.php`, `AuthService.php` |
