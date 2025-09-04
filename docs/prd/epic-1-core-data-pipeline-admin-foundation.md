# Epic 1: Core Data Pipeline & Admin Foundation

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
