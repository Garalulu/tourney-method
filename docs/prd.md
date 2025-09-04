# Tourney Method Product Requirements Document (PRD)

## Goals and Background Context

#### Goals
*   To provide osu! players with a centralized, reliable, and easy-to-use platform to discover tournaments.
*   To provide tournament organizers with a more effective platform to gain visibility for their events.
*   To automate the manual process of collecting and curating tournament information, saving significant time and effort.
*   To establish a foundational platform that can later be expanded with more community-focused features.

#### Background Context
The osu! tournament scene is a vibrant and growing part of the community. However, the tools for managing it have not kept pace. Information is fragmented across the official forums, where posts are easily lost, and dozens of individual Discord servers. This makes it difficult for players to find tournaments and for organizers to reach an audience.

This project, "Tourney Method," aims to solve this problem by creating a single source of truth. It will automatically parse tournament announcements, structure the data, and present it in a clean, searchable interface. This will eliminate the manual work currently required, reduce frustration for both players and organizers, and provide a solid foundation for a thriving tournament ecosystem.

#### Change Log
| Date | Version | Description | Author |
| :--- | :--- | :--- | :--- |
| 2025-09-04 | 1.0 | Initial draft | John (PM) |

---

## Requirements

#### Functional Requirements

1.  **FR1: Automated Daily Parsing:** The system shall have an automated script that runs on a daily schedule to fetch new topics from the osu! Standard tournament forum.
2.  **FR2: Core Data Extraction:** The parser shall extract the following data points from a tournament post: Tournament Title, Host, Mode, Banner Image URL, Links (Sheet, Discord, Stream, Registration), Badge Presence, Rank Range, and Tournament Dates.
3.  **FR3: Admin Authentication:** The system shall provide a secure login for administrators using the osu! OAuth 2.0 protocol.
4.  **FR4: Admin Review Queue:** The admin panel shall display a list of all newly parsed tournaments that are awaiting approval.
5.  **FR5: Data Editing & Approval:** Admins shall be able to edit any data field for a parsed tournament and approve it to be published to the public site.
6.  **FR6: Visual Highlighting for Failures:** In the admin panel, any data fields that the parser could not automatically populate shall be visually highlighted (e.g., with a red border) to alert the admin.
7.  **FR7: Public Tournament List:** The system shall display a publicly accessible page listing all approved, active tournaments, viewable without a login.
8.  **FR8: Tournament Filtering:** The public tournament list must be filterable by at least Rank Range and Registration Status (Open / Ongoing).
9.  **FR9: Tournament Detail View:** Each tournament in the list shall link to a dedicated detail page that displays all of its structured information.
10. **FR10: Duplicate Topic Prevention:** Before processing a topic from the API, the system shall first check if a tournament with the same `topic_id` already exists in the database. If it exists, the system shall skip that topic to prevent duplicate entries.

#### Non-Functional Requirements

1.  **NFR1: Performance:** Public-facing pages must be lightweight and load quickly (< 2 seconds) on a standard internet connection, even when served from low-cost infrastructure.
2.  **NFR2: Security:** The application must be protected against common web vulnerabilities. Specifically, it must use prepared statements to prevent SQL injection, escape all output to prevent XSS, and use anti-CSRF tokens on all admin forms.
3.  **NFR3: Reliability:** The daily parsing script must be reliable. In case of API failure, it must log the error and fail gracefully without impacting the availability of the public website.
4.  **NFR4: Usability:** The user interface must be clean, simple, and intuitive, requiring no special instructions for the target user base.
5.  **NFR5: Maintainability:** The codebase must be clear and well-organized to allow for efficient future development by a solo developer.

---

## User Interface Design Goals

*   **Overall UX Vision:** The UX will be defined by speed and simplicity. To achieve a modern aesthetic with minimal overhead, the project will adopt a lightweight, class-less CSS framework (e.g., **Pico.css**).
*   **Theme Support:** The interface must support both **light and dark color schemes**, ideally automatically detecting the user's operating system preference.
*   **Key Interaction Paradigms:**
    *   **Filter-First:** The primary way users will interact with the site is by applying powerful filters to the main tournament list.
    *   **Drill-Down:** The core navigation flow will be a simple "list -> detail" pattern. Users select a tournament from the list to drill down into its specific details.
