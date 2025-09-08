# Wireframes & Mockups

**Primary Design Files:** To be created in Figma/Sketch - focusing on mobile-first responsive layouts with light/dark theme support

## Key Screen Layouts

### Homepage Layout
**Purpose:** Fast tournament discovery with immediate actionable options

**Key Elements:**
- Clean header with Tourney Method logo and "All Tournaments" CTA
- Brief site description (1-2 sentences)  
- Featured tournaments section (exactly 3 tournaments, cards/table format)
- Prominent "Submit Tournament" button for organizers
- "View All Tournaments" call-to-action

**Interaction Notes:** Tournament titles click to open forum posts in new tabs. Tournament card/row areas click to open modal dialogs. Mobile-first design with clear visual distinction between title links and clickable card areas.

**Design File Reference:** Homepage - Mobile & Desktop variants with modal trigger areas

### All Tournaments Page Layout  
**Purpose:** Comprehensive tournament discovery with filtering and modal previews

**Key Elements:**
- Search bar (prominent, top placement)
- Filter sidebar/panel (Rank Range: Open, 100+, 500+, 1k+, 5k+, 10k+, Registration Status, Game Mode)
- Tournament list/table with clear title links (10 default, expand to 25/50)
- Pagination controls ("Show more" buttons)
- Clear filter/reset options

**Interaction Notes:** Tournament titles open forum posts in new tabs. Tournament rows/cards open modal dialogs preserving scroll position. Filters applied instantly without page reload. Mobile version collapses filters into dropdown/modal.

**Design File Reference:** All Tournaments - Filter states, pagination variants, modal overlay states

### Tournament Detail Modal/Dialog
**Purpose:** Show full tournament information without breaking browsing flow

**Key Elements:**
- Modal overlay with tournament banner as header
- Scrollable modal content (banner, title, structured data, links)
- Clear close button (X) and ESC key support
- Background page remains visible with scroll position preserved
- Mobile-optimized modal (full screen on small devices)

**Interaction Notes:** Tournament titles within modal still open forum posts in new tabs. All external links (registration, Discord, streams) open in new tabs. Modal close returns to exact scroll position. URL hash updates for bookmarking. Keyboard navigation support.

**Design File Reference:** Tournament Modal - Desktop overlay, mobile full-screen, loading states
