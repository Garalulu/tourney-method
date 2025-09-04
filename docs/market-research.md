# Market Research Report: Tourney Method

## Executive Summary

This report validates the market need for the "Tourney Method," a centralized platform designed to automate the discovery and tracking of osu! community tournaments. The research, based on three years of expert-driven data collection, confirms that the current system relies on inefficient and fragmented channels like the official forums and Discord, creating significant friction for both players and organizers.

The primary target market is the active osu! Standard mode community, comprising at least 1,800+ unique, highly engaged players and hundreds of volunteer organizers annually. This community currently lacks a dedicated tool for discovering tournaments, creating a clear market gap for a well-designed solution.

The "Tourney Method" is positioned as a free, community-focused utility to solve this problem. The strategic recommendation is a phased rollout, beginning with the Korean-speaking community to ensure stability and gather feedback, followed by a broader launch to the global English-speaking community.

The project's success hinges on a robust, automated parsing system and a simple, reliable user experience. By solving the core pain points of information discovery and manual data management, the platform is well-positioned for high adoption. The ultimate vision is to transform a tedious, one-person job into an automated, collaborative system that benefits the entire osu! tournament ecosystem.

---

## Research Objectives & Methodology

### Research Objectives

*   **Primary Objective:** To determine if there is a large enough and accessible market of osu! tournament players and organizers who would adopt a centralized, automated platform, justifying the development of the "Tourney Method" project.
*   **Validated Assumptions:**
    1.  The Standard game mode represents the largest and most active tournament community.
    2.  Organizers' primary pain point is the lack of visibility and the ephemeral nature of forum posts.
    3.  Players' primary pain points are the difficulty of discovering eligible tournaments and finding suitable teammates.
    4.  There are no direct commercial competitors due to the non-profit nature of the scene.

### Research Methodology

*   **Research Approach:** This research is primarily based on an expert-driven analysis, leveraging the project owner's three years of direct experience manually collecting and curating osu! tournament data. This qualitative and quantitative historical data serves as the primary source.
*   **Data Sources:**
    *   **Primary:** Longitudinal data from the "osu! Korean Tourney Hub" Discord server and associated Google Sheets (2022-2025). Direct domain expertise from the project owner.
    *   **Secondary:** Public osu! tournament forums, community knowledge from Discord, and historical data aggregated by the `tournament-tracker` GitHub project.
*   **Limitations:** The analysis is heavily informed by the project owner's perspective, particularly concerning the Korean-speaking community. Data on non-Standard game modes is acknowledged to be less comprehensive.

---

## Market Overview

#### Market Definition
*   **Product/Service:** A centralized web platform for the discovery, tracking, and aggregation of information related to community-run osu! tournaments.
*   **Geographic Scope:** Global, with an initial focus on the Korean-speaking and English-speaking communities.
*   **Customer Segments:**
    1.  **Players:** Active osu! users across all game modes seeking tournaments that match their rank and play style.
    2.  **Organizers:** Volunteers who run tournaments and need a platform for visibility and to reach potential participants.
*   **Value Chain Position:** A community utility that acts as an aggregator and structured information source, sitting between the official osu! forums and the fragmented landscape of individual Discord servers.

#### Market Size & Growth
*   **Total Addressable Market (TAM):** The total number of players participating in any osu! tournament annually. While the exact number is unknown, the "badged" tournament scene provides a strong indicator of the most engaged segment.
*   **Serviceable Addressable Market (SAM):** The core community active in Standard mode tournaments, which is the largest and most active scene. Based on your data, this is at least **160+ tournaments** and **1,800+ unique players awarded badges** annually. The market for other modes is smaller but still significant.
*   **Serviceable Obtainable Market (SOM):** The initial target for the "Tourney Method" platform. A realistic 1-2 year goal would be to capture a majority of the Korean player base and become a primary resource for a significant fraction (e.g., 20-30%) of the English-speaking players currently using forums and disparate Discord servers.