*   **Core Screens and Views:**
    *   **Public:**
        1.  Tournament List (Homepage)
        2.  Tournament Detail Page
    *   **Admin:**
        1.  Login Page
        2.  Admin Dashboard (Review Queue)
        3.  Edit Tournament Form
        4.  System Logs Viewer
*   **Accessibility: WCAG 2.1 AA**
    *   The project will aim to meet WCAG AA standards to ensure it is usable by as many people as possible. This includes using strong color contrast, semantic HTML, and ensuring keyboard navigability.
*   **Branding:**
    *   There is no formal branding. The aesthetic will be guided by the chosen lightweight CSS framework, ensuring a clean, modern, and consistent look across the entire application.
*   **Target Device and Platforms: Web Responsive**
    *   The site must be fully functional and easy to use on both desktop and mobile web browsers.

---

## Technical Assumptions

*   **Repository Structure: Monorepo**
    *   All project code, including backend (PHP), frontend (CSS/JS), and database schema, will be contained within a single Git repository.
*   **Service Architecture: Monolith**
    *   The application will be a traditional, self-contained monolith. All functionality will be deployed as a single unit. This is the simplest and most appropriate architecture for the project's scale.
*   **Testing Requirements: Unit + Integration Testing**
    *   **Unit Tests:** Critical, isolated pieces of logic (like the BWS calculation) should be covered by unit tests to ensure their correctness.
    *   **Integration Tests:** The data pipeline—specifically the parser's ability to correctly save information to the database—should be covered by integration tests. This is to ensure the most critical part of the system works as expected.
    *   *Note:* A full end-to-end (E2E) automated testing suite is considered out of scope for the MVP due to its complexity.
*   **Additional Technical Assumptions:**
    *   The technology stack is fixed as **Vanilla PHP, jQuery, and SQLite**.
    *   The application must be designed to run efficiently on a **low-resource DigitalOcean server**.
    *   The security requirements (prepared statements, output escaping, anti-CSRF tokens) are **non-negotiable** and must be implemented from the beginning.

---

## Epic List

**Epic 1: Core Data Pipeline & Admin Foundation**
*   **Goal:** To establish the project's technical foundation and build the complete, automated data pipeline, from parsing forum posts to saving admin-approved tournament data in the database. At the end of this epic, the system will be correctly collecting and storing high-quality data, even though it won't be visible to the public yet.

**Epic 2: Public Interface & Launch**
*   **Goal:** To build the public-facing, responsive website that allows any user to view, filter, and search the tournament data collected in Epic 1. This epic makes the data useful to the community and comprises all the work needed to achieve the MVP launch.

---

## Epic 1: Core Data Pipeline & Admin Foundation

**Goal:** To establish the project's technical foundation and build the complete, automated data pipeline, from parsing forum posts to saving admin-approved tournament data in the database. At the end of this epic, the system will be correctly collecting and storing high-quality data, even though it won't be visible to the public yet.

---
**Story 1.1: Project & Database Setup**
*   As a Developer,
*   I want: The basic file structure, repository, and an initial SQLite database schema created,
*   So that: I have a foundational codebase to start building features on.
*   **Acceptance Criteria:**
    1.  A Git repository is initialized.
    2.  A basic PHP application structure exists (e.g., folders for public assets, backend logic, templates).
    3.  An SQLite database file is created with the initial tables (`tournaments`, `users`, `system_logs`, etc.) and schema.

---
**Story 1.2: Admin Login**
*   As a Site Administrator,
*   I want: To log in to the application securely using my osu! account,
*   So that: I can access protected admin-only areas.
*   **Acceptance Criteria:**
    1.  An admin-only login page exists.
    2.  The page has a "Login with osu!" button that initiates the osu! OAuth 2.0 flow.
    3.  Upon successful authentication, a server-side session is created for the user.
    4.  The application checks the authenticated user's osu! ID against a hard-coded list of authorized admins.

