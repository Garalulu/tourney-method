# Frontend Codemap

<!-- Generated: 2026-03-31 | Files scanned: 65+ | Token estimate: ~1,150 -->

**Last Updated:** 2026-03-31
**Tech Stack:** Blade + Livewire 3.7.3 + Alpine.js 3.15.3 + Tailwind CSS 3.4.19
**PHP Version:** 8.5.3

## Directory Structure

```
resources/views/
├── layouts/
│   ├── app.blade.php           # Main app layout
│   └── admin.blade.php         # Admin panel layout
├── components/
│   ├── navigation.blade.php
│   ├── tournament-card.blade.php
│   ├── tournament-carousel.blade.php
│   ├── watch-button.blade.php
│   ├── reject-modal.blade.php
│   ├── **NEW** search-modal.blade.php
│   ├── **NEW** tournament-podium.blade.php
│   ├── **NEW** tournament-staff-section.blade.php
│   ├── **NEW** last-week-results.blade.php
│   ├── tournament-staff.blade.php
│   └── admin/
│       └── tournaments/        # Admin tournament components
│           ├── tournament-header.blade.php
│           ├── tournament-actions.blade.php
│           ├── tournament-final-actions.blade.php # Skip Discord webhook checkbox
│           ├── tournament-staff-management.blade.php
│           ├── forum-content.blade.php
│           └── parse-history-diff.blade.php
├── livewire/
│   ├── tournament-list.blade.php
│   ├── match-submission.blade.php
│   └── watch-button.blade.php
├── admin/                      # Admin pages
│   ├── dashboard.blade.php
│   ├── tournaments/
│   │   ├── pending.blade.php
│   │   ├── review.blade.php
│   │   ├── parse-history.blade.php
│   │   └── parse-history-show.blade.php
│   ├── matches/
│   │   ├── pending.blade.php
│   │   └── show.blade.php
│   ├── staff/
│   │   └── pending.blade.php
│   ├── imports/
│   │   └── index.blade.php
│   ├── audit-log/
│   │   └── index.blade.php
│   └── users/
│       └── index.blade.php
├── auth/
│   ├── login.blade.php
│   └── setup.blade.php
├── tournaments/
│   ├── index.blade.php
│   └── show.blade.php
├── users/
│   ├── show.blade.php
│   └── partials/
│       ├── badges.blade.php
│       ├── match-records.blade.php
│       ├── tournament-history.blade.php
│       └── year-recap.blade.php
├── dashboard/
│   └── index.blade.php
├── settings/
│   └── index.blade.php
└── errors/
    ├── 401.blade.php
    ├── 403.blade.php
    ├── 404.blade.php
    └── 500.blade.php
```

## Livewire Components (app/Livewire)

### Tournament Components
- `TournamentList` - Filterable tournament list
  - Search, mode filter, status filter, pagination
  - **NEW:** Enhanced with global search integration
- `MatchSubmission` - Match link submission form
  - URL validation, duplicate detection
- `WatchButton` - Tournament watch management
  - Toggle functionality, watch count display
  - Notification preferences for registration reminders and live streams

### Key Features
- Real-time updates via Livewire
- Server-side rendering for SEO
- Minimal JavaScript for interactions

## Alpine.js Components

### Interactive Features
- **Dropdown Menus** - Admin panel navigation
- **Modals** - Confirm dialogs, detail views
- **Tabs** - Tournament details (info, staff, matches)
- **Toast Notifications** - Success/error messages
- **Live Search** - Tournament/user filtering
- **Toggle Switches** - Settings toggles
- **Accordion** - Expandable sections
- **NEW:** Global search modal with keyboard shortcut (Cmd+K / Ctrl+K)

### Data Flow
```
Livewire Property (Backend)
    ↓
Alpine.js x-data (Frontend State)
    ↓
DOM Updates (Reactive)
```

## Tailwind CSS Configuration

### Custom Theme
```javascript
theme: {
  extend: {
    colors: {
      osu: {
        pink: '#ff66ab',
        dark: '#2d2d2d',
      },
      staff: {
        organizer: '#ef4444',    // red-500
        mapper: '#f97316',       // orange-500
        mappooler: '#f59e0b',     // amber-500
        referee: '#eab308',      // yellow-500
        playtester: '#84cc16',    // lime-500
        gfx: '#10b981',          // emerald-500
        sheeter: '#14b8a6',      // teal-500
        streamer: '#0ea5e9',     // sky-500
        commentator: '#3b82f6',  // blue-500
        other: '#6b7280',        // gray-500
      }
    }
  }
}
```

### Common Utility Patterns
**Layout:**
- Flexbox: `flex`, `items-center`, `justify-between`
- Grid: `grid`, `grid-cols-1 md:grid-cols-3`
- Spacing: `p-4`, `m-2`, `gap-4`

