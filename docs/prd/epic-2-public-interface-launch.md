# Epic 2: Public Interface & Launch

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
