# Frontend Architecture

## Component Architecture

### Component Organization
```
public/assets/js/
├── main.js                 # Core application initialization
├── components/
│   ├── TournamentCard.js   # Tournament display component
│   ├── FilterPanel.js      # Tournament filtering interface
│   ├── ModalViewer.js      # Tournament detail modal
│   └── Pagination.js       # Results pagination controls
├── services/
│   ├── ApiClient.js        # REST API communication
│   ├── FilterService.js    # Client-side filtering logic
│   └── StorageService.js   # Browser storage management
└── utils/
    ├── KoreanUtils.js      # Korean text processing utilities
    └── DateUtils.js        # KST timezone handling
```

### Component Template
```typescript
// TournamentCard.js - Progressive Enhancement Component
const TournamentCard = {
    // Component initialization
    init: function(element, options = {}) {
        this.element = $(element);
        this.options = $.extend({}, this.defaults, options);
        this.bindEvents();
        this.loadTournamentData();
    },

    // Default configuration
    defaults: {
        modalTarget: '#tournament-modal',
        apiEndpoint: '/api/tournaments',
        lazyLoadImages: true
    },

    // Event binding for progressive enhancement
    bindEvents: function() {
        this.element.on('click', '.tournament-title', this.handleTitleClick.bind(this));
        this.element.on('click', '.tournament-card', this.handleCardClick.bind(this));
        this.element.on('error', 'img', this.handleImageError.bind(this));
    },

    // Korean-specific text handling
    handleKoreanText: function(text) {
        return KoreanUtils.processText(text);
    },

    // Graceful fallback for JavaScript disabled
    handleNoScript: function() {
        // Ensure all functionality works without JavaScript
        this.element.find('a').attr('target', '_blank');
    }
};
```

## State Management Architecture

### State Structure
```typescript
// Client-side state management with browser storage
interface AppState {
  filters: {
    mode: string;
    rankRange: string;
    registrationStatus: string;
    searchQuery: string;
  };
  tournaments: {
    list: Tournament[];
    total: number;
    loading: boolean;
    error: string | null;
  };
  ui: {
    currentPage: number;
    selectedTournament: number | null;
    modalOpen: boolean;
    filtersExpanded: boolean;
  };
  user: {
    preferences: {
      theme: 'light' | 'dark' | 'auto';
      language: 'ko' | 'en';
      pageSize: 10 | 25 | 50;
    };
  };
}
```

### State Management Patterns
- **Centralized State:** Single state object managed through jQuery patterns
- **Local Storage Persistence:** User preferences and filter state persist across sessions
- **Event-Driven Updates:** Components communicate through custom jQuery events
- **Optimistic UI Updates:** Immediate UI feedback with server-side validation
- **Korean Language State:** Separate handling for Korean vs English content

## Routing Architecture

### Route Organization
```
public/
├── index.php               # Homepage (featured tournaments)
├── tournaments.php         # All tournaments with filtering
├── tournament.php          # Individual tournament details
└── admin/
    ├── index.php          # Admin dashboard
    ├── edit.php           # Tournament editing
    └── logs.php           # System logs viewer
```

### Protected Route Pattern
```typescript
// Admin route protection with osu! OAuth
const AdminRouteGuard = {
    checkAuthentication: function() {
        return fetch('/api/auth/check', {
            credentials: 'same-origin'
        }).then(response => response.json());
    },

    redirectToLogin: function() {
        window.location.href = '/admin/login.php?return=' + 
            encodeURIComponent(window.location.pathname);
    },

    init: function() {
        this.checkAuthentication().then(auth => {
            if (!auth.authenticated || !auth.isAdmin) {
                this.redirectToLogin();
            }
        });
    }
};
```

## Frontend Services Layer

### API Client Setup
```typescript
// RESTful API client with Korean timezone handling
const ApiClient = {
    baseUrl: '/api',
    
    // Default request configuration
    defaults: {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Timezone': 'Asia/Seoul'
        },
        credentials: 'same-origin'
    },

    // Korean-aware request handling
    request: function(endpoint, options = {}) {
        const url = this.baseUrl + endpoint;
        const config = $.extend(true, {}, this.defaults, options);
        
        return fetch(url, config)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`API Error: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Convert timestamps to KST
                return this.processKoreanTimestamps(data);
            });
    },

    // KST timezone processing
    processKoreanTimestamps: function(data) {
        // Convert UTC timestamps to KST display format
        return DateUtils.convertToKST(data);
    }
};
```

### Service Example
```typescript
// Tournament service with filtering and search
const TournamentService = {
    // Get tournaments with Korean language support
    getTournaments: function(filters = {}) {
        const params = new URLSearchParams({
            mode: filters.mode || '',
            rank_range: filters.rankRange || '',
            status: filters.registrationStatus || '',
            search: filters.searchQuery || '',
            limit: filters.pageSize || 10,
            offset: (filters.page - 1) * (filters.pageSize || 10)
        });

        return ApiClient.request(`/tournaments?${params}`);
    },

    // Search with Korean text support
    searchTournaments: function(query, language = 'auto') {
        const searchParams = {
            q: query,
            lang: language === 'auto' ? KoreanUtils.detectLanguage(query) : language
        };

        return ApiClient.request('/tournaments/search', {
            method: 'POST',
            body: JSON.stringify(searchParams)
        });
    },

    // Get tournament details
    getTournamentDetails: function(id) {
        return ApiClient.request(`/tournaments/${id}`);
    }
};
```