**Typography:**
- `text-xl`, `font-bold`, `text-gray-700`

**Responsive:**
- `md:flex`, `lg:w-1/2`, `xl:grid-cols-4`

**Staff Roles:**
- `bg-staff-organizer`, `text-staff-referee`, etc.

**Diff Highlighting:**
- `bg-green-100` (additions)
- `bg-red-100` (deletions)

## Blade Templates

### Layouts

**app.blade.php** - Main layout
```blade
<!DOCTYPE html>
<html>
<head>
    <!-- Meta tags, Tailwind CSS, Livewire scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-50">
    @auth
        @include('components.navigation')
    @endauth

    <main class="container mx-auto px-4 py-8">
        @yield('content')
    </main>

    @livewireScripts
    @stack('scripts')
</body>
</html>
```

**admin.blade.php** - Admin layout
```blade
@extends('layouts.app')

@section('content')
    <div class="flex">
        <aside class="w-64">
            <!-- Admin navigation -->
        </aside>
        <div class="flex-1">
            @yield('admin-content')
        </div>
    </div>
@endsection
```

### **NEW Components**

**search-modal.blade.php** - Global search modal
```blade
<div x-data="{ open: false, query: '', results: { users: [], tournaments: [] } }"
     x-show="open"
     x-transition
     @keydown.cmd.k.prevent="open = true"
     @keydown.ctrl.k.prevent="open = true">
    <div class="fixed inset-0 z-50">
        <div class="modal-backdrop" @click="open = false"></div>
        <div class="modal-content">
            <input type="text" x-model="query" placeholder="Search users, tournaments...">
            <div class="results">
                <template x-for="user in results.users">
                    <a :href="`/users/${user.id}`">{{ user.username }}</a>
                </template>
                <template x-for="tournament in results.tournaments">
                    <a :href="`/tournaments/${tournament.id}`">{{ tournament.title }}</a>
                </template>
            </div>
        </div>
    </div>
</div>
```

**tournament-podium.blade.php** - Tournament podium display
```blade
<div class="podium-display">
    <div class="podium-place first">
        <img :src="winner.badge_image_url" :alt="winner.username">
        <h3>{{ winner.username }}</h3>
        <span class="placement">1st Place</span>
    </div>
    <div class="podium-place second">
        <!-- 2nd place -->
    </div>
    <div class="podium-place third">
        <!-- 3rd place -->
    </div>
</div>
```

**tournament-staff-section.blade.php** - Staff section with smart sorting
```blade
<div class="tournament-staff">
    @foreach($staffByRole as $role => $members)
        <div class="role-group">
            <h4 class="role-title bg-staff-{{ $role }}">
                {{ ucfirst($role) }} ({{ count($members) }})
            </h4>
            <div class="member-list">
                @foreach($members as $member)
                    <a href="/users/{{ $member->id }}">{{ $member->username }}</a>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
```

**last-week-results.blade.php** - Dashboard component
```blade
<div class="last-week-results">
    <h3>Last Week's Results</h3>
    <div class="results-grid">
        @foreach($results as $result)
            <div class="result-card">
                <img :src="result.winner.avatar_url" :alt="result.winner.username">
                <div class="result-info">
                    <h4>{{ result.tournament.title }}</h4>
                    <p>Winner: {{ result.winner.username }}</p>
                </div>
            </div>
        @endforeach
    </div>
</div>
```

### Key Components

**tournament-card.blade.php**
```blade
<div {{ $attributes }}>
    <h3>{{ $tournament->title }}</h3>
    <p>{{ $tournament->description }}</p>

    @foreach($tournament->staff as $staff)
        <span class="badge bg-staff-{{ $staff->pivot->role }}">
            {{ $staff->pivot->role }}
        </span>
    @endforeach
</div>
```

**parse-history-diff.blade.php**
```blade
<div class="diff-viewer">
    @foreach($changes as $change)
        @if($change['type'] === 'added')
            <div class="bg-green-100">{{ $change['content'] }}</div>
        @elseif($change['type'] === 'removed')
            <div class="bg-red-100">{{ $change['content'] }}</div>
        @endif
    @endforeach
</div>
```

## Asset Compilation (Vite)

### Entry Points
**resources/js/app.js**
```javascript
import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

// Global search keyboard shortcut
document.addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        // Open search modal
    }
});
```

**resources/css/app.css**
```css
@tailwind base;
@tailwind components;
@tailwind utilities;

/* Custom styles */
.osu-pink {
    color: #ff66ab;
}

/* Search modal transitions */
.search-modal-enter-active,
.search-modal-leave-active {
    transition: opacity 0.2s ease;
}
```

