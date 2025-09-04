# Project Brief: Tourney Method

## Problem Statement

The process of discovering, tracking, and participating in community-run osu! tournaments is inefficient and fragmented. In the past, tournaments were primarily for elite players. However, with relaxed requirements for earning profile badges, the volume and variety of tournaments have exploded, now catering to all skill levels. This growth has overwhelmed the official osu! forums, the traditional discovery tool.

Players now struggle to find tournaments matching their rank and schedule because information is scattered across these crowded forums, where posts are easily buried, and dozens of disparate Discord servers. This leads to missed opportunities and frustration. For tournament organizers, gaining visibility in this noisy environment is a major challenge. They invest significant time and effort, only to struggle to reach their target audience.

The lack of a centralized, structured platform capable of handling this new scale means that valuable community-run events do not reach their full potential, and both players and organizers are left with a time-consuming and frustrating experience.

---

## Proposed Solution

The proposed solution is the "Tourney Method": a centralized, automated web platform designed to be the definitive source for osu! tournament information.

The core of the solution is a daily automated script that parses the official osu! tournament forums. It will extract key data points—such as tournament name, rank range, game mode, schedule, and links to Discord servers and registration forms—and store them in a structured local database.

This curated data will be presented to users through a clean, fast, and simple web interface. The key differentiators from existing methods are:

*   **Automation & Centralization:** It replaces the manual, repetitive effort of searching forums with a single, automatically updated source of truth.
*   **Powerful Discovery:** Users will be able to instantly filter and search for tournaments by rank, game mode, format (e.g., 1v1, 2v2), and registration status—functionality that is completely absent from the forums.
*   **Data Reliability:** An admin approval workflow will ensure all parsed information is verified for accuracy before being published, creating a more trustworthy data source than uncurated forum posts.

The high-level vision is to create an indispensable community utility that not only solves the immediate problem of tournament discovery but also evolves into a hub for the community with features that support team and staff formation.

---

## Target Users

#### Primary User Segment: The Competitive Player

*   **Profile:** Active osu! players of all skill levels, from top rankers to those in 4-digit or 5-digit brackets, who are motivated to compete.
*   **Current Behaviors:** They currently spend significant time manually scanning the osu! forums, monitoring multiple Discord servers, and asking friends to find tournaments. They are adept at using the existing fragmented tools but are frustrated by the inefficiency.
*   **Goals & Needs:** Their primary goal is to find and participate in tournaments that match their rank, schedule, and preferred format (e.g., team vs. solo, specific game modes). They need a way to do this quickly and reliably, without the fear of missing registration deadlines. A secondary need is finding suitable teammates.

#### Secondary User Segment: The Tournament Organizer

*   **Profile:** Passionate, volunteer community members who invest their own time to create and manage tournaments.
*   **Current Behaviors:** They are power users of community tools, managing forum posts, Discord servers, and complex Google Sheets to run their events.
*   **Goals & Needs:** Their primary goal is to get visibility for their tournament to attract a healthy number of participants. They need a more effective channel than the crowded official forums to reach their target player base. A secondary need is finding reliable staff (referees, mappers, etc.) to help run the event.

---

## Goals & Success Metrics

#### Project Objectives

*   **Launch (3 Months):** Launch the public MVP of the platform, successfully parsing and displaying at least 95% of active osu! Standard tournaments.
*   **Adoption (6 Months):** Become a primary tool for tournament discovery within the Korean community, demonstrated by repeat traffic and positive community feedback.
*   **Expansion (1 Year):** Achieve measurable adoption within the broader English-speaking community and begin planning for the inclusion of other game modes.

#### User Success Metrics

*   **For Players:** Success is when a player feels, "I can find a tournament I want to join in just a few minutes," and "I'm no longer worried about missing signups because I didn't see a forum post."
*   **For Organizers:** Success is when an organizer feels, "My tournament got more visibility and signups by being on this platform," and "I spent less time answering basic questions."

