# Brainstorming Session Results

**Session Date:** 2025-09-04
**Facilitator:** Business Analyst Mary
**Participant:** Sion

---

## Executive Summary

**Topic:** The "Tourney Method" project.

**Session Goals:** Focused ideation on the existing plan, keeping it minimal and fast for a resource-constrained environment on DigitalOcean's cheapest plan.

**Techniques Used:** 
- Resource Constraints
- Question Storming

**Total Ideas Generated:** 6

### Key Themes Identified:
- Data normalization is a key concern.
- SQLite is a suitable lightweight database choice.
- A simple, relational schema is needed.
- Foreign keys and junction tables are essential for data integrity and flexibility.
- Parsing logic is a significant point of failure.

---

## Action Planning

### Top Priority Ideas

**#1 Priority: Handling Parsing Failures**
- **Rationale:** The parser is a primary point of failure. A robust workflow for handling failures is critical for data quality and minimizing admin workload.
- **Next steps:**
  1. When the parser fails to find a specific data point (e.g., rank range), it will save the tournament to the database with a `NULL` value for that field.
  2. In the admin review UI, all newly parsed tournaments are shown. Any fields with `NULL` or empty values will be automatically highlighted in red.
  3. This provides a clear, immediate visual cue for admins to identify and manually correct missing information during their standard review process, without needing a separate queue.
  4. If the parser finds no recognizable English keywords, it will flag the tournament as "Manual Entry Required." This signals to the admin that they need to fill out all the data for that tournament from scratch.

**#2 Priority: Security Hardening**
- **Rationale:** Protecting user data and preventing common web vulnerabilities is a fundamental requirement, especially when not using a large framework that provides these protections by default.
- **Next steps:**
  1. **Prevent SQL Injection:** Strictly use prepared statements (parameterized queries) for all database queries involving external data. Do not use manual string escaping.
  2. **Prevent XSS (Cross-Site Scripting):** Strictly escape all data that is output to HTML using the `htmlspecialchars()` function to render special characters as harmless text.
  3. **Prevent CSRF (Cross-Site Request Forgery):** Protect all state-changing forms (especially in the admin panel) by implementing an anti-CSRF token system. The server will generate a token, place it in the user's session and in a hidden form field, and validate it upon submission.

**#3 Priority: Dependency Resilience**
- **Rationale:** The application depends on external APIs for all its data. It must be resilient to API failures to ensure site stability and data integrity.
- **Next steps:**
  1. **Create a `system_logs` table:** This table will store all important system events, especially errors from the parsing scripts.
  2. **Implement Graceful Failure:** Scripts that call external APIs will be wrapped in robust error handling. On failure, they will log the error and exit cleanly without crashing.
  3. **Admin Panel Log Viewer:** A new page will be added to the admin panel to display messages from the `system_logs` table, allowing for easy monitoring of the parsers' health.
  4. **Defensive Coding:** Parsers will be written defensively, never assuming an API field exists before trying to access it. This prevents crashes if an API schema changes unexpectedly.

**#4 Priority: Efficient BWS Calculation**
- **Rationale:** A real-time BWS calculation would be too slow and resource-intensive. A daily-updated local cache of user stats is a much more efficient and robust approach.
- **Next steps:**
  1. On a user's first login, fetch their `rank` and `badge_count` from the osu! API and store it in the local `Users` table.
  2. A daily cron job will iterate through all registered users and update their `rank` and `badge_count` from the API.
  3. The "Can I join?" feature will use this locally-cached data, avoiding expensive real-time API calls on page load.
  4. **Error Handling:** If the daily update job encounters a persistent API failure (e.g., fails 5 times in a row), it will log the error and abort, ensuring a temporary API outage doesn't overload the system. The data will simply be one day stale.

### Resource Constraints - 15 minutes

**Description:** The facilitator poses a hypothetical, extreme constraint to challenge the plan and uncover creative, hyper-efficient solutions. The initial constraint was "no database," forcing a focus on data storage.

**Ideas Generated:**
1. A database is essential to avoid data duplication, especially for user data (hosts, staff, etc.).
2. SQLite is a good fit for the project's constraints as it's a self-contained, single-file database requiring no separate server process.
3. A minimal database schema should consist of three main tables: `Tournaments`, `Users`, and `Badges`.
4. The `Tournaments` table should use a foreign key (e.g., `host_user_id`) referencing the `Users` table to prevent data duplication and maintain integrity.
5. A separate `Formats` table (e.g., `format_id`, `format_name`) should be created to normalize tournament formats (like 'Double Elimination').
6. A junction table (e.g., `Tournament_Features`) is the best way to handle the many-to-many relationship between tournaments and their features (like 'Draft', 'Regional').

**Insights Discovered:**
- The core challenge isn't just storing data, but storing it efficiently and without redundancy.
- A well-structured relational schema in SQLite provides significant flexibility and efficiency with minimal overhead.

**Notable Connections:**
- The discussion directly connects the high-level goal of "minimal and fast" to concrete database design patterns.

---

### Question Storming - 5 minutes

**Description:** A divergent thinking exercise where participants generate questions instead of answers to uncover risks, assumptions, and potential points of failure.

**Questions Generated:**
1. What happens if a tournament organizer posts a title that doesn't match the expected format, like forgetting to add the rank range?
2. What is the plan if the osu! API is down or rate-limits our requests during the daily 18:00 UTC+9 parsing job?
3. What if the forum title and post is written entirely in a language other than English?
4. What if the structure of the data from the `tournament-tracker` GitHub repository changes or the repository becomes unavailable?
5. How will the website handle security, especially with osu! OAuth login, to prevent common vulnerabilities like XSS or CSRF without relying on large, pre-built frameworks?
6. How will the website handle the database file to prevent SQL injection?
7. The "Can I join?" feature requires calculating a user's rank with a custom BWS formula. How will the site get the user's current badge count and rank in real-time to perform this calculation, and what happens if that data can't be fetched?
