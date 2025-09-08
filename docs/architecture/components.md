# Components

## TournamentParser
**Responsibility:** Parse osu! forum posts to extract structured tournament data with cross-language support

**Key Interfaces:**
- parseForumPost(topic_id): ParsedTournament
- extractTerms(post_content, language): TermExtractionResult
- mapForeignTerms(terms, language): MappedTerms

**Dependencies:** osu! Forum API, TermMappingService, SystemLog

**Technology Stack:** Vanilla PHP with regex parsing, HTML DOM parser, UTF-8 text processing

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