#### Key Performance Indicators (KPIs)

*   **Data Completeness:** The platform should list >95% of all active tournaments posted on the official osu! Standard forums.
*   **Parser Accuracy:** The automated parser should correctly extract all key data fields for >80% of new English-language tournament posts without requiring manual admin correction.
*   **User Engagement:** Track Monthly Active Users (MAU), with an initial goal of establishing a baseline and seeing consistent growth post-launch.
*   **User Satisfaction:** A simple "Was this page helpful?" feedback mechanism on tournament listing pages, aiming for a >90% positive rating.

---

## MVP Scope

#### Core Features (Must-Have for MVP)

*   **Automated Parser:** A daily script that fetches and parses new tournament posts from the osu! Standard forums.
*   **Admin Review & Approval Panel:** A secure page where an admin can view all parsed tournaments, edit any incorrect or missing data (with failed fields highlighted), and approve them to be published.
*   **Public Tournament Listings:** A clean, fast-loading main page displaying active and upcoming tournaments.
*   **Filtering & Search:** The core user value. The public list *must* be filterable by:
    *   Rank Range
    *   Registration Status (Open / Closed)
    *   Tournament Format (e.g., 1v1, 2v2, etc.)
*   **Tournament Detail View:** A simple, dedicated page for each tournament showing all its details in a structured format.
*   **osu! OAuth Login:** For admin access to the review panel. The public site will not require user login.

#### Out of Scope for MVP

To ensure a fast launch, the following features will be explicitly excluded from the initial version:

*   **Team & Staff Finder:** This is the highest priority for a future version (V2), but it adds too much complexity for the MVP.
*   **In-App Registration:** The MVP will link *to* existing registration forms (Google Forms, etc.), not replace them.
*   **Public User Accounts:** There will be no public-facing login, user profiles, saved preferences, or "favorite tournament" features in the MVP.
*   **Multi-language UI:** The user interface will be in English only for the initial launch.
*   **Official support for other game modes:** The parser will be built and tested exclusively for Standard mode tournaments.

---

## Post-MVP Vision

#### Phase 2 Features (The Next Priorities)

Once the MVP is stable and has achieved its initial adoption goals, the next priorities will be:

1.  **Team & Staff Finder:** Introduce user profiles and dedicated features for players to find teams and for organizers to recruit staff. This is the most requested feature beyond core discovery.
2.  **Expanded Game Mode Support:** Systematically add full support for Taiko, Catch, and Mania tournaments, making the platform truly comprehensive.
3.  **Public User Accounts:** Allow public users to log in with their osu! account to unlock features like saving/tracking favorite tournaments and managing their team/staff finder profiles.

#### Long-Term Vision (1-2 Year Horizon)

The long-term vision is for the "Tourney Method" to evolve from a discovery platform into an **Automated Tournament Hosting Platform**. The ultimate goal is to dramatically lower the barrier to entry for creating tournaments. An organizer should be able to come to the site, fill out a simple form with their core ideas (mappool, rank range, schedule), and have the platform automatically generate the necessary infrastructure (e.g., brackets, schedules, communication channels). By automating the most difficult parts of hosting, the platform could enable a new wave of community-run events.

#### Expansion Opportunities

*   **Discord Bot Integration:** A companion Discord bot that could announce new tournaments matching a user's criteria directly into their own servers.
*   **Match & Bracket API:** An API that would allow organizers to push live bracket and schedule updates to the platform, making it a central place for players to track their progress.
*   **Community Sustainability:** If hosting costs become a concern, a simple, non-intrusive donation link (e.g., Ko-fi) could be added to allow the community to support the platform's long-term health.

---

## Technical Considerations

#### Platform Requirements