---
**Story 1.3: Basic Parser Script**
*   As a System,
*   I want: A script that can fetch the latest topics from the osu! Standard tournament forum API,
*   So that: I have the raw data needed for processing.
*   **Acceptance Criteria:**
    1.  A PHP script exists that can be executed.
    2.  The script successfully calls the osu! API and retrieves a list of topics.
    3.  Before processing, the script checks if a topic's ID already exists in the `tournaments` table and skips it if it does.
    4.  The raw title and post body of new, unique topics are saved for the next step.

---
**Story 1.4: Data Extraction & Storage**
*   As a System,
*   I want: To parse the raw post body to extract key tournament details,
*   So that: The unstructured data is converted into a structured format.
*   **Acceptance Criteria:**
    1.  The parser logic can extract key fields (Title, Rank Range, etc.) from a typical forum post.
    2.  For common URL types (Google Sheets, osu! forums, Google Forms, Challonge, YouTube, Twitch), the parser extracts and stores **only the unique ID or slug** from the URL, not the entire string.
    3.  If a field cannot be found, its value is stored as NULL in the database.
    4.  The extracted data is saved as a new row in the `tournaments` table with a status of "pending_review".

---
**Story 1.5: Admin Review UI**
*   As a Site Administrator,
*   I want: To see a list of all tournaments that are "pending_review" in a simple table,
*   So that: I know which tournaments I need to check.
*   **Acceptance Criteria:**
    1.  A protected admin page displays a table of all tournaments with the "pending_review" status.
    2.  The table shows the Tournament Title and the date it was parsed.
    3.  Each row has an "Edit" button that links to the edit page for that tournament.

---
**Story 1.6: Edit & Approve Tournament**
*   As a Site Administrator,
*   I want: To use an edit form to correct any parsed data and approve the tournament,
*   So that: I can ensure data quality before it goes public.
*   **Acceptance Criteria:**
    1.  The "Edit" button opens a form pre-filled with all the data for the selected tournament.
    2.  Any fields that were parsed as NULL are visually highlighted.
    3.  The form can be submitted to update the tournament's data in the database.
    4.  An "Approve" button on the form changes the tournament's status to "approved".
    5.  The form is protected against CSRF attacks.

---
**Story 1.7: Error Logging**
*   As a Site Administrator,
*   I want: The system to automatically log any errors from the parser,
*   So that: I can diagnose problems with the data pipeline.
*   **Acceptance Criteria:**
    1.  When the parser script fails (e.g., cannot connect to the API), it writes a descriptive error to the `system_logs` table.
    2.  A new page in the admin panel, "System Logs," displays the contents of the `system_logs` table.

---

## Epic 2: Public Interface & Launch

**Goal:** To build the public-facing, responsive website that allows any user to view, filter, and search the tournament data collected in Epic 1. This epic makes the data useful to the community and comprises all the work needed to achieve the MVP launch.

---
**Story 2.1: Public Tournament List Display**
*   As a Player,
*   I want: To see a list of all approved, active tournaments on the homepage,
*   So that: I can quickly browse available tournaments.
*   **Acceptance Criteria:**
    1.  The homepage displays a table or list of tournaments with key information (Title, Mode, Rank Range, Status).
    2.  Only tournaments with "approved" status are displayed.
    3.  The list is accessible without login.
    4.  The page is responsive and works well on both desktop and mobile browsers.
    5.  The page uses the chosen lightweight CSS framework (e.g., Pico.css) and supports light/dark themes.

---
**Story 2.2: Tournament Filtering**
*   As a Player,
*   I want: To filter the tournament list by rank range, game mode, and registration status,
*   So that: I can quickly find tournaments relevant to me.
*   **Acceptance Criteria:**
    1.  Filter controls are present on the tournament list page.
    2.  Users can filter by rank range (e.g., 1k-5k, Open).
    3.  Users can filter by game mode (Standard only for MVP, but the filter should be present).
    4.  Users can filter by registration status (Open, Closed, Ongoing).
    5.  Applying filters updates the list dynamically without a full page reload.

