# User Flows

## Flow 1: Quick Tournament Discovery (Casual Player)

**User Goal:** Find an open tournament I can join right now

**Entry Points:** Direct homepage visit, social media links, word-of-mouth

**Success Criteria:** Player finds and accesses registration for a suitable tournament within 60 seconds

### Flow Diagram
```mermaid
graph TD
    A[Land on Homepage] --> B[Scan 3 Featured Tournaments]
    B --> C{Find suitable tournament?}
    C -->|Yes| D[Click Tournament]
    C -->|No| E[Click 'All Tournaments']
    D --> F[View Tournament Details]
    E --> G[View 10 Tournament List Default]
    F --> H[Check Registration Status]
    G --> I{Apply Rank Filter?}
    I -->|Yes| J[Filter Applied - Show 10 Results]
    I -->|No| K{Need more tournaments?}
    J --> L[Browse Filtered Results 1-10]
    K -->|Yes| M[Expand to 25 or 50]
    L --> N[Click Suitable Tournament]
    M --> O[Browse Expanded Results]
    N --> F
    O --> P[Click Suitable Tournament]
    H --> Q{Still Open?}
    P --> F
    Q -->|Yes| R[Click Registration Link]
    Q -->|No| S[Return to Browse]
    R --> T[External Registration Success]
```

### Edge Cases & Error Handling:
- No tournaments in first 10 results → Show "Load More" or "Expand to 25/50" options prominently
- Filter returns 0 results → "No tournaments found. Try different filters or view all tournaments"
- Performance on 50 tournament view → Ensure fast loading even on mobile/low bandwidth
- Registration link is broken → Show error message with forum link as backup
- All featured tournaments have closed registration → Display "View All Tournaments" prominently

### Notes:
The pagination approach (10 → 25 → 50) balances performance with discovery needs. Most users will find what they need in the first 10, but power users can expand as needed without impacting initial page load.

## Flow 2: Tournament Organizer Verification (Admin)

**User Goal:** Ensure tournament data is accurate and publish it to the platform

**Entry Points:** Admin notification of new parsed tournament, periodic checking

**Success Criteria:** Tournament data is verified and published within 24 hours of forum posting

### Flow Diagram
```mermaid
graph TD
    A[Tournament Posted on Forums] --> B[Daily Parser Runs]
    B --> C[Tournament Data Extracted]
    C --> D[Saved as 'Pending Review']
    D --> E[Admin Gets Notification]
    E --> F[Admin Views Review Queue]
    F --> G[Admin Clicks Edit Tournament]
    G --> H[Review Parsed Data]
    H --> I{Data Accurate?}
    I -->|Yes| J[Click Approve]
    I -->|No| K[Edit Incorrect Fields]
    K --> L[Save Changes]
    L --> J
    J --> M[Tournament Goes Live]
    M --> N[Organizer Sees Tournament Listed]
```

### Edge Cases & Error Handling:
- Parser fails to extract key data → Fields highlighted in red for admin attention
- Tournament format not recognized → Admin manually categorizes
- Duplicate tournament detected → System prevents duplicate, shows existing entry
- Admin unavailable for 48+ hours → Backup notification system

## Flow 3: Manual Tournament Submission (Authenticated Organizer)

**User Goal:** Submit my tournament immediately as the verified topic creator

**Entry Points:** "Submit Tournament" button (requires login)

**Success Criteria:** Verified tournament creator submits accurate data within 5 minutes

### Flow Diagram
```mermaid
graph TD
    A[Host Visits Site] --> B[Click 'Submit Tournament' Button]
    B --> C{User Logged In?}
    C -->|No| D[Redirect to osu! OAuth Login]
    C -->|Yes| E[Enter Forum Post URL]
    D --> F[Complete osu! Authentication]
    F --> E
    E --> G[Click 'Parse Now']
    G --> H[System Fetches Forum Post]
    H --> I{User ID Matches Topic Creator?}
    I -->|No| J[Show Error: 'Only topic creator can submit']
    I -->|Yes| K[Real-time Parser Extraction]
    J --> L[Return to Form]
    K --> M[Show Parsed Data to Host]
    M --> N{All Required Fields Present?}
    N -->|Yes| O[Click 'Submit for Review']
    N -->|No| P[Edit Missing/Incorrect Fields]
    P --> Q{Validation Passes?}
    Q -->|Yes| O
    Q -->|No| R[Show Error Messages]
    R --> P
    O --> S[Tournament Saved as 'Pending Review']
    S --> T[Host Gets Confirmation]
    T --> U[Admin Reviews & Approves]
    U --> V[Tournament Goes Live]
```

### Edge Cases & Error Handling:
- User ID doesn't match topic creator → "Only the tournament organizer who created the forum post can submit this tournament"
- Authentication fails → Redirect to login with return URL
- Topic creator tries to submit duplicate → "This tournament is already submitted/live"
- Forum post doesn't exist/is private → "Unable to access forum post. Please check URL and permissions"

### Authentication Requirements:
- **osu! OAuth Login Required:** User must authenticate with osu! account
- **Topic Creator Verification:** System verifies authenticated user ID matches forum post creator ID
- **Session Management:** Maintain login session for form completion