#### Market Trends & Drivers
*   **Key Trends:** A shift from simple forum browsing to a desire for more structured, data-rich platforms. Increasing use of complex metrics like BWS for matchmaking.
*   **Growth Drivers:** The prestige and recognition associated with earning profile badges is a primary driver for participation in competitive tournaments. The social aspect of team-based events also fuels engagement.
*   **Market Inhibitors:** The volunteer-run, non-commercial nature of the scene leads to high organizer burnout and inconsistent tournament quality. Information is highly fragmented across forums, Discord, and Google Sheets, making discovery a persistent challenge (this is the core problem your project solves).

---

## Customer Analysis

#### Target Segment Profiles

**Segment 1: The Competitive Player**
*   **Description:** An active osu! player motivated by the challenge of competition and the prestige of earning profile badges.
*   **Size:** A core of at least 1,800+ unique players who win badges in the Standard mode annually, with a much larger pool of unbadged participants.
*   **Needs & Pain Points:**
    *   **Discovery:** Struggles to find tournaments matching their specific rank, mode, and format preferences due to information being scattered across forums and Discord.
    *   **Team Formation:** Finds it difficult to find suitable teammates, as there is no easy way to gauge other players' specific skills and history.
*   **Willingness to Pay:** Zero. The strong community expectation is that tools and resources are provided for free.

**Segment 2: The Tournament Organizer**
*   **Description:** A dedicated community volunteer who invests significant personal time into planning and executing tournaments.
*   **Size:** A small, high-value group, likely numbering in the hundreds for all significant tournaments globally.
*   **Needs & Pain Points:**
    *   **Visibility:** Difficulty getting their tournament noticed on the official forums, where posts can quickly get buried.
    *   **Efficiency:** Managing sign-ups, disseminating information, and communicating with players is a manual, time-consuming process.
*   **Willingness to Pay:** Zero. As volunteers, they expect free tools to help them contribute to the community.

#### Jobs-to-be-Done Analysis

What customers "hire" this product to do:

*   **Functional Jobs:**
    *   *For Players:* "Help me find a tournament I'm eligible for, quickly." and "Help me find a good teammate for a specific tournament."
    *   *For Organizers:* "Help me get my tournament in front of the right players." and "Help me find reliable staff for my tournament."
*   **Emotional & Social Jobs:**
    *   *For Players:* "Help me feel the thrill of competition and be recognized for my skills."
    *   *For Organizers:* "Help me feel like a valued community contributor by running a successful event."

---

## Competitive Landscape

#### Market Structure
*   **Competitive Intensity:** The market is not commercially competitive, but it is highly fragmented. The "competition" is for user attention and adoption, challenging the established habits of players and organizers. The primary goal is to be more convenient and efficient than the current methods.

#### Major Players Analysis (The Status Quo)

1.  **The Official osu! Forums:**
    *   **Role:** The canonical source for all tournament announcements.
    *   **Strengths:** Official, comprehensive, and the starting point for all information.
    *   **Weaknesses:** Poor discoverability. Posts are sorted chronologically, meaning active or popular threads dominate while new announcements can be easily missed. Data is completely unstructured.

2.  **Discord Servers:**
    *   **Role:** The primary, self-contained hub for communication, community, and operations for an individual tournament.
    *   **Strengths:** Allows organizers to create a unique, branded experience for their event. Excellent for real-time communication and community building.
    *   **Weaknesses:** **Discoverability.** While essential for running a tournament, there is no central directory of these servers, making it difficult for players to find which one to join for a given event. This is the specific problem the Tourney Method solves.

3.  **The `tournament-tracker` GitHub Project:**
    *   **Role:** A valuable community-run data archive for historical tournaments.
    *   **Strengths:** Provides a structured source of past tournament data.
    *   **Weaknesses:** It is a data source, not a user-facing platform for discovering *upcoming* tournaments. It serves as a potential data backend, not a competitor.

#### Competitive Positioning
*   **Market Gap:** There is a clear and significant gap in the market for a centralized, structured, and searchable platform for tournament discovery. The current "solutions" are all pieces of a puzzle, but no one has assembled the puzzle.
*   **"Tourney Method" Differentiation:**
    1.  **Centralization:** One single place to find all relevant tournament information.
    2.  **Structure:** Presents tournament data in a standardized, easy-to-digest format.
    3.  **Discoverability:** Powerful search and filtering tools that solve the core weakness of the forums.
    4.  **Value-Add Services:** Future features like the proposed "Teammate Finder" and "Staff Finder" address needs that are completely unmet by any existing tool.