---
**Story 2.3: Tournament Search**
*   As a Player,
*   I want: To search for tournaments by keywords in their title,
*   So that: I can quickly find specific tournaments I'm looking for.
*   **Acceptance Criteria:**
    1.  A search bar is present on the tournament list page.
    2.  Typing in keywords filters the list to show only matching tournaments.
    3.  Search is case-insensitive.

---
**Story 2.4: Tournament Detail Page**
*   As a Player,
*   I want: To click on a tournament and see all its detailed information,
*   So that: I can learn everything I need to know about it.
*   **Acceptance Criteria:**
    1.  Clicking a tournament in the list navigates to a dedicated detail page.
    2.  The detail page displays all structured information for the tournament (Title, Host, Mode, Rank Range, Dates, Links, etc.).
    3.  Links (Discord, Registration, Forum, Challonge, Stream) are correctly reconstructed from their unique IDs and are clickable.
    4.  The page is responsive and uses the chosen UI/UX design.

---
**Story 2.5: BWS Calculation for "Can I Join?"**
*   As a Player,
*   I want: To see if I am eligible for a tournament based on my BWS rank,
*   So that: I can quickly determine if I can join.
*   **Acceptance Criteria:**
    1.  When an admin logs in (for MVP testing), their current rank and badge count are fetched from the osu! API and stored/updated in the local database.
    2.  A daily cron job updates the rank and badge count for all users in the database.
    3.  The BWS calculation must only consider badges relevant to the tournament's mode (Standard for MVP) and that are classified as "tournament badges." This implies a lookup mechanism (e.g., a badge classification table) to filter out irrelevant badges.
    4.  On the tournament list and detail pages, a visual indicator (e.g., "Eligible" / "Not Eligible") is displayed for the logged-in user based on their stored BWS rank and the tournament's rank range.

## Checklist Results Report

### Document Completeness
- [x] All sections of the PRD template are filled out.
- [x] Executive Summary is concise and accurate.
- [x] Problem Statement is clear, evidence-based, and compelling.
- [x] Proposed Solution is well-defined and addresses the problem.
- [x] Target Users are clearly identified and characterized.
- [x] Goals & Success Metrics are SMART and measurable.
- [x] MVP Scope is clearly defined (in and out).
- [x] Post-MVP Vision provides a compelling future roadmap.
- [x] Technical Considerations are documented (stack, architecture, security).
- [x] Constraints & Assumptions are clearly stated.
- [x] Risks & Open Questions are identified and mitigated where possible.
- [x] Epic List is logical, sequential, and delivers value.
- [x] All Epics have detailed stories with clear Acceptance Criteria.

### Content Quality
- [x] Requirements are clear, unambiguous, and testable.
- [x] Functional Requirements cover all MVP features.
- [x] Non-Functional Requirements cover performance, security, and reliability.
- [x] UI/UX Design Goals are consistent with project philosophy.
- [x] Technical Assumptions align with project constraints.
- [x] Stories are appropriately sized for AI agent execution (small, focused, self-contained).
- [x] Stories are logically sequential within each epic.
- [x] Acceptance Criteria are precise and define "done."

### Cross-Document Consistency
- [x] PRD is consistent with the Project Brief.
- [x] PRD is consistent with the Market Research Report.
- [x] All key decisions from brainstorming are reflected.

### Next Steps Readiness
- [x] Clear prompts for UX Expert and Architect can be generated.

---

## Next Steps

### UX Expert Prompt
@ux-expert: Please review the "User Interface Design Goals" section of this PRD (docs/prd.md) and provide a detailed front-end specification, including wireframes or mockups, that aligns with the minimalist and performance-focused principles, and incorporates light/dark theme support.

### Architect Prompt
@architect: Please review the "Technical Considerations" and "Technical Assumptions" sections of this PRD (docs/prd.md) and create a detailed architecture document. Pay special attention to the security requirements, the SQLite database design, and the parsing logic.
