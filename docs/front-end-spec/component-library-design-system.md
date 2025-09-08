# Component Library / Design System

**Design System Approach:** Leverage Pico.css as the foundation with minimal custom components. Build a small, focused component library that extends Pico.css for tournament-specific needs while maintaining lightweight performance.

## Core Components

### Tournament Card
**Purpose:** Display tournament summary information in lists and featured sections

**Variants:**
- Featured card (homepage, larger with prominent CTA)
- List item (All Tournaments page, compact row format)
- Search result (with highlighted search terms)

**States:** Default, hover, loading (skeleton), error (broken banner/data)

**Usage Guidelines:** Always include rank range and registration status. Banner images optional with graceful fallbacks. Click areas clearly distinguished between title (external) and card (modal).

### Filter Panel
**Purpose:** Provide efficient tournament filtering capabilities

**Variants:**
- Desktop sidebar (persistent, expanded)
- Mobile dropdown/modal (collapsible)
- Applied filters display (chips/tags showing active filters)

**States:** Default, expanded, collapsed, loading, error (no results)

**Usage Guidelines:** Filters apply instantly without page refresh. Clear visual feedback for active filters. Reset/clear all option always available.

### Tournament Modal
**Purpose:** Display detailed tournament information without navigation

**Variants:**
- Desktop overlay (partial screen with background visible)
- Mobile full-screen (maximizes content space)
- Loading state (skeleton content)

**States:** Opening, open, closing, error (failed to load data)

**Usage Guidelines:** Always preserve scroll position on close. Banner images load lazily. External links clearly indicated with icons.
