# Requirements

### Functional Requirements

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

### Non-Functional Requirements

1.  **NFR1: Performance:** Public-facing pages must be lightweight and load quickly (< 2 seconds) on a standard internet connection, even when served from low-cost infrastructure.
2.  **NFR2: Security:** The application must be protected against common web vulnerabilities. Specifically, it must use prepared statements to prevent SQL injection, escape all output to prevent XSS, and use anti-CSRF tokens on all admin forms.
3.  **NFR3: Reliability:** The daily parsing script must be reliable. In case of API failure, it must log the error and fail gracefully without impacting the availability of the public website.
4.  **NFR4: Usability:** The user interface must be clean, simple, and intuitive, requiring no special instructions for the target user base.
5.  **NFR5: Maintainability:** The codebase must be clear and well-organized to allow for efficient future development by a solo developer.

---
