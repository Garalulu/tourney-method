# Components

## ForumPostParserService (Enhanced v1.3)
**Responsibility:** Comprehensive parsing of osu! forum posts to extract structured tournament data with confidence scoring and multi-language support

**Key Interfaces:**
- parseForumPost(content, title, topicUserId): ExtractedData
- parseTopicTitleMetadata(topicTitle): TitleMetadata
- extractDiscordLink(content): ?string
- extractStarRating(content): StarRatingData
- extractRegistrationDates(content): RegistrationDates
- extractEndDate(content): ?string
- extractBannerUrl(content): ?string

**Core Parsing Features:**
- **Title Metadata Extraction**: Team formats (1v1, 2v2), game modes, rank ranges, BWS indicators
- **Star Rating Parsing**: Comprehensive bracket parsing for min/max/qualifier ratings
- **Date Extraction**: Registration dates, tournament end dates with format normalization
- **Discord Integration**: Invite code extraction from BBCode and imagemaps  
- **Banner Detection**: First image URL extraction for tournament banners
- **Host Resolution**: osu! API integration for username resolution from topic creator
- **Confidence Scoring**: Quality assessment for all extracted fields

**Enhanced Pattern Matching:**
- **Rank Range Conversion**: String ranges to numeric min/max values
- **Game Mode Normalization**: Standardized mode codes (STD, TAIKO, CATCH, MANIA4, etc.)
- **Team Size Detection**: Team size extraction with range handling (TS2-3 → 3)
- **BWS Detection**: Badge Weighted Seeding tournament identification
- **Korean Language Support**: Bilingual term matching for international tournaments

**Dependencies:** 
- osu! API v2 (OAuth client credentials)
- SecurityHelper (input validation)
- Pattern matching libraries
- SystemLog for error tracking

**Technology Stack:** 
- PHP 8+ with advanced regex patterns
- cURL for osu! API integration
- JSON parsing for confidence data
- UTF-8 text processing for international content

## AdminDashboard
**Responsibility:** Provide admin interface for tournament review, approval, and term mapping management

**Key Interfaces:**
- displayPendingTournaments(): TournamentList
- showEditForm(tournament_id): EditForm
- manageTerm Mappings(): TermMappingInterface

**Dependencies:** AuthService, TournamentRepository, TermMappingRepository

**Technology Stack:** PHP templating with Pico.css styling, jQuery for form interactions

## PublicInterface
**Responsibility:** Display public tournament list with filtering, search, and detail modals

**Key Interfaces:**
- renderTournamentList(filters): TournamentGrid
- showTournamentModal(tournament_id): Modal
- applyFilters(criteria): FilteredResults

**Dependencies:** TournamentRepository, FilterService

**Technology Stack:** Progressive enhancement with jQuery, responsive Pico.css design, modal overlays

## FilterService
**Responsibility:** Handle complex tournament filtering with Korean language support

**Key Interfaces:**
- applyRankRangeFilter(tournaments, range): FilteredTournaments
- searchTournaments(query, language): SearchResults
- buildFilterSQL(criteria): SQLQuery

**Dependencies:** TournamentRepository, Database utilities

**Technology Stack:** PHP with SQLite full-text search, UTF-8 collation support

## TermMappingService
**Responsibility:** Manage cross-language term mapping for international tournament parsing

**Key Interfaces:**
- mapTerm(foreign_term, language): english_concept
- addTermMapping(term, concept, confidence): TermMapping
- getLanguageStatistics(): LanguageStats

**Dependencies:** TermMappingRepository, AdminUser validation

**Technology Stack:** PHP with Unicode text processing, statistical term frequency analysis