**Frontend Dependencies (package.json):**
```json
{
  "dependencies": {
    "@alpinejs/collapse": "^3.15.8",
    "alpinejs": "^3.15.3"
  },
  "devDependencies": {
    "autoprefixer": "^10.4.23",
    "axios": "^1.7.4",
    "concurrently": "^9.0.1",
    "laravel-vite-plugin": "^1.2.0",
    "postcss": "^8.5.6",
    "tailwindcss": "^3.4.19",
    "vite": "^6.0.11"
  }
}
```

### Build Commands
```bash
# Development
npm run dev

# Production
npm run build
```

## Real-Time Updates

### Livewire Wireables
- **Properties** - Automatically synced to backend
- **Computed** - Recalculated on dependency change
- **Actions** - Trigger backend methods

### Polling (Optional)
```blade
<div wire:poll.5s="refreshStats">
    Stats: {{ $stats }}
</div>
```

### Events
```javascript
// Dispatch Alpine event
$dispatch('tournament-updated');

// Listen in Livewire
protected $listeners = ['tournament-updated' => 'refresh'];
```

## Responsive Design

### Breakpoints
- **sm** - 640px (mobile landscape)
- **md** - 768px (tablet)
- **lg** - 1024px (desktop)
- **xl** - 1280px (wide desktop)

### Mobile-First Approach
```blade
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
    <!-- Stack on mobile, 2 columns on tablet, 3 on desktop -->
</div>
```

## Accessibility

### ARIA Labels
```blade
<button aria-label="Close modal" @click="close">
    <span aria-hidden="true">&times;</span>
</button>
```

### Keyboard Navigation
- Tab order follows visual flow
- Enter/Space triggers buttons
- Escape closes modals
- **NEW:** Cmd+K / Ctrl+K opens global search

### Focus States
```html
<button class="focus:ring-2 focus:ring-osu-pink">
    Submit
</button>
```

## Performance Optimization

### Lazy Loading
```blade
<livewire:heavy-component lazy />
```

### Pagination
```blade
{{ $tournaments->links() }}
```

### Image Optimization
- WebP format when supported
- Lazy load below-fold images
- Responsive images (srcset)

## **NEW Features**

### Global Search
- **Keyboard shortcut:** Cmd+K (macOS) / Ctrl+K (Windows/Linux)
- **Modal interface:** Fast access from any page
- **Real-time results:** Debounced API calls
- **Fuzzy matching:** Tolerance for typos
- **Previous username search:** Find users by old usernames
- **Rate limiting:** 60 requests/minute

### Enhanced Tournament Display
- **Podium component:** Visual display of tournament winners (1st, 2nd, 3rd place)
- **Smart staff sorting:** Grouped by role with member count
- **Tri-badge support:** Display multiple badges per placement
- **Badge tooltips:** Enhanced with rank range, team size, and scope info

### Dashboard Improvements
- **Last week's results:** Shows recent tournament winners
- **Enhanced layout:** Better visual hierarchy
- **Performance:** Optimized queries and caching

## Testing URLs

### Local Development
- **Application** - http://localhost
- **Horizon Dashboard** - http://localhost/horizon
- **Mailpit** - http://localhost:8025

### DevTools
- **Network Tab** - Monitor Livewire updates
- **Console** - Alpine.js errors
- **Elements** - Inspect DOM, Alpine x-data

## Key Pages

### Authentication
- `/auth/login` - OAuth2 login with osu!
- `/auth/setup` - Initial user setup

### Main Features
- `/tournaments` - Tournaments list with filters
- `/tournaments/{id}` - Tournament details
  - **NEW:** Enhanced podium display
  - **NEW:** Smart staff sorting
- `/dashboard` - User dashboard
  - **NEW:** Last week's results component
- `/users/{user}` - User profile with stats
- `/users/me/settings` - User settings

### Admin Panel
- `/admin` - Admin dashboard
- `/admin/tournaments/pending` - Pending tournaments
- `/admin/tournaments/{id}/review` - Tournament review
- `/admin/tournaments/{id}/final-actions` - Tournament approval with skip Discord option
- `/admin/matches/pending` - Pending matches
- `/admin/imports` - Import job management
- `/admin/audit-log` - Admin action logs
- `/admin/users` - User management

## UI/UX Improvements (NEW)

### Tournament Detail Page
- **Podium section:** Visual hierarchy for winners
- **Staff section:** Grouped by role with counts
- **Badge tooltips:** Enhanced metadata display
- **Performance:** Optimized queries

### User Profile Page
- **Badge display:** Improved layout with gamemode filtering
- **Tournament history:** Enhanced with expanded items
- **Search:** Find users by previous usernames

### Dashboard
- **Last week's results:** Quick overview of recent winners
- **Activity feed:** Improved visual design
- **Performance:** Cached queries for faster load times