*   **Target Platforms:** The application will be a responsive website accessible on all modern desktop and mobile web browsers (Chrome, Firefox, Safari, Edge).
*   **Performance Requirements:** The application must be extremely lightweight and fast. All backend logic and database queries must be highly optimized to perform well on a low-resource hosting plan (e.g., DigitalOcean's cheapest tier).

#### Technology Preferences

As per the initial project plan, the technology stack will be intentionally minimalist:

*   **Frontend:** Vanilla CSS, jQuery
*   **Backend:** Vanilla PHP
*   **Database:** SQLite
*   **Hosting:** DigitalOcean

#### Architecture Considerations

*   **Service Architecture:** A simple, monolithic application. All code will reside in a single codebase running on a single server.
*   **Integration Requirements:** The platform will integrate with three external services:
    1.  **osu! API v2:** For parsing new tournaments and fetching user data.
    2.  **`tournament-tracker` GitHub API:** For an initial backfill of historical tournament data.
    3.  **osu! OAuth 2.0:** For admin user authentication.
*   **Security Requirements:** The following principles are non-negotiable:
    1.  **SQL Injection:** All database queries involving external data *must* use prepared statements.
    2.  **Cross-Site Scripting (XSS):** All data rendered to HTML *must* be escaped using `htmlspecialchars()`.
    3.  **Cross-Site Request Forgery (CSRF):** All state-changing forms (e.g., in the admin panel) *must* be protected with anti-CSRF tokens.

---

## Constraints & Assumptions

#### Constraints

*   **Budget:** Zero. The project relies on free and open-source tools and must run on the most inexpensive hosting plan available (e.g., DigitalOcean's cheapest tier).
*   **Resources:** This is a solo developer project. This strictly limits the scope and complexity that can be achieved for the MVP.
*   **Technology:** The project is constrained to the chosen minimalist tech stack (Vanilla PHP, jQuery, SQLite). This choice prioritizes performance on low-resource hardware over development speed that might be gained from modern frameworks.

#### Key Assumptions

The project's success rests on the following assumptions being true:

*   **Community Need:** We assume the inconvenience of the current methods is significant enough that both players and organizers will be motivated to learn and adopt a new platform.
*   **Data Source Stability:** We assume the osu! forums will remain the primary, public location for tournament announcements and that the osu! API will remain available and accessible for our needs.
*   **Parser Feasibility:** We assume that forum posts, while varied, have enough structural consistency for a parser to be effective in extracting the majority of the required data automatically.
*   **Admin Availability:** We assume a volunteer admin will be consistently available to perform the crucial task of reviewing and approving parsed tournaments to ensure data quality.

---

## Risks & Open Questions

#### Key Risks

*   **Parser Inaccuracy:**
    *   **Risk:** The parser logic may not be robust enough to handle the wide variety of forum post formats, leading to a high rate of manual correction and defeating the project's automation goal.
    *   **Mitigation:** The admin review panel is the primary mitigation, ensuring a human is always in the loop to guarantee data quality.
*   **External Dependency Failure:**
    *   **Risk:** The osu! API could become unavailable, or the `tournament-tracker` repository could be removed, cutting off the platform's data sources.
    *   **Mitigation:** The "Log, Fail Gracefully, and Alert" system we designed ensures that the site remains stable and that the admin is aware of any data source interruptions.
*   **Low Community Adoption:**
    *   **Risk:** The community may be too accustomed to existing methods and may not see enough value in a new platform to change their habits.
    *   **Mitigation:** The phased go-to-market strategy, starting with a core group of "early adopters," is designed to build momentum and gather crucial feedback to ensure the product meets a real need.
*   **Security Vulnerabilities:**
    *   **Risk:** Building the application from scratch without a major framework increases the risk of introducing common web vulnerabilities.
    *   **Mitigation:** Strict, disciplined adherence to the fundamental security principles we defined (prepared statements, output escaping, anti-CSRF tokens).

#### Open Questions

*   What will the real-world server load and performance be on the cheapest hosting tier once public traffic begins?
*   How much time will the admin realistically need to spend each day reviewing and correcting tournament data?
*   Once the MVP is launched, what features will the community request most urgently? Will it be the "Team Finder," or will other priorities emerge?