---

## Industry Analysis

#### Porter's Five Forces Assessment (Community Adaptation)

This framework helps assess the forces that shape the competitive intensity and attractiveness of an industry. Here, "attractiveness" means the project's ability to thrive and be adopted.

*   **Supplier Power: High**
    *   *Analysis:* The "suppliers" are the tournament organizers. Their power is high because they create the content (tournaments) voluntarily. If they don't see value in the platform, the content stream dries up.
*   **Buyer Power: High**
    *   *Analysis:* The "buyers" are the players. Their power is high because they have zero cost to switch back to the old methods (forums, Discord). They will only adopt the platform if it is genuinely better and more convenient.
*   **Competitive Rivalry: Medium**
    *   *Analysis:* The rivalry is not with other companies, but with the inertia of the "status quo." The current manual methods are inconvenient but deeply familiar. The platform must be *significantly* better to change these ingrained habits.
*   **Threat of New Entry: High**
    *   *Analysis:* The technical barrier to creating a similar tool is relatively low, as this project demonstrates. Another passionate developer could build a competing platform. The best defense is to build a strong network effect by becoming the most trusted and comprehensive resource.
*   **Threat of Substitutes: High**
    *   *Analysis:* A well-designed Discord bot or a new, better-organized section on the official forums could emerge as a "good enough" substitute, potentially fragmenting the user base.

#### Technology Adoption Lifecycle Stage
*   **Current Stage: Early Adopters.** The market for a *solution* to this problem is in its early stages. You and your community are the "Innovators" and "Early Adopters"—those who feel the pain most acutely and are actively seeking a better way. The "Early Majority" of players are still using the old methods but are likely frustrated and would switch if a clearly superior solution were presented.
*   **Implication:** The initial version of the platform must be laser-focused on delighting these Early Adopters. Their positive experience and word-of-mouth will be the primary driver for wider adoption.

---

## Opportunity Assessment

#### Market Opportunities

1.  **Centralized Tournament Discovery Platform:**
    *   **Description:** To become the single, trusted source for finding, filtering, and tracking all community tournaments. This directly solves the core problem of information fragmentation.
    *   **Potential:** High. This is the primary value proposition for both players and organizers.
    *   **Risks:** Success is dependent on the reliability of the data sources (osu! API) and the robustness of the parsing logic.

2.  **Team & Staff Formation Hub (Future Opportunity):**
    *   **Description:** To create a dedicated space where players can find teammates and organizers can recruit staff (mappers, designers, referees).
    *   **Potential:** Very High. This is a critical, currently unmet need that would create a strong network effect and make the platform indispensable.
    *   **Recommendation:** Given the complexity, this should be considered a high-priority feature for a future version (V2), after the core discovery platform is established.

#### Strategic Recommendations

*   **Go-to-Market Strategy:**
    1.  **Initial Launch:** Focus exclusively on the Korean-speaking osu! Standard community. Use your existing Discord hub to launch to a friendly "Early Adopter" audience, gather feedback, and refine the platform.
    2.  **Wider Release:** Once the platform is stable and proven with the initial community, announce it more broadly to the English-speaking community on the official osu! forums.
    3.  **Future Expansion:** Add support for other game modes (Taiko, Catch, Mania) in a later phase.

*   **Positioning Strategy:** Market the platform as a fast, simple, and reliable utility "built for players, by a player." Emphasize that it solves the frustrations that everyone in the community faces.

*   **Pricing Strategy:**
    *   **Price:** Free.
    *   **Rationale:** The project is a community good. The market expectation is that such tools are free, and any attempt at monetization would harm adoption.

*   **Risk Mitigation:**
    *   **Adoption Risk:** A "build it and they won't come" scenario.
        *   **Mitigation:** The phased rollout (Korea first) allows you to build a loyal user base and generate positive word-of-mouth before a wider launch.
    *   **Execution Risk:** Technical failures (e.g., parser errors, API downtime).
        *   **Mitigation:** Implement the robust logging, admin review, and graceful failure mechanisms we designed earlier in our brainstorming session.
